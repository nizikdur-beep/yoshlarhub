<?php

declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../config/config.php';

if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $telegramId = trim($_POST['telegram_id'] ?? '');

    if (
        $telegramId !== '' &&
        ctype_digit($telegramId) &&
        (int) $telegramId === $config['admin_id']
    ) {
        session_regenerate_id(true);

        $_SESSION['admin'] = true;
        $_SESSION['admin_id'] = (int) $telegramId;

        header('Location: index.php');
        exit;
    }

    $error = 'Telegram ID noto\'g\'ri.';
}

?>
<!doctype html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>YoshlarHub Admin</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .box {
            width: 340px;
            background: white;
            padding: 30px;
            border-radius: 14px;
        }

        input, button {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-top: 10px;
        }

        button {
            cursor: pointer;
        }

        .error {
            color: #dc2626;
        }
    </style>
</head>

<body>

<div class="box">

    <h2>🔐 YoshlarHub Admin</h2>

    <?php if ($error): ?>
        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="post">

        <label>Telegram ID</label>

        <input
            type="number"
            name="telegram_id"
            placeholder="123456789"
            required
        >

        <button type="submit">
            Kirish
        </button>

    </form>

</div>

</body>
</html>
