<?php
/**
 * kitchen/index.php — Kitchen board: accepted queue + cooking column.
 *
 * Two states only: accepted (cashier queue) → cooking (preparing/ready).
 * Double-tap / Done writes done (soft, lands in history). Cashier owns rest.
 */
require_once __DIR__ . '/../init.php';
require_kitchen();

use App\Models\Order;

/* ---------- display helpers (shared bits live in functions.php) ---------- */
if (!function_exists('k_elapsed')) {
    function k_elapsed(?string $ts): string
    {
        if ($ts === null || $ts === '') {
            return '—';
        }
        $t = strtotime($ts);
        if ($t === false) {
            return '—';
        }
        $diff = max(0, time() - $t);
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . 'h ago';
        }
        return (int) floor($diff / 86400) . 'd ago';
    }
}

/* Render one ticket card. Shared by the initial board + the ?check poll. */
if (!function_exists('kitchen_ticket_html')) {
    function kitchen_ticket_html(string $id, array $o, string $col): string
    {
        $elapsed = k_elapsed((string) ($o['created_at'] ?? $o['placed_at'] ?? ''));
        $status = (string) ($o['status'] ?? 'accepted');
        ob_start();
        ?>
        <article class="k-ticket" data-order-id="<?= e($id) ?>" data-status="<?= e($status) ?>" tabindex="0">
          <div class="k-ticket__head">
            <span class="kbd">#<?= e(short_id($id)) ?></span>
            <span class="muted"><?= e($elapsed) ?></span>
          </div>
          <div class="k-ticket__items"><?= items_html($o['items'] ?? []) ?></div>
          <?php if (!empty($o['notes'])): ?>
            <div class="k-note"><?= e($o['notes']) ?></div>
          <?php endif; ?>
          <div class="k-ticket__actions">
            <?php if ($col === 'accepted'): ?>
              <form method="post" action="/kitchen/" class="k-claim">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start">
                <input type="hidden" name="order_id" value="<?= e($id) ?>">
                <button type="submit" class="btn btn--gold btn--sm">Claim</button>
              </form>
            <?php endif; ?>
            <form method="post" action="/kitchen/" class="k-done">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="done">
              <input type="hidden" name="order_id" value="<?= e($id) ?>">
              <button type="submit" class="btn btn--ghost btn--sm">Done</button>
            </form>
          </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }
}

/* ---------- POST: claim / done / undo (optimistic fetch or full fallback) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) post('action', '');
    $orderId = (string) post('order_id', '');
    $undoTo = (string) post('to', '');
    $isAjax = is_ajax_request();

    $fail = function (string $msg) use ($isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        flash($msg, 'danger');
        redirect('/kitchen/');
    };

    $transitions = [
        'accept' => ['pending' => 'accepted'],
        'start' => ['accepted' => 'preparing'],
        'ready' => ['preparing' => 'ready'],
        'done' => ['accepted' => 'done', 'preparing' => 'done', 'ready' => 'done'],
    ];

    $order = $orderId !== '' ? Order::find($orderId) : null;
    if (!$order || !isset($order->status)) {
        $fail('Order not found.');
    }
    $current = (string) $order->status;

    if ($action === 'undo') {
        $allowed = ($current === 'preparing' && $undoTo === 'accepted')
            || ($current === 'done' && ($undoTo === 'accepted' || $undoTo === 'preparing' || $undoTo === 'ready'));
        if (!$allowed) {
            $fail('Cannot undo that move.');
        }
        $newStatus = $undoTo;
        $patch = ['status' => $newStatus, 'updated_at' => now()];
    } else {
        if ($orderId === '' || !isset($transitions[$action])) {
            $fail('Invalid request.');
        }
        $map = $transitions[$action];
        if (!isset($map[$current])) {
            $fail('Invalid status transition.');
        }
        $newStatus = $map[$current];
        $patch = ['status' => $newStatus, 'updated_at' => now()];
        if ($newStatus === 'accepted') {
            $patch['accepted_at'] = now();
        }
        if ($newStatus === 'preparing') {
            $patch['preparing_at'] = now();
        }
        if ($newStatus === 'ready') {
            $patch['ready_at'] = now();
        }
        if ($newStatus === 'done') {
            $patch['done_at'] = now();
            $patch['done_by'] = $_SESSION['kitchen_name'] ?? '';
        }
    }

    try {
        $order->update($patch);
    } catch (Throwable $ex) {
        $fail('Could not update the order. Please try again.');
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'status' => $newStatus, 'id' => $orderId]);
        exit;
    }
    if ($newStatus === 'done') {
        flash('Order #' . short_id($orderId) . ' done. Cashier handles the rest.', 'ok');
    } else {
        flash('Order #' . short_id($orderId) . ' moved to Cooking.', 'ok');
    }
    redirect('/kitchen/');
}

/* ---------- GET: ?check poll (counts + fragments, never a bare array) ---------- */
if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    $fetched = Order::whereAny('status', ['accepted', 'preparing', 'ready']);
    $board = Order::kitchenBoard($fetched);
    $cards = [];
    foreach (['accepted' => 'accepted', 'cooking' => 'cooking'] as $key => $col) {
        foreach ($board[$key] as $oid => $row) {
            $cards[] = ['id' => (string) $oid, 'status' => (string) ($row['status'] ?? ''), 'col' => $col, 'html' => kitchen_ticket_html((string) $oid, $row, $col)];
        }
    }
    echo json_encode(['accepted' => count($board['accepted']), 'cooking' => count($board['cooking']), 'cards' => $cards]);
    exit;
}

/* ---------- GET: initial board (indexed; oldest first for FIFO cooking) ---------- */
$fetched = Order::whereAny('status', ['accepted', 'preparing', 'ready']);
$board = Order::kitchenBoard($fetched);
$chefName = (string) ($_SESSION['kitchen_name'] ?? 'Kitchen');

$pageTitle = 'Kitchen Board';
$activeNav = 'orders';
$layout = 'wide';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
  .k-board { display: grid; gap: 16px; grid-template-columns: 1fr; }
  @media (min-width: 640px) { .k-board { grid-template-columns: 1fr 1fr; } }
  .k-col { background: var(--surface-2); border: 1px solid var(--line); border-radius: var(--radius); padding: 12px; min-height: 200px; }
  .k-col__head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 10px; }
  .k-col__head h2 { margin: 0; font-size: 16px; }
  .k-col--drop { outline: 2px dashed var(--gold); outline-offset: -6px; }
  .k-list { display: grid; gap: 10px; }
  .k-ticket { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-sm); box-shadow: var(--shadow-sm); padding: 10px 12px; touch-action: pan-y; }
  .k-ticket--drag { opacity: .7; box-shadow: var(--shadow); }
  .k-ticket__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
  .k-ticket__items { font-size: 14px; }
  .k-note { margin-top: 8px; padding: 6px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; background: var(--warn-bg); color: var(--warn); border-left: 3px solid var(--warn); white-space: normal; }
  .k-ticket__actions { display: flex; gap: 8px; margin-top: 10px; }
  .k-ticket__actions form { margin: 0; }
  .k-toast { position: fixed; left: 50%; bottom: 18px; transform: translateX(-50%); background: var(--ink); color: var(--bg); border-radius: 999px; padding: 10px 14px; display: flex; gap: 10px; align-items: center; z-index: 60; box-shadow: var(--shadow); }
  .k-toast button { background: var(--gold); color: #fff; border: 0; border-radius: 999px; padding: 6px 12px; font-weight: 700; cursor: pointer; }
  @media (prefers-reduced-motion: reduce) { .k-ticket, .k-toast { transition: none; } }
</style>

<div class="page-head">
  <div class="page-head__row">
    <div>
      <span class="eyebrow">Kitchen Board</span>
      <h1 class="mt-2">Cook what cashier accepted</h1>
      <p>Drag to Cooking, double-tap Done. Cashier handles the rest.</p>
    </div>
  </div>
</div>

<div class="k-board" id="kBoard">
  <section class="k-col" id="kColAccepted" aria-label="Accepted queue">
    <div class="k-col__head">
      <h2>Accepted</h2>
      <span class="muted" aria-live="polite"><span id="kAcceptedCount"><?= count($board['accepted']) ?></span> waiting</span>
    </div>
    <div class="k-list" id="kAcceptedList">
      <?php if (empty($board['accepted'])): ?>
        <div class="empty" data-empty="accepted"><p>Nothing waiting. New accepted orders land here.</p></div>
      <?php else: ?>
        <?php foreach ($board['accepted'] as $id => $o): ?>
          <?= kitchen_ticket_html((string) $id, $o, 'accepted') ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
  <section class="k-col" id="kColCooking" aria-label="Cooking column">
    <div class="k-col__head">
      <h2><?= e($chefName) ?> — Cooking</h2>
      <span class="muted" aria-live="polite"><span id="kCookingCount"><?= count($board['cooking']) ?></span> cooking</span>
    </div>
    <div class="k-list" id="kCookingList">
      <?php if (empty($board['cooking'])): ?>
        <div class="empty" data-empty="cooking"><p>Nothing cooking. Claim a ticket to start.</p></div>
      <?php else: ?>
        <?php foreach ($board['cooking'] as $id => $o): ?>
          <?= kitchen_ticket_html((string) $id, $o, 'cooking') ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
(function () {
  var board = document.getElementById('kBoard');
  if (!board) return;
  var acceptedList = document.getElementById('kAcceptedList');
  var cookingList = document.getElementById('kCookingList');
  var acceptedCount = document.getElementById('kAcceptedCount');
  var cookingCount = document.getElementById('kCookingCount');
  var dragging = null;
  var lastTap = { id: null, at: 0 };

  function toast(message, undo) {
    var old = document.querySelector('.k-toast');
    if (old) old.remove();
    var t = document.createElement('div');
    t.className = 'k-toast';
    var s = document.createElement('span');
    s.textContent = message;
    t.appendChild(s);
    if (undo) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Undo';
      b.addEventListener('click', function () { undo(); t.remove(); });
      t.appendChild(b);
      setTimeout(function () { if (t.parentNode) t.remove(); }, 6000);
    } else {
      setTimeout(function () { if (t.parentNode) t.remove(); }, 4000);
    }
    document.body.appendChild(t);
  }

  function postAction(id, action, to, onOk, onFail) {
    var fd = new FormData();
    var token = document.querySelector('input[name="csrf_token"]');
    fd.append('action', action);
    fd.append('order_id', id);
    if (to) fd.append('to', to);
    if (token) fd.append(token.name, token.value);
    fetch('/kitchen/', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) { if (d.success) { onOk(d); } else { onFail(); } })
      .catch(onFail);
  }

  function cardById(id) {
    return board.querySelector('[data-order-id="' + CSS.escape(id) + '"]');
  }

  function moveCard(id, toCol) {
    var card = cardById(id);
    if (!card) return;
    var fromCol = card.closest('#kAcceptedList') ? 'accepted' : 'cooking';
    if (fromCol === toCol) return;
    var fromList = fromCol === 'accepted' ? acceptedList : cookingList;
    var dest = toCol === 'accepted' ? acceptedList : cookingList;
    var empty = dest.querySelector('[data-empty]');
    if (empty) empty.remove();
    dest.append(card);
    if (fromList.children.length === 0) {
      var e = document.createElement('div');
      e.className = 'empty';
      e.setAttribute('data-empty', fromList === acceptedList ? 'accepted' : 'cooking');
      e.innerHTML = '<p>' + (fromList === acceptedList ? 'Nothing waiting.' : 'Nothing cooking.') + '</p>';
      fromList.appendChild(e);
    }
    return fromCol;
  }

  function updateCounts() {
    acceptedCount.textContent = acceptedList.querySelectorAll('[data-order-id]').length;
    cookingCount.textContent = cookingList.querySelectorAll('[data-order-id]').length;
  }

  function claim(id) {
    var from = moveCard(id, 'cooking');
    if (!from) return;
    postAction(id, 'start', null, function () {
      var card = cardById(id);
      if (card) card.setAttribute('data-status', 'preparing');
      toast('Claimed. Cooking now.', function () {
        moveCard(id, 'accepted');
        postAction(id, 'undo', 'accepted', function () {}, function () { window.location.reload(); });
      });
    }, function () {
      moveCard(id, from);
      toast('Claim failed. Try again.');
    });
  }

  var justDone = {};
  function done(id) {
    var card = cardById(id);
    if (!card) return;
    var from = card.closest('#kAcceptedList') ? 'accepted' : 'cooking';
    var st = card.getAttribute('data-status');
    var prev = st === 'accepted' ? 'accepted' : (st === 'ready' ? 'ready' : 'preparing');
    card.remove();
    updateCounts();
    postAction(id, 'done', null, function () {
      justDone[id] = Date.now();
      toast('Done. Cashier handles the rest.', function () {
        delete justDone[id];
        postAction(id, 'undo', prev, function () { refresh(true); }, function () { window.location.reload(); });
      });
    }, function () {
      (from === 'accepted' ? acceptedList : cookingList).append(card);
      updateCounts();
      toast('Done failed. Card restored.');
    });
  }

  board.addEventListener('submit', function (e) {
    var form = e.target.closest('form.k-claim, form.k-done');
    if (!form) return;
    e.preventDefault();
    var card = e.target.closest('[data-order-id]');
    if (!card) return;
    var id = card.getAttribute('data-order-id');
    if (form.classList.contains('k-claim')) claim(id);
    else done(id);
  });

  board.addEventListener('click', function (e) {
    var card = e.target.closest('[data-order-id]');
    if (!card || e.target.closest('button')) return;
    var id = card.getAttribute('data-order-id');
    var now = Date.now();
    if (lastTap.id === id && now - lastTap.at < 400) {
      lastTap = { id: null, at: 0 };
      done(id);
    } else {
      lastTap = { id: id, at: now };
    }
  });

  board.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var card = e.target.closest('[data-order-id]');
    if (!card || e.target.closest('button')) return;
    e.preventDefault();
    var id = card.getAttribute('data-order-id');
    if (card.closest('#kAcceptedList')) claim(id);
    else done(id);
  });

  board.addEventListener('pointerdown', function (e) {
    var card = e.target.closest('[data-order-id]');
    if (!card || e.target.closest('button')) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    dragging = { id: card.getAttribute('data-order-id'), el: card, x: e.clientX, y: e.clientY, active: false };
  });
  document.addEventListener('pointermove', function (e) {
    if (!dragging) return;
    if (!dragging.active && Math.hypot(e.clientX - dragging.x, e.clientY - dragging.y) > 8) {
      dragging.active = true;
      dragging.el.classList.add('k-ticket--drag');
    }
    if (!dragging.active) return;
    document.querySelectorAll('.k-col').forEach(function (col) {
      var r = col.getBoundingClientRect();
      var over = e.clientX > r.left && e.clientX < r.right && e.clientY > r.top && e.clientY < r.bottom;
      col.classList.toggle('k-col--drop', over);
    });
  });
  document.addEventListener('pointerup', function (e) {
    if (!dragging) return;
    var d = dragging;
    dragging = null;
    d.el.classList.remove('k-ticket--drag');
    document.querySelectorAll('.k-col').forEach(function (col) { col.classList.remove('k-col--drop'); });
    if (!d.active) return;
    var cookR = document.getElementById('kColCooking').getBoundingClientRect();
    var accR = document.getElementById('kColAccepted').getBoundingClientRect();
    var inCook = e.clientX > cookR.left && e.clientX < cookR.right && e.clientY > cookR.top && e.clientY < cookR.bottom;
    var inAcc = e.clientX > accR.left && e.clientX < accR.right && e.clientY > accR.top && e.clientY < accR.bottom;
    var card = cardById(d.id);
    if (!card) return;
    var cur = card.closest('#kAcceptedList') ? 'accepted' : 'cooking';
    if (inCook && cur === 'accepted') claim(d.id);
    else if (inAcc && cur === 'cooking') {
      var from = moveCard(d.id, 'accepted');
      postAction(d.id, 'undo', 'accepted', function () {
        toast('Moved back to Accepted.', function () {
          claim(d.id);
        });
      }, function () { moveCard(d.id, from); toast('Move failed.'); });
    }
  });

  var knownGone = {};
  function refresh(silent) {
    if (dragging) return;
    fetch('/kitchen/?check=1', { cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) {
        var seen = {};
        (d.cards || []).forEach(function (c) {
          seen[c.id] = true;
          var card = cardById(c.id);
          var wantCol = c.col === 'cooking' ? 'cooking' : 'accepted';
          if (!card) {
            var dest = wantCol === 'accepted' ? acceptedList : cookingList;
            var empty = dest.querySelector('[data-empty]');
            if (empty) empty.remove();
            dest.insertAdjacentHTML('beforeend', c.html);
          } else {
            var cur = card.closest('#kAcceptedList') ? 'accepted' : 'cooking';
            if (cur !== wantCol) moveCard(c.id, wantCol);
            card.setAttribute('data-status', c.status);
          }
        });
        board.querySelectorAll('[data-order-id]').forEach(function (card) {
          var id = card.getAttribute('data-order-id');
          if (!seen[id]) {
            if (justDone[id] && Date.now() - justDone[id] < 30000) {
              card.remove();
              return;
            }
            delete justDone[id];
            if (!knownGone[id]) {
              knownGone[id] = true;
              toast('An order left the board (done or cancelled).');
            }
            card.remove();
          }
        });
        updateCounts();
      })
      .catch(function () { if (!silent) toast('Refresh failed. Check connection.'); });
  }
  setInterval(refresh, 10000);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
