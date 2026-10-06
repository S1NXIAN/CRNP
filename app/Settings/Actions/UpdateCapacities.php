<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Settings\ReservationArea;
use App\Settings\SettingsRepository;

/**
 * Persists reservation capacities per area — the smart defaults feeding the
 * public occupancy view. An area left blank keeps its default.
 */
final readonly class UpdateCapacities
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @param  array<string, mixed>  $capacities
     */
    public function __invoke(array $capacities): void
    {
        $current = $this->settings->current();

        $resolved = [];

        foreach (ReservationArea::cases() as $area) {
            $value = $capacities[$area->value] ?? null;

            if (is_numeric($value) && (int) $value >= 0) {
                $resolved[$area->value] = (int) $value;
            } else {
                $resolved[$area->value] = $current->capacityFor($area);
            }
        }

        $this->settings->save($current->withCapacities($resolved));
    }
}
