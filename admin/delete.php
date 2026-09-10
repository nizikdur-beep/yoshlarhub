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

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $db->prepare("DELETE FROM opportunities WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: opportunities.php');
exit;
