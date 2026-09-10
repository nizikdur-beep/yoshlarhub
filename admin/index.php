<?php

declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/../src/Database.php';

$db = new Database($config)->pdo();

$users = (int) $db
    ->query("SELECT COUNT(*) FROM users")
    ->fetchColumn();

$opportunities = (int) $db
    ->query("SELECT COUNT(*) FROM opportunities")
    ->fetchColumn();

$active = (int) $db
    ->query("
        SELECT COUNT(*)
        FROM opportunities
        WHERE is_active = 1
    ")
    ->fetchColumn();

$bookmarks = (int) $db
    ->query("SELECT COUNT(*) FROM bookmarks")
    ->fetchColumn();

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
            max-width: 1000px;
            margin: 30px auto;
            padding: 15px;
            background: #f5f7fb;
        }

        .cards {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
        }

        .number {
            font-size: 32px;
            font-weight: bold;
            margin-top: 10px;
        }

        .menu {
            margin-top: 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        a {
            background: #111827;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
        }

        .logout {
            background: #dc2626;
        }
    </style>
</head>

<body>

<h1>🚀 YoshlarHub Admin</h1>

<div class="cards">

    <div class="card">
        👥 Foydalanuvchilar
        <div class="number"><?= $users ?></div>
    </div>

    <div class="card">
        📋 Imkoniyatlar
        <div class="number"><?= $opportunities ?></div>
    </div>

    <div class="card">
        ✅ Faol
        <div class="number"><?= $active ?></div>
    </div>

    <div class="card">
        ⭐ Saqlashlar
        <div class="number"><?= $bookmarks ?></div>
    </div>

</div>

<div class="menu">

    <a href="opportunities.php">
        📋 Imkoniyatlar
    </a>

    <a href="add.php">
        ➕ Yangi imkoniyat
    </a>

    <a class="logout" href="logout.php">
        🚪 Chiqish
    </a>

</div>

</body>
</html>
