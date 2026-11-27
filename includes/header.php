<?php
require_once __DIR__.'/bootstrap.php';
$notice = flash();
$currentQuery = trim($_GET['q'] ?? '');
$route = basename($_SERVER['SCRIPT_NAME'] ?? '');
$accessoriesId = 0;
$shoesId = 0;
if ($pdo !== null) {
    try { $accessoriesId = (int)($pdo->query("SELECT category_id FROM categories WHERE name='Accessories' LIMIT 1")->fetchColumn() ?: 0); } catch (Throwable $e) { $accessoriesId = 0; }
    try { $shoesId = (int)($pdo->query("SELECT category_id FROM categories WHERE name='Shoes' LIMIT 1")->fetchColumn() ?: 0); } catch (Throwable $e) { $shoesId = 0; }
}
$user = signed_in();
$workspace = in_array(($workspaceType ?? ''), ['vendor','customer'], true);
$showBag = true;
$showSignIn = !$user;
$showAccount = (bool)$user;
$showOrders = !$user;
$bagCount = array_sum(array_map(static fn($item) => is_array($item) ? (int)($item['quantity'] ?? 0) : (int)$item, $_SESSION['cart'] ?? []));
$accountHref = 'login.php';
$accountLabel = 'Sign in';
if (in_array($route, ['vendor-register.php','vendor-login.php'], true)) $accountHref = 'vendor-login.php';
if ($user && $showAccount) {
    $accountHref = match ($user['role']) {
        'vendor' => 'vendor-dashboard.php',
        'admin' => 'admin-dashboard.php',
        default => 'customer-dashboard.php',
    };
    $accountLabel = match ($user['role']) {
        'vendor' => 'Vendor home',
        'admin' => 'Admin',
        default => 'My orders',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=e($pageTitle ?? 'Redline Royale')?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="stylesheet" href="style.css">
</head>
<body class="<?=e($pageClass ?? '')?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header<?= $workspace ? ' workspace-header' : '' ?>">
  <a class="brand" href="<?=signed_in() ? 'home.php' : 'login.php'?>">Redline Royale</a>
<?php if (!$workspace): ?>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav site-tools" aria-label="Open menu">
    <span></span><span></span><span></span>
  </button>
  <nav class="store-nav" id="primary-nav" aria-label="Shop categories">
    <a href="index.php?sort=new">New In</a>
    <a href="index.php">Clothing</a>
    <a href="index.php?category=<?=$shoesId?>">Shoes</a>
    <a href="index.php?category=<?=$accessoriesId?>">Accessories</a>
  </nav>
  <div class="header-tools" id="site-tools">
    <form class="header-search" action="index.php" method="get" role="search">
      <input type="search" name="q" value="<?=e($currentQuery)?>" placeholder="Search products" aria-label="Search products">
    </form>
    <?php if ($showBag): ?><a class="header-bag" href="cart.php">Cart (<?=$bagCount?>)</a><?php endif; ?>
    <?php if ($showOrders): ?><a class="header-orders" href="<?=$user?'customer-dashboard.php':'login.php'?>">My orders</a><?php endif; ?>
    <?php if ($showSignIn): ?><a class="header-account" href="login.php">Sign in</a><?php endif; ?>
    <?php if ($showAccount): ?><a class="header-account" href="<?=e($accountHref)?>"><?=e($accountLabel)?></a><?php endif; ?>
    <?php if ($showAccount): ?><a class="header-signout" href="logout.php">Sign out</a><?php endif; ?>
  </div>
  <?php else: ?><div class="workspace-header-actions"><span><?=($workspaceType==='vendor'?'Vendor workspace':'Customer account')?></span><a href="home.php">View shop</a><a class="workspace-signout" href="logout.php">Sign out</a></div>
  <?php endif; ?>
</header>
<?php if ($workspace): $workspaceLinks=($workspaceType==='vendor')?['Overview'=>'vendor-dashboard.php','Products'=>'product-list.php','Orders'=>'vendor-orders.php','Reports'=>'reports.php','Business profile'=>'profile.php']:['Overview'=>'customer-dashboard.php','My orders'=>'customer-dashboard.php#orders','Order tracking'=>'order-tracking.php','Reports'=>'customer-reports.php','Profile'=>'profile.php']; ?><main class="dashboard-shell" id="main-content"><aside class="dashboard-sidebar" aria-label="<?=e(ucfirst($workspaceType))?> navigation"><nav><?php foreach ($workspaceLinks as $label=>$href): ?><a href="<?=$href?>" class="<?=($workspaceActive??'')===$label?'is-active':''?>" <?=($workspaceActive??'')===$label?'aria-current="page"':''?>><?=e($label)?></a><?php endforeach; ?></nav></aside><div class="dashboard-content"><div class="dashboard-main">
<?php else: ?><main class="wrap" id="main-content"><?php endif; ?>
<?php if ($notice): ?><div class="notice <?=e(notice_class($notice))?>" role="status"><?=e($notice)?></div><?php endif; ?>
