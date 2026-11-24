<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$configFile = dirname(__DIR__) . '/config/database.php';
$legacyConfigFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile) && !is_file($legacyConfigFile)) { http_response_code(500); exit('Copy config.example.php to config.php and enter the local MySQL settings.'); }
$config = require $configFile;
$pdo = null;
$dbProblem = '';
$opts = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$ports = !empty($config['port']) ? [(int)$config['port']] : [3306, 3307];
if (!empty($config['port'])) { foreach ([3306, 3307] as $alt) if (!in_array($alt, $ports, true)) $ports[] = $alt; }
$charset = $config['charset'] ?? 'utf8mb4';
foreach ($ports as $port) {
    try {
        // Connect to the server first (without a database) so a missing database can be created.
        $server = new PDO("mysql:host={$config['host']};port={$port};charset={$charset}", $config['username'], $config['password'] ?? '', $opts);
    } catch (PDOException $e) { $dbProblem = $e->getMessage(); continue; }
    try {
        $exists = $server->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=".$server->quote($config['database']))->fetchColumn();
        if (!$exists) {
            $sqlFile = dirname(__DIR__).'/database/redline_royale.sql';
            if (!is_file($sqlFile)) throw new PDOException('The database export file is missing.');
            $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($sqlFile));
            // The export contains one SQL statement per line-terminated semicolon.
            // Execute each statement separately; PDO::exec does not reliably run a dump.
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
                $statement = trim($statement);
                if ($statement !== '') $server->exec($statement);
            }
        } else {
            $has = $server->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=".$server->quote($config['database'])." AND TABLE_NAME IN ('users','vendor_profiles','categories','products','orders','order_items','transactions','order_status_history','reviews','product_views')")->fetchColumn();
            if ((int)$has !== 10) {
                $dbProblem = 'The configured database exists but is missing application tables. Import database/redline_royale.sql into a fresh database or restore the missing tables.';
                break;
            }
        }
        $pdo = new PDO("mysql:host={$config['host']};port={$port};dbname={$config['database']};charset={$charset}", $config['username'], $config['password'] ?? '', $opts);
        break;
    } catch (PDOException $e) { $dbProblem = $e->getMessage(); $pdo = null; }
}
if ($pdo === null) {
    $_SESSION['db_error'] = 'Database unavailable. Start MySQL/MariaDB, check the PDO MySQL extension and settings, and confirm the configured database has all ten tables.'; error_log('Redline Royale DB error: '.$dbProblem);
}

if ($pdo === null) {
    error_page(503, 'Database Unavailable', $_SESSION['db_error'] ?? 'Database connection failed.');
}
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function asset_url($path) { $path = ltrim((string)$path, '/'); if (str_starts_with($path, 'assets/images/')) $path = substr($path, 7); $base = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'); return ($base === '' ? '' : '/'.$base).'/'.$path; }
function product_image_url(array $product) {
    $title = mb_strtolower(trim((string)($product['title'] ?? '')));
    $image = (string)($product['image_url'] ?? '');
    $catalogImages = [
        'handwoven pocket square' => 'images/products/silk-blazer.png',
        'dhaka topi' => 'images/products/dhaka-topi.png',
        'handcrafted leather belt' => 'images/products/belt.png',
        'wool blend heritage jacket' => 'images/products/heritage-jacket.png',
        'silk blend blazer' => 'images/products/silk-blazer.png',
        'heritage cotton kurta' => 'images/products/kurta.png',
        'cotton churidar pyjama' => 'images/products/churidar.png',
        'linen summer shirt' => 'images/products/linen-shirt.png',
        'nepali dhaka shirt' => 'images/products/nepali-dhaka-shirt.png',
        'leather oxford shoes' => 'images/products/leather-shoes.png',
        'canvas sneakers' => 'images/products/sneakers.png',
        'hemp slip-on loafers' => 'images/products/hemp-loafer.png',
        'embroidered waistcoat' => 'images/products/waistcoat.png',
    ];
    if (isset($catalogImages[$title]) && ($image === '' || str_ends_with(mb_strtolower($image), '.svg'))) return $catalogImages[$title];
    return $image ?: 'images/products/heritage-cotton-kurta.svg';
}
function signed_in() { return $_SESSION['user'] ?? null; }
function safe_next($value, $fallback = 'home.php') {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '' || preg_match('/[\x00-\x20]/', $value)) return $fallback;
    if (!preg_match('/^[A-Za-z0-9._-]+\.php(\?[A-Za-z0-9._~%=&+-]*)?$/', $value)) return $fallback;
    return $value;
}
function error_page($code, $heading, $message) {
    http_response_code($code);
    global $pdo, $notice, $route, $user, $showBag, $showSignIn, $showAccount, $showOrders, $bagCount, $accountHref, $accountLabel, $currentQuery, $accessoriesId, $shoesId;
    $GLOBALS['pageTitle'] = $heading.' | Redline Royale';
    $GLOBALS['pageClass'] = 'error-page';
    $pageTitle = $GLOBALS['pageTitle'];
    $pageClass = $GLOBALS['pageClass'];
    $notice = $notice ?? flash();
    $route = $route ?? basename($_SERVER['SCRIPT_NAME'] ?? '');
    $user = signed_in();
    $showBag = true;
    $showSignIn = !$user;
    $showAccount = (bool)$user;
    $showOrders = !$user;
    $bagCount = array_sum(array_map(static fn($item) => is_array($item) ? (int)($item['quantity'] ?? 0) : (int)$item, $_SESSION['cart'] ?? []));
    $accountHref = 'login.php';
    $accountLabel = 'Sign in';
    if ($user && $showAccount) {
        $accountHref = match ($user['role']) { 'vendor'=>'vendor-dashboard.php', 'admin'=>'admin-dashboard.php', default=>'customer-dashboard.php' };
        $accountLabel = match ($user['role']) { 'vendor'=>'Vendor home', 'admin'=>'Admin', default=>'My orders' };
    }
    $currentQuery = trim($_GET['q'] ?? '');
    $accessoriesId = 0;
    $shoesId = 0;
    if ($pdo !== null) {
        try { $accessoriesId = (int)($pdo->query("SELECT category_id FROM categories WHERE name='Accessories' LIMIT 1")->fetchColumn() ?: 0); } catch (Throwable $e) { $accessoriesId = 0; }
        try { $shoesId = (int)($pdo->query("SELECT category_id FROM categories WHERE name='Shoes' LIMIT 1")->fetchColumn() ?: 0); } catch (Throwable $e) { $shoesId = 0; }
    }
    include __DIR__.'/header.php';
    echo '<div class="page-kicker">ERROR '.$code.'</div>';
    echo '<h1 class="page-heading">'.e($heading).'</h1>';
    echo '<section class="panel error-panel"><p>'.e($message).'</p>';
    echo '<p class="error-actions"><a class="button" href="home.php">Back to home</a> <a class="button button-secondary" href="index.html">Store homepage</a></p>';
    echo '<p class="error-help">If this keeps happening, sign out and sign in again, or contact the store administrator.</p></section>';
    include __DIR__.'/footer.php';
    exit;
}
function require_role(array $roles) {
    if (!signed_in()) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Location: login.php'); exit; }
        $next = basename($_SERVER['SCRIPT_NAME'] ?? 'home.php');
        $query = (string)($_SERVER['QUERY_STRING'] ?? '');
        if ($query !== '') $next .= '?'.$query;
        header('Location: login.php?next='.rawurlencode($next));
        exit;
    }
    if (!in_array(signed_in()['role'], $roles, true)) {
        error_page(403, 'You do not have access to this page', 'Your account is signed in as a different type of user. Use the link in the header to open the area that belongs to your account.');
    }
}
function flash($message=null) { if ($message !== null) { $_SESSION['flash']=$message; return; } $value=$_SESSION['flash']??''; unset($_SESSION['flash']); return $value; }
function notice_class($message) {
    $message = mb_strtolower((string)$message);
    if (preg_match('/\b(error|could not|couldn.t|cannot|can.t|no longer|failed|failure|invalid|incorrect|unavailable|too low|not found|no change|already|empty|deleted? cannot|not be saved|try again|choose a valid|complete the|required|smaller than|use a jpg|no vendor|linked to an order|not part of|no changes)\b/u', $message)) return 'notice-error';
    if (preg_match('/\b(successfully|added|updated|created|saved|cancelled|confirmed|placed|deleted|ready|thank you|account is ready|has been cancelled|status updated|signed in|password reset)\b/u', $message)) return 'notice-success';
    return 'notice-neutral';
}
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf() { if (!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')) { http_response_code(403); exit('Invalid form token. Refresh and try again.'); } }
require_once __DIR__.'/report_export.php';
require_once __DIR__.'/report_export_xml.php';
