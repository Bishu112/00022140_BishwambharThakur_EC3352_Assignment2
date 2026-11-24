<?php
require_once __DIR__.'/../includes/bootstrap.php';
$sort = $_GET['sort'] ?? 'popular';
$order = match($sort) { 'recent'=>'p.created_at DESC', 'viewed'=>'p.views DESC', 'purchased'=>'purchased DESC', 'recommended'=>'rating DESC, (p.stock > 0) DESC, purchased DESC, p.views DESC', default=>'(purchased * 2 + p.views) DESC, p.created_at DESC' };
$products=$pdo->query("SELECT p.product_id,p.title,p.price,p.stock,p.image_url,c.name category,COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON o.order_id=oi.order_id WHERE oi.product_id=p.product_id AND o.status<>'discarded'),0) purchased,COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id=p.product_id),0) rating FROM products p JOIN categories c ON c.category_id=p.category_id WHERE p.status='active' ORDER BY $order")->fetchAll();
$accessoriesId=(int)($pdo->query("SELECT category_id FROM categories WHERE name='Accessories' LIMIT 1")->fetchColumn()?:0);
$shoesId=(int)($pdo->query("SELECT category_id FROM categories WHERE name='Shoes' LIMIT 1")->fetchColumn()?:0);
$pageTitle='Redline Royale | Pieces to wear on repeat';$pageClass='home-page';include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">HOME</div><h1 class="page-heading">Pieces to wear on repeat</h1>
<section class="home-hero"><p class="page-kicker">NEW SEASON · RESPONSIBLY CHOSEN</p><h2>Considered pieces, made to last.</h2><p>Thoughtful apparel from independent labels.</p><a class="primary-link" href="index.php?sort=new">Explore new arrivals</a></section>
<h2 class="section-title">Shop by category</h2><div class="category-links"><a href="index.php">Clothing</a><a href="index.php?category=<?=$shoesId?>">Shoes</a><a href="index.php?category=<?=$accessoriesId?>">Accessories</a></div>
<nav class="product-tabs"><a href="home.php?sort=popular">Popular</a><span>·</span><a href="home.php?sort=recent">Recent</a><span>·</span><a href="home.php?sort=viewed">Most viewed</a><span>·</span><a href="home.php?sort=purchased">Most purchased</a><span>·</span><a href="home.php?sort=recommended">Recommended</a></nav>
<h2 class="section-title">All products <span class="home-product-count"><?=count($products)?></span></h2>
<?php if (!$products): ?><p class="empty-state">No products are available right now. Please check back soon.</p><?php endif; ?>
<div class="home-products"><?php foreach($products as $p):?><a class="home-product" href="product.php?id=<?=$p['product_id']?>"><img src="<?=e(asset_url(product_image_url($p)))?>" alt="<?=e($p['title'])?>"><strong><?=e($p['title'])?></strong><span>Rs. <?=number_format((float)$p['price'],2)?></span><small><?=e($p['category'])?> · <?=$p['stock']>0?'In stock':'Out of stock'?> · Details →</small></a><?php endforeach;?></div>
<?php include __DIR__.'/../includes/footer.php';?>
