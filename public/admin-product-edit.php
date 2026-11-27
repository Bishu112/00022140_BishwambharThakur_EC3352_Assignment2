<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['admin']);

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT p.*,c.name category,COALESCE(v.business_name,u.full_name) vendor FROM products p JOIN categories c ON c.category_id=p.category_id JOIN users u ON u.user_id=p.vendor_id LEFT JOIN vendor_profiles v ON v.user_id=p.vendor_id WHERE p.product_id=?");
$st->execute([$id]);
$product = $st->fetch();
if (!$product) error_page(404, 'Product not found', 'This product is not available in the catalogue.');

$vendors = $pdo->query("SELECT u.user_id,COALESCE(v.business_name,u.full_name) label FROM users u LEFT JOIN vendor_profiles v ON v.user_id=u.user_id WHERE u.role='vendor' ORDER BY label")->fetchAll();
$categories = $pdo->query('SELECT category_id,name FROM categories ORDER BY name')->fetchAll();
$pageClass = 'admin-premium-page';
$pageTitle = 'Edit '.$product['title'].' | Admin products';
include __DIR__.'/../includes/header.php';
?>
<section class="admin-premium-hero admin-premium-hero--compact">
  <div>
    <p class="admin-premium-kicker">Redline Royale · Atelier edit</p>
    <h1>Edit one product</h1>
    <p>Refine this piece with care. Only the selected product is opened for editing.</p>
  </div>
  <a class="admin-premium-back" href="admin-products.php">← Back to catalogue</a>
</section>

<form class="panel product-form product-edit admin-product-edit admin-premium-edit-form" action="admin-products.php" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
  <div class="admin-premium-edit-grid">
    <aside class="admin-premium-edit-preview">
      <p class="admin-premium-kicker">Selected piece</p>
      <img class="admin-product-image" src="<?=e(asset_url(product_image_url($product)))?>" alt="<?=e($product['title'])?>">
      <h2><?=e($product['title'])?></h2>
      <p class="price">Rs. <?=number_format((float)$product['price'],2)?></p>
      <small><?=e($product['category'])?> · Piece No. <?=(int)$product['product_id']?></small>
      <a href="admin-product-view.php?id=<?=(int)$product['product_id']?>">View full details →</a>
    </aside>

    <div class="admin-premium-edit-fields">
      <div class="admin-premium-section-heading"><span>01</span><h2>Catalogue details</h2></div>
      <div class="fields">
        <div><label for="admin-edit-vendor">Vendor</label><select id="admin-edit-vendor" name="vendor_id" required><?php foreach($vendors as $v):?><option value="<?= (int)$v['user_id'] ?>" <?= (int)$v['user_id'] === (int)$product['vendor_id'] ? 'selected' : '' ?>><?=e($v['label'])?></option><?php endforeach;?></select></div>
        <div><label for="admin-edit-title">Title</label><input id="admin-edit-title" name="title" value="<?=e($product['title'])?>" required></div>
        <div><label for="admin-edit-category">Category</label><select id="admin-edit-category" name="category_id" required><?php foreach($categories as $c):?><option value="<?= (int)$c['category_id'] ?>" <?= (int)$c['category_id'] === (int)$product['category_id'] ? 'selected' : '' ?>><?=e($c['name'])?></option><?php endforeach;?></select></div>
        <div><label for="admin-edit-price">Price (Rs.)</label><input id="admin-edit-price" type="number" min="0" step="0.01" name="price" value="<?=e($product['price'])?>" required></div>
        <div><label for="admin-edit-stock">Stock</label><input id="admin-edit-stock" type="number" min="0" name="stock" value="<?= (int)$product['stock'] ?>" required></div>
        <div><label for="admin-edit-sizes">Sizes (comma-separated)</label><input id="admin-edit-sizes" name="sizes" value="<?=e($product['sizes'])?>" required></div>
        <div><label for="admin-edit-colours">Colours (comma-separated)</label><input id="admin-edit-colours" name="colours" value="<?=e($product['colours'])?>" required></div>
      </div>

      <div class="admin-premium-section-heading"><span>02</span><h2>Story & presentation</h2></div>
      <label for="admin-edit-description">Description</label><textarea id="admin-edit-description" name="description" required><?=e($product['description'])?></textarea>
      <div class="fields">
        <div><label for="admin-edit-image">Replace image</label><input id="admin-edit-image" type="file" name="product_image" data-image-preview="admin-edit-preview" accept="image/jpeg,image/png,image/webp,image/gif"><img id="admin-edit-preview" class="image-preview" src="<?=e(asset_url(product_image_url($product)))?>" alt="Current <?=e($product['title'])?> image preview"><small>Leave blank to keep the current image.</small></div>
        <div><label for="admin-edit-status">Status</label><select id="admin-edit-status" name="status"><option value="active" <?= $product['status']==='active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $product['status']==='inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
      </div>

      <p class="admin-premium-form-actions">
        <button type="submit">Save changes</button>
        <a class="button button-secondary" href="admin-products.php">Cancel</a>
      </p>
    </div>
  </div>
</form>
<?php include __DIR__.'/../includes/footer.php'; ?>
