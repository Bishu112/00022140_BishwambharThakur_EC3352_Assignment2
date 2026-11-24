<?php
require_once __DIR__.'/../includes/bootstrap.php';
if (signed_in()) { header('Location: vendor-dashboard.php'); exit; }
$pageTitle = 'Become a Vendor | Redline Royale';
$pageClass = 'signup-page';

$formError = '';
$values = ['business_name' => '', 'owner_name' => '', 'email' => '', 'phone' => '', 'business_address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($values as $key => $unused) $values[$key] = trim((string)($_POST[$key] ?? ''));
    $values['email'] = strtolower($values['email']);
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($values['business_name'] === '') {
        $formError = 'Enter the name of your business.';
    } elseif ($values['owner_name'] === '') {
        $formError = 'Enter the name of the business owner.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $formError = 'Enter a valid email address, for example name@example.com.';
    } elseif ($values['phone'] === '') {
        $formError = 'Enter a contact phone number for your business.';
    } elseif ($values['business_address'] === '') {
        $formError = 'Enter the business address.';
    } elseif (strlen($password) < 8) {
        $formError = 'Choose a password of at least 8 characters.';
    } elseif ($password !== $confirm) {
        $formError = 'The two passwords do not match. Please type them again.';
    } else {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,'vendor')");
            $st->execute([$values['owner_name'], $values['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $uid = (int)$pdo->lastInsertId();
            $st = $pdo->prepare('INSERT INTO vendor_profiles(user_id,business_name,owner_name,phone,address) VALUES(?,?,?,?,?)');
            $st->execute([$uid, $values['business_name'], $values['owner_name'], $values['phone'], $values['business_address']]);
            $pdo->commit();
            flash('Vendor account created. Sign in to manage your store.');
            header('Location: vendor-login.php');
            exit;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $formError = 'That email address may already be registered. Try signing in instead.';
        }
    }
}
include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">VENDOR SIGN UP</div>
<h1 class="page-heading">Create your vendor account</h1>
<section class="auth-card signup-card" aria-labelledby="vendor-signup-heading">
  <p class="auth-brand">JOIN THE REDLINE ROYALE MARKETPLACE</p>
  <h2 id="vendor-signup-heading">Register your business</h2>
  <?php if ($formError !== ''): ?><p class="notice" role="alert"><?=e($formError)?></p><?php endif; ?>
  <form class="auth-form signup-form" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <div class="fields">
      <div><label for="business">Business name</label><input id="business" name="business_name" placeholder="Enter business name" autocomplete="organization" value="<?=e($values['business_name'])?>" required></div>
      <div><label for="owner">Owner name</label><input id="owner" name="owner_name" placeholder="Enter owner name" autocomplete="name" value="<?=e($values['owner_name'])?>" required></div>
      <div><label for="vendor-email">Email</label><input id="vendor-email" type="email" name="email" placeholder="Enter email" autocomplete="email" value="<?=e($values['email'])?>" required></div>
      <div><label for="phone">Phone number</label><input id="phone" type="tel" name="phone" placeholder="Enter phone number" autocomplete="tel" value="<?=e($values['phone'])?>" required></div>
      <div class="vendor-address"><label for="address">Business address</label><input id="address" name="business_address" placeholder="Enter business address" autocomplete="street-address" value="<?=e($values['business_address'])?>" required></div>
      <div><label for="vendor-password">Password</label><div class="password-control"><input id="vendor-password" type="password" name="password" minlength="8" placeholder="At least 8 characters" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="vendor-password" aria-pressed="false">Show</button></div></div>
      <div><label for="vendor-confirm">Confirm password</label><div class="password-control"><input id="vendor-confirm" type="password" name="password_confirm" minlength="8" placeholder="Confirm password" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="vendor-confirm" aria-pressed="false">Show</button></div></div>
    </div>
    <button type="submit">Create vendor account</button>
    <p class="auth-link"><a href="vendor-login.php">Already a vendor? Sign in</a></p>
  </form>
  <p class="vendor-note">Shopping instead? <a href="register.php">Create a customer account</a></p>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
