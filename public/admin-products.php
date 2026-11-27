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
        $redirect = $id > 0 ? 'admin-product-edit.php?id='.$id : 'admin-products.php';
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
            header('Location: '.$redirect); exit;
        }

        $newImagePath = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['product_image'];
            if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
                flash('Choose an image smaller than 5 MB and try again.');
                header('Location: '.$redirect); exit;
            }
            $imageInfo = @getimagesize($upload['tmp_name']);
            $mime = $imageInfo['mime'] ?? '';
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($extensions[$mime])) {
                flash('Use a JPG, PNG, WEBP, or GIF product image.');
                header('Location: '.$redirect); exit;
            }
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                flash('The product image folder could not be created.');
                header('Location: '.$redirect); exit;
            }
            $fileName = bin2hex(random_bytes(16)).'.'.$extensions[$mime];
            if (!move_uploaded_file($upload['tmp_name'], $uploadDirectory.DIRECTORY_SEPARATOR.$fileName)) {
                flash('The image could not be saved. Check the uploads folder permissions.');
                header('Location: '.$redirect); exit;
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
            header('Location: '.$redirect); exit;
        }
        header('Location: admin-products.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['product_id'] ?? 0);
        $find = $pdo->prepare('SELECT title,image_url FROM products WHERE product_id=?');
        $find->execute([$id]);
        $existing = $find->fetch();
        try {
            $pdo->beginTransaction();
            if ($existing) {
                // Keep the sold-item name in past orders before ON DELETE SET NULL clears the link.
                $snapshot = $pdo->prepare("UPDATE order_items SET product_title=? WHERE product_id=? AND (product_title IS NULL OR product_title='')");
                $snapshot->execute([$existing['title'], $id]);
            }
            $remove = $pdo->prepare('DELETE FROM products WHERE product_id=?');
            $remove->execute([$id]);
            $deleted = $remove->rowCount();
            $pdo->commit();
            if ($deleted) {
                if ($existing) remove_uploaded_admin_image($existing['image_url'], $uploadDirectory);
                flash('Product deleted. Past orders keep their item details.');
            } else {
                flash('Product not found.');
            }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('The product could not be deleted. Please try again.');
        }
        header('Location: admin-products.php'); exit;
    }
}

$categories = $pdo->query('SELECT category_id,name FROM categories ORDER BY name')->fetchAll();
$products = $pdo->query("SELECT p.*,c.name category,COALESCE(v.business_name,u.full_name) vendor FROM products p JOIN categories c ON c.category_id=p.category_id JOIN users u ON u.user_id=p.vendor_id LEFT JOIN vendor_profiles v ON v.user_id=p.vendor_id ORDER BY p.created_at DESC")->fetchAll();
$totalProducts = count($products);
$activeProducts = count(array_filter($products, static fn($p) => $p['status'] === 'active'));
$lowStock = count(array_filter($products, static fn($p) => (int)$p['stock'] <= 5));
$vendorCount = count($vendors);
$pageClass = 'admin-premium-page';
$pageTitle = 'Admin product management';
include __DIR__.'/../includes/header.php';
?>
<section class="admin-premium-hero">
  <div>
    <p class="admin-premium-kicker">Redline Royale · Administration</p>
    <h1>Product management</h1>
    <p>Review the catalogue with a curator’s eye. Open any piece to view, refine or retire it — one product at a time.</p>
  </div>
  <div class="admin-premium-monogram" aria-hidden="true"><span>RR</span><small>Atelier</small></div>
</section>

<div class="admin-premium-stats" aria-label="Catalogue summary">
  <article class="admin-premium-stat"><span>Catalogue pieces</span><strong><?=$totalProducts?></strong><small>Total products</small></article>
  <article class="admin-premium-stat"><span>Live pieces</span><strong><?=$activeProducts?></strong><small>Active listings</small></article>
  <article class="admin-premium-stat"><span>Low stock</span><strong><?=$lowStock?></strong><small>5 units or fewer</small></article>
  <article class="admin-premium-stat"><span>Vendor partners</span><strong><?=$vendorCount?></strong><small>Connected makers</small></article>
</div>

<?php if (!$vendors): ?>
  <p class="notice" role="status">No vendor accounts exist yet. A vendor must register before products can be added.</p>
<?php else: ?>
<details class="workspace-add-product admin-premium-add" id="add-product">
  <summary class="button admin-premium-add-button">+ Add a new piece</summary>
  <form class="panel product-form" method="post" enctype="multipart/form-data">
    <h2>New catalogue piece</h2>
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="save">
    <div class="fields">
      <div><label for="admin-new-vendor">Vendor</label><select id="admin-new-vendor" name="vendor_id" required><?php foreach($vendors as $v):?><option value="<?= (int)$v['user_id'] ?>"><?=e($v['label'])?></option><?php endforeach;?></select></div>
      <div><label for="admin-new-title">Product title</label><input id="admin-new-title" name="title" required></div>
      <div><label for="admin-new-category">Category</label><select id="admin-new-category" name="category_id" required><?php foreach($categories as $c):?><option value="<?= (int)$c['category_id'] ?>"><?=e($c['name'])?></option><?php endforeach;?></select></div>
      <div><label for="admin-new-price">Price (Rs.)</label><input id="admin-new-price" type="number" min="0" step="0.01" name="price" required></div>
      <div><label for="admin-new-stock">Stock</label><input id="admin-new-stock" type="number" min="0" name="stock" required></div>
      <div><label for="admin-new-sizes">Sizes (comma-separated)</label><input id="admin-new-sizes" name="sizes" value="S,M,L,XL" required></div>
      <div><label for="admin-new-colours">Colours (comma-separated)</label><input id="admin-new-colours" name="colours" value="Natural,Olive,Black" required></div>
      <div><label for="admin-new-image">Product image</label><input id="admin-new-image" type="file" name="product_image" data-image-preview="admin-new-preview" accept="image/jpeg,image/png,image/webp,image/gif"><img id="admin-new-preview" class="image-preview" alt="Selected product image preview" hidden><small>Optional · JPG, PNG, WEBP or GIF · up to 5 MB</small></div>
      <div><label for="admin-new-status">Status</label><select id="admin-new-status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </div>
    <label for="admin-new-description">Description</label><textarea id="admin-new-description" name="description" required></textarea>
    <p><button>Add product</button></p>
  </form>
</details>
<?php endif; ?>

<section class="admin-premium-collection">
  <div class="admin-premium-collection-head">
    <div>
      <p class="admin-premium-kicker">The collection</p>
      <h2>All products <span class="admin-premium-count"><?=$totalProducts?></span></h2>
    </div>
    <?php if ($products): ?>
      <div class="admin-premium-search">
        <label class="workspace-search-label" for="admin-product-search">Search products</label>
        <input id="admin-product-search" class="workspace-search" type="search" placeholder="Search by title, category, vendor or SKU" data-product-search>
      </div>
    <?php endif; ?>
  </div>

  <p class="muted">Administrators can delete any catalogue piece. Past orders keep the sold item's name and price after deletion.</p>

  <?php if ($products): ?>
    <div class="table-scroll workspace-product-table admin-product-table admin-premium-table" role="region" aria-label="Product catalogue" tabindex="0">
      <table>
        <thead><tr><th>Product</th><th>Category</th><th>Vendor</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($products as $p): ?>
          <tr data-product-row>
            <td>
              <div class="admin-premium-thumb">
                <img src="<?=e(asset_url(product_image_url($p)))?>" alt="<?=e($p['title'])?>">
                <div><strong><?=e($p['title'])?></strong><small>Product #<?=(int)$p['product_id']?></small></div>
              </div>
            </td>
            <td><span class="admin-premium-tag"><?=e($p['category'])?></span></td>
            <td><span class="admin-premium-tag"><?=e($p['vendor'])?></span></td>
            <td><span class="admin-premium-price">Rs. <?=number_format((float)$p['price'],2)?></span></td>
            <td>
              <div class="admin-premium-stock <?= (int)$p['stock'] <= 5 ? 'is-low' : '' ?>">
                <strong><?=(int)$p['stock']?></strong>
                <small><?= (int)$p['stock'] <= 5 ? 'Low stock' : 'Available units' ?></small>
              </div>
            </td>
            <td><span class="admin-premium-status <?= $p['status']==='active'?'is-active':'is-inactive' ?>"><?=e(ucfirst($p['status']))?></span></td>
            <td>
              <div class="admin-premium-actions">
                <a class="admin-premium-action" href="admin-product-view.php?id=<?=(int)$p['product_id']?>" aria-label="View product: <?=e($p['title'])?>">View</a>
                <a class="admin-premium-action" href="admin-product-edit.php?id=<?=(int)$p['product_id']?>" aria-label="Edit product: <?=e($p['title'])?>">Edit</a>
                <form method="post" data-confirm="<?=e('Delete '.$p['title'].'? This cannot be undone.')?>">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                  <button type="submit" class="admin-premium-action is-danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="admin-premium-empty">No products yet. Add your first catalogue piece above.</div>
  <?php endif; ?>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
