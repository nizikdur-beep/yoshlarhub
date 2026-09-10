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

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    exit('Noto\'g\'ri ID');
}

$stmt = $db->prepare("SELECT * FROM opportunities WHERE id = ?");
$stmt->execute([$id]);

$opportunity = $stmt->fetch();

if (!$opportunity) {
    exit('Imkoniyat topilmadi');
}

$categories = $db->query("
    SELECT id, name, emoji
    FROM categories
    ORDER BY id
")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $region = trim($_POST['region'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');

    if ($title === '' || $description === '' || $categoryId <= 0) {
        $error = 'Majburiy maydonlarni to\'ldiring.';
    } else {

        $stmt = $db->prepare("
            UPDATE opportunities
            SET
                title = :title,
                description = :description,
                category_id = :category_id,
                region = :region,
                organizer = :organizer,
                url = :url,
                deadline = :deadline
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':category_id' => $categoryId,
            ':region' => $region ?: null,
            ':organizer' => $organizer ?: null,
            ':url' => $url ?: null,
            ':deadline' => $deadline ?: null,
            ':id' => $id,
        ]);

        header('Location: opportunities.php');
        exit;
    }
}

?>
<!doctype html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Imkoniyatni tahrirlash</title>
</head>

<body>

<h1>✏️ Tahrirlash</h1>

<?php if ($error): ?>
    <p style="color:red"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post">

    <input type="hidden" name="id" value="<?= $id ?>">

    <p>
        <label>Nomi</label><br>
        <input
            type="text"
            name="title"
            value="<?= htmlspecialchars($opportunity['title']) ?>"
            required
        >
    </p>

    <p>
        <label>Tavsif</label><br>
        <textarea
            name="description"
            rows="8"
            required
        ><?= htmlspecialchars($opportunity['description']) ?></textarea>
    </p>

    <p>
        <label>Kategoriya</label><br>

        <select name="category_id" required>

            <?php foreach ($categories as $category): ?>

                <option
                    value="<?= (int) $category['id'] ?>"
                    <?= (int) $opportunity['category_id'] === (int) $category['id']
                        ? 'selected'
                        : ''
                    ?>
                >
                    <?= htmlspecialchars($category['emoji'] . ' ' . $category['name']) ?>
                </option>

            <?php endforeach; ?>

        </select>
    </p>

    <p>
        <label>Hudud</label><br>
        <input
            type="text"
            name="region"
            value="<?= htmlspecialchars($opportunity['region'] ?? '') ?>"
            placeholder="O'zbekiston / Sirdaryo / Toshkent..."
        >
    </p>

    <p>
        <label>Tashkilotchi</label><br>
        <input
            type="text"
            name="organizer"
            value="<?= htmlspecialchars($opportunity['organizer'] ?? '') ?>"
        >
    </p>

    <p>
        <label>Havola</label><br>
        <input
            type="url"
            name="url"
            value="<?= htmlspecialchars($opportunity['url'] ?? '') ?>"
            placeholder="https://..."
        >
    </p>

    <p>
        <label>Deadline</label><br>
        <input
            type="datetime-local"
            name="deadline"
            value="<?= $opportunity['deadline']
                ? date('Y-m-d\TH:i', strtotime($opportunity['deadline']))
                : ''
            ?>"
        >
    </p>

    <button type="submit">💾 Saqlash</button>

</form>

</body>
</html>
