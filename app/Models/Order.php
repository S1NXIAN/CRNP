<?php

namespace App\Models;

use App\Core\Model;

/**
 * Order — represents a customer order in /orders.
 */
class Order extends Model
{
    protected static string $table = 'orders';

    protected static array $fillable = [
        'user_id', 'user_name', 'user_email',
        'customer_name', 'contact', 'phone',
        'items', 'total', 'notes',
        'payment_method', 'payment_status', 'receipt', 'gcash_receipt',
        'status', 'table_number', 'num_customers',
        'cash_tendered', 'change',
        'created_at', 'placed_at',
        'accepted_at', 'accepted_by',
        'cancelled_at', 'cancelled_by', 'cancel_note',
        'restored_at', 'restored_by',
        'verified_at', 'verified_by', 'payment_verified',
        'created_by', 'source',
    ];

    /* ---- Convenience statics ---- */

    /** Today's date string. */
    private static function today(): string
    {
        return date('Y-m-d');
    }

    /** Sum of today's paid order totals. */
    public static function todaySales(): float
    {
        $today = static::today();
        $sum = 0.0;
        foreach (static::raw() as $o) {
            if (!is_array($o)) {
                continue;
            }
            $d = substr((string) ($o['created_at'] ?? ''), 0, 10);
            if ($d === $today && (string) ($o['payment_status'] ?? '') === 'paid') {
                $sum += (float) ($o['total'] ?? 0);
            }
        }
        return $sum;
    }

    /** Pending count (indexed). */
    public static function pendingCount(): int
    {
        return count(static::where('status', 'pending'));
    }

    /**
     * Pending rows the POS has not rendered yet, newest first, capped.
     * Pure (no DB): feeds the ?check poll so new rows inject without reload.
     */
    public static function selectNew(array $pending, array $knownIds, int $cap = 20): array
    {
        $known = array_fill_keys(array_map('strval', $knownIds), true);
        $fresh = [];
        foreach ($pending as $oid => $row) {
            if (isset($known[(string) $oid]) || !is_array($row)) {
                continue;
            }
            $fresh[(string) $oid] = $row;
        }
        uasort($fresh, function ($a, $b) {
            return strcmp((string) ($b['created_at'] ?? $b['placed_at'] ?? ''), (string) ($a['created_at'] ?? $a['placed_at'] ?? ''));
        });
        return array_slice($fresh, 0, $cap, true);
    }

    /**
     * Split rows into the two kitchen columns, oldest first.
     * Pure (no DB): feeds the board render + ?check poll.
     * Cooking merges preparing + ready so stranded ready rows stay visible.
     *
     * @param array<string,mixed> $orders
     * @return array{accepted:array<string,array<string,mixed>>,cooking:array<string,array<string,mixed>>}
     */
    public static function kitchenBoard(array $orders): array
    {
        $accepted = [];
        $cooking = [];
        foreach ($orders as $oid => $row) {
            if (!is_array($row)) {
                continue;
            }
            $st = (string) ($row['status'] ?? '');
            if ($st === 'accepted') {
                $accepted[(string) $oid] = $row;
            } elseif ($st === 'preparing' || $st === 'ready') {
                $cooking[(string) $oid] = $row;
            }
        }
        $byOldest = function ($a, $b) {
            $ta = strtotime((string) ($a['created_at'] ?? $a['placed_at'] ?? ''));
            $tb = strtotime((string) ($b['created_at'] ?? $b['placed_at'] ?? ''));
            if ($ta === false && $tb === false) {
                return 0;
            }
            if ($ta === false) {
                return 1;
            }
            if ($tb === false) {
                return -1;
            }
            return $ta - $tb;
        };
        uasort($accepted, $byOldest);
        uasort($cooking, $byOldest);
        return ['accepted' => $accepted, 'cooking' => $cooking];
    }


    /** Unpaid count (excludes cancelled). */
    public static function unpaidCount(): int
    {
        $n = 0;
        foreach (static::raw() as $o) {
            if (!is_array($o)) {
                continue;
            }
            $st = (string) ($o['status'] ?? '');
            if (($o['payment_status'] ?? '') !== 'paid'
                && $st !== 'cashier_cancelled' && $st !== 'cancelled') {
                $n++;
            }
        }
        return $n;
    }

    /** Last N orders sorted newest-first (raw arrays keyed by Firebase key). */
    public static function recent(int $limit = 8): array
    {
        return static::recentBy('created_at', $limit);
    }

    /** Best-selling products: name => qty (top N, raw). */
    public static function topProducts(int $limit = 10): array
    {
        $productSales = [];
        foreach (static::raw() as $o) {
            if (!is_array($o)) {
                continue;
            }
            if (in_array(($o['status'] ?? ''), ['cancelled', 'cashier_cancelled'], true)) {
                continue;
            }
            foreach (($o['items'] ?? []) as $pid => $info) {
                if (!is_array($info)) {
                    continue;
                }
                $qty = (int) ($info['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $productSales[$pid] = ($productSales[$pid] ?? 0) + $qty;
            }
        }
        arsort($productSales);
        return array_slice($productSales, 0, $limit, true);
    }

    /** Best-selling by category: category => qty (top N). */
    public static function topCategories(int $limit = 8, array $products = []): array
    {
        $catSales = [];
        foreach (static::raw() as $o) {
            if (!is_array($o)) {
                continue;
            }
            if (in_array(($o['status'] ?? ''), ['cancelled', 'cashier_cancelled'], true)) {
                continue;
            }
            foreach (($o['items'] ?? []) as $pid => $info) {
                if (!is_array($info)) {
                    continue;
                }
                $qty = (int) ($info['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $cat = (string) ($products[$pid]['category'] ?? 'Uncategorized');
                $catSales[$cat] = ($catSales[$cat] ?? 0) + $qty;
            }
        }
        arsort($catSales);
        return array_slice($catSales, 0, $limit, true);
    }

    /** Payment method breakdown for paid orders: label => count. */
    public static function paymentMethodBreakdown(): array
    {
        $methods = [];
        foreach (static::raw() as $o) {
            if (!is_array($o)) {
                continue;
            }
            if ((string) ($o['payment_status'] ?? '') !== 'paid') {
                continue;
            }
            $pm = (string) ($o['payment_method'] ?? 'counter');
            $label = $pm === 'gcash' ? 'GCash' : 'Counter';
            $methods[$label] = ($methods[$label] ?? 0) + 1;
        }
        arsort($methods);
        return $methods;
    }

    /** Peak hours: index 0..23 => count. */
    public static function peakHours(): array
    {
        $hours = array_fill(0, 24, 0);
        foreach (static::raw() as $o) {
            if (!is_array($o)) {
                continue;
            }
            $created = (string) ($o['created_at'] ?? '');
            if ($created !== '') {
                $hour = (int) date('G', strtotime($created));
                $hours[$hour]++;
            }
        }
        return $hours;
    }

    /** 7-day sales totals: dateKey => float. */
    public static function last7DaysSales(): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $key = date('Y-m-d', strtotime("-$i days"));
            $days[$key] = 0.0;
        }
        foreach (static::raw() as $o) {
            if (!is_array($o)) {
                continue;
            }
            if ((string) ($o['payment_status'] ?? '') !== 'paid') {
                continue;
            }
            $day = substr((string) ($o['created_at'] ?? ''), 0, 10);
            if (isset($days[$day])) {
                $days[$day] += (float) ($o['total'] ?? 0);
            }
        }
        return $days;
    }

    /** Orders with created_at in [startDate, endDate] (indexed range + PHP refine). */
    public static function byDateRange(string $startDate, string $endDate): array
    {
        $out = [];
        foreach (static::whereRange('created_at', $startDate, $endDate . '\uf8ff') as $k => $o) {
            if (!is_array($o)) {
                continue;
            }
            $d = substr((string) ($o['created_at'] ?? ''), 0, 10);
            if ($d >= $startDate && $d <= $endDate) {
                $out[$k] = $o;
            }
        }
        return $out;
    }
}
