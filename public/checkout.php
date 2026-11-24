<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['customer']);

$cart=$_SESSION['cart']??[];
if(!$cart){flash('Your cart is empty.');header('Location: cart.php');exit;}

$details=$_SESSION['checkout_details']??[];
$ids=array_map('intval',array_keys($cart));
$marks=implode(',',array_fill(0,count($ids),'?'));
$st=$pdo->prepare("SELECT product_id,price,stock,status FROM products WHERE product_id IN ($marks)");
$st->execute($ids);
$cartProducts=$st->fetchAll();
$items=0;$subtotal=0.0;
foreach($cartProducts as $product){
    $qty=(int)($cart[$product['product_id']]??0);
    if($product['status']!=='active'||$qty<1||$qty>(int)$product['stock']){
        flash('A cart item is unavailable or its stock changed. Please review your cart.');
        header('Location: cart.php');exit;
    }
    $items+=$qty;$subtotal+=$qty*(float)$product['price'];
}
if(count($cartProducts)!==count($ids)){
    flash('A cart item is unavailable. Please review your cart.');
    header('Location: cart.php');exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $fulfillment=($_POST['fulfillment']??'delivery')==='collection'?'collection':'delivery';
    $details=[
        'fulfillment'=>$fulfillment,
        'recipient_name'=>trim($_POST['recipient_name']??signed_in()['full_name']),
        'phone'=>mb_substr(trim($_POST['phone']??''),0,40),
        'address'=>mb_substr(trim($_POST['address']??''),0,180),
        'city'=>mb_substr(trim($_POST['city']??''),0,60),
        'notes'=>mb_substr(trim($_POST['notes']??''),0,120),
    ];
    if($details['recipient_name']===''||$details['phone']===''||($fulfillment==='delivery'&&($details['address']===''||$details['city']===''))){
        $_SESSION['checkout_details']=$details;
        flash('Complete all required delivery details.');
        header('Location: checkout.php');exit;
    }
    $_SESSION['checkout_details']=$details;
    header('Location: payment.php');exit;
}

$fulfillment=$details['fulfillment']??'delivery';
$delivery=$fulfillment==='delivery'?150:0;
$pageTitle='Delivery details | Redline Royale';$pageClass='checkout-page';
include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">CHECKOUT</div>
<h1 class="page-heading">Delivery details</h1>
<p class="checkout-steps" aria-label="Checkout progress"><strong>1 Delivery</strong><span aria-hidden="true">→</span><span>2 Review</span><span aria-hidden="true">→</span><span>3 Place order</span></p>
<div class="checkout-layout">
  <form id="delivery-form" class="delivery-form" method="post" data-fulfilment-form>
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <h2>Delivery or collection</h2>
    <div class="fulfillment-options">
      <label><input type="radio" name="fulfillment" value="delivery" <?=$fulfillment==='delivery'?'checked':''?>> Delivery</label>
      <label><input type="radio" name="fulfillment" value="collection" <?=$fulfillment==='collection'?'checked':''?>> Collection</label>
    </div>
    <div class="delivery-fields">
      <div><label for="recipient">Recipient name</label><input id="recipient" name="recipient_name" autocomplete="name" placeholder="Enter recipient name" value="<?=e($details['recipient_name']??signed_in()['full_name'])?>" required></div>
      <div><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="Enter phone number" value="<?=e($details['phone']??'')?>" required></div>
      <div class="address-field" data-delivery-only><label for="address">Address</label><input id="address" name="address" autocomplete="street-address" placeholder="Enter address" value="<?=e($details['address']??'')?>" data-delivery-field></div>
      <div data-delivery-only><label for="city">City / area</label><input id="city" name="city" autocomplete="address-level2" placeholder="Enter city / area" value="<?=e($details['city']??'')?>" data-delivery-field></div>
      <div data-delivery-only><label for="notes">Delivery notes</label><input id="notes" name="notes" placeholder="Enter delivery notes" value="<?=e($details['notes']??'')?>"></div>
    </div>
  </form>
  <aside class="checkout-summary">
    <h2><?=$items?> <?=($items===1?'item':'items')?> · Subtotal Rs. <?=number_format($subtotal,2)?></h2>
    <p><span>Delivery</span><span><?=$delivery?'Rs. '.number_format($delivery,2):'Free'?></span></p>
    <p class="checkout-total"><strong>Total</strong><strong>Rs. <?=number_format($subtotal+$delivery,2)?></strong></p>
    <small>Cash on delivery or collection. Payment is due when your order arrives or is picked up.</small>
    <button type="submit" form="delivery-form">Review order</button>
  </aside>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
