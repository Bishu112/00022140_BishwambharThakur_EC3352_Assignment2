<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['vendor']);
$vendorId=(int)signed_in()['user_id'];
$st=$pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id=? AND status='active'");$st->execute([$vendorId]);$products=(int)$st->fetchColumn();
$st=$pdo->prepare("SELECT COALESCE(SUM(i.quantity),0) units,COALESCE(SUM(i.quantity*i.unit_price),0) revenue FROM order_items i JOIN orders o ON o.order_id=i.order_id WHERE i.vendor_id=? AND o.created_at>=DATE_FORMAT(CURRENT_DATE,'%Y-%m-01') AND o.status<>'discarded'");$st->execute([$vendorId]);$sales=$st->fetch();
$st=$pdo->prepare("SELECT COUNT(DISTINCT o.order_id) FROM orders o JOIN order_items i ON i.order_id=o.order_id WHERE i.vendor_id=? AND o.status='placed'");$st->execute([$vendorId]);$pending=(int)$st->fetchColumn();
$st=$pdo->prepare('SELECT o.order_id,o.status,o.total,o.created_at,u.full_name, SUM(i.quantity*i.unit_price) vendor_total FROM orders o JOIN order_items i ON i.order_id=o.order_id JOIN users u ON u.user_id=o.customer_id WHERE i.vendor_id=? GROUP BY o.order_id,o.status,o.total,o.created_at,u.full_name ORDER BY o.created_at DESC LIMIT 3');$st->execute([$vendorId]);$orders=$st->fetchAll();
$profile=$pdo->prepare('SELECT business_name FROM vendor_profiles WHERE user_id=?');$profile->execute([$vendorId]);$business=$profile->fetchColumn()?:signed_in()['full_name'];
$pageTitle='Vendor dashboard';$workspaceType='vendor';$workspaceActive='Overview';include __DIR__.'/../includes/header.php';
?>
<p class="eyebrow">Dashboard welcome screen</p>
<h1>Good morning, <?=e(signed_in()['full_name'])?>!</h1>
<section class="workspace-metrics" aria-label="Store summary">
  <article class="workspace-card"><span>Products listed</span><strong><?=number_format($products)?></strong><small>Active catalogue</small></article>
  <article class="workspace-card"><span>Sold this month</span><strong><?=number_format((int)$sales['units'])?></strong><small>Units ordered</small></article>
  <article class="workspace-card"><span>Revenue this month</span><strong>Rs. <?=number_format((float)$sales['revenue'],2)?></strong><small>From your orders</small></article>
  <article class="workspace-card"><span>Pending requests</span><strong><?=number_format($pending)?></strong><small>Awaiting action</small></article>
</section>
<section class="workspace-panels">
  <article class="workspace-panel"><h2>Recent orders and status</h2>
    <?php if($orders): ?><ul class="workspace-order-list"><?php foreach($orders as $order): ?><li><span>#<?=e($order['order_id'])?> / <?=e($order['full_name'])?> / <?=e(ucfirst($order['status']))?></span><span>Rs. <?=number_format((float)$order['vendor_total'],2)?></span></li><?php endforeach; ?></ul><?php else: ?><p class="muted">No orders yet. Your customer orders will appear here.</p><?php endif; ?>
  </article>
  <article class="workspace-panel"><h2>Quick actions</h2><a class="button workspace-action" href="product-list.php#add-product">Add a product</a><a class="button workspace-action" href="vendor-orders.php">Review order requests</a><p class="workspace-profile">Business profile: <span class="status-active">Active</span><br><small><?=e($business)?></small></p></article>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
