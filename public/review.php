<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['customer']);
if(empty($_SESSION['cart'])){header('Location: cart.php');exit;}
if(empty($_SESSION['checkout_details'])){header('Location: checkout.php');exit;}
$cart=$_SESSION['cart'];$details=$_SESSION['checkout_details'];
$ids=array_map('intval',array_keys($cart));$marks=implode(',',array_fill(0,count($ids),'?'));

if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    try{
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT product_id,vendor_id,price,stock,title FROM products WHERE status='active' AND product_id IN ($marks) FOR UPDATE");
        $st->execute($ids);$products=$st->fetchAll();
        if(count($products)!==count($ids))throw new RuntimeException('A product is no longer available.');
        $total=0;
        foreach($products as $product){$qty=(int)$cart[$product['product_id']];if($qty>(int)$product['stock'])throw new RuntimeException('Stock changed. Please review your cart.');$total+=$qty*(float)$product['price'];}
        $fulfillment=$details['fulfillment']==='collection'?'collection':'delivery';
        $delivery=$fulfillment==='delivery'?150:0;$total+=$delivery;
        $address=$fulfillment==='delivery'?mb_substr(trim(($details['address']??'').', '.($details['city']??'').(!empty($details['notes'])?' ('.$details['notes'].')':'')),0,255):null;
        $payment=$fulfillment==='delivery'?'cash_on_delivery':'cash_at_collection';
        $st=$pdo->prepare('INSERT INTO orders(customer_id,fulfillment,recipient_name,phone,address,payment_method,total) VALUES(?,?,?,?,?,?,?)');
        $st->execute([signed_in()['user_id'],$fulfillment,$details['recipient_name'],$details['phone'],$address,$payment,$total]);
        $orderId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO transactions(order_id,payment_method,amount,payment_status) VALUES(?,?,?,?)')->execute([$orderId,$payment,$total,'pending']);
        $pdo->prepare('INSERT INTO order_status_history(order_id,changed_by,status,note) VALUES(?,?,?,?)')->execute([$orderId,signed_in()['user_id'],'placed','Order placed by customer']);
        $line=$pdo->prepare('INSERT INTO order_items(order_id,product_id,product_title,vendor_id,quantity,unit_price) VALUES(?,?,?,?,?,?)');
        $updateStock=$pdo->prepare('UPDATE products SET stock=stock-? WHERE product_id=?');
        foreach($products as $product){$qty=(int)$cart[$product['product_id']];$line->execute([$orderId,$product['product_id'],$product['title'],$product['vendor_id'],$qty,$product['price']]);$updateStock->execute([$qty,$product['product_id']]);}
        $pdo->commit();
        unset($_SESSION['cart'],$_SESSION['cart_variants'],$_SESSION['checkout_details'],$_SESSION['payment_method']);
        header('Location: thank-you.php?order_id='.$orderId);exit;
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();flash('Order could not be placed: '.$error->getMessage());header('Location: cart.php');exit;}
}

$items=[];$subtotal=0;$st=$pdo->prepare("SELECT product_id,title,price FROM products WHERE product_id IN ($marks)");$st->execute($ids);
foreach($st as $product){$qty=(int)$cart[$product['product_id']];$items[]=['product'=>$product,'qty'=>$qty];$subtotal+=$qty*(float)$product['price'];}
$delivery=($details['fulfillment']??'delivery')==='delivery'?150:0;
$pageTitle='Review order | Redline Royale';$pageClass='review-page';include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">CHECKOUT</div><h1 class="page-heading">Review your order</h1>
<p class="checkout-steps"><span>1 Delivery</span><span aria-hidden="true">→</span><strong>2 Review</strong><span aria-hidden="true">→</span><span>3 Place order</span></p>
<section class="review-card">
  <h2><?=$details['fulfillment']==='delivery'?'Delivery':'Collection'?> details</h2>
  <p><?=e($details['recipient_name'])?> · <?=e($details['phone'])?></p>
  <?php if(($details['fulfillment']??'delivery')==='delivery'):?><p><?=e($details['address']??'')?> <?=e($details['city']??'')?></p><?php endif;?>
  <h2>Items</h2>
  <?php foreach($items as $item):?><p><?=e($item['product']['title'])?> × <?=$item['qty']?> <span>Rs. <?=number_format($item['qty']*(float)$item['product']['price'],2)?></span></p><?php endforeach;?>
  <p>Delivery <span><?=$delivery?'Rs. '.number_format($delivery,2):'Free'?></span></p>
  <h2>Total Rs. <?=number_format($subtotal+$delivery,2)?></h2>
  <div class="checkout-return-actions"><a href="checkout.php">Edit delivery details</a><a href="payment.php">Back to payment</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button type="submit">Place order</button></form></div>
</section>
<?php include __DIR__.'/../includes/footer.php';?>
