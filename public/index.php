<?php
require_once __DIR__.'/../includes/bootstrap.php';
$pageTitle = 'Shop all clothing | Redline Royale';
$pageClass = 'catalog-page';
$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['category'] ?? 0);
$priceRange = $_GET['price'] ?? '';
$sort = $_GET['sort'] ?? 'new';
$view = ($_GET['view'] ?? '') === 'table' ? 'table' : 'grid';
$viewQuery = static function (string $mode): string { $q = $_GET; $q['view'] = $mode; return 'index.php?'.http_build_query($q); };
$availability = $_GET['availability'] ?? '';
$excerpt = static function ($text, $len = 90) {
    $t = trim(preg_replace('/\s+/', ' ', (string)$text));
    $long = function_exists('mb_strlen') ? mb_strlen($t) > $len : strlen($t) > $len;
    if (!$long) return $t;
    return (function_exists('mb_substr') ? mb_substr($t, 0, $len) : substr($t, 0, $len)).'…';
};

$sql = "SELECT p.product_id,p.title,p.description,p.price,p.stock,p.image_url,c.name category FROM products p JOIN categories c ON c.category_id=p.category_id WHERE p.status='active'";
$args = [];
if ($q !== '') { $sql .= ' AND (p.title LIKE ? OR p.description LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($cat > 0) { $sql .= ' AND p.category_id=?'; $args[] = $cat; }
if ($priceRange === 'under-2500') $sql .= ' AND p.price < 2500';
if ($priceRange === '2500-5000') $sql .= ' AND p.price BETWEEN 2500 AND 5000';
if ($priceRange === 'over-5000') $sql .= ' AND p.price > 5000';
if ($availability === 'in-stock') $sql .= ' AND p.stock > 0';
if ($availability === 'out-of-stock') $sql .= ' AND p.stock = 0';
$sql .= match ($sort) {
    'price' => ' ORDER BY p.price ASC',
    'price-high' => ' ORDER BY p.price DESC',
    'most-viewed' => ' ORDER BY p.views DESC, p.created_at DESC',
    'most-purchased' => " ORDER BY (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.order_id=oi.order_id WHERE oi.product_id=p.product_id AND o.status<>'discarded') DESC, p.created_at DESC",
    default => ' ORDER BY p.created_at DESC',
};
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();
$categories = $pdo->query('SELECT category_id,name FROM categories ORDER BY name')->fetchAll();
include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">PRODUCT LISTING</div>
<h1 class="page-heading">Shop all clothing</h1>
<div class="catalog-layout">
  <aside class="filter-panel">
    <h2>Refine results</h2>
    <form method="get" action="index.php" data-auto-submit>
      <input type="hidden" name="q" value="<?=e($q)?>">
      <input type="hidden" name="view" value="<?=e($view)?>">
      <div class="filter-field">
      <label for="category">Category</label>
      <select id="category" name="category" aria-label="Filter by category">
        <option value="0">Select</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?=$c['category_id']?>" <?=$cat === (int)$c['category_id'] ? 'selected' : ''?>><?=e($c['name'])?></option>
        <?php endforeach; ?>
      </select>
      </div>

      <div class="filter-field">
      <label for="price">Price</label>
      <select id="price" name="price" aria-label="Filter by price">
        <option value="">Select</option>
        <option value="under-2500" <?=$priceRange === 'under-2500' ? 'selected' : ''?>>Under Rs. 2,500</option>
        <option value="2500-5000" <?=$priceRange === '2500-5000' ? 'selected' : ''?>>Rs. 2,500 – 5,000</option>
        <option value="over-5000" <?=$priceRange === 'over-5000' ? 'selected' : ''?>>Over Rs. 5,000</option>
      </select>
      </div>

      <div class="filter-field">
      <label for="sort">Sort order</label>
      <select id="sort" name="sort" aria-label="Sort order">
        <option value="new" <?=$sort === 'new' ? 'selected' : ''?>>Select</option>
        <option value="price" <?=$sort === 'price' ? 'selected' : ''?>>Price: low to high</option>
        <option value="price-high" <?=$sort === 'price-high' ? 'selected' : ''?>>Price: high to low</option>
        <option value="most-viewed" <?=$sort === 'most-viewed' ? 'selected' : ''?>>Most viewed</option>
        <option value="most-purchased" <?=$sort === 'most-purchased' ? 'selected' : ''?>>Most purchased</option>
      </select>
      </div>

      <div class="filter-field">
      <label for="availability">Availability</label>
      <select id="availability" name="availability" aria-label="Filter by availability">
        <option value="" <?=$availability === '' ? 'selected' : ''?>>Select</option>
        <option value="in-stock" <?=$availability === 'in-stock' ? 'selected' : ''?>>In stock</option>
        <option value="out-of-stock" <?=$availability === 'out-of-stock' ? 'selected' : ''?>>Out of stock</option>
      </select>
      </div>
      <div class="filter-field filter-submit"><button type="submit">Apply filters</button></div>
      <p class="filter-reset"><a href="index.php">Clear all filters</a></p>
    </form>
  </aside>

  <section class="catalog-results" aria-label="Product results">
    <p class="result-count"><?=count($products)?> <?=count($products) === 1 ? 'product' : 'products'?></p>
    <p class="view-toggle">View: <a href="<?=e($viewQuery('grid'))?>" <?=$view === 'grid' ? 'aria-current="true"' : ''?>>Cards</a> · <a href="<?=e($viewQuery('table'))?>" <?=$view === 'table' ? 'aria-current="true"' : ''?>>Table</a></p>
    <?php if ($products && $view === 'table'): ?>
      <div class="table-scroll" role="region" aria-label="Product table" tabindex="0">
        <table>
          <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Details</th></tr></thead>
          <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td><img class="admin-product-image" src="<?=e(asset_url(product_image_url($p)))?>" alt="<?=e($p['title'])?>" loading="lazy"></td>
              <td><?=e($p['title'])?></td>
              <td><?=e($p['category'])?></td>
              <td>Rs. <?=number_format((float)$p['price'],2)?></td>
              <td><?=$p['stock'] > 0 ? (int)$p['stock'] : 'Out of stock'?></td>
              <td><a href="product.php?id=<?=$p['product_id']?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($products): ?>
      <div class="catalog-grid">
        <?php foreach ($products as $p): ?>
          <article class="catalog-card">
            <a href="product.php?id=<?=$p['product_id']?>" aria-label="View <?=e($p['title'])?>"><img class="catalog-image" src="<?=e(asset_url(product_image_url($p)))?>" alt="<?=e($p['title'])?>" loading="lazy"></a>
            <h2><?=e($p['title'])?></h2>
            <p class="catalog-desc"><?=e($excerpt($p['description']))?></p>
            <p class="price">Rs. <?=number_format((float)$p['price'],2)?></p>
            <p class="catalog-category"><?=e($p['category'])?> · <?=$p['stock'] > 0 ? (int)$p['stock'].' in stock' : 'Out of stock'?></p>
            <?php if ($p['stock'] > 0): ?>
              <a class="product-details-link" href="product.php?id=<?=$p['product_id']?>">Product details <span aria-hidden="true">→</span></a>
            <?php else: ?><p class="catalog-unavailable">Currently unavailable</p><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty-results">No products match these filters.</p>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
