<?php
require_once __DIR__.'/../includes/bootstrap.php';
$isVendor = ($_GET['role'] ?? '') === 'vendor';
if ($isVendor) { header('Location: vendor-register.php'); exit; }
$pageTitle = ($isVendor ? 'Vendor sign up' : 'Sign up').' | Redline Royale';
$pageClass = 'signup-page';
$formError = '';
$name = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $role = 'customer';
    if ($name === '') {
        $formError = 'Enter your full name so we know who the account belongs to.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Enter a valid email address, for example name@example.com.';
    } elseif (strlen($password) < 8) {
        $formError = 'Choose a password of at least 8 characters.';
    } elseif ($password !== $confirm) {
        $formError = 'The two passwords do not match. Please type them again.';
    } else {
        try {
            $st = $pdo->prepare('INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,?)');
            $st->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('Your account is ready. Sign in to start shopping.');
            header('Location: login.php'); exit;
        } catch (PDOException $error) {
            $formError = 'That email address is already registered. Try signing in instead.';
        }
    }
}
include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">SIGN UP</div>
<h1 class="page-heading"><?=$isVendor ? 'Create your vendor account' : 'Create your customer account'?></h1>
<section class="auth-card signup-card" aria-labelledby="signup-heading">
  <p class="auth-brand">JOIN THE COMMUNITY</p>
  <h2 id="signup-heading"><?=$isVendor ? 'Create your vendor account' : 'Create your customer account'?></h2>
  <?php if ($formError !== ''): ?><p class="notice" role="alert"><?=e($formError)?></p><?php endif; ?>
  <form class="auth-form signup-form" method="post" novalidate>
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="role" value="customer">
    <div class="fields">
      <div><label for="full-name">Full name</label><input id="full-name" name="full_name" placeholder="Enter full name" autocomplete="name" value="<?=e($name)?>" required></div>
      <div><label for="signup-email">Email address</label><input id="signup-email" type="email" name="email" placeholder="Enter email address" autocomplete="email" value="<?=e($email)?>" required></div>
      <div><label for="signup-password">Password</label><div class="password-control"><input id="signup-password" type="password" name="password" placeholder="Enter password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="signup-password" aria-pressed="false">Show</button></div></div>
      <div><label for="password-confirm">Confirm password</label><div class="password-control"><input id="password-confirm" type="password" name="password_confirm" placeholder="Enter confirm password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="password-confirm" aria-pressed="false">Show</button></div></div>
    </div>
    <button type="submit">Create account</button>
    <p class="auth-link"><a href="login.php">Already registered? Sign in</a></p>
  </form>
  <p class="vendor-note">Vendor? Register separately · <a href="vendor-register.php">Become a Vendor</a></p>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
