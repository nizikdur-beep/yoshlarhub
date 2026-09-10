<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Telegram.php';

$db = new Database($config)->pdo();
$telegram = new Telegram($config['bot_token']);

$stmt = $db->query("
    SELECT
        o.id,
        o.title,
        o.deadline,
        u.id AS user_id
    FROM opportunities o
    JOIN bookmarks b
        ON b.opportunity_id = o.id
    JOIN users u
        ON u.id = b.user_id
    WHERE
        o.is_active = 1
        AND u.notifications_enabled = 1
        AND o.deadline IS NOT NULL
        AND o.deadline BETWEEN
            NOW()
            AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
");

$rows = $stmt->fetchAll();

foreach ($rows as $row) {

    $text =
        "⏰ <b>Deadline yaqin!</b>\n\n" .
        "📌 " . htmlspecialchars($row['title']) . "\n" .
        "🗓 " . htmlspecialchars($row['deadline']) . "\n\n" .
        "Imkoniyatni o'tkazib yubormang!";

    try {
        $telegram->sendMessage(
            (int) $row['user_id'],
            $text
        );
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}
