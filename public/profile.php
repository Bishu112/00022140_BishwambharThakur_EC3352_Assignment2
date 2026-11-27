<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['customer', 'vendor']);

$user = signed_in();
$userId = (int)$user['user_id'];
$isVendor = $user['role'] === 'vendor';

// Read the current record instead of relying only on the login session.
$st = $pdo->prepare('SELECT * FROM users WHERE user_id=?');
$st->execute([$userId]);
$account = $st->fetch() ?: $user;

$profile = [];
if ($isVendor) {
    $st = $pdo->prepare('SELECT business_name, owner_name, phone, address AS business_address FROM vendor_profiles WHERE user_id=?');
    $st->execute([$userId]);
    $profile = $st->fetch() ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $preferredSize = trim($_POST['preferred_size'] ?? '');
    $preferredColour = trim($_POST['preferred_colour'] ?? '');
    $updatesOptIn = isset($_POST['updates_opt_in']) ? 1 : 0;
    $business = trim($_POST['business_name'] ?? '');
    $businessAddress = trim($_POST['business_address'] ?? '');

    $valid = $name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($phone !== '' && !preg_match('/^[0-9+\s()-]{7,30}$/', $phone)) $valid = false;
    if ($postalCode !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9\s-]{2,19}$/', $postalCode)) $valid = false;
    if ($isVendor && ($business === '' || $phone === '' || $businessAddress === '')) $valid = false;

    if (!$valid) {
        flash('Complete the required account details with a valid email, phone number and postal code.');
        header('Location: profile.php');
        exit;
    }

    // Keep optional text inside the column limits defined in redline_royale.sql.
    $address = mb_substr($address, 0, 255);
    $city = mb_substr($city, 0, 80);
    $district = mb_substr($district, 0, 80);
    $postalCode = mb_substr($postalCode, 0, 20);
    $preferredSize = mb_substr($preferredSize, 0, 40);
    $preferredColour = mb_substr($preferredColour, 0, 80);

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=?,address=?,city=?,district=?,postal_code=?,preferred_size=?,preferred_colour=?,updates_opt_in=? WHERE user_id=?');
        $st->execute([$name, $email, $phone, $address, $city, $district, $postalCode, $preferredSize, $preferredColour, $updatesOptIn, $userId]);

        if ($isVendor) {
            $st = $pdo->prepare('INSERT INTO vendor_profiles(user_id,business_name,owner_name,phone,address) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE business_name=VALUES(business_name),owner_name=VALUES(owner_name),phone=VALUES(phone),address=VALUES(address)');
            $st->execute([$userId, $business, $name, $phone, $businessAddress]);
        }
        $pdo->commit();

        $_SESSION['user']['full_name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['phone'] = $phone;
        $_SESSION['user']['address'] = $address;
        $_SESSION['user']['city'] = $city;
        $_SESSION['user']['district'] = $district;
        $_SESSION['user']['postal_code'] = $postalCode;
        $_SESSION['user']['preferred_size'] = $preferredSize;
        $_SESSION['user']['preferred_colour'] = $preferredColour;
        $_SESSION['user']['updates_opt_in'] = $updatesOptIn;

        flash('Account settings updated.');
        header('Location: profile.php');
        exit;
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('That email is already in use. No changes were saved.');
        header('Location: profile.php');
        exit;
    }
}

// Activity figures make the settings page useful, not just editable.
if ($isVendor) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE vendor_id=?');
    $st->execute([$userId]);
    $primaryCount = (int)$st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE vendor_id=?');
    $st->execute([$userId]);
    $secondaryCount = (int)$st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM reviews r JOIN products p ON p.product_id=r.product_id WHERE p.vendor_id=?');
    $st->execute([$userId]);
    $reviewCount = (int)$st->fetchColumn();
} else {
    $st = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id=?');
    $st->execute([$userId]);
    $primaryCount = (int)$st->fetchColumn();
    $st = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id=?');
    $st->execute([$userId]);
    $orderValue = (float)$st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE customer_id=?');
    $st->execute([$userId]);
    $reviewCount = (int)$st->fetchColumn();
}

$contactPhone = (string)($account['phone'] ?? '');
if ($isVendor && $contactPhone === '') $contactPhone = (string)($profile['phone'] ?? '');
$fullAddress = array_filter([$account['address'] ?? '', $account['city'] ?? '', $account['district'] ?? '', $account['postal_code'] ?? ''], static fn($value) => trim((string)$value) !== '');
$initials = '';
foreach (preg_split('/\s+/u', trim((string)$account['full_name'])) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: 'RR';

$pageClass = 'account-settings-page';
$pageTitle = 'Account settings | Redline Royale';
$workspaceType = $isVendor ? 'vendor' : 'customer';
$workspaceActive = $isVendor ? 'Business profile' : 'Profile';
include __DIR__.'/../includes/header.php';
?>
<section class="account-hero">
  <div class="account-hero-copy">
    <p class="account-kicker">Redline Royale · Account settings</p>
    <h1><?= $isVendor ? 'Business profile' : 'Customer profile' ?></h1>
    <p>Keep your contact details, delivery defaults and style preferences current so every order feels personal.</p>
  </div>
  <div class="account-identity">
    <span class="account-monogram" aria-hidden="true"><?=e($initials)?></span>
    <div><small>Member number</small><strong>#<?=str_pad((string)$userId, 5, '0', STR_PAD_LEFT)?></strong></div>
  </div>
</section>

<div class="account-stats" aria-label="Account summary">
  <article class="account-stat"><span>Member since</span><strong><?=e(date('M Y', strtotime((string)($account['created_at'] ?? 'now'))))?></strong><small>With Redline Royale</small></article>
  <?php if ($isVendor): ?>
    <article class="account-stat"><span>Catalogue</span><strong><?=$primaryCount?></strong><small>Listed products</small></article>
    <article class="account-stat"><span>Order lines</span><strong><?=$secondaryCount?></strong><small>Sold pieces</small></article>
  <?php else: ?>
    <article class="account-stat"><span>Orders</span><strong><?=$primaryCount?></strong><small>Placed so far</small></article>
    <article class="account-stat"><span>Order value</span><strong>Rs. <?=number_format($orderValue, 0)?></strong><small>Lifetime total</small></article>
  <?php endif; ?>
  <article class="account-stat"><span>Reviews</span><strong><?=$reviewCount?></strong><small>Shared with the community</small></article>
</div>

<div class="account-layout">
  <aside class="account-side">
    <section class="account-card account-profile-card">
      <p class="account-kicker">Profile snapshot</p>
      <div class="account-avatar" aria-hidden="true"><?=e($initials)?></div>
      <h2><?=e($account['full_name'])?></h2>
      <p class="account-email"><?=e($account['email'])?></p>
      <span class="account-role"><?= $isVendor ? 'Vendor partner' : 'Customer account' ?></span>
      <dl class="account-mini-facts">
        <div><dt>Phone</dt><dd><?=e($contactPhone !== '' ? $contactPhone : 'Not set')?></dd></div>
        <div><dt>Location</dt><dd><?=e($fullAddress ? implode(', ', $fullAddress) : 'Not set')?></dd></div>
      </dl>
    </section>

    <section class="account-card account-preference-card">
      <p class="account-kicker">Saved preferences</p>
      <dl class="account-mini-facts">
        <div><dt>Preferred size</dt><dd><?=e(($account['preferred_size'] ?? '') ?: 'Not set')?></dd></div>
        <div><dt>Preferred colour</dt><dd><?=e(($account['preferred_colour'] ?? '') ?: 'Not set')?></dd></div>
        <div><dt>Store updates</dt><dd><?= !empty($account['updates_opt_in']) ? 'Subscribed' : 'Not subscribed' ?></dd></div>
      </dl>
    </section>

    <section class="account-card account-help-card">
      <p class="account-kicker">Quick links</p>
      <a href="<?= $isVendor ? 'vendor-dashboard.php' : 'customer-dashboard.php' ?>"><?= $isVendor ? 'Vendor dashboard' : 'My orders and dashboard' ?> →</a>
      <a href="order-tracking.php">Track an order →</a>
      <a href="home.php">Browse the collection →</a>
    </section>
  </aside>

  <form class="account-card account-form" method="post">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

    <section class="account-section">
      <div class="account-section-heading"><span>01</span><div><h2>Personal details</h2><p>The name and contact information used for your account.</p></div></div>
      <div class="fields">
        <div><label for="full-name"><?= $isVendor ? 'Owner name' : 'Full name' ?></label><input id="full-name" name="full_name" value="<?=e($account['full_name'])?>" maxlength="120" required></div>
        <div><label for="email">Email address</label><input id="email" name="email" type="email" value="<?=e($account['email'])?>" maxlength="190" required></div>
        <div><label for="phone">Phone number</label><input id="phone" name="phone" value="<?=e($contactPhone)?>" maxlength="30" placeholder="98XXXXXXXX" <?= $isVendor ? 'required' : '' ?>><small>Used for delivery and order updates.</small></div>
      </div>
    </section>

    <section class="account-section">
      <div class="account-section-heading"><span>02</span><div><h2>Delivery defaults</h2><p>Saved so checkout can be quicker next time.</p></div></div>
      <div class="fields">
        <div class="account-full"><label for="address">Street address</label><textarea id="address" name="address" maxlength="255" placeholder="House / flat, street, neighbourhood"><?=e($account['address'] ?? '')?></textarea></div>
        <div><label for="city">City / municipality</label><input id="city" name="city" value="<?=e($account['city'] ?? '')?>" maxlength="80" placeholder="Kathmandu"></div>
        <div><label for="district">District</label><input id="district" name="district" value="<?=e($account['district'] ?? '')?>" maxlength="80" placeholder="Kathmandu"></div>
        <div><label for="postal-code">Postal code</label><input id="postal-code" name="postal_code" value="<?=e($account['postal_code'] ?? '')?>" maxlength="20" placeholder="44600"></div>
      </div>
    </section>

    <section class="account-section">
      <div class="account-section-heading"><span>03</span><div><h2>Style & communication</h2><p>Tell us what you like and how often you want to hear from us.</p></div></div>
      <div class="fields">
        <div>
          <label for="preferred-size">Preferred size</label>
          <input id="preferred-size" name="preferred_size" list="account-sizes" value="<?=e($account['preferred_size'] ?? '')?>" maxlength="40" placeholder="M">
          <datalist id="account-sizes"><option value="XS"><option value="S"><option value="M"><option value="L"><option value="XL"><option value="XXL"><option value="Custom"></datalist>
        </div>
        <div>
          <label for="preferred-colour">Preferred colour</label>
          <input id="preferred-colour" name="preferred_colour" list="account-colours" value="<?=e($account['preferred_colour'] ?? '')?>" maxlength="80" placeholder="Olive">
          <datalist id="account-colours"><option value="Natural"><option value="Olive"><option value="Black"><option value="Ivory"><option value="Navy"><option value="Maroon"></datalist>
        </div>
      </div>
      <label class="account-check"><input type="checkbox" name="updates_opt_in" value="1" <?= !empty($account['updates_opt_in']) ? 'checked' : '' ?>><span>Send me occasional collection updates and offers.</span></label>
    </section>

    <?php if ($isVendor): ?>
    <section class="account-section">
      <div class="account-section-heading"><span>04</span><div><h2>Business details</h2><p>Shown to customers beside your products.</p></div></div>
      <div class="fields">
        <div><label for="business-name">Business name</label><input id="business-name" name="business_name" value="<?=e($profile['business_name'] ?? '')?>" maxlength="160" required></div>
        <div class="account-full"><label for="business-address">Business address</label><textarea id="business-address" name="business_address" maxlength="500" required><?=e($profile['business_address'] ?? '')?></textarea></div>
      </div>
    </section>
    <?php endif; ?>

    <p class="account-actions"><button type="submit">Save account settings</button><a class="button button-secondary" href="<?= $isVendor ? 'vendor-dashboard.php' : 'customer-dashboard.php' ?>">Back to dashboard</a></p>
  </form>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
