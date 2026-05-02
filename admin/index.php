<?php
session_start();

// ─── ПАРОЛЬ АДМИНА — поменяй здесь ───────────────────────
define('ADMIN_PASSWORD', 'cheesmart2024');
// ──────────────────────────────────────────────────────────

define('SETTINGS_FILE', __DIR__ . '/../data/settings.json');

function loadSettings(): array {
    if (!file_exists(SETTINGS_FILE)) return [];
    return json_decode(file_get_contents(SETTINGS_FILE), true) ?? [];
}

function saveSettings(array $data): bool {
    $dir = dirname(SETTINGS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return file_put_contents(SETTINGS_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

$error = '';
$success = '';

// Логаут
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// Логин
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: /admin/');
        exit;
    } else {
        $error = 'Неверный пароль';
    }
}

// Сохранение настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && isset($_SESSION['admin'])) {
    $settings = [
        'ms_token'           => trim($_POST['ms_token'] ?? ''),
        'ms_pricelist_id'    => trim($_POST['ms_pricelist_id'] ?? ''),
        'ms_organization_id' => trim($_POST['ms_organization_id'] ?? ''),
        'notify_email'       => trim($_POST['notify_email'] ?? ''),
        'allowed_origin'     => trim($_POST['allowed_origin'] ?? ''),
    ];
    if (saveSettings($settings)) {
        $success = 'Настройки сохранены!';
    } else {
        $error = 'Не удалось сохранить файл. Проверьте права на папку data/';
    }
}

$isLoggedIn = isset($_SESSION['admin']);
$settings = $isLoggedIn ? loadSettings() : [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Настройки прайс-листа — Cheesmart</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --cream: #F7F2E8;
    --warm:  #EDE4D0;
    --sand:  #D4C4A0;
    --gold:  #B8975A;
    --dark:  #2C2416;
    --muted: #7A6E5C;
    --red:   #C0392B;
    --green: #2E7D52;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Jost', sans-serif;
    background: var(--cream);
    color: var(--dark);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
  }

  .card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 40px rgba(0,0,0,0.08);
    width: 100%;
    max-width: 520px;
    overflow: hidden;
  }

  .card-header {
    background: var(--dark);
    color: var(--cream);
    padding: 24px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .card-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    font-weight: 600;
  }
  .card-header .logo-small {
    color: var(--gold);
    font-size: 0.8rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }
  .logout-link {
    color: var(--sand);
    font-size: 0.78rem;
    text-decoration: none;
    letter-spacing: 0.05em;
    border: 1px solid var(--muted);
    padding: 4px 10px;
    border-radius: 4px;
    transition: color 0.15s, border-color 0.15s;
  }
  .logout-link:hover { color: #fff; border-color: #fff; }

  .card-body { padding: 32px; }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 20px;
  }
  .form-group:last-of-type { margin-bottom: 0; }

  label {
    font-size: 0.72rem;
    color: var(--muted);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 500;
  }
  .label-hint {
    font-size: 0.72rem;
    color: var(--sand);
    margin-top: 2px;
    font-style: italic;
    text-transform: none;
    letter-spacing: 0;
  }

  input[type="text"],
  input[type="email"],
  input[type="password"],
  input[type="url"] {
    border: 1.5px solid var(--sand);
    border-radius: 6px;
    padding: 10px 14px;
    font-family: 'Jost', sans-serif;
    font-size: 0.9rem;
    color: var(--dark);
    background: var(--cream);
    outline: none;
    transition: border-color 0.2s;
    width: 100%;
  }
  input:focus { border-color: var(--gold); background: #fff; }

  .token-wrap { position: relative; }
  .token-wrap input { padding-right: 44px; font-family: monospace; font-size: 0.8rem; }
  .toggle-eye {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--muted);
    font-size: 1rem;
    padding: 4px;
  }
  .toggle-eye:hover { color: var(--dark); }

  .divider {
    border: none;
    border-top: 1.5px solid var(--warm);
    margin: 24px 0;
  }
  .section-title {
    font-size: 0.72rem;
    color: var(--muted);
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 16px;
    font-weight: 500;
  }

  .btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 6px;
    font-family: 'Jost', sans-serif;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    letter-spacing: 0.04em;
    transition: background 0.2s, transform 0.1s;
    margin-top: 24px;
  }
  .btn-primary { background: var(--dark); color: var(--cream); }
  .btn-primary:hover { background: #3d3120; transform: translateY(-1px); }
  .btn-gold { background: var(--gold); color: var(--dark); }
  .btn-gold:hover { background: #c9a86a; }

  .msg {
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 0.85rem;
    margin-bottom: 20px;
    text-align: center;
  }
  .msg.error  { background: #fdf0ee; color: var(--red); }
  .msg.success { background: #e8f5ee; color: var(--green); }

  .pricelist-link {
    display: block;
    text-align: center;
    margin-top: 16px;
    font-size: 0.8rem;
    color: var(--muted);
    text-decoration: none;
  }
  .pricelist-link:hover { color: var(--gold); }

  /* LOGIN */
  .login-wrap {
    text-align: center;
    padding: 8px 0 8px;
  }
  .login-wrap p {
    font-size: 0.85rem;
    color: var(--muted);
    margin-bottom: 24px;
    line-height: 1.6;
  }
  .lock-icon { font-size: 2.5rem; margin-bottom: 12px; }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <div>
      <div class="logo-small">Cheesmart</div>
      <h1>Настройки прайс-листа</h1>
    </div>
    <?php if ($isLoggedIn): ?>
      <a href="?logout" class="logout-link">Выйти</a>
    <?php endif; ?>
  </div>

  <div class="card-body">

    <?php if ($error): ?>
      <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="msg success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$isLoggedIn): ?>
    <!-- ─── ФОРМА ВХОДА ─── -->
    <div class="login-wrap">
      <div class="lock-icon">🔐</div>
      <p>Введите пароль администратора<br>для доступа к настройкам</p>
    </div>
    <form method="POST">
      <div class="form-group">
        <label>Пароль</label>
        <input type="password" name="password" autofocus placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary">Войти</button>
    </form>

    <?php else: ?>
    <!-- ─── ФОРМА НАСТРОЕК ─── -->
    <form method="POST">
      <input type="hidden" name="save" value="1">

      <div class="section-title">МойСклад — API</div>

      <div class="form-group">
        <label>Токен API</label>
        <div class="token-wrap">
          <input type="password" name="ms_token" id="msToken"
            value="<?= htmlspecialchars($settings['ms_token'] ?? '') ?>"
            placeholder="Настройки → Безопасность → Токен">
          <button type="button" class="toggle-eye" onclick="toggleToken()">👁</button>
        </div>
        <span class="label-hint">МойСклад → Настройки → Безопасность → Токен</span>
      </div>

      <div class="form-group">
        <label>ID Прайс-листа</label>
        <input type="text" name="ms_pricelist_id"
          value="<?= htmlspecialchars($settings['ms_pricelist_id'] ?? '') ?>"
          placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        <span class="label-hint">Продажи → Прайс-листы → открыть нужный → скопировать ID из URL</span>
      </div>

      <hr class="divider">
      <div class="section-title">МойСклад — Организация</div>

      <div class="form-group">
        <label>ID Организации</label>
        <input type="text" name="ms_organization_id"
          value="<?= htmlspecialchars($settings['ms_organization_id'] ?? '') ?>"
          placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        <span class="label-hint">Настройки → Организации → открыть → ID из URL</span>
      </div>

      <hr class="divider">
      <div class="section-title">Дополнительно</div>

      <div class="form-group">
        <label>Email для уведомлений о заказах</label>
        <input type="email" name="notify_email"
          value="<?= htmlspecialchars($settings['notify_email'] ?? '') ?>"
          placeholder="info@cheesmart.ru">
        <span class="label-hint">Необязательно — дублирует заказы на почту</span>
      </div>

      <div class="form-group">
        <label>Домен прайс-листа (CORS)</label>
        <input type="text" name="allowed_origin"
          value="<?= htmlspecialchars($settings['allowed_origin'] ?? 'https://price.cheesmart.ru') ?>"
          placeholder="https://price.cheesmart.ru">
      </div>

      <button type="submit" class="btn btn-gold">💾 Сохранить настройки</button>
    </form>

    <a href="/" class="pricelist-link">← Открыть прайс-лист</a>
    <?php endif; ?>

  </div>
</div>

<script>
function toggleToken() {
  const input = document.getElementById('msToken');
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
