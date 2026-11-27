<?php
require_once __DIR__.'/../includes/bootstrap.php';
$pageTitle='Reset password | Redline Royale';
$pageClass='auth-page';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $adminEmail=strtolower(trim($_POST['admin_email']??''));
    $adminPassword=$_POST['admin_password']??'';
    $email=strtolower(trim($_POST['email']??''));
    $password=$_POST['new_password']??'';
    $confirm=$_POST['confirm_password']??'';
    $st=$pdo->prepare("SELECT user_id,password_hash FROM users WHERE email=? AND role='admin'");
    $st->execute([$adminEmail]);
    $admin=$st->fetch();
    if(!$admin||!password_verify($adminPassword,$admin['password_hash'])){
        $error='Administrator sign-in could not be verified.';
    }elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||$password!==$confirm){
        $error='Enter the account email and matching new passwords of at least 8 characters.';
    }else{
        $st=$pdo->prepare("UPDATE users SET password_hash=? WHERE email=? AND role IN ('customer','vendor')");
        $st->execute([password_hash($password,PASSWORD_DEFAULT),$email]);
        if($st->rowCount()){
            flash('Password reset. You can now sign in with your new password.');
            header('Location: login.php');exit;
        }
        $error='No customer or vendor account was found for that email address.';
    }
}
include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">ACCOUNT RECOVERY</div>
<h1 class="page-heading">Reset your password</h1>
<section class="auth-card recovery-card" aria-labelledby="recovery-heading">
  <p class="auth-brand">REDLINE ROYALE</p>
  <h2 id="recovery-heading">Get back into your account</h2>
  <p class="recovery-note">This coursework demo does not send email. Ask your group administrator to approve the reset by signing in below.</p>
  <?php if($error!==''):?><p class="notice" role="alert"><?=e($error)?></p><?php endif;?>
  <form class="auth-form recovery-form" method="post">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <label for="admin-email">Administrator email</label><input id="admin-email" type="email" name="admin_email" autocomplete="username" required>
    <label for="admin-password">Administrator password</label><div class="password-control"><input id="admin-password" type="password" name="admin_password" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="admin-password" aria-pressed="false">Show</button></div>
    <label for="account-email">Account email to reset</label><input id="account-email" type="email" name="email" autocomplete="email" required>
    <label for="new-password">New password</label><div class="password-control"><input id="new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="new-password" aria-pressed="false">Show</button></div>
    <label for="confirm-password">Confirm new password</label><div class="password-control"><input id="confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="confirm-password" aria-pressed="false">Show</button></div>
    <button type="submit">Reset password</button>
    <p class="auth-link"><a href="login.php">Back to sign in</a></p>
  </form>
</section>
<?php include __DIR__.'/../includes/footer.php';?>
