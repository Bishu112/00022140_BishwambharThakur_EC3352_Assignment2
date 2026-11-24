<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['admin']);
$uploadDirectory = __DIR__.'/uploads/products';

function remove_uploaded_admin_image($imagePath, $uploadDirectory) {
    if (!is_string($imagePath) || !str_starts_with($imagePath, 'uploads/products/')) return;
    $fullPath = $uploadDirectory.DIRECTORY_SEPARATOR.basename($imagePath);
    if (is_file($fullPath)) @unlink($fullPath);
}

$vendors = $pdo->query("SELECT u.user_id,u.full_name,COALESCE(v.business_name,u.full_name) label FROM users u LEFT JOIN vendor_profiles v ON v.user_id=u.user_id WHERE u.role='vendor' ORDER BY label")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['product_id'] ?? 0);
        $vendorId = (int)($_POST['vendor_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $sizes = implode(',', array_slice(array_filter(array_map('trim', explode(',', trim($_POST['sizes'] ?? 'S,M,L,XL')))), 0, 12));
        $colours = implode(',', array_slice(array_filter(array_map('trim', explode(',', trim($_POST['colours'] ?? 'Natural,Olive,Black')))), 0, 12));
        $description = trim($_POST['description'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $vendorCheck = $pdo->prepare("SELECT user_id FROM users WHERE user_id=? AND role='vendor'");
        $vendorCheck->execute([$vendorId]);
        if (!$vendorCheck->fetchColumn() || $title === '' || $category < 1 || $price < 0 || $stock < 0 || $description === '' || $sizes === '' || $colours === '') {
            flash('Complete the product fields and choose a valid vendor.');
            header('Location: admin-products.php'); exit;
        }

        $newImagePath = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['product_image'];
            if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
                flash('Choose an image smaller than 5 MB and try again.');
                header('Location: admin-products.php'); exit;
            }
            $imageInfo = @getimagesize($upload['tmp_name']);
            $mime = $imageInfo['mime'] ?? '';
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($extensions[$mime])) {
                flash('Use a JPG, PNG, WEBP, or GIF product image.');
                header('Location: admin-products.php'); exit;
            }
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                flash('The product image folder could not be created.');
                header('Location: admin-products.php'); exit;
            }
            $fileName = bin2hex(random_bytes(16)).'.'.$extensions[$mime];
            if (!move_uploaded_file($upload['tmp_name'], $uploadDirectory.DIRECTORY_SEPARATOR.$fileName)) {
                flash('The image could not be saved. Check the uploads folder permissions.');
                header('Location: admin-products.php'); exit;
            }
            $newImagePath = 'uploads/products/'.$fileName;
        }

        try {
            if ($id) {
                $find = $pdo->prepare('SELECT image_url FROM products WHERE product_id=?');
                $find->execute([$id]);
                $existing = $find->fetch();
                if (!$existing) {
                    if ($newImagePath) remove_uploaded_admin_image($newImagePath, $uploadDirectory);
                    flash('Product not found.');
                    header('Location: admin-products.php'); exit;
                }
                if ($newImagePath) {
                    $save = $pdo->prepare('UPDATE products SET vendor_id=?,category_id=?,title=?,description=?,price=?,stock=?,sizes=?,colours=?,status=?,image_url=? WHERE product_id=?');
                    $save->execute([$vendorId, $category, $title, $description, $price, $stock, $sizes, $colours, $status, $newImagePath, $id]);
                    remove_uploaded_admin_image($existing['image_url'], $uploadDirectory);
                } else {
                    $save = $pdo->prepare('UPDATE products SET vendor_id=?,category_id=?,title=?,description=?,price=?,stock=?,sizes=?,colours=?,status=? WHERE product_id=?');
                    $save->execute([$vendorId, $category, $title, $description, $price, $stock, $sizes, $colours, $status, $id]);
                }
                flash('Product updated.');
            } else {
                $save = $pdo->prepare('INSERT INTO products(vendor_id,category_id,title,description,price,stock,sizes,colours,status,image_url) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $save->execute([$vendorId, $category, $title, $description, $price, $stock, $sizes, $colours, $status, $newImagePath]);
                flash('Product added.');
            }
        } catch (Throwable $error) {
            if ($newImagePath) remove_uploaded_admin_image($newImagePath, $uploadDirectory);
            flash('The product could not be saved. Please check the fields and try again.');
        }
        header('Location: admin-products.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['product_id'] ?? 0);
        $find = $pdo->prepare('SELECT image_url FROM products WHERE product_id=?');
        $find->execute([$id]);
        $existing = $find->fetch();
        try {
            $remove = $pdo->prepare('DELETE FROM products WHERE product_id=?');
            $remove->execute([$id]);
            if ($remove->rowCount()) {
                if ($existing) remove_uploaded_admin_image($existing['image_url'], $uploadDirectory);
                flash('Product deleted.');
            } else {
                flash('Product not found.');
            }
        } catch (PDOException $error) {
            flash('This product is linked to an order and cannot be deleted.');
        }
        header('Location: admin-products.php'); exit;
    }
}

$categories = $pdo->query('SELECT category_id,name FROM categories ORDER BY name')->fetchAll();
$products = $pdo->query("SELECT p.*,c.name category,COALESCE(v.business_name,u.full_name) vendor FROM products p JOIN categories c ON c.category_id=p.category_id JOIN users u ON u.user_id=p.vendor_id LEFT JOIN vendor_profiles v ON v.user_id=p.vendor_id ORDER BY p.created_at DESC")->fetchAll();
$pageTitle = 'Admin product management';
include __DIR__.'/../includes/header.php';
?>
<h1>Product management</h1>
<p>Add, edit, or remove products on behalf of any vendor.</p>
<?php if ($message = flash()): ?><p class="notice" role="status"><?=e($message)?></p><?php endif; ?>
<?php if (!$vendors): ?>
  <p class="notice" role="status">No vendor accounts exist yet. A vendor must register before products can be added.</p>
<?php else: ?>
<form class="panel product-form" method="post" enctype="multipart/form-data">
  <h2>Add a product</h2>
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
  <input type="hidden" name="action" value="save">
  <div class="fields">
    <div><label>Vendor</label><select name="vendor_id" required><?php foreach($vendors as $v):?><option value="<?=$v['user_id']?>"><?=e($v['label'])?></option><?php endforeach;?></select></div>
    <div><label>Product title</label><input name="title" required></div>
    <div><label>Category</label><select name="category_id" required><?php foreach($categories as $c):?><option value="<?=$c['category_id']?>"><?=e($c['name'])?></option><?php endforeach;?></select></div>
    <div><label>Price (Rs.)</label><input type="number" min="0" step="0.01" name="price" required></div>
    <div><label>Stock</label><input type="number" min="0" name="stock" required></div>
    <div><label>Sizes (comma-separated)</label><input name="sizes" value="S,M,L,XL" required></div>
    <div><label>Colours (comma-separated)</label><input name="colours" value="Natural,Olive,Black" required></div>
    <div><label for="admin-new-image">Product image</label><input id="admin-new-image" type="file" name="product_image" data-image-preview="admin-new-preview" accept="image/jpeg,image/png,image/webp,image/gif"><img id="admin-new-preview" class="image-preview" alt="Selected product image preview" hidden><small>Optional · JPG, PNG, WEBP or GIF · up to 5 MB</small></div>
    <div><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
  </div>
  <label>Description</label><textarea name="description" required></textarea>
  <p><button>Add product</button></p>
</form>
<?php endif; ?>

<h2>All products (<?=count($products)?>)</h2>
<?php foreach($products as $p): ?>
  <form class="panel product-form product-edit" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="product_id" value="<?=$p['product_id']?>">
    <div class="product-edit-heading">
      <img class="admin-product-image" src="<?=e(asset_url(product_image_url($p)))?>" alt="<?=e($p['title'])?>">
      <div><small><?=e($p['category'])?> · Product #<?=$p['product_id']?> · <?=e($p['vendor'])?></small><h3><?=e($p['title'])?></h3><p class="price">Rs. <?=number_format((float)$p['price'],2)?></p></div>
    </div>
    <div class="fields">
      <div><label>Vendor</label><select name="vendor_id"><?php foreach($vendors as $v):?><option value="<?=$v['user_id']?>" <?=$v['user_id']==$p['vendor_id']?'selected':''?>><?=e($v['label'])?></option><?php endforeach;?></select></div>
      <div><label>Title</label><input name="title" value="<?=e($p['title'])?>" required></div>
      <div><label>Category</label><select name="category_id"><?php foreach($categories as $c):?><option value="<?=$c['category_id']?>" <?=$c['category_id']==$p['category_id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div>
      <div><label>Price (Rs.)</label><input type="number" min="0" step="0.01" name="price" value="<?=e($p['price'])?>" required></div>
      <div><label>Stock</label><input type="number" min="0" name="stock" value="<?=e($p['stock'])?>" required></div>
      <div><label>Sizes (comma-separated)</label><input name="sizes" value="<?=e($p['sizes'])?>" required></div>
      <div><label>Colours (comma-separated)</label><input name="colours" value="<?=e($p['colours'])?>" required></div>
      <div><label for="admin-image-<?=$p['product_id']?>">Replace image</label><input id="admin-image-<?=$p['product_id']?>" type="file" name="product_image" data-image-preview="admin-preview-<?=$p['product_id']?>" accept="image/jpeg,image/png,image/webp,image/gif"><img id="admin-preview-<?=$p['product_id']?>" class="image-preview" src="<?=e(asset_url(product_image_url($p)))?>" alt="Current <?=e($p['title'])?> image preview"><small>Leave blank to keep the current image.</small></div>
      <div><label>Status</label><select name="status"><option value="active" <?=$p['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$p['status']==='inactive'?'selected':''?>>Inactive</option></select></div>
    </div>
    <label>Description</label><textarea name="description" required><?=e($p['description'])?></textarea>
    <p><button>Save changes</button></p>
  </form>
  <form method="post" onsubmit="return confirm('Delete this product?')" class="delete-product-form">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?=$p['product_id']?>"><button class="button-secondary">Delete product</button>
  </form>
<?php endforeach; ?>
<?php if(!$products): ?><p>No products yet.</p><?php endif; ?>
<?php include __DIR__.'/../includes/footer.php'; ?>