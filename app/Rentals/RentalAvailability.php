<?php

declare(strict_types=1);

namespace App\Rentals;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Computes an item's **per-date availability** — the rental half of the
 * reservation conflict math (PLAN §4 *Rentals*).
 *
 * For each date in the window, availability is `units − overlapping confirmed
 * bookings` — the same overlap rule reservations use, so a double-booking is
 * blocked the same way. Confirmed bookings only: a booking still awaiting
 * payment holds nothing (it lapses with stock untouched), a handed-over booking
 * has already left the shelf (stock fell at handover), and a returned one has
 * come back.
 *
 * The read is bounded by the `dateFrom` index to the window plus the maximum
 * booking length, so a catalog view never scans the booking tree. The computed
 * result is what the shared availability cache coalesces.
 */
final readonly class RentalAvailability
{
    public function __construct(
        private RentalBookings $bookings,
    ) {}

    /**
     * The availability of one item over $days days starting at $from's date.
     *
     * @return list<RentalAvailabilityDay>
     */
    public function forItem(RentalItem $item, CarbonInterface $from, int $days): array
    {
        $start = CarbonImmutable::instance($from)->setTimezone(self::timezone())->startOfDay();
        $end = $start->addDays(max(0, $days - 1));
        $period = RentalPeriod::fromStrings($start->toDateString(), $end->toDateString());

        $reserved = $this->reservedByDate($item, $period);

        $availability = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();

            $availability[] = new RentalAvailabilityDay(
                date: $date,
                units: $item->units,
                reserved: $reserved[$date] ?? 0,
            );
        }

        return $availability;
    }

    /**
     * Whether $qty of an item is available across a whole booking range — the
     * server-side re-check before a booking is written, and the last word on
     * confirm (the public availability cache may be stale).
     */
    public function isAvailable(RentalItem $item, RentalPeriod $period, int $qty, ?string $exceptId = null): bool
    {
        if ($qty < 1) {
            return false;
        }

        $reserved = $this->reservedByDate($item, $period, $exceptId);

        $day = $period->from;

        while (! $day->greaterThan($period->to)) {
            if (($reserved[$day->toDateString()] ?? 0) + $qty > $item->units) {
                return false;
            }

            $day = $day->addDay();
        }

        return true;
    }

    /**
     * The units reserved per date by confirmed bookings of an item that overlap
     * the range, keyed by `Y-m-d`.
     *
     * @return array<string, int>
     */
    private function reservedByDate(RentalItem $item, RentalPeriod $period, ?string $exceptId = null): array
    {
        $reserved = [];

        foreach ($this->bookings->overlapping($period) as $id => $booking) {
            if ($id === $exceptId || $booking->itemId !== $item->id || ! $booking->reservesStock()) {
                continue;
            }

            $day = $booking->period->from->greaterThan($period->from) ? $booking->period->from : $period->from;
            $last = $booking->period->to->lessThan($period->to) ? $booking->period->to : $period->to;

            while (! $day->greaterThan($last)) {
                $date = $day->toDateString();
                $reserved[$date] = ($reserved[$date] ?? 0) + $booking->qty;
                $day = $day->addDay();
            }
        }

        return $reserved;
    }

    private static function timezone(): string
    {
        $timezone = config('settings.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Asia/Manila';
    }
}
