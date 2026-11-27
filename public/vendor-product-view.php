<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['vendor']);
$vendorId=(int)signed_in()['user_id'];
$id=(int)($_GET['id']??0);
$st=$pdo->prepare('SELECT p.*,c.name category FROM products p JOIN categories c ON c.category_id=p.category_id WHERE p.product_id=? AND p.vendor_id=?');
$st->execute([$id,$vendorId]);
$product=$st->fetch();
if(!$product) error_page(404,'Product not found','This product is not available in your vendor catalogue.');
$pageTitle='View '.$product['title'].' | Vendor workspace';$workspaceType='vendor';$workspaceActive='Products';
include __DIR__.'/../includes/header.php';
?>
<p class="eyebrow">Product management</p>
<p><a href="product-list.php">← Back to products</a></p>
<h1>Product details</h1>
<section class="panel vendor-product-view">
  <img class="admin-product-image" src="<?=e(asset_url(product_image_url($product)))?>" alt="<?=e($product['title'])?>">
  <div class="vendor-product-view-copy">
    <p class="eyebrow"><?=e($product['category'])?> · Product #<?=(int)$product['product_id']?></p>
    <h2><?=e($product['title'])?></h2>
    <p class="price">Rs. <?=number_format((float)$product['price'],2)?></p>
    <p><?=nl2br(e($product['description']))?></p>
    <dl class="vendor-product-facts">
      <div><dt>Stock</dt><dd><?=(int)$product['stock']?></dd></div>
      <div><dt>Status</dt><dd><span class="<?=$product['status']==='active'?'status-active':''?>"><?=e(ucfirst($product['status']))?></span></dd></div>
      <div><dt>Sizes</dt><dd><?=e($product['sizes'])?></dd></div>
      <div><dt>Colours</dt><dd><?=e($product['colours'])?></dd></div>
    </dl>
    <a class="button" href="vendor-product-edit.php?id=<?=(int)$product['product_id']?>">Edit this product</a>
  </div>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
