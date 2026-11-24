<?php
// Non-destructive integration smoke test. Writes are rolled back at completion.
require_once __DIR__.'/../includes/bootstrap.php';
$check = static function(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
};
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach (['users','vendor_profiles','categories','products','product_views','orders','order_items','transactions','order_status_history','reviews'] as $table) {
    $check(in_array($table, $tables, true), "database table {$table} exists");
}
$seed = $pdo->prepare('SELECT password_hash FROM users WHERE email=?');
$seed->execute(['customer@redlineroyale.test']);
$hash = $seed->fetchColumn();
$check($hash && password_verify('Demo123!', $hash), 'seeded customer password verifies');

$pdo->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(5));
    $st = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,'vendor')");
    $st->execute(['Smoke Vendor', "smoke-vendor-{$suffix}@example.test", password_hash('TestPass123!', PASSWORD_DEFAULT)]);
    $vendorId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO vendor_profiles(user_id,business_name,owner_name,phone,address) VALUES(?,?,?,?,?)')->execute([$vendorId,'Smoke Shop','Smoke Owner','9800000001','Kathmandu']);
    $customerSt = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,'customer')");
    $customerSt->execute(['Smoke Customer', "smoke-customer-{$suffix}@example.test", password_hash('TestPass123!', PASSWORD_DEFAULT)]);
    $customerId = (int)$pdo->lastInsertId();
    $categoryId = (int)$pdo->query('SELECT category_id FROM categories ORDER BY category_id LIMIT 1')->fetchColumn();
    $productSt = $pdo->prepare("INSERT INTO products(vendor_id,category_id,title,description,price,stock,sizes,colours,status) VALUES(?,?,?,?,?,?,'S,M','Natural,Olive','active')");
    $productSt->execute([$vendorId,$categoryId,'Smoke test product','Temporary CRUD integration test.',100,4]);
    $productId = (int)$pdo->lastInsertId();
    $check((int)$pdo->query("SELECT COUNT(*) FROM products WHERE product_id={$productId} AND vendor_id={$vendorId}")->fetchColumn() === 1, 'product INSERT and vendor-scoped SELECT work');
    $pdo->prepare('UPDATE products SET stock=? WHERE product_id=? AND vendor_id=?')->execute([3,$productId,$vendorId]);
    $check((int)$pdo->query("SELECT stock FROM products WHERE product_id={$productId}")->fetchColumn() === 3, 'vendor-owned product UPDATE works');
    $pdo->prepare('INSERT INTO orders(customer_id,fulfillment,recipient_name,phone,payment_method,total) VALUES(?,?,?,?,?,?)')->execute([$customerId,'delivery','Smoke Customer','9800000002','cash_on_delivery',250]);
    $orderId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO order_items(order_id,product_id,vendor_id,quantity,unit_price) VALUES(?,?,?,?,?)')->execute([$orderId,$productId,$vendorId,1,100]);
    $pdo->prepare('INSERT INTO reviews(customer_id,product_id,rating,comment) VALUES(?,?,?,?)')->execute([$customerId,$productId,5,'Smoke-test review']);
    $review = $pdo->prepare('SELECT rating,comment FROM reviews WHERE customer_id=? AND product_id=?');
    $review->execute([$customerId,$productId]);
    $savedReview = $review->fetch();
    $check($savedReview && (int)$savedReview['rating'] === 5 && $savedReview['comment'] === 'Smoke-test review', 'eligible customer review INSERT and SELECT work');
    $pdo->prepare('INSERT INTO transactions(order_id,payment_method,amount,payment_status) VALUES(?,?,?,?)')->execute([$orderId,'cash_on_delivery',250,'pending']);
    $pdo->prepare('INSERT INTO order_status_history(order_id,changed_by,status,note) VALUES(?,?,?,?)')->execute([$orderId,$vendorId,'placed','Smoke test']);
    $pdo->prepare('INSERT INTO product_views(product_id) VALUES(?)')->execute([$productId]);
    $check((int)$pdo->query("SELECT COUNT(*) FROM order_items WHERE order_id={$orderId}")->fetchColumn() === 1, 'order and order-item INSERT/SELECT work');
    $pdo->prepare('UPDATE orders SET status=? WHERE order_id=? AND EXISTS (SELECT 1 FROM order_items WHERE order_items.order_id=orders.order_id AND vendor_id=?)')->execute(['vendor accepted',$orderId,$vendorId]);
    $check($pdo->query("SELECT status FROM orders WHERE order_id={$orderId}")->fetchColumn() === 'vendor accepted', 'vendor-scoped order status UPDATE works');
    $pdo->prepare('DELETE FROM reviews WHERE product_id=? AND customer_id=?')->execute([$productId,$customerId]);
    $pdo->prepare('DELETE FROM order_status_history WHERE order_id=?')->execute([$orderId]);
    $pdo->prepare('DELETE FROM transactions WHERE order_id=?')->execute([$orderId]);
    $pdo->prepare('DELETE FROM orders WHERE order_id=?')->execute([$orderId]);
    $pdo->prepare('DELETE FROM products WHERE product_id=? AND vendor_id=?')->execute([$productId,$vendorId]);
    $check((int)$pdo->query("SELECT COUNT(*) FROM products WHERE product_id={$productId}")->fetchColumn() === 0, 'vendor-owned product DELETE works');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
echo "PASS: integration writes rolled back; database remains unchanged.\n";
