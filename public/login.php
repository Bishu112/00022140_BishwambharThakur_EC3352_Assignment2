<?php
require_once __DIR__.'/../includes/bootstrap.php';
$pageTitle = 'Sign in | Redline Royale';
$pageClass = 'auth-page';
$formError = '';
$formEmail = '';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $formEmail = strtolower(trim($_POST['email'] ?? ''));
    $st = $pdo->prepare('SELECT user_id,full_name,email,password_hash,role FROM users WHERE email=?');
    $st->execute([$formEmail]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        unset($u['password_hash']);
        $_SESSION['user'] = $u;
        flash('Signed in successfully.');
        header('Location: '.$next); exit;
    }
    $formError = 'That email and password combination does not match our records. Check both fields and try again.';
}
include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">SIGN IN</div>
<h1 class="page-heading">Welcome back !</h1>
<section class="auth-card" aria-labelledby="login-heading">
  <p class="auth-brand">Redline Royale</p>
  <h2 id="login-heading">Welcome back to Redline Royale</h2>
  <?php if($formError!==''):?><p class="notice" role="alert"><?=e($formError)?></p><?php endif;?>
  <form class="auth-form" method="post">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="next" value="<?=e($next)?>">
    <label for="email">Email address</label>
    <input id="email" type="email" name="email" placeholder="customer@redlineroyale.test" autocomplete="email" value="<?=e($formEmail)?>" required>
    <label for="password">Password</label>
    <div class="password-control"><input id="password" type="password" name="password" placeholder="Demo123!" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-pressed="false">Show</button></div>
    <p class="forgot-help"><a href="forgot-password.php">Forgot password?</a></p>
    <button type="submit">Sign in</button>
    <p class="auth-link"><a href="register.php">New customer? Create an account</a></p>
  </form>
  <p class="vendor-note">Vendor? <a href="vendor-login.php">Vendor sign in</a> · <a href="vendor-register.php">Become a Vendor</a></p>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
