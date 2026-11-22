<?php
/**
 * Barangay inventory. Inventory belongs to the barangay; the federation
 * has none. quantity_on_hand is the barangay's total holding and
 * quantity_released is what is out on loan; available is the difference.
 * Approving a request, in one transaction: writes the issue transaction,
 * increases quantity_released and recomputes stock_status. Returns reverse it.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_BARANGAY);
$b = my_barangay();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $tab = post('tab') ?: 'requests';
    $pdo = db();

    if ($action === 'save_item') {
        $tab = 'inventory';
        $id = post_int('item_id');
        $name = post('item_name');
        $total = post_int('quantity_on_hand');
        $reorder = max(0, post_int('reorder_level'));
        if ($name === '' || $total < 0) {
            flash('error', 'Enter the item name and a total quantity of zero or more.');
        } elseif ($id) {
            $pdo->beginTransaction();
            $item = q_one('SELECT * FROM inventory_items WHERE item_id = ? AND barangay_id = ? FOR UPDATE', [$id, $b]) ?? require_barangay_access(null);
            if ($total < (int) $item['quantity_released']) {
                $pdo->rollBack();
                flash('error', 'Total cannot be lower than the ' . (int) $item['quantity_released'] . ' currently issued out.');
            } else {
                q('UPDATE inventory_items SET item_name = ?, unit = ?, quantity_on_hand = ?, reorder_level = ? WHERE item_id = ?', [$name, post('unit') ?: null, $total, $reorder, $id]);
                $delta = $total - (int) $item['quantity_on_hand'];
                if ($delta !== 0) {
                    q('INSERT INTO inventory_transactions (item_id, recorded_by, trans_type, quantity) VALUES (?, ?, ?, ?)', [$id, uid(), $delta > 0 ? 'stock_in' : 'adjustment', abs($delta)]);
                }
                refresh_item_status($id);
                $pdo->commit();
                audit('INVENTORY_UPDATED', 'Item #' . $id . ' ' . $name . ($delta ? ' total ' . ($delta > 0 ? '+' : '') . $delta : ''));
                flash('success', $name . ' updated.');
            }
        } else {
            $pdo->beginTransaction();
            q('INSERT INTO inventory_items (barangay_id, item_name, unit, quantity_on_hand, reorder_level) VALUES (?, ?, ?, ?, ?)', [$b, $name, post('unit') ?: null, $total, $reorder]);
            $id = (int) $pdo->lastInsertId();
            if ($total > 0) {
                q('INSERT INTO inventory_transactions (item_id, recorded_by, trans_type, quantity) VALUES (?, ?, "stock_in", ?)', [$id, uid(), $total]);
            }
            refresh_item_status($id);
            $pdo->commit();
            audit('INVENTORY_CREATED', 'Added item #' . $id . ' ' . $name);
            flash('success', $name . ' added to the inventory.');
        }
    }

    if (in_array($action, ['approve', 'decline', 'return'], true)) {
        $rid = post_int('request_id');
        $remarks = post('remarks');
        $pdo->beginTransaction();
        // Lock the request and its item; both must belong to this barangay.
        $req = q_one('SELECT r.*, i.item_name, i.quantity_on_hand, i.quantity_released, yp.user_id
                        FROM equipment_requests r JOIN inventory_items i ON i.item_id = r.item_id JOIN youth_profiles yp ON yp.profile_id = r.requested_by
                       WHERE r.request_id = ? AND i.barangay_id = ? FOR UPDATE', [$rid, $b]);
        if (!$req) {
            $pdo->rollBack();
            require_barangay_access(null);
        }
        $msg = null;
        if ($action === 'approve' && $req['req_status'] === 'pending') {
            $available = (int) $req['quantity_on_hand'] - (int) $req['quantity_released'];
            if ($req['quantity'] > $available) {
                $pdo->rollBack();
                flash('error', 'Only ' . $available . ' available. Decline the request or adjust the stock first.');
                redirect('barangay/inventory.php');
            }
            q('UPDATE equipment_requests SET req_status = "approved", approved_by = ?, remarks = ?, date_acted = NOW() WHERE request_id = ?', [uid(), $remarks ?: 'Approved for pickup at the barangay hall.', $rid]);
            q('INSERT INTO inventory_transactions (item_id, request_id, recorded_by, trans_type, quantity) VALUES (?, ?, ?, "issue", ?)', [$req['item_id'], $rid, uid(), $req['quantity']]);
            q('UPDATE inventory_items SET quantity_released = quantity_released + ? WHERE item_id = ?', [$req['quantity'], $req['item_id']]);
            refresh_item_status((int) $req['item_id']);
            $msg = ['Equipment request approved', 'Your request for ' . $req['quantity'] . ' × ' . $req['item_name'] . ' was approved. ' . ($remarks ?: 'Pick it up at the barangay hall.')];
        } elseif ($action === 'decline' && $req['req_status'] === 'pending') {
            if ($remarks === '') {
                $pdo->rollBack();
                flash('error', 'Give the reason for declining so the member knows why.');
                redirect('barangay/inventory.php');
            }
            q('UPDATE equipment_requests SET req_status = "declined", approved_by = ?, remarks = ?, date_acted = NOW() WHERE request_id = ?', [uid(), $remarks, $rid]);
            $msg = ['Equipment request declined', $req['item_name'] . ': ' . $remarks];
        } elseif ($action === 'return' && $req['req_status'] === 'approved') {
            $tab = 'issuances';
            q('UPDATE equipment_requests SET req_status = "returned", date_returned = NOW() WHERE request_id = ?', [$rid]);
            q('INSERT INTO inventory_transactions (item_id, request_id, recorded_by, trans_type, quantity) VALUES (?, ?, ?, "return", ?)', [$req['item_id'], $rid, uid(), $req['quantity']]);
            q('UPDATE inventory_items SET quantity_released = GREATEST(quantity_released - ?, 0) WHERE item_id = ?', [$req['quantity'], $req['item_id']]);
            refresh_item_status((int) $req['item_id']);
        } else {
            $pdo->rollBack();
            flash('error', 'That request has already been acted on.');
            redirect('barangay/inventory.php');
        }
        $pdo->commit();
        audit('EQUIPMENT_' . strtoupper($action === 'return' ? 'returned' : ($action === 'approve' ? 'approved' : 'declined')), 'Request #' . $rid . ' · ' . $req['quantity'] . ' × ' . $req['item_name']);
        if ($msg && $req['user_id']) {
            notify((int) $req['user_id'], $msg[0], $msg[1], 'member/equipment.php');
        }
        flash('success', 'Request #' . $rid . ' ' . ['approve' => 'approved — stock deducted', 'decline' => 'declined', 'return' => 'marked returned — stock restored'][$action] . '.');
    }
    redirect('barangay/inventory.php?tab=' . urlencode($tab));
}

$tab = in_array(get('tab'), ['requests', 'inventory', 'issuances'], true) ? get('tab') : 'requests';
$items = q_all('SELECT *, quantity_on_hand - quantity_released AS available FROM inventory_items WHERE barangay_id = ? ORDER BY item_name', [$b]);
$requests = q_all('SELECT r.*, i.item_name, i.unit, i.quantity_on_hand - i.quantity_released AS available, yp.full_name, yp.date_encoded, u.username AS actor
                     FROM equipment_requests r JOIN inventory_items i ON i.item_id = r.item_id JOIN youth_profiles yp ON yp.profile_id = r.requested_by
                     LEFT JOIN users u ON u.user_id = r.approved_by WHERE i.barangay_id = ? ORDER BY (r.req_status = "pending") DESC, r.date_requested DESC', [$b]);
$pending = array_filter($requests, fn ($r) => $r['req_status'] === 'pending');
$issued = array_filter($requests, fn ($r) => in_array($r['req_status'], ['approved', 'returned'], true));

if (get('export') === 'csv') {
    send_csv('inventory-' . date('Ymd') . '.csv', ['Item', 'Unit', 'Total', 'Issued', 'Available', 'Reorder level', 'Status'],
        array_map(fn ($i) => [$i['item_name'], $i['unit'], $i['quantity_on_hand'], $i['quantity_released'], $i['available'], $i['reorder_level'], $i['stock_status']], $items));
}

$title = 'Inventory';
$nav = 'inventory';
$crumb = 'Resources · Inventory';
require __DIR__ . '/../includes/header.php';
$reqCode = fn ($r) => sprintf('EQR-%s-%03d', date('Y', strtotime($r['date_requested'])), $r['request_id']);
?>
<div class="page-head">
  <div><h1 class="page-title">Inventory</h1><div class="page-sub"><?= e(current_user()['barangay_name']) ?> · Requests from registered KK members</div></div>
  <div class="page-actions"><?= pill('pending', count($pending) . ' pending review') ?><a class="btn btn-secondary" href="?export=csv">↓ Export</a><button class="btn btn-primary" data-open="dlg-item" data-fill='{"item_id":"0","item_name":"","unit":"pcs","quantity_on_hand":"","reorder_level":"0"}'>+ Add item</button></div>
</div>
<div class="scope-banner">Only this barangay's SK officials can approve or reject equipment requests from their registered KK members. Approving deducts the issued quantity and updates the stock status automatically.</div>

<div class="grid grid-4 mb">
  <div class="card stat"><div class="stat-label">Pending Requests</div><div class="stat-value" style="color:#C99400"><?= count($pending) ?></div></div>
  <div class="card stat"><div class="stat-label">Approved Requests</div><div class="stat-value green"><?= count(array_filter($requests, fn ($r) => $r['req_status'] === 'approved')) ?></div></div>
  <div class="card stat"><div class="stat-label">Available Units</div><div class="stat-value blue"><?= array_sum(array_column($items, 'available')) ?></div></div>
  <div class="card stat"><div class="stat-label">Low / Out of Stock</div><div class="stat-value red"><?= count(array_filter($items, fn ($i) => $i['stock_status'] !== 'available')) ?></div></div>
</div>

<div class="tabs">
  <a class="tab<?= $tab === 'requests' ? ' active' : '' ?>" href="?tab=requests">Member Requests</a>
  <a class="tab<?= $tab === 'inventory' ? ' active' : '' ?>" href="?tab=inventory">Barangay Inventory</a>
  <a class="tab<?= $tab === 'issuances' ? ' active' : '' ?>" href="?tab=issuances">Issuance Log</a>
</div>

<?php if ($tab === 'requests'): ?>
  <?php foreach ($requests as $r): $st = $r['req_status']; if ($st === 'returned') continue; ?>
    <div class="row-card <?= ['pending' => 'amber', 'approved' => 'green', 'declined' => 'red'][$st] ?? '' ?>">
      <div>
        <div class="row" style="gap:8px"><span class="record-id"><?= e($reqCode($r)) ?></span><?= pill($st) ?></div>
        <div class="row-card-title"><?= e($r['item_name']) ?> ×<?= (int) $r['quantity'] ?></div>
        <div class="row-card-detail"><?= e($r['full_name']) ?> · <?= e(kk_id((int) $r['requested_by'], $r['date_encoded'])) ?></div>
        <div class="row-card-detail">Needed by <?= e(fmt_date($r['date_needed'])) ?> · <?= e($r['purpose']) ?></div>
        <?php if ($r['remarks']): ?><div class="row-card-detail" style="margin-top:4px"><strong>SK remarks:</strong> <?= e($r['remarks']) ?></div><?php endif; ?>
      </div>
      <div class="row-card-side">
        <span class="row-card-time">Submitted <?= e(fmt_date($r['date_requested'])) ?></span>
        <?php if ($st === 'pending'): ?><button class="btn btn-primary btn-sm" data-open="dlg-review" data-fill='<?= e(json_encode(['request_id' => $r['request_id'], 'rv_code' => $reqCode($r), 'rv_member' => $r['full_name'], 'rv_item' => $r['item_name'] . ' ×' . $r['quantity'], 'rv_avail' => $r['available'] . ' available now', 'rv_needed' => fmt_date($r['date_needed']), 'rv_purpose' => $r['purpose'], 'remarks' => ''])) ?>'>Review request</button><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!array_filter($requests, fn ($r) => $r['req_status'] !== 'returned')): ?><div class="card empty">No equipment requests yet.</div><?php endif; ?>

<?php elseif ($tab === 'inventory'): ?>
  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Item Code</th><th>Item</th><th class="num">Total</th><th class="num">Issued</th><th class="num">Available</th><th class="num">Reorder at</th><th>Stock Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $i): $pctAvail = $i['quantity_on_hand'] ? $i['available'] / $i['quantity_on_hand'] * 100 : 0; ?>
        <tr><td class="record-id"><?= e(sprintf('ITM-%03d', $i['item_id'])) ?></td><td style="font-weight:500"><?= e($i['item_name']) ?></td>
          <td class="num"><?= (int) $i['quantity_on_hand'] ?> <?= e($i['unit']) ?></td><td class="num"><?= (int) $i['quantity_released'] ?></td>
          <td class="num"><strong><?= (int) $i['available'] ?></strong><div style="margin-top:4px"><?= bar($pctAvail, $i['stock_status'] === 'available' ? 'green' : ($i['stock_status'] === 'low stock' ? 'amber' : 'red')) ?></div></td>
          <td class="num"><?= (int) $i['reorder_level'] ?></td><td><?= pill($i['stock_status']) ?></td>
          <td class="right"><button class="btn btn-secondary btn-sm" data-open="dlg-item" data-fill='<?= e(json_encode(['item_id' => $i['item_id'], 'item_name' => $i['item_name'], 'unit' => $i['unit'] ?? '', 'quantity_on_hand' => $i['quantity_on_hand'], 'reorder_level' => $i['reorder_level']])) ?>'>Edit</button></td></tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="8" class="empty">No items yet. Add the barangay's equipment.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Issuance</th><th>KK Member</th><th>Item</th><th>Issued</th><th>Due / Returned</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($issued as $r): ?>
        <tr><td class="record-id"><?= e($reqCode($r)) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['item_name']) ?> ×<?= (int) $r['quantity'] ?></td>
          <td class="sub"><?= e(fmt_date($r['date_acted'])) ?></td><td class="sub"><?= e($r['req_status'] === 'returned' ? fmt_date($r['date_returned']) : fmt_date($r['date_needed'])) ?></td>
          <td><?= $r['req_status'] === 'returned' ? pill('completed', 'Returned') : pill('on loan', 'On loan') ?></td>
          <td class="right"><?php if ($r['req_status'] === 'approved'): ?><form method="post" data-confirm="Mark <?= e($r['item_name']) ?> ×<?= (int) $r['quantity'] ?> as returned?"><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>"><button class="btn btn-secondary btn-sm">Record return</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$issued): ?><tr><td colspan="7" class="empty">Nothing has been issued yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<dialog class="modal" id="dlg-review">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="request_id">
    <div class="modal-head"><div><div class="modal-title">Review Equipment Request</div><div class="record-id" data-text="rv_code"></div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="kv plain"><span>KK Member</span><span data-text="rv_member"></span></div>
    <div class="kv plain"><span>Item</span><span data-text="rv_item"></span></div>
    <div class="kv plain"><span>Stock</span><span data-text="rv_avail"></span></div>
    <div class="kv plain"><span>Date Needed</span><span data-text="rv_needed"></span></div>
    <div class="kv plain"><span>Purpose</span><span data-text="rv_purpose"></span></div>
    <div class="field mt"><label class="label-mono">Remarks</label><textarea class="input" name="remarks" rows="3" maxlength="255" placeholder="Add pickup instructions, or the reason for declining (required to decline)"></textarea></div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-danger" name="action" value="decline">Decline</button><button class="btn btn-primary" name="action" value="approve">Approve</button></div>
  </form>
</dialog>
<dialog class="modal" id="dlg-item">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_item"><input type="hidden" name="item_id">
    <div class="modal-head"><div class="modal-title">Inventory Item</div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label>Item name</label><input class="input" name="item_name" required maxlength="150"></div>
    <div class="form-grid">
      <div class="field"><label>Total quantity held</label><input class="input" type="number" min="0" name="quantity_on_hand" required></div>
      <div class="field"><label>Unit</label><input class="input" name="unit" maxlength="30" placeholder="pcs, set, unit"></div>
      <div class="field"><label>Reorder / low-stock level</label><input class="input" type="number" min="0" name="reorder_level"><div class="hint">Status turns "low stock" when available falls to this level.</div></div>
    </div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Save item</button></div>
  </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php';
