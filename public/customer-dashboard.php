<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['customer']);
$customerId=(int)signed_in()['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $st = $pdo->prepare('SELECT status FROM orders WHERE order_id=? AND customer_id=?');
    $st->execute([$orderId, $customerId]);
    $current = $st->fetchColumn();
    if ($current === false) {
        flash('We could not find that order on your account.');
    } elseif (!in_array($current, ['placed', 'vendor accepted'], true)) {
        flash('Order #'.$orderId.' can no longer be cancelled. Please contact the store for help.');
    } else {
        $pdo->beginTransaction();
        $upd = $pdo->prepare("UPDATE orders SET status='discarded' WHERE order_id=? AND customer_id=? AND status IN ('placed','vendor accepted')");
        $upd->execute([$orderId, $customerId]);
        if ($upd->rowCount()) {
            $pdo->prepare('INSERT INTO order_status_history(order_id,changed_by,status,note) VALUES(?,?,?,?)')
                ->execute([$orderId, $customerId, 'discarded', 'Cancelled by customer']);
            $pdo->prepare("UPDATE transactions SET payment_status='void' WHERE order_id=?")->execute([$orderId]);
            $pdo->commit();
            flash('Order #'.$orderId.' has been cancelled.');
        } else {
            $pdo->rollBack();
            flash('That order could not be cancelled. Refresh the page and try again.');
        }
    }
    header('Location: customer-dashboard.php');
    exit;
}

$st=$pdo->prepare("SELECT COUNT(*) total_orders,COALESCE(SUM(total),0) total_spend FROM orders WHERE customer_id=? AND status<>'discarded'");
$st->execute([$customerId]);
$summary=$st->fetch();
$st=$pdo->prepare("SELECT SUM(status IN ('placed','vendor accepted','ready for delivery','shipped')) in_progress,SUM(status='delivered') delivered FROM orders WHERE customer_id=?");
$st->execute([$customerId]);
$orderStats=$st->fetch()?:['in_progress'=>0,'delivered'=>0];
$st=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status='discarded'");
$st->execute([$customerId]);
$cancelled=(int)$st->fetchColumn();
$st=$pdo->prepare('SELECT o.order_id,o.status,o.fulfillment,o.total,o.created_at,COUNT(i.order_item_id) item_count FROM orders o LEFT JOIN order_items i ON i.order_id=o.order_id WHERE o.customer_id=? GROUP BY o.order_id ORDER BY o.created_at DESC LIMIT 10');
$st->execute([$customerId]);
$orders=$st->fetchAll();
$pageTitle='My account | Redline Royale';$workspaceType='customer';$workspaceActive='Overview';
include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">DASHBOARD WELCOME SCREEN</div>
<h1 class="page-heading">Welcome back <?=e(signed_in()['full_name'])?></h1>
<p class="customer-welcome-copy">Your orders, account activity and recommendations in one place.</p>
<section class="workspace-metrics customer-metrics" aria-label="Order summary">
  <article class="workspace-card"><span>Orders placed</span><strong><?= (int)$summary['total_orders']?></strong><small>All-time orders</small></article>
  <article class="workspace-card"><span>In progress</span><strong><?= (int)$orderStats['in_progress']?></strong><small>On the way to you</small></article>
  <article class="workspace-card"><span>Delivered</span><strong><?= (int)$orderStats['delivered']?></strong><small>Successfully received</small></article>
</section>
<section class="workspace-panels customer-panels">
<article class="workspace-panel customer-recent-panel"><h2>Recent order <?php if($orders):?>· AB-<?=str_pad((string)$orders[0]['order_id'],5,'0',STR_PAD_LEFT)?><?php endif;?></h2>
<?php if($orders):$latest=$orders[0]; ?><p>Placed <?=e(date('d M Y',strtotime($latest['created_at'])))?> · <?=e(strtoupper($latest['fulfillment']==='delivery'?'COD':'Collection'))?> · Rs. <?=number_format((float)$latest['total'],2)?></p><p><strong>Status: <?=e(ucfirst($latest['status']))?></strong></p><a class="button customer-track-button" href="order-tracking.php?id=<?=(int)$latest['order_id']?>">Track order</a><?php else:?><p class="muted">Your recent orders will appear here.</p><a class="button customer-track-button" href="index.php">Browse products</a><?php endif;?>
</article>
<article class="workspace-panel customer-recommended-panel"><h2>Recommended for you</h2><p>Linen layers · Recent · Most viewed</p><a class="button workspace-action" href="index.php?sort=recommended">Browse products</a></article>
</section>
<section class="customer-order-list" id="orders"><h2>My orders</h2>
<?php if($orders):?>
<div class="table-scroll" role="region" aria-label="Recent orders" tabindex="0"><table><thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Fulfilment</th><th>Status</th><th>Total</th><th>Actions</th></tr></thead><tbody>
<?php foreach($orders as $order):?>
<tr>
  <td>#<?=(int)$order['order_id']?></td>
  <td><?=e(date('d M Y', strtotime($order['created_at'])))?></td>
  <td><?=(int)$order['item_count']?></td>
  <td><?=e(ucfirst($order['fulfillment']))?></td>
  <td><span class="status-pill<?= $order['status']==='discarded'?' is-cancelled':''?>"><?=e(ucfirst($order['status']))?></span></td>
  <td>Rs. <?=number_format((float)$order['total'],2)?></td>
  <td class="order-actions">
    <a href="order-tracking.php?id=<?=(int)$order['order_id']?>">Track order</a>
    <?php if(in_array($order['status'], ['placed','vendor accepted'], true)):?>
      <form method="post" data-confirm="Cancel order #<?=(int)$order['order_id']?>? This cannot be undone."><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=(int)$order['order_id']?>"><button class="text-button" type="submit">Cancel order</button></form>
    <?php endif;?>
  </td>
</tr>
<?php endforeach;?>
</tbody></table></div>
<?php else:?><section class="panel"><p>You haven’t placed an order yet.</p><a class="button" href="index.php">Browse clothing</a></section><?php endif;?>
 </section>
<?php include __DIR__.'/../includes/footer.php';?>
