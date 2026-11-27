<?php
require_once __DIR__.'/../includes/bootstrap.php';require_role(['vendor']);$vendorId=(int)signed_in()['user_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  $allowed=['vendor accepted','ready for delivery','shipped','delivered','discarded'];
  $status=$_POST['status']??'';
  $orderId=(int)($_POST['order_id']??0);
  if(!in_array($status,$allowed,true)){
    flash('Choose a valid order status.');
  }else{
    $st=$pdo->prepare('SELECT o.status FROM orders o JOIN order_items i ON i.order_id=o.order_id WHERE o.order_id=? AND i.vendor_id=? LIMIT 1');
    $st->execute([$orderId,$vendorId]);
    $current=$st->fetchColumn();
    if($current===false){
      flash('That order is not part of your store.');
    }elseif($current===$status){
      flash('Order #'.$orderId.' is already marked “'.$status.'”.');
    }else{
      $st=$pdo->prepare('UPDATE orders o JOIN order_items i ON i.order_id=o.order_id SET o.status=? WHERE o.order_id=? AND i.vendor_id=?');
      $st->execute([$status,$orderId,$vendorId]);
      $log=$pdo->prepare('INSERT INTO order_status_history(order_id,changed_by,status,note) VALUES(?,?,?,?)');
      $log->execute([$orderId,$vendorId,$status,'Updated by vendor']);
      $txn=$pdo->prepare("UPDATE transactions SET payment_status=IF(?='delivered','paid',IF(?='discarded','void',payment_status)) WHERE order_id=?");
      $txn->execute([$status,$status,$orderId]);
      flash('Order #'.$orderId.' updated to “'.$status.'”.');
    }
  }
  header('Location: vendor-orders.php');exit;
}
$st=$pdo->prepare('SELECT DISTINCT o.order_id,o.fulfillment,o.status,o.total,o.created_at,u.full_name customer FROM orders o JOIN order_items i ON i.order_id=o.order_id JOIN users u ON u.user_id=o.customer_id WHERE i.vendor_id=? ORDER BY o.created_at DESC');$st->execute([$vendorId]);$orders=$st->fetchAll();
$it=$pdo->prepare('SELECT i.order_id,i.quantity,i.unit_price,p.title,o.created_at,o.status FROM order_items i JOIN products p ON p.product_id=i.product_id JOIN orders o ON o.order_id=i.order_id WHERE i.vendor_id=? ORDER BY o.created_at DESC, i.order_item_id');
$it->execute([$vendorId]);
$soldItems=$it->fetchAll();
$pageTitle='Vendor orders';$workspaceType='vendor';$workspaceActive='Orders';include __DIR__.'/../includes/header.php';?><div class="page-kicker">ORDER MANAGEMENT</div><h1 class="page-heading">Customer orders</h1><div class="table-scroll" role="region" aria-label="Vendor order requests" tabindex="0"><table><thead><tr><th>Order/customer</th><th>Fulfilment</th><th>Status</th><th>Total</th><th>Update</th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td>#<?=$o['order_id']?> · <?=e($o['customer'])?><br><small><?=e($o['created_at'])?></small></td><td><?=e($o['fulfillment'])?></td><td><?=e($o['status'])?></td><td>Rs. <?=number_format((float)$o['total'],2)?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="order_id" value="<?=$o['order_id']?>"><select name="status"><?php foreach(['vendor accepted','ready for delivery','shipped','delivered','discarded'] as $s):?><option value="<?=e($s)?>" <?=$o['status']===$s?'selected':''?>><?=e(ucfirst($s))?></option><?php endforeach;?></select><button>Save</button></form></td></tr><?php endforeach;?></tbody></table></div><?php if(!$orders):?><p>No orders yet.</p><?php endif;?>
<h2>Sold items (order detail)</h2>
<?php if($soldItems):?>
<div class="table-scroll" role="region" aria-label="Sold items" tabindex="0"><table><thead><tr><th>Order</th><th>Product</th><th>Qty</th><th>Unit price</th><th>Line total</th><th>Date</th><th>Order status</th></tr></thead><tbody>
<?php foreach($soldItems as $s):?><tr><td>#<?=(int)$s['order_id']?></td><td><?=e($s['title'])?></td><td><?=(int)$s['quantity']?></td><td>Rs. <?=number_format((float)$s['unit_price'],2)?></td><td>Rs. <?=number_format((float)$s['unit_price']*(int)$s['quantity'],2)?></td><td><?=e($s['created_at'])?></td><td><?=e(ucfirst($s['status']))?></td></tr><?php endforeach;?>
</tbody></table></div>
<?php else:?><p>No items sold yet.</p><?php endif;?>
<?php include __DIR__.'/../includes/footer.php';?>
