<?php
/**
 * KK members view their barangay's inventory availability and request
 * equipment. Only the barangay officials change stock.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$b = my_barangay();
$pid = my_profile_id();

if (is_post()) {
    csrf_check();
    $itemId = post_int('item_id');
    $qty = post_int('quantity');
    $needed = post('date_needed');
    $purpose = post('purpose');
    $item = q_one('SELECT *, quantity_on_hand - quantity_released AS available FROM inventory_items WHERE item_id = ? AND barangay_id = ?', [$itemId, $b]);
    if (!$item) {
        flash('error', 'Choose equipment from your barangay inventory.');
    } elseif ($qty < 1 || $qty > (int) $item['available']) {
        flash('error', 'Request between 1 and ' . (int) $item['available'] . ' ' . ($item['unit'] ?: 'units') . ' — that is what is available now.');
    } elseif (!strtotime($needed) || $needed < date('Y-m-d')) {
        flash('error', 'Choose a date needed that is today or later.');
    } elseif ($purpose === '') {
        flash('error', 'Explain how the equipment will be used.');
    } else {
        q('INSERT INTO equipment_requests (item_id, requested_by, quantity, purpose, date_needed) VALUES (?, ?, ?, ?, ?)', [$itemId, $pid, $qty, mb_substr($purpose, 0, 255), $needed]);
        $rid = (int) db()->lastInsertId();
        audit('EQUIPMENT_REQUESTED', 'Request #' . $rid . ' for ' . $qty . ' × ' . $item['item_name']);
        notify_role(ROLE_BARANGAY, 'New equipment request', display_name() . ' requested ' . $qty . ' × ' . $item['item_name'] . ' for ' . fmt_date($needed) . '.', 'barangay/inventory.php', $b);
        flash('success', 'Request sent to the SK Barangay Official of ' . current_user()['barangay_name'] . '.');
    }
    redirect('member/equipment.php');
}

$items = q_all('SELECT *, quantity_on_hand - quantity_released AS available FROM inventory_items WHERE barangay_id = ? ORDER BY item_name', [$b]);
$requests = q_all('SELECT r.*, i.item_name, i.unit FROM equipment_requests r JOIN inventory_items i ON i.item_id = r.item_id WHERE r.requested_by = ? ORDER BY r.date_requested DESC', [$pid]);

$title = 'Request Equipment';
$nav = 'equipment';
$crumb = 'Requests · Request Equipment';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Barangay Inventory</h1><div class="page-sub">Requests are sent to the SK Barangay Official of <?= e(current_user()['barangay_name']) ?></div></div>
  <button class="btn btn-primary" data-open="dlg-req">+ New request</button>
</div>
<div class="scope-banner">Your barangay SK Official will review the request and provide approval, rejection, or pickup instructions.</div>

<div class="grid grid-2">
  <div>
    <div class="section-title mb">My Requests</div>
    <?php foreach ($requests as $r): $st = $r['req_status']; ?>
      <div class="row-card <?= ['pending' => 'amber', 'approved' => 'green', 'declined' => 'red', 'returned' => ''][$st] ?>">
        <div>
          <div class="row" style="gap:8px"><span class="record-id"><?= e(sprintf('EQR-%s-%03d', date('Y', strtotime($r['date_requested'])), $r['request_id'])) ?></span><?= $st === 'returned' ? pill('completed', 'Returned') : pill($st) ?></div>
          <div class="row-card-title"><?= e($r['item_name']) ?> ×<?= (int) $r['quantity'] ?></div>
          <div class="row-card-detail">Needed by <?= e(fmt_date($r['date_needed'])) ?> · <?= e($r['purpose']) ?></div>
          <?php if ($r['remarks']): ?><div class="row-card-detail" style="margin-top:4px"><strong>SK Barangay:</strong> <?= e($r['remarks']) ?></div><?php endif; ?>
        </div>
        <div class="row-card-side"><span class="row-card-time"><?= e(fmt_date($r['date_requested'])) ?></span></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$requests): ?><div class="card empty">You have not requested any equipment yet.</div><?php endif; ?>
  </div>
  <div class="card table-wrap" style="align-self:start">
    <div class="card-head"><div class="section-title">Availability</div></div>
    <table class="table">
      <thead><tr><th>Item</th><th class="num">Available</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($items as $i): ?>
        <tr><td style="font-weight:500"><?= e($i['item_name']) ?></td><td class="num"><?= (int) $i['available'] ?> <?= e($i['unit']) ?></td><td><?= pill($i['stock_status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="3" class="empty">Your barangay has not listed any equipment yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<dialog class="modal" id="dlg-req">
  <form method="post">
    <?= csrf_field() ?>
    <div class="modal-head"><div><div class="modal-title">Request Barangay Equipment</div><div class="muted small">Submit to the SK Barangay Official of <?= e(current_user()['barangay_name']) ?>.</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label class="label-mono">Equipment</label><select class="input" name="item_id" required><option value="">Select equipment…</option>
      <?php foreach ($items as $i): if ((int) $i['available'] < 1) continue; ?><option value="<?= (int) $i['item_id'] ?>"><?= e($i['item_name']) ?> · <?= (int) $i['available'] ?> available</option><?php endforeach; ?></select></div>
    <div class="form-grid">
      <div class="field"><label class="label-mono">Quantity</label><input class="input" type="number" min="1" name="quantity" required></div>
      <div class="field"><label class="label-mono">Date needed</label><input class="input" type="date" name="date_needed" min="<?= date('Y-m-d') ?>" required></div>
    </div>
    <div class="field"><label class="label-mono">Purpose / activity</label><textarea class="input" name="purpose" rows="3" maxlength="255" required placeholder="Explain how the equipment will be used"></textarea></div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Submit request</button></div>
  </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php';
