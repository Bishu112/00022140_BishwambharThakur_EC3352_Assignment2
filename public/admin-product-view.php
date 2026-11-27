<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['admin']);

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT p.*,c.name category,COALESCE(v.business_name,u.full_name) vendor FROM products p JOIN categories c ON c.category_id=p.category_id JOIN users u ON u.user_id=p.vendor_id LEFT JOIN vendor_profiles v ON v.user_id=p.vendor_id WHERE p.product_id=?");
$st->execute([$id]);
$product = $st->fetch();
if (!$product) error_page(404, 'Product not found', 'This product is not available in the catalogue.');

$pageClass = 'admin-premium-page';
$pageTitle = 'View '.$product['title'].' | Admin products';
include __DIR__.'/../includes/header.php';
?>
<section class="admin-premium-hero admin-premium-hero--compact">
  <div>
    <p class="admin-premium-kicker">Redline Royale · Piece detail</p>
    <h1>Product details</h1>
    <p>A focused view of one catalogue piece, with every detail ready for review.</p>
  </div>
  <a class="admin-premium-back" href="admin-products.php">← Back to catalogue</a>
</section>

<section class="admin-premium-detail">
  <div class="admin-premium-detail-media">
    <span class="admin-premium-media-badge">Piece No. <?=(int)$product['product_id']?></span>
    <img src="<?=e(asset_url(product_image_url($product)))?>" alt="<?=e($product['title'])?>">
  </div>
  <div class="admin-premium-detail-copy">
    <p class="admin-premium-kicker admin-premium-kicker--dark"><?=e($product['category'])?> · <?=e($product['vendor'])?></p>
    <h2><?=e($product['title'])?></h2>
    <p class="price admin-premium-price-large">Rs. <?=number_format((float)$product['price'],2)?></p>
    <p class="admin-premium-description"><?=nl2br(e($product['description']))?></p>
    <dl class="admin-premium-facts">
      <div><dt>Stock</dt><dd><?=(int)$product['stock']?> units</dd></div>
      <div><dt>Status</dt><dd><span class="admin-premium-status <?= $product['status']==='active'?'is-active':'is-inactive' ?>"><?=e(ucfirst($product['status']))?></span></dd></div>
      <div><dt>Sizes</dt><dd><?=e($product['sizes'])?></dd></div>
      <div><dt>Colours</dt><dd><?=e($product['colours'])?></dd></div>
      <div><dt>Vendor</dt><dd><?=e($product['vendor'])?></dd></div>
      <div><dt>Added</dt><dd><?=e(date('d M Y', strtotime((string)($product['created_at'] ?? 'now'))))?></dd></div>
    </dl>
    <div class="admin-premium-detail-actions">
      <a class="button" href="admin-product-edit.php?id=<?=(int)$product['product_id']?>">Edit this product</a>
      <a class="button button-secondary" href="admin-products.php">Back to products</a>
    </div>
  </div>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
