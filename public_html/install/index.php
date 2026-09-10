<?php
/**
 * Установщик. Открывается один раз при первом запуске сайта.
 * После успешной установки папку /install нужно удалить с сервера.
 */
declare(strict_types=1);
session_start();

$root       = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$installed  = is_file($configFile);

$step   = (int)($_GET['step'] ?? ($installed ? 2 : 1));
$errors = [];
$done   = false;
$locked = false;

// ---------------------------------------------------------------------
// Защита: если установка уже выполнена и учётная запись с правами создана,
// установщик больше ничего не делает. Иначе забытая на сервере папка
// /install позволила бы кому угодно создать себе доступ разработчика.
// ---------------------------------------------------------------------
if ($installed) {
    try {
        $cfg = require $configFile;
        $d   = $cfg['db'];
        $probe = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $has = $probe->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','dev')")->fetchColumn();
        if ((int)$has > 0) {
            $locked = true;
        }
    } catch (Throwable $e) {
        // Таблиц ещё нет — установка не завершена, продолжаем.
    }
}

// ---------------------------------------------------------------------
// Шаг 1 — подключение к базе и создание таблиц
// ---------------------------------------------------------------------
if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '1') {
    $host = trim((string)($_POST['db_host'] ?? 'localhost'));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');
    $base = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');

    if ($name === '' || $user === '') {
        $errors[] = 'Заполните имя базы и пользователя.';
    }

    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Импорт структуры
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('Не найден файл schema.sql');
            }
            $pdo->exec($sql);

            // Запись конфигурации
            $devKey = bin2hex(random_bytes(16));
            $cfg = "<?php\n\nreturn [\n"
                 . "    'db' => [\n"
                 . "        'host'     => " . var_export($host, true) . ",\n"
                 . "        'name'     => " . var_export($name, true) . ",\n"
                 . "        'user'     => " . var_export($user, true) . ",\n"
                 . "        'password' => " . var_export($pass, true) . ",\n"
                 . "        'charset'  => 'utf8mb4',\n"
                 . "    ],\n"
                 . "    'base_url' => " . var_export($base, true) . ",\n"
                 . "    'timezone' => 'Europe/Moscow',\n"
                 . "    'debug'    => false,\n"
                 . "    'dev_key'  => " . var_export($devKey, true) . ",\n"
                 . "];\n";

            if (!is_dir($root . '/config')) {
                mkdir($root . '/config', 0755, true);
            }
            if (file_put_contents($configFile, $cfg) === false) {
                throw new RuntimeException('Не удалось записать config/config.php — проверьте права на папку config.');
            }

            header('Location: index.php?step=2');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Ошибка: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------
// Шаг 2 — создание учётной записи разработчика
// ---------------------------------------------------------------------
if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '2') {
    if (!$installed) {
        $errors[] = 'Сначала выполните первый шаг.';
    } else {
        $config = require $configFile;
        $d = $config['db'];
        try {
            $pdo = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $last  = trim((string)($_POST['last_name'] ?? ''));
            $first = trim((string)($_POST['first_name'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            $pass2 = (string)($_POST['password2'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Укажите корректную почту.';
            if ($last === '' || $first === '')               $errors[] = 'Укажите фамилию и имя.';
            if (mb_strlen($pass) < 8)                        $errors[] = 'Пароль должен быть не короче 8 символов.';
            if ($pass !== $pass2)                            $errors[] = 'Пароли не совпадают.';

            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $errors[] = 'Пользователь с такой почтой уже существует.';
            }

            if (!$errors) {
                $st = $pdo->prepare(
                    "INSERT INTO users (email, password_hash, last_name, first_name, role, status, approved_at)
                     VALUES (?,?,?,?,'dev','approved',NOW())"
                );
                $st->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $last, $first]);
                $done = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Ошибка: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Установка сайта — Молодая Гвардия Щёлково</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Inter','Segoe UI',system-ui,sans-serif;background:#F4F7FD;color:#0C1A33;line-height:1.6;padding:40px 20px;}
  .wrap{max-width:620px;margin:0 auto;}
  .card{background:#fff;border:1px solid #E2E9F5;border-radius:20px;padding:34px;margin-bottom:20px;}
  h1{color:#0A1E45;font-size:1.5rem;margin-bottom:8px;}
  h2{color:#0A1E45;font-size:1.15rem;margin-bottom:6px;}
  p.lead{color:#647089;font-size:.94rem;margin-bottom:24px;}
  .steps{display:flex;gap:10px;margin-bottom:24px;}
  .steps div{flex:1;padding:10px;text-align:center;border-radius:999px;font-size:.85rem;font-weight:600;background:#EAF2FC;color:#134494;}
  .steps div.is-active{background:#2953FF;color:#fff;}
  .steps div.is-done{background:#E4F7EF;color:#0C5636;}
  label{display:block;font-weight:600;font-size:.86rem;margin-bottom:6px;}
  input{width:100%;padding:11px 13px;border:1.5px solid #E2E9F5;border-radius:10px;font:inherit;font-size:.94rem;margin-bottom:16px;}
  input:focus{border-color:#2953FF;outline:none;}
  .hint{font-size:.79rem;color:#647089;margin:-12px 0 16px;}
  button{background:#2953FF;color:#fff;border:none;padding:13px 26px;border-radius:999px;font:inherit;font-weight:700;cursor:pointer;width:100%;}
  button:hover{background:#173CDB;}
  .alert{padding:13px 16px;border-radius:10px;margin-bottom:18px;font-size:.9rem;border-left:4px solid;}
  .alert-error{background:#E9ECFB;border-color:#2A3AA6;color:#212F8C;}
  .alert-success{background:#E4F7EF;border-color:#0E8F63;color:#0C5636;}
  .alert-warn{background:#FDF4E3;border-color:#B8781A;color:#7A4E08;}
  .alert ul{margin:6px 0 0 18px;}
  a.btn{display:block;text-align:center;background:#2953FF;color:#fff;padding:13px;border-radius:999px;text-decoration:none;font-weight:700;}
  code{background:#EAF2FC;padding:2px 7px;border-radius:4px;font-size:.86rem;}
</style>
</head>
<body>
<div class="wrap">

  <div class="steps">
    <div class="<?= $step === 1 ? 'is-active' : ($installed ? 'is-done' : '') ?>">1. База данных</div>
    <div class="<?= $step === 2 && !$done ? 'is-active' : ($done ? 'is-done' : '') ?>">2. Администратор</div>
    <div class="<?= $done ? 'is-active' : '' ?>">3. Готово</div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= htmlspecialchars($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php if ($locked): ?>

    <div class="card">
      <h1>Установка уже выполнена</h1>
      <div class="alert alert-warn">
        Сайт настроен, учётная запись с правами администратора существует.
        Повторный запуск установщика заблокирован.
      </div>
      <div class="alert alert-error">
        <b>Удалите папку <code>/install</code> с сервера.</b>
        Пока она на месте, установщик доступен всем посетителям.
      </div>
      <a class="btn" href="../login.php">Перейти ко входу</a>
    </div>

  <?php elseif ($done): ?>

    <div class="card">
      <h1>Установка завершена</h1>
      <div class="alert alert-success">Сайт готов к работе. Учётная запись разработчика создана.</div>
      <div class="alert alert-warn">
        <b>Важно:</b> удалите папку <code>/install</code> с сервера — иначе установщик сможет открыть кто угодно.
      </div>
      <a class="btn" href="../login.php">Перейти ко входу</a>
    </div>

  <?php elseif ($step === 1): ?>

    <div class="card">
      <h1>Установка сайта</h1>
      <p class="lead">Шаг 1: подключение к базе данных. Данные возьмите в панели управления хостингом — раздел «Базы данных».</p>
      <form method="post">
        <input type="hidden" name="step" value="1">
        <label for="db_host">Сервер базы данных</label>
        <input type="text" id="db_host" name="db_host" value="localhost" required>
        <label for="db_name">Имя базы данных</label>
        <input type="text" id="db_name" name="db_name" required>
        <label for="db_user">Пользователь базы</label>
        <input type="text" id="db_user" name="db_user" required>
        <label for="db_pass">Пароль базы</label>
        <input type="password" id="db_pass" name="db_pass">
        <label for="base_url">Адрес сайта внутри домена</label>
        <input type="text" id="base_url" name="base_url" placeholder="оставьте пустым, если сайт в корне">
        <div class="hint">Заполняйте, только если сайт лежит в подпапке — например <code>/mger</code>.</div>
        <button type="submit">Создать таблицы и продолжить</button>
      </form>
    </div>

  <?php else: ?>

    <div class="card">
      <h1>Учётная запись разработчика</h1>
      <p class="lead">Шаг 2: создайте главную учётную запись. У неё будет доступ ко всем панелям, включая панель разработчика.</p>
      <form method="post">
        <input type="hidden" name="step" value="2">
        <label for="last_name">Фамилия</label>
        <input type="text" id="last_name" name="last_name" required>
        <label for="first_name">Имя</label>
        <input type="text" id="first_name" name="first_name" required>
        <label for="email">Электронная почта</label>
        <input type="email" id="email" name="email" required>
        <label for="password">Пароль</label>
        <input type="password" id="password" name="password" required minlength="8">
        <div class="hint">Не короче 8 символов.</div>
        <label for="password2">Повторите пароль</label>
        <input type="password" id="password2" name="password2" required minlength="8">
        <button type="submit">Создать и завершить установку</button>
      </form>
    </div>

  <?php endif; ?>

</div>
</body>
</html>
