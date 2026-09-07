<?php

namespace App\Models;

use App\Core\Model;

/**
 * Booking — represents a booking/reservation in /bookings.
 */
class Booking extends Model
{
    protected static string $table = 'bookings';

    protected static array $fillable = [
        'user_id', 'user_name', 'user_email',
        'items', 'total', 'full_name', 'contact', 'address', 'notes',
        'status', 'payment_status', 'payment_method',
        'appointment_time', 'return_time', 'receipt', 'created_at',
        'cancelled_at', 'cancelled_by', 'cancel_note',
        'accepted_at', 'accepted_by',
        'returned_at', 'returned_by',
    ];

    /** Bookings by status: label => count. */
    public static function statusBreakdown(): array
    {
        $statuses = [];
        foreach (static::raw() as $b) {
            if (!is_array($b)) {
                continue;
            }
            $st = (string) ($b['status'] ?? 'unknown');
            [$label] = \booking_status_label($st);
            $statuses[$label] = ($statuses[$label] ?? 0) + 1;
        }
        arsort($statuses);
        return $statuses;
    }

    /** Best-selling rent items from bookings: name => qty (top N). */
    public static function topRentItems(int $limit = 10, array $rentItems = []): array
    {
        $sales = [];
        foreach (static::raw() as $b) {
            if (!is_array($b)) {
                continue;
            }
            if (in_array(($b['status'] ?? ''), ['cancelled', 'rejected'], true)) {
                continue;
            }
            foreach (($b['items'] ?? []) as $rid => $info) {
                if (!is_array($info)) {
                    continue;
                }
                $qty = (int) ($info['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $name = (string) ($rentItems[$rid]['name'] ?? $info['name'] ?? 'Item');
                $sales[$name] = ($sales[$name] ?? 0) + $qty;
            }
        }
        arsort($sales);
        return array_slice($sales, 0, $limit, true);
    }

    /** Last N bookings sorted newest-first (raw arrays). */
    public static function recent(int $limit = 5): array
    {
        return static::recentBy('created_at', $limit);
    }

    /** Bookings with created_at in [startDate, endDate] (indexed range + PHP refine). */
    public static function byDateRange(string $startDate, string $endDate): array
    {
        $out = [];
        foreach (static::whereRange('created_at', $startDate, $endDate . '\uf8ff') as $k => $b) {
            if (!is_array($b)) {
                continue;
            }
            $d = substr((string) ($b['created_at'] ?? ''), 0, 10);
            if ($d >= $startDate && $d <= $endDate) {
                $out[$k] = $b;
            }
        }
        return $out;
    }
}
