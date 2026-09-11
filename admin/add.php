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

$categories = $db
    ->query("
        SELECT id, name, emoji
        FROM categories
        ORDER BY id
    ")
    ->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $region = trim($_POST['region'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');

    if (
        $title === '' ||
        $description === '' ||
        $categoryId <= 0
    ) {
        exit('Majburiy maydonlarni to\'ldiring.');
    }

    $stmt = $db->prepare("
        INSERT INTO opportunities
        (
            title,
            description,
            category_id,
            region,
            organizer,
            url,
            image_url,
            deadline,
            is_active
        )
        VALUES
        (
            :title,
            :description,
            :category_id,
            :region,
            :organizer,
            :url,
            :image_url,
            :deadline,
            1
        )
    ");

    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':category_id' => $categoryId,
        ':region' => $region ?: null,
        ':organizer' => $organizer ?: null,
        ':url' => $url ?: null,
        ':image_url' => $imageUrl ?: null,
        ':deadline' => $deadline ?: null,
    ]);

    header('Location: opportunities.php');

    exit;
}

?>

<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Imkoniyat qo'shish</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            margin: 0;
        }

        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 25px;
            background: white;
            border-radius: 15px;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            margin: 7px 0 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        button {
            border: 0;
            padding: 13px 20px;
            border-radius: 9px;
            background: #111827;
            color: white;
            cursor: pointer;
        }

        label {
            font-weight: bold;
        }

        a {
            color: #111827;
        }
    </style>
</head>

<body>

<div class="container">

    <p><a href="index.php">← Dashboard</a></p>

    <h2>➕ Yangi imkoniyat</h2>

    <form method="POST">

        <label>Nomi</label>
        <input
            type="text"
            name="title"
            required
            placeholder="Masalan: Yoshlar Startup Challenge"
        >

        <label>Tavsif</label>
        <textarea
            name="description"
            required
            placeholder="Imkoniyat haqida ma'lumot..."
        ></textarea>

        <label>Kategoriya</label>

        <select name="category_id" required>

            <option value="">
                Kategoriyani tanlang
            </option>

            <?php foreach ($categories as $category): ?>

                <option value="<?= $category['id'] ?>">
                    <?= htmlspecialchars(
                        $category['emoji'] . ' ' . $category['name']
                    ) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <label>Hudud</label>

        <input
            type="text"
            name="region"
            placeholder="O'zbekiston / Sirdaryo / Online"
        >

        <label>Tashkilotchi</label>

        <input
            type="text"
            name="organizer"
            placeholder="Tashkilot nomi"
        >

        <label>Link (Havola)</label>

        <input
            type="url"
            name="url"
            placeholder="https://..."
        >

        <label>🖼️ Rasm / Banner havolasi (URL - ixtiyoriy)</label>

        <input
            type="url"
            name="image_url"
            placeholder="https://... (Post uchun rasm/poster linki)"
        >

        <label>Deadline</label>

        <input
            type="datetime-local"
            name="deadline"
        >

        <button type="submit">
            🚀 E'lon qilish
        </button>

    </form>

</div>

</body>
</html>
