<?php
/**
 * cashier/manual_booking.php — create a walk-in rental booking.
 * Cashier picks rent items + qty, fills the customer's name/contact/address,
 * and appointment/return times. Walk-ins always record 'counter'; a manual
 * GCash payment is verified in person. Stock is decremented immediately and
 * the booking lands in the bookings queue as 'pending'.
 */
require_once __DIR__ . '/../init.php';
require_cashier();

use App\Models\Booking;
use App\Models\RentItem;

$cashierName = $_SESSION['cashier_name'] ?? 'Cashier';
$rentItems   = RentItem::raw();

/** price accessor that tolerates either `price` or `rental_price`. */
$priceOf = static function (array $item): float {
    return (float) ($item['price'] ?? $item['rental_price'] ?? 0);
};

/* ---------- POST: build & insert walk-in booking ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName         = trim((string) post('full_name', ''));
    $contact          = trim((string) post('contact', ''));
    $address          = trim((string) post('address', ''));
    $appointmentTime  = trim((string) post('appointment_time', ''));
    $returnTime       = trim((string) post('return_time', ''));
    $notes            = trim((string) post('notes', ''));
    $qtyMap           = post('qty', []);
    if (!is_array($qtyMap)) {
        $qtyMap = [];
    }

    $errors = [];
    if ($fullName === '') {
        $errors[] = 'Customer name is required.';
    }
    if ($contact === '') {
        $errors[] = 'Contact number is required.';
    }
    if ($appointmentTime === '' || $returnTime === '') {
        $errors[] = 'Both appointment and return times are required.';
    } elseif (strtotime($returnTime) <= strtotime($appointmentTime)) {
        $errors[] = 'Return time must be after the appointment time.';
    }

    // Re-fetch rent_items fresh so we check live stock.
    $liveItems = RentItem::raw();

    $items  = [];
    $total  = 0.0;
    $anyQty = false;
    foreach ($qtyMap as $itemId => $qty) {
        $itemId = (string) $itemId;
        $qty    = (int) $qty;
        if ($qty <= 0 || !isset($liveItems[$itemId]) || !is_array($liveItems[$itemId])) {
            continue;
        }
        $anyQty = true;
        $item   = $liveItems[$itemId];
        $stock  = (int) ($item['quantity'] ?? 0);
        if ($qty > $stock) {
            $errors[] = 'Requested ' . $qty . ' × ' . ($item['name'] ?? 'item')
                      . ' but only ' . $stock . ' in stock.';
            continue;
        }
        $price    = $priceOf($item);
        $subtotal = $price * $qty;
        $items[$itemId] = [
            'name'     => (string) ($item['name'] ?? 'Item'),
            'qty'      => $qty,
            'price'    => $price,
            'subtotal' => $subtotal,
        ];
        $total += $subtotal;
    }
    if (!$anyQty) {
        $errors[] = 'Select at least one rental item with a quantity.';
    }

    if ($errors) {
        foreach ($errors as $msg) {
            flash($msg, 'danger');
        }
        // Fall through to render form (input preserved via post()).
    } else {
        $booking = [
            'user_email'       => 'walk-in',
            'user_id'          => '',
            'user_name'        => $fullName,
            'full_name'        => $fullName,
            'contact'          => $contact,
            'address'          => $address,
            'items'            => $items,
            'total'            => $total,
            'notes'            => $notes,
            'appointment_time' => $appointmentTime,
            'return_time'      => $returnTime,
            'payment_method'   => 'counter', // Walk-ins always counter; manual GCash verified in person.
            'payment_status'   => 'no_payment_required',
            'payment_verified' => false,
            'receipt'          => null,
            'status'           => 'pending',
            'created_at'       => now(),
            'created_by'       => $cashierName,
            'source'           => 'walk-in',
        ];

        $newBooking = (new Booking($booking))->save();
        if (!$newBooking) {
            flash('Could not create the booking. Please try again.', 'danger');
        } else {
            // Decrement rent stock by KEY (only after a successful insert).
            foreach ($items as $itemId => $info) {
                RentItem::decrementStock((string) $itemId, (int) $info['qty']);
            }
            flash('Walk-in booking #' . substr($newBooking, 0, 6) . ' created for ' . $fullName . '.', 'ok');
            redirect('/cashier/bookings.php');
        }
    }
}

$pageTitle = 'New Walk-in Booking';
$activeNav = 'bookings';
$layout    = 'narrow';
require_once __DIR__ . '/../includes/header.php';
?>
<header class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Cashier Console</span>
      <h1>New walk-in booking</h1>
      <p>Create an in-store rental reservation. Stock is deducted immediately and the booking appears in the queue as <strong>Pending</strong>.</p>
    </div>
    <a class="btn btn--outline btn--sm" href="/cashier/bookings.php">&larr; Back to bookings</a>
  </div>
</header>

<form method="post" class="card card--pad">
  <?= csrf_field() ?>
  <h3 class="mb-2">Customer details</h3>
  <div class="form-grid form-grid--2 mb-4">
    <div class="field">
      <label for="full_name">Full name *</label>
      <input class="input" type="text" id="full_name" name="full_name" required
             value="<?= e(post('full_name')) ?>" placeholder="Juan Dela Cruz">
    </div>
    <div class="field">
      <label for="contact">Contact number *</label>
      <input class="input" type="tel" id="contact" name="contact" required
             value="<?= e(post('contact')) ?>" placeholder="0917 123 4567">
    </div>
    <div class="field" style="grid-column:1 / -1">
      <label for="address">Address</label>
      <input class="input" type="text" id="address" name="address"
             value="<?= e(post('address')) ?>" placeholder="Optional — for delivery or follow-up">
    </div>
  </div>

  <h3 class="mb-2">Schedule</h3>
  <div class="form-grid form-grid--2 mb-4">
    <div class="field">
      <label for="appointment_time">Appointment time *</label>
      <input class="input" type="datetime-local" id="appointment_time" name="appointment_time" required
             value="<?= e(post('appointment_time')) ?>">
    </div>
    <div class="field">
      <label for="return_time">Return time *</label>
      <input class="input" type="datetime-local" id="return_time" name="return_time" required
             value="<?= e(post('return_time')) ?>">
    </div>
  </div>

  <h3 class="mb-2">Rental items</h3>
  <?php if (!$rentItems): ?>
    <div class="empty mb-4">
      <div class="empty__icon" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
      <h3>No rental items</h3>
      <p>An administrator must add rental items before you can create a booking.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap mb-4">
      <table class="tbl">
        <thead>
          <tr>
            <th>Item</th>
            <th class="num">In stock</th>
            <th class="num">Price</th>
            <th class="num">Quantity</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rentItems as $itemId => $item):
              $stock   = (int) ($item['quantity'] ?? 0);
              $price   = $priceOf($item);
              $current = (int) ($_POST['qty'][$itemId] ?? 0);
              ?>
            <tr>
              <td>
                <strong><?= e($item['name'] ?? 'Item') ?></strong>
                <?php if (!empty($item['description'])): ?>
                  <br><small class="muted"><?= e($item['description']) ?></small>
                <?php endif; ?>
              </td>
              <td class="num <?= $stock === 0 ? 'muted' : '' ?>"><?= $stock ?></td>
              <td class="num"><?= e(money($price)) ?></td>
              <td class="num">
                <input class="input" type="number" min="0" max="<?= (int) $stock ?>"
                       name="qty[<?= e($itemId) ?>]" value="<?= $current ?>"
                       style="width:84px;text-align:right" <?= $stock === 0 ? 'disabled' : '' ?>>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="font-size:12px;margin-top:-8px">Enter 0 (or leave blank) to skip an item. Quantities are validated against live stock.</p>
  <?php endif; ?>

  <h3 class="mb-2 mt-6">Payment</h3>
  <div class="form-grid mb-2">
    <div class="field">
      <label>Payment method</label>
      <p class="muted" style="margin:0">Pay at counter — cashier verifies manual GCash in person.</p>
    </div>
    <div class="field" style="grid-column:1 / -1">
      <label for="notes">Special instructions</label>
      <textarea class="textarea" id="notes" name="notes" rows="2" placeholder="e.g. Setup on the second floor, clean pieces"><?= e(post('notes')) ?></textarea>
    </div>
  </div>

  <div class="form-actions mt-6">
    <button type="submit" class="btn btn--gold btn--lg" <?= !$rentItems ? 'disabled' : '' ?>>Create booking</button>
    <a class="btn btn--ghost" href="/cashier/bookings.php">Cancel</a>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
