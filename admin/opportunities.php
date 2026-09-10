<?php

declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit('Kirish taqiqlangan');
}

require __DIR__ . '/../src/Database.php';

$db = new Database($config)->pdo();

$rows = $db->query("
    SELECT
        o.id,
        o.title,
        o.region,
        o.organizer,
        o.deadline,
        o.is_active,
        c.name AS category_name,
        c.emoji
    FROM opportunities o
    JOIN categories c ON c.id = o.category_id
    ORDER BY o.created_at DESC
")->fetchAll();

?>
<!doctype html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YoshlarHub — Imkoniyatlar</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 15px;
            background: #f5f7fb;
        }

        h1 {
            margin-bottom: 20px;
        }

        .top {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        a {
            text-decoration: none;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            background: #111827;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f1f5f9;
        }

        .active {
            color: green;
        }

        .inactive {
            color: red;
        }
    </style>
</head>

<body>

<h1>📋 Imkoniyatlar</h1>

<div class="top">
    <a class="btn" href="index.php">← Dashboard</a>
    <a class="btn" href="add.php">➕ Qo'shish</a>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nomi</th>
            <th>Kategoriya</th>
            <th>Hudud</th>
            <th>Deadline</th>
            <th>Status</th>
            <th>Amal</th>
        </tr>
    </thead>

    <tbody>

    <?php foreach ($rows as $row): ?>

        <tr>
            <td><?= (int) $row['id'] ?></td>

            <td>
                <?= htmlspecialchars($row['title']) ?>
            </td>

            <td>
                <?= htmlspecialchars($row['emoji'] . ' ' . $row['category_name']) ?>
            </td>

            <td>
                <?= htmlspecialchars($row['region'] ?: 'O\'zbekiston') ?>
            </td>

            <td>
                <?= $row['deadline']
                    ? htmlspecialchars($row['deadline'])
                    : 'Cheklanmagan'
                ?>
            </td>

            <td class="<?= $row['is_active'] ? 'active' : 'inactive' ?>">
                <?= $row['is_active'] ? 'Faol' : 'Nofaol' ?>
            </td>

            <td>
                <a href="edit.php?id=<?= (int) $row['id'] ?>">✏️</a>
                |
                <a
                    href="delete.php?id=<?= (int) $row['id'] ?>"
                    onclick="return confirm('O\'chirishni tasdiqlaysizmi?')"
                >🗑️</a>
            </td>
        </tr>

    <?php endforeach; ?>

    </tbody>
</table>

</body>
</html>
