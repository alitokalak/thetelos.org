<?php
session_start();
require_once __DIR__ . '/config.php';

// ── Giriş kontrolü ────────────────────────────────────────
if (isset($_POST['panel_pw'])) {
    if ($_POST['panel_pw'] === PANEL_PASSWORD) {
        $_SESSION['tls_auth'] = true;
    } else {
        $error = 'Şifre yanlış.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!empty($_SESSION['tls_auth'])) {
    header('Location: panel.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Thetelos Content Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f0f0f;font-family:'DM Sans',sans-serif}
.login-box{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:48px 40px;width:380px;text-align:center}
.login-box h1{font-family:'DM Serif Display',serif;color:#d4b483;font-size:26px;margin-bottom:6px}
.login-box p{color:#666;font-size:13px;margin-bottom:32px}
.login-box input[type=password]{width:100%;padding:12px 16px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:14px;outline:none;transition:.2s}
.login-box input[type=password]:focus{border-color:#d4b483}
.login-box button{width:100%;margin-top:14px;padding:13px;background:#d4b483;color:#0f0f0f;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:.2s}
.login-box button:hover{background:#c9a96e}
.error{color:#e05050;font-size:13px;margin-top:12px}
</style>
</head>
<body>
<div class="login-box">
    <h1>Thetelos</h1>
    <p>Content Panel · Giriş</p>
    <form method="POST">
        <input type="password" name="panel_pw" placeholder="Panel şifresi" autofocus>
        <button type="submit">Giriş Yap</button>
    </form>
    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</div>
</body>
</html>
