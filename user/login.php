<?php
/**
 * login.php — customer sign-in (email + password).
 * On success regenerates the session and stores the customer session keys.
 */
require_once __DIR__ . '/../init.php';
security_headers();

// Already signed in? Skip the form.
if (!empty($_SESSION['user_id'])) {
    redirect('/user/products.php');
}

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) post('email', ''));
    $pw    = (string) post('password', '');

    // Rate limit: 5 attempts per 15 minutes per email (fallback to IP).
    $rlKey = 'login_' . ($email !== '' ? $email : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if (!rate_limit($rlKey, 5, 900)) {
        flash('Too many login attempts. Try again in 15 minutes.', 'danger');
        redirect('/user/login.php');
    }

    $errors = [];
    if ($email === '' || $pw === '') {
        $errors[] = 'Please enter your email and password.';
    }

    $user   = null;
    $userId = null;
    if (!$errors) {
        $existing = db_find_by_email('/user', $email);
        if (!$existing) {
            $errors[] = 'Invalid email or password.';
        } else {
            $userId = (string) array_key_first($existing);
            $user   = reset($existing);
            if (!password_verify($pw, (string) ($user['password_hash'] ?? ''))) {
                $errors[] = 'Invalid email or password.';
            }
        }
    }

    if (!$errors && $user && empty($user['email_verified'])) {
        flash('Please verify your email before signing in.', 'warn');
        redirect('/user/verify_otp.php?email=' . urlencode($email));
    }

    if (!$errors && $user) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['name'] ?? '';
        $_SESSION['user_image'] = $user['profile_image'] ?? '';
        flash('Welcome back, ' . ($_SESSION['user_name'] ?: 'guest') . '.', 'ok');
        redirect('/user/products.php');
    }

    foreach ($errors as $err) {
        flash($err, 'danger');
    }
}

$flashes = get_flashes();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in &middot; <?= e(BRAND_NAME) ?></title>
  <meta name="description" content="<?= e(BRAND_NAME) ?> — <?= e(BRAND_TAGLINE) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <script>(function(){try{var t=localStorage.getItem('ss-theme');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&m)){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}})();</script>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .theme-toggle--floating { position: fixed; top: 16px; right: 16px; z-index: 100; width: 42px; height: 42px; }
    @media (min-width: 901px) { .theme-toggle--floating { top: 20px; right: 24px; } }
    .auth__aside { background-image: linear-gradient(160deg, rgba(42,33,24,.88), rgba(24,18,16,.95)), url('/assets/img/login-bg.png'); background-size: cover; background-position: center; }
  </style>
  <link rel="icon" href="/assets/img/logo.png">
</head>
<body>
<button class="theme-toggle theme-toggle--floating" type="button" aria-label="Toggle dark mode" aria-pressed="false" data-theme-toggle title="Toggle theme">
  <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
  <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>
<div class="auth">
  <aside class="auth__aside">
    <a class="brand" href="/user/login.php">
      <span class="brand__mark"><img src="/assets/img/logo.png" alt="CRATES N' PLATES" class="brand__logo"></span>
      <span>
        <span class="brand__name"><?= e(BRAND_NAME) ?></span><br>
        <span class="brand__tag"><?= e(BRAND_TAGLINE) ?></span>
      </span>
    </a>

    <div>
      <h1 style="font-size:clamp(1.6rem,1.1rem+1.8vw,2.2rem);max-width:18ch;"><?= e(get_settings()['hero_title']) ?></h1>
      <p style="color:#cdbfa6;font-size:15px;max-width:36ch;"><?= e(get_settings()['hero_subtitle']) ?></p>
    </div>

    <div style="display:flex;flex-direction:column;gap:6px;color:#9c8f7b;font-size:13px;line-height:1.7;">
      <div style="display:flex;align-items:center;gap:8px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c8a45c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <?= e(get_settings()['address']) ?>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c8a45c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <?= e(get_settings()['hours']) ?>
      </div>
      <a href="/user/about.php" style="color:#c8a45c;font-weight:600;margin-top:6px;display:inline-flex;align-items:center;gap:6px;">About us <span aria-hidden>&rarr;</span></a>
    </div>

    <small style="color:#9c8f7b;">&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?>. All rights reserved.</small>
  </aside>

  <main class="auth__main">
    <div class="auth__card card card--pad-lg">
      <h2>Sign in</h2>
      <p class="muted mt-0" style="margin-top:2px;">Welcome back to <?= e(BRAND_NAME) ?>.</p>

      <?php foreach ($flashes as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>" role="status">
          <span><?= e($f['message']) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="post" action="/user/login.php" autocomplete="on" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid mt-4">
          <div class="field">
            <label for="email">Email address</label>
            <input class="input" id="email" name="email" type="email" autocomplete="email"
                   placeholder="you@example.com" value="<?= e($email) ?>" required>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input class="input" id="password" name="password" type="password" autocomplete="current-password"
                     placeholder="Your password" required>
            </div>
            <div style="text-align:right;margin-top:6px;">
              <a href="/user/forgot_password.php" style="font-size:13px;color:var(--gold);">Forgot password?</a>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn--gold btn--lg btn--block" type="submit">Sign in</button>
          </div>
        </div>
      </form>

      <p class="auth__switch">
        New here? <a href="/user/signup.php">Create an account</a>
      </p>
    </div>
  </main>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
