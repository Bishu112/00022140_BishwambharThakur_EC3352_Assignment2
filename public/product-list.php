<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['vendor']);
$vendorId = (int)signed_in()['user_id'];
$uploadDirectory = __DIR__.'/uploads/products';

function remove_uploaded_product_image($imagePath, $uploadDirectory) {
    if (!is_string($imagePath) || !str_starts_with($imagePath, 'uploads/products/')) return;
    $fileName = basename($imagePath);
    $fullPath = $uploadDirectory.DIRECTORY_SEPARATOR.$fileName;
    if (is_file($fullPath)) @unlink($fullPath);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['product_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $sizes = implode(',', array_slice(array_filter(array_map('trim', explode(',', trim($_POST['sizes'] ?? 'S,M,L,XL')))), 0, 12));
        $colours = implode(',', array_slice(array_filter(array_map('trim', explode(',', trim($_POST['colours'] ?? 'Natural,Olive,Black')))), 0, 12));
        $description = trim($_POST['description'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($title === '' || $category < 1 || $price < 0 || $stock < 0 || $description === '' || $sizes === '' || $colours === '') {
            flash('Complete the product fields with valid values.');
            header('Location: product-list.php'); exit;
        }

        $newImagePath = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['product_image'];
            if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
                flash('Choose an image smaller than 5 MB and try again.');
                header('Location: product-list.php'); exit;
            }

            $imageInfo = @getimagesize($upload['tmp_name']);
            $mime = $imageInfo['mime'] ?? '';
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($extensions[$mime])) {
                flash('Use a JPG, PNG, WEBP, or GIF product image.');
                header('Location: product-list.php'); exit;
            }

            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                flash('The product image folder could not be created.');
                header('Location: product-list.php'); exit;
            }
            $fileName = bin2hex(random_bytes(16)).'.'.$extensions[$mime];
            if (!move_uploaded_file($upload['tmp_name'], $uploadDirectory.DIRECTORY_SEPARATOR.$fileName)) {
                flash('The image could not be saved. Check the uploads folder permissions.');
                header('Location: product-list.php'); exit;
            }
            $newImagePath = 'uploads/products/'.$fileName;
        }

        try {
            if ($id) {
                $find = $pdo->prepare('SELECT image_url FROM products WHERE product_id=? AND vendor_id=?');
                $find->execute([$id, $vendorId]);
                $existing = $find->fetch();
                if (!$existing) {
                    if ($newImagePath) remove_uploaded_product_image($newImagePath, $uploadDirectory);
                    flash('Product not found.');
                    header('Location: product-list.php'); exit;
                }
                if ($newImagePath) {
                    $save = $pdo->prepare('UPDATE products SET category_id=?,title=?,description=?,price=?,stock=?,sizes=?,colours=?,status=?,image_url=? WHERE product_id=? AND vendor_id=?');
                    $save->execute([$category, $title, $description, $price, $stock, $sizes, $colours, $status, $newImagePath, $id, $vendorId]);
                    remove_uploaded_product_image($existing['image_url'], $uploadDirectory);
                } else {
                    $save = $pdo->prepare('UPDATE products SET category_id=?,title=?,description=?,price=?,stock=?,sizes=?,colours=?,status=? WHERE product_id=? AND vendor_id=?');
                    $save->execute([$category, $title, $description, $price, $stock, $sizes, $colours, $status, $id, $vendorId]);
                }
                flash('Product updated.');
            } else {
                $save = $pdo->prepare('INSERT INTO products(vendor_id,category_id,title,description,price,stock,sizes,colours,status,image_url) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $save->execute([$vendorId, $category, $title, $description, $price, $stock, $sizes, $colours, $status, $newImagePath]);
                flash('Product added.');
            }
        } catch (Throwable $error) {
            if ($newImagePath) remove_uploaded_product_image($newImagePath, $uploadDirectory);
            flash('The product could not be saved. Please check the fields and try again.');
        }
        header('Location: product-list.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['product_id'] ?? 0);
        $find = $pdo->prepare('SELECT title,image_url FROM products WHERE product_id=? AND vendor_id=?');
        $find->execute([$id, $vendorId]);
        $existing = $find->fetch();
        try {
            $pdo->beginTransaction();
            if ($existing) {
                // Keep the sold-item name in past orders before ON DELETE SET NULL clears the link.
                $snapshot = $pdo->prepare("UPDATE order_items SET product_title=? WHERE product_id=? AND (product_title IS NULL OR product_title='')");
                $snapshot->execute([$existing['title'], $id]);
            }
            $remove = $pdo->prepare('DELETE FROM products WHERE product_id=? AND vendor_id=?');
            $remove->execute([$id, $vendorId]);
            $deleted = $remove->rowCount();
            $pdo->commit();
            if ($deleted) {
                if ($existing) remove_uploaded_product_image($existing['image_url'], $uploadDirectory);
                flash('Product deleted. Past orders keep their item details.');
            } else {
                flash('Product not found.');
            }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('The product could not be deleted. Please try again.');
        }
        header('Location: product-list.php'); exit;
    }
}

$categories = $pdo->query('SELECT category_id,name FROM categories ORDER BY name')->fetchAll();
$st = $pdo->prepare('SELECT p.*,c.name category FROM products p JOIN categories c ON c.category_id=p.category_id WHERE p.vendor_id=? ORDER BY p.created_at DESC');
$st->execute([$vendorId]);
$products = $st->fetchAll();
$pageTitle = 'Vendor product management';$workspaceType='vendor';$workspaceActive='Products';
include __DIR__.'/../includes/header.php';
?>
<p class="eyebrow">Product management</p><h1>Manage your catalogue</h1>
<?php if ($message = flash()): ?><p class="notice" role="status"><?=e($message)?></p><?php endif; ?>
<details class="workspace-add-product" id="add-product" <?=isset($_GET['add'])?'open':''?>><summary class="button">+ Add product</summary>
<form class="panel product-form" method="post" enctype="multipart/form-data">
  <h2>Add a product</h2>
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
  <input type="hidden" name="action" value="save">
  <div class="fields">
    <div><label>Product title</label><input name="title" required></div>
    <div><label>Category</label><select name="category_id" required><?php foreach($categories as $c):?><option value="<?=$c['category_id']?>"><?=e($c['name'])?></option><?php endforeach;?></select></div>
    <div><label>Price (Rs.)</label><input type="number" min="0" step="0.01" name="price" required></div>
    <div><label>Stock</label><input type="number" min="0" name="stock" required></div>
    <div><label>Sizes (comma-separated)</label><input name="sizes" value="S,M,L,XL" required></div>
    <div><label>Colours (comma-separated)</label><input name="colours" value="Natural,Olive,Black" required></div>
  </div>
  <label>Description</label><textarea name="description" required></textarea>
  <div class="fields">
    <div><label for="new-product-image">Product image</label><input id="new-product-image" type="file" name="product_image" data-image-preview="new-product-preview" accept="image/jpeg,image/png,image/webp,image/gif"><img id="new-product-preview" class="image-preview" alt="Selected product image preview" hidden><small>Optional Â· JPG, PNG, WEBP or GIF Â· up to 5 MB</small></div>
    <div><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
  </div>
  <p><button>Add product</button></p>
</form></details>

<label class="workspace-search-label" for="product-search">Search by title or SKU</label><input id="product-search" class="workspace-search" type="search" placeholder="Search by title or SKU" data-product-search>
<div class="table-scroll workspace-product-table"><table><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($products as $p): ?><tr data-product-row><td><?=e($p['title'])?></td><td><?=e($p['category'])?></td><td>Rs. <?=number_format((float)$p['price'],2)?></td><td><?=(int)$p['stock']?></td><td><span class="<?= $p['status']==='active'?'status-active':'' ?>"><?=e(ucfirst($p['status']))?></span></td><td><div class="workspace-product-actions"><a href="vendor-product-view.php?id=<?=(int)$p['product_id']?>" aria-label="View product: <?=e($p['title'])?>">View</a><a href="vendor-product-edit.php?id=<?=(int)$p['product_id']?>" aria-label="Edit product: <?=e($p['title'])?>">Edit</a><form method="post" onsubmit="return confirm('Delete <?=e(addslashes($p['title']))?>? This cannot be undone.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?=$p['product_id']?>"><button type="submit" class="workspace-delete-link">Delete</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if(!$products): ?><p>No products yet.</p><?php endif; ?>
<?php include __DIR__.'/../includes/footer.php'; ?>
