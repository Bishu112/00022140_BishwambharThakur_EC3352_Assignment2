<?php
require_once __DIR__.'/../includes/bootstrap.php';
$id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT p.*,c.name category FROM products p JOIN categories c ON c.category_id=p.category_id WHERE p.product_id=? AND p.status='active'");
$st->execute([$id]);
$p=$st->fetch();
if(!$p){http_response_code(404);exit('Product not found.');}
$sizes=array_filter(array_map('trim',explode(',',(string)($p['sizes']??'S,M,L,XL'))));
$colours=array_filter(array_map('trim',explode(',',(string)($p['colours']??'Natural,Olive,Black'))));
if(!$sizes)$sizes=['M'];if(!$colours)$colours=['Natural'];
$pdo->prepare('UPDATE products SET views=views+1 WHERE product_id=?')->execute([$id]);
$pdo->prepare('INSERT INTO product_views(product_id,session_id) VALUES(?,?)')->execute([$id,session_id()]);
$rv=$pdo->prepare('SELECT ROUND(AVG(r.rating),1) rating,COUNT(*) total FROM reviews r WHERE r.product_id=?');$rv->execute([$id]);$rating=$rv->fetch();
$st=$pdo->prepare('SELECT r.rating,r.comment,r.created_at,u.full_name FROM reviews r JOIN users u ON u.user_id=r.customer_id WHERE r.product_id=? ORDER BY r.created_at DESC LIMIT 8');$st->execute([$id]);$reviews=$st->fetchAll();
$canReview=false;$hasReviewed=false;
if(signed_in()&&signed_in()['role']==='customer'){
    $st=$pdo->prepare("SELECT EXISTS(SELECT 1 FROM order_items i JOIN orders o ON o.order_id=i.order_id WHERE i.product_id=? AND o.customer_id=? AND o.status<>'discarded')");
    $st->execute([$id,signed_in()['user_id']]);$canReview=(bool)$st->fetchColumn();
    $st=$pdo->prepare('SELECT EXISTS(SELECT 1 FROM reviews WHERE product_id=? AND customer_id=?)');$st->execute([$id,signed_in()['user_id']]);$hasReviewed=(bool)$st->fetchColumn();
}
$pageTitle=e($p['title']).' | Redline Royale';$pageClass='product-detail-page';include __DIR__.'/../includes/header.php';
?>
<nav class="product-breadcrumbs" aria-label="Breadcrumb"><a href="home.php">Home</a><span aria-hidden="true">/</span><a href="index.php?category=<?=(int)$p['category_id']?>"><?=e($p['category'])?></a><span aria-hidden="true">/</span><span aria-current="page"><?=e($p['title'])?></span></nav>
<div class="product-detail-layout">
  <div class="product-gallery">
    <div class="product-gallery-frame"><img class="detail-image" src="<?=e(asset_url(product_image_url($p)))?>" alt="<?=e($p['title'])?>"><span class="gallery-label"><?=e($p['category'])?> · REDLINE ROYALE</span></div>
    <p class="gallery-caption">A closer look at <?=e($p['title'])?></p>
  </div>
  <section class="detail-info" aria-labelledby="product-title">
    <p class="page-kicker product-category-kicker"><?=e($p['category'])?> · THE COLLECTION</p>
    <h1 id="product-title" class="product-detail-title"><?=e($p['title'])?></h1>
    <p class="rating"><?php if((int)$rating['total']>0):?><?=str_repeat('★',(int)round((float)$rating['rating'])) . str_repeat('☆',5-(int)round((float)$rating['rating']))?> <span><?=e($rating['rating'])?> · <?= (int)$rating['total']?> reviews</span><?php else:?>No customer reviews yet<?php endif;?></p>
    <div class="product-price-stock"><strong class="product-detail-price">Rs. <?=number_format((float)$p['price'],2)?></strong><span class="stock-pill <?=$p['stock']>0?'is-in-stock':'is-out-of-stock'?>"><?=$p['stock']>0?'In stock':'Out of stock'?></span></div>
    <form id="add-to-cart" action="cart.php" method="post" class="detail-buy">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?=$p['product_id']?>">
      <fieldset class="color-picker"><legend>Colour</legend><div class="color-options"><?php foreach($colours as $i=>$colour):?><label class="color-choice"><input type="radio" name="color" value="<?=e($colour)?>" <?=$i===0?'checked':''?>><span><?=e($colour)?></span></label><?php endforeach;?></div></fieldset>
      <div class="size-heading"><label for="size">Select size</label><details class="product-size-guide" id="size-guide"><summary>Size guide</summary><p>Available sizes for this item: <?=e(implode(', ',$sizes))?>. Select your preferred size before adding the item to your cart.</p></details></div>
      <select id="size" name="size" required><option value="">Choose a size</option><?php foreach($sizes as $size):?><option value="<?=e($size)?>"><?=e($size)?></option><?php endforeach;?></select>
      <button class="product-add-button" type="submit" <?=$p['stock']<1?'disabled':''?>>Add to cart <span aria-hidden="true">→</span></button>
    </form>
    <p class="delivery-note">Delivery or collection options are shown at checkout.</p>
    <section class="product-copy-panel"><p class="product-copy-label">PRODUCT DETAILS</p><p><?=e($p['description'])?></p></section>
  </section>
</div>
<section class="product-reviews" id="reviews" aria-labelledby="reviews-title">
  <div class="product-reviews-heading"><div><p class="page-kicker">COMMUNITY NOTES</p><h2 id="reviews-title">Customer reviews</h2></div><span><?= (int)$rating['total']?> <?=((int)$rating['total']===1?'review':'reviews')?></span></div>
  <?php if($canReview&&!$hasReviewed):?><form class="review-form" action="product-review.php" method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="product_id" value="<?=$id?>"><label for="review-rating">Your rating</label><select id="review-rating" name="rating" required><option value="">Choose a rating</option><?php for($score=5;$score>=1;$score--):?><option value="<?=$score?>"><?=$score?> out of 5</option><?php endfor;?></select><label for="review-comment">Your review</label><textarea id="review-comment" name="comment" maxlength="1000" rows="4" placeholder="Share a few words about the product" required></textarea><button type="submit">Submit review</button></form>
  <?php elseif($hasReviewed):?><p class="review-hint">Thank you for sharing your review of this item.</p>
  <?php elseif(signed_in()&&signed_in()['role']==='customer'):?><p class="review-hint">Place an order for this item to leave a review.</p>
  <?php else:?><p class="review-hint"><a href="login.php">Sign in</a> after ordering this item to write a review.</p><?php endif;?>
  <?php if($reviews):?><div class="review-list"><?php foreach($reviews as $review):?><article class="review-entry"><div><strong><?=e($review['full_name'])?></strong><span class="review-stars" aria-label="<?=$review['rating']?> out of 5 stars"><?=str_repeat('★',(int)$review['rating']).str_repeat('☆',5-(int)$review['rating'])?></span></div><time datetime="<?=e(date('c',strtotime($review['created_at'])))?>"><?=e(date('d M Y',strtotime($review['created_at'])))?></time><p><?=nl2br(e($review['comment']))?></p></article><?php endforeach;?></div><?php else:?><p class="review-hint">No reviews yet. An eligible customer can be the first to review this product.</p><?php endif;?>
</section>
<?php include __DIR__.'/../includes/footer.php';?>
