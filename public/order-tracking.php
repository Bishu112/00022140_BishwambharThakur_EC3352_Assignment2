<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['customer']);
// Open the latest customer order when the tracking link has no order id.
$orderId=(int)($_GET['id']??0);
if($orderId<1){$latest=$pdo->prepare('SELECT order_id FROM orders WHERE customer_id=? ORDER BY created_at DESC LIMIT 1');$latest->execute([signed_in()['user_id']]);$orderId=(int)($latest->fetchColumn()?:0);if($orderId<1){flash('You have no orders to track yet.');header('Location: customer-dashboard.php');exit;}}
$st=$pdo->prepare('SELECT order_id,fulfillment,recipient_name,phone,address,payment_method,status,total,created_at FROM orders WHERE order_id=? AND customer_id=?');
$st->execute([$orderId,signed_in()['user_id']]);
$order=$st->fetch();
if(!$order){http_response_code(404);exit('Order not found.');}
$st=$pdo->prepare('SELECT status,note,changed_at AS created_at FROM order_status_history WHERE order_id=? ORDER BY changed_at,history_id');
$st->execute([$orderId]);
$history=$st->fetchAll();
$steps=['placed','vendor accepted','ready for delivery','shipped','delivered'];
$current=array_search($order['status'],$steps,true);
$pageTitle='Track order #'.$orderId.' | Redline Royale';
$workspaceType='customer';$workspaceActive='Order tracking';
$pageClass='tracking-page';
include __DIR__.'/../includes/header.php';
?>
<div class="tracking-heading-row">
  <div><div class="page-kicker">REDLINE ROYALE · ORDER CARE</div><h1 class="page-heading">Your order, in progress.</h1><p class="tracking-intro">Follow each step from confirmation to delivery.</p></div>
  <a class="tracking-back" href="customer-dashboard.php">← Back to your account</a>
</div>
<section class="tracking-overview">
  <div><span class="tracking-label">ORDER NUMBER</span><strong class="tracking-order-number">#<?= (int)$order['order_id']?></strong><span class="tracking-date">Placed <?=e(date('d M Y',strtotime($order['created_at'])))?></span></div>
  <div class="tracking-overview-status"><span class="tracking-label">CURRENT STATUS</span><span class="tracking-status-pill <?= $order['status']==='discarded'?'is-cancelled':''?>"><i></i><?=e(ucfirst($order['status']))?></span></div>
  <div class="tracking-overview-total"><span class="tracking-label">ORDER TOTAL</span><strong>Rs. <?=number_format((float)$order['total'],2)?></strong></div>
</section>
<div class="tracking-content">
  <section class="tracking-panel tracking-progress-panel">
    <div class="tracking-panel-heading"><div><span class="tracking-label">LIVE ORDER UPDATES</span><h2><?= $order['status']==='discarded'?'This order was cancelled':'Your delivery journey'?></h2></div><span class="tracking-live-mark"><i></i> ORDER STATUS</span></div>
    <?php if($order['status']!=='discarded'):?>
    <ol class="tracking-timeline"><?php foreach($steps as $index=>$step):$done=$current!==false&&$index<$current;$active=$current===$index;$event=null;foreach($history as $item){if($item['status']===$step){$event=$item;break;}}?><li class="<?=$done?'is-complete':''?> <?=$active?'is-current':''?>"><div class="tracking-timeline-marker"><?=$done?'✓':($index+1)?></div><div class="tracking-timeline-copy"><strong><?=e(ucfirst($step))?></strong><?php if($active):?><span class="tracking-now">Current step</span><?php endif;?><?php if($event):?><small><?=e(date('d M Y · g:i A',strtotime($event['created_at'])))?></small><?php if($event['note']):?><p><?=e($event['note'])?></p><?php endif;?><?php elseif($active):?><small>We’ll update this page as your order progresses.</small><?php else:?><small>Next in your order journey</small><?php endif;?></div></li><?php endforeach;?></ol>
    <?php else:?><p class="tracking-cancelled-copy">Please contact the store if you need help with this order.</p><?php endif;?>
  </section>
  <aside class="tracking-side-column">
    <section class="tracking-panel tracking-detail-panel"><span class="tracking-label">FULFILMENT</span><h2><?=e(ucfirst($order['fulfillment']))?></h2><div class="tracking-rule"></div><dl><dt>Recipient</dt><dd><?=e($order['recipient_name'])?></dd><dt>Phone</dt><dd><?=e($order['phone'])?></dd><dt><?= $order['fulfillment']==='delivery'?'Delivery address':'Collection point'?></dt><dd><?=e($order['fulfillment']==='delivery'?$order['address']:'Selected pickup point')?></dd></dl></section>
    <section class="tracking-panel tracking-payment-panel"><span class="tracking-label">PAYMENT</span><h2><?=e($order['payment_method']==='cash_on_delivery'?'Cash on delivery':'Cash at collection')?></h2><div class="tracking-rule"></div><p>Total due</p><strong class="tracking-payment-total">Rs. <?=number_format((float)$order['total'],2)?></strong></section>
    <section class="tracking-support"><span class="tracking-support-icon">✦</span><div><strong>Here to help</strong><p>Contact the store and quote order #<?=(int)$order['order_id']?>.</p></div></section>
  </aside>
</div>
<?php include __DIR__.'/../includes/footer.php';?>
