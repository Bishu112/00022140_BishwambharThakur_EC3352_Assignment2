<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['vendor']);
$vendorId=(int)signed_in()['user_id'];
$id=(int)($_GET['id']??0);
$st=$pdo->prepare('SELECT p.*,c.name category FROM products p JOIN categories c ON c.category_id=p.category_id WHERE p.product_id=? AND p.vendor_id=?');
$st->execute([$id,$vendorId]);
$product=$st->fetch();
if(!$product) error_page(404,'Product not found','This product is not available in your vendor catalogue.');
$categories=$pdo->query('SELECT category_id,name FROM categories ORDER BY name')->fetchAll();
$pageTitle='Edit '.$product['title'].' | Vendor workspace';$workspaceType='vendor';$workspaceActive='Products';
include __DIR__.'/../includes/header.php';
?>
<p class="eyebrow">Product management</p>
<p><a href="product-list.php">← Back to products</a></p>
<h1>Edit product</h1>
<form class="panel product-form product-edit vendor-product-edit" action="product-list.php" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="product_id" value="<?=(int)$product['product_id']?>">
  <div class="product-edit-heading">
    <img class="admin-product-image" src="<?=e(asset_url(product_image_url($product)))?>" alt="<?=e($product['title'])?>">
    <div><small><?=e($product['category'])?> · Product #<?=(int)$product['product_id']?></small><h2><?=e($product['title'])?></h2><p class="price">Rs. <?=number_format((float)$product['price'],2)?></p></div>
  </div>
  <div class="fields">
    <div><label for="product-title">Title</label><input id="product-title" name="title" value="<?=e($product['title'])?>" required></div>
    <div><label for="product-category">Category</label><select id="product-category" name="category_id" required><?php foreach($categories as $category):?><option value="<?=(int)$category['category_id']?>" <?=$category['category_id']==$product['category_id']?'selected':''?>><?=e($category['name'])?></option><?php endforeach;?></select></div>
    <div><label for="product-price">Price (Rs.)</label><input id="product-price" type="number" min="0" step="0.01" name="price" value="<?=e($product['price'])?>" required></div>
    <div><label for="product-stock">Stock</label><input id="product-stock" type="number" min="0" name="stock" value="<?=(int)$product['stock']?>" required></div>
    <div><label for="product-sizes">Sizes (comma-separated)</label><input id="product-sizes" name="sizes" value="<?=e($product['sizes'])?>" required></div>
    <div><label for="product-colours">Colours (comma-separated)</label><input id="product-colours" name="colours" value="<?=e($product['colours'])?>" required></div>
  </div>
  <label for="product-description">Description</label><textarea id="product-description" name="description" required><?=e($product['description'])?></textarea>
  <div class="fields">
    <div><label for="product-image">Replace product image</label><input id="product-image" type="file" name="product_image" data-image-preview="product-image-preview" accept="image/jpeg,image/png,image/webp,image/gif"><img id="product-image-preview" class="image-preview" src="<?=e(asset_url(product_image_url($product)))?>" alt="Current product image preview"><small>Leave blank to keep the current image.</small></div>
    <div><label for="product-status">Status</label><select id="product-status" name="status"><option value="active" <?=$product['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$product['status']==='inactive'?'selected':''?>>Inactive</option></select></div>
  </div>
  <p><button type="submit">Save changes</button></p>
</form>
<?php include __DIR__.'/../includes/footer.php'; ?>
