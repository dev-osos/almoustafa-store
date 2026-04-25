<?php
declare(strict_types=1);

session_start();

// Already logged in → redirect to dashboard
if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../apis/bootstrap.php';

// ── Auto-create dashboard_users table + seed default super admin ──────────────
$pdo     = null;
$dbReady = false;
try {
    require_once __DIR__ . '/../../apis/db.php';
    $pdo = api_pdo();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dashboard_users (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username      VARCHAR(50)  NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role          ENUM('super_admin','admin','support') NOT NULL DEFAULT 'support',
            is_active     TINYINT(1)   NOT NULL DEFAULT 1,
            created_by    INT UNSIGNED DEFAULT NULL,
            created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_du_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ((int) $pdo->query("SELECT COUNT(*) FROM dashboard_users")->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO dashboard_users (username, password_hash, role) VALUES (?, ?, 'super_admin')")
            ->execute(['1', password_hash('1', PASSWORD_BCRYPT, ['cost' => 12])]);
    }
    $dbReady = true;
} catch (Throwable) {}

// ── Handle login POST ─────────────────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbReady && $pdo) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']       ?? '';
    try {
        $stmt = $pdo->prepare("
            SELECT id, password_hash, role
            FROM   dashboard_users
            WHERE  username = :u AND is_active = 1
            LIMIT  1
        ");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_auth']     = true;
            $_SESSION['admin_role']     = $user['role'];
            $_SESSION['admin_id']       = (int) $user['id'];
            $_SESSION['admin_username'] = $username;
            header('Location: index.php');
            exit;
        }
    } catch (Throwable) {}
    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#3c0004">
<title>تسجيل الدخول — لوحة تحكم المصطفى</title>
<link rel="manifest" href="manifest.json">
<link rel="icon" href="cp-logo.jpg" type="image/jpeg">
<link rel="apple-touch-icon" href="cp-logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --primary:       #3c0004;
  --primary-light: #5e0007;
  --gold:          #735c00;
  --border:        #e5ddd0;
  --surface:       #ffffff;
  --on-surface-dim:#5a4e3a;
  --red-bg:        #fdecea;
  --red-text:      #b71c1c;
  --shadow-lg:     0 8px 32px rgba(60,0,4,.18);
  --radius:        14px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; }
body {
  font-family: 'Manrope', 'Amiri', sans-serif;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--primary) 0%, #1a0002 60%, #2d1000 100%);
  padding: 1.5rem;
}

.card {
  background: var(--surface);
  border-radius: var(--radius);
  width: 100%;
  max-width: 400px;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.card-header {
  background: var(--primary);
  padding: 2rem 2rem 1.75rem;
  text-align: center;
}
.logo {
  font-family: 'Amiri', serif;
  font-size: 2.2rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.1;
}
.logo-sub {
  color: rgba(255,255,255,.55);
  font-size: .8rem;
  margin-top: .35rem;
  letter-spacing: .03em;
}
.logo-divider {
  width: 40px; height: 2px;
  background: var(--gold);
  margin: .9rem auto 0;
  border-radius: 2px;
  opacity: .7;
}

.card-body { padding: 2rem; }

.alert-error {
  display: flex;
  align-items: center;
  gap: .5rem;
  background: var(--red-bg);
  color: var(--red-text);
  border-radius: 10px;
  padding: .75rem 1rem;
  font-size: .85rem;
  font-weight: 600;
  margin-bottom: 1.25rem;
}
.alert-error::before { content: '✕'; font-weight: 900; font-size: .9rem; }

.alert-warn {
  background: #fff8e1;
  color: #795500;
  border-radius: 10px;
  padding: .7rem 1rem;
  font-size: .82rem;
  margin-bottom: 1.25rem;
  text-align: center;
}

.field { margin-bottom: 1.1rem; }
.field-label {
  display: block;
  font-size: .78rem;
  font-weight: 700;
  color: var(--on-surface-dim);
  margin-bottom: .45rem;
}
.field-wrap {
  position: relative;
}
.field-input {
  width: 100%;
  padding: .75rem 1rem;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: .95rem;
  font-family: inherit;
  color: #1a1006;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  direction: rtl;
  text-align: right;
  background: #faf8f5;
}
.field-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(60,0,4,.08);
  background: #fff;
}

.submit-btn {
  width: 100%;
  margin-top: .5rem;
  padding: .9rem;
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: background .2s, transform .1s;
  letter-spacing: .02em;
}
.submit-btn:hover:not(:disabled) { background: var(--primary-light); }
.submit-btn:active:not(:disabled) { transform: scale(.99); }
.submit-btn:disabled { opacity: .55; cursor: not-allowed; }

.card-footer {
  border-top: 1px solid var(--border);
  padding: .9rem 2rem;
  text-align: center;
  font-size: .75rem;
  color: var(--on-surface-dim);
}
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <div class="logo">المصطفى</div>
    <div class="logo-sub">لوحة التحكم</div>
    <div class="logo-divider"></div>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$dbReady): ?>
      <div class="alert-warn">⚠️ تعذّر الاتصال بقاعدة البيانات</div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <div class="field">
        <label class="field-label" for="uname">اسم المستخدم</label>
        <div class="field-wrap">
          <input
            class="field-input"
            type="text"
            id="uname"
            name="username"
            placeholder="أدخل اسم المستخدم"
            autofocus
            required
            autocomplete="username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          >
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="pwd">كلمة المرور</label>
        <div class="field-wrap">
          <input
            class="field-input"
            type="password"
            id="pwd"
            name="password"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          >
        </div>
      </div>

      <button class="submit-btn" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>
        دخول إلى لوحة التحكم
      </button>
    </form>
  </div>

  <div class="card-footer">
    المصطفى للعسل &mdash; لوحة الإدارة الداخلية
  </div>
</div>

</body>
</html>
