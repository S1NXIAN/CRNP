<?php
/**
 * kitchen/history.php — Read-only archive of completed (done) orders.
 * Sorted by done_at desc (falls back to updated_at).
 */
require_once __DIR__ . '/../init.php';
require_kitchen();

use App\Models\Order;

/* ---------- display helpers (shared bits live in functions.php) ---------- */
if (!function_exists('k_format_ts')) {
    function k_format_ts(?string $ts): string
    {
        if ($ts === null || $ts === '') {
            return '—';
        }
        $t = strtotime($ts);
        if ($t === false) {
            return '—';
        }
        return date('M j, Y g:i A', $t);
    }
}

$done = Order::where('status', 'done');

uasort($done, function ($a, $b) {
    $ta = strtotime((string) ($a['done_at'] ?? $a['updated_at'] ?? ''));
    $tb = strtotime((string) ($b['done_at'] ?? $b['updated_at'] ?? ''));
    if ($ta === false && $tb === false) {
        return 0;
    }
    if ($ta === false) {
        return 1;
    }
    if ($tb === false) {
        return -1;
    }
    return $tb - $ta; // newest completed first
});

$pageTitle = 'Completed Orders';
$activeNav = 'history';
$layout    = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Kitchen History</span>
      <h1 class="mt-2">Completed Orders</h1>
      <p>A read-only archive of every ticket you and the team have finished.</p>
    </div>
    <a class="btn btn--outline btn--sm" href="/kitchen/">&larr; Back to display</a>
  </div>
</div>

<?php if (empty($done)): ?>
  <div class="empty">
    <div class="empty__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6L9 17l-5-5"/></svg>
    </div>
    <h3>Nothing completed yet</h3>
    <p>Once orders are marked done they will be archived here, newest first.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <colgroup>
        <col style="width:96px">
        <col style="width:170px">
        <col>
        <col style="width:140px">
        <col style="width:180px">
        <col style="width:120px">
      </colgroup>
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Cook</th>
          <th>Completed</th>
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($done as $id => $o): ?>
          <?php $total = (float) ($o['total'] ?? $o['grand_total'] ?? 0); ?>
          <tr>
            <td><span class="kbd">#<?= e(short_id((string) $id)) ?></span></td>
            <td><?= e(order_customer_name($o)) ?></td>
            <td><?= items_html($o['items'] ?? []) ?></td>
            <td><?= e($o['done_by'] ?? '') ?></td>
            <td class="muted"><?= e(k_format_ts((string) ($o['done_at'] ?? $o['updated_at'] ?? ''))) ?></td>
            <td class="num"><?= e(money($total)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
