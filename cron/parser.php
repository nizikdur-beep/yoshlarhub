<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Telegram.php';
require __DIR__ . '/../src/ChannelScraper.php';
require __DIR__ . '/../src/AIParser.php';

$db = new Database($config)->pdo();
$scraper = new ChannelScraper();
$geminiApiKey = $config['gemini_api_key'] ?? getenv('GEMINI_API_KEY') ?: '';
$aiParser = new AIParser((string)$geminiApiKey);

// Duplikatlarni tekshirish uchun jadval yaratish
$db->exec("
    CREATE TABLE IF NOT EXISTS parsed_sources (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_type VARCHAR(50) NOT NULL,
        source_name VARCHAR(100) NOT NULL,
        post_identifier VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
");

// Reset parametri berilgan bo'lsa qaytadan tozalaymiz
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $db->exec("TRUNCATE TABLE parsed_sources");
    echo "<p style='color:orange;'>🔄 Xotira tozalandi, barcha manbalar qaytadan tahlil qilinadi!</p>";
}

// Kuzatiladigan Telegram kanallari va Saytlar
$sources = [
    // 1. Telegram kanallar
    ['type' => 'telegram', 'target' => 'grantlar', 'name' => '@grantlar'],
    ['type' => 'telegram', 'target' => 'GrantGoUz', 'name' => '@GrantGoUz'],
    ['type' => 'telegram', 'target' => 'yoshlaragentligi', 'name' => '@yoshlaragentligi'],
    ['type' => 'telegram', 'target' => 'yoshlareduuz', 'name' => '@yoshlareduuz'],

    // 2. Saytlar (RSS lenta)
    ['type' => 'rss', 'target' => 'https://grantlar.uz/feed/', 'name' => 'Grantlar.uz'],
    ['type' => 'rss', 'target' => 'https://grantgo.uz/feed/', 'name' => 'GrantGO'],
    ['type' => 'rss', 'target' => 'https://brightfuturesuzbekistan.uz/feed/', 'name' => 'Bright Futures'],
];

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>YoshlarHub Parser</title>";
echo "<style>body{font-family:sans-serif;background:#1e1e2f;color:#fff;padding:20px;line-height:1.6;} .card{background:#2a2b3d;padding:15px;margin-bottom:15px;border-radius:8px;} .success{color:#4ade80;} .info{color:#60a5fa;} .warn{color:#fbbf24;}</style>";
echo "</head><body>";

echo "<h2>🚀 YoshlarHub Avtomatik AI Parser</h2>";

if (empty($geminiApiKey)) {
    echo "<div class='card' style='border-left:4px solid red;'><b style='color:#f87171;'>⚠️ DIQQAT: GEMINI_API_KEY topilmadi!</b><br>Iltimos, hostingdagi <code>.env</code> faylga <code>GEMINI_API_KEY=AIzaSy...</code> deb yozing.</div>";
}

$addedCount = 0;

foreach ($sources as $source) {
    echo "<div class='card'>";
    echo "<h3 class='info'>🔍 Tekshirilmoqda: {$source['name']} ({$source['type']})</h3>";

    $posts = [];
    if ($source['type'] === 'telegram') {
        $posts = $scraper->scrapeTelegramChannel($source['target'], 3);
    } elseif ($source['type'] === 'rss') {
        $posts = $scraper->scrapeRssFeed($source['target'], $source['name'], 3);
    }

    if (empty($posts)) {
        echo "<p class='warn'>⚠️ Bu manbadan postlar topilmadi.</p>";
        echo "</div>";
        continue;
    }

    foreach ($posts as $post) {
        $identifier = $post['post_id'];

        // Avval ko'rilganmi tekshirish
        $checkStmt = $db->prepare("SELECT id FROM parsed_sources WHERE post_identifier = ?");
        $checkStmt->execute([$identifier]);
        if ($checkStmt->fetch()) {
            echo "<p style='color:#9ca3af;'>⏩ Allaqachon ko'rilgan: " . htmlspecialchars(mb_substr($post['text'], 0, 40)) . "...</p>";
            continue;
        }

        echo "<p>🤖 <b>AI tahlil qilmoqda:</b> <i>" . htmlspecialchars(mb_substr($post['text'], 0, 60)) . "...</i>";

        // Gemini AI orqali tahlil qilish
        $analyzed = $aiParser->analyzeOpportunity(
            $post['text'],
            $post['url'],
            $post['image_url']
        );

        if (!$analyzed) {
            echo " → <span class='warn'>O'tkazib yuborildi (imkoniyat emas yoki xatolik).</span></p>";
            // Faqat API xatosi bo'lmasa eslab qolamiz
            if (!empty($geminiApiKey)) {
                $insParsed = $db->prepare("INSERT IGNORE INTO parsed_sources (source_type, source_name, post_identifier) VALUES (?, ?, ?)");
                $insParsed->execute([$post['source_type'], $post['source_name'], $identifier]);
            }
            continue;
        }

        // Boshqa qayta tahlil qilinmasligi uchun saqlab qo'yamiz
        $insParsed = $db->prepare("INSERT IGNORE INTO parsed_sources (source_type, source_name, post_identifier) VALUES (?, ?, ?)");
        $insParsed->execute([$post['source_type'], $post['source_name'], $identifier]);

        // Baza (opportunities) ga yozish
        $insertStmt = $db->prepare("
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

        $insertStmt->execute([
            ':title' => $analyzed['title'],
            ':description' => $analyzed['description'],
            ':category_id' => $analyzed['category_id'],
            ':region' => $analyzed['region'],
            ':organizer' => $analyzed['organizer'],
            ':url' => $analyzed['url'],
            ':image_url' => $analyzed['image_url'],
            ':deadline' => $analyzed['deadline'],
        ]);

        $addedCount++;
        echo " → <b class='success'>✅ BAZAGA QO'SHILDI:</b> " . htmlspecialchars($analyzed['title']) . " (" . htmlspecialchars($analyzed['region']) . ")</p>";

        // Gemini API limitiga tushmaslik uchun 1 soniya kutish
        sleep(1);
    }

    echo "</div>";
}

echo "<div class='card' style='border-top:2px solid #4ade80;'>";
echo "<h3>🎉 Jarayon yakunlandi! Jami yangi qo'shilgan imkoniyatlar: <span class='success'>{$addedCount} ta</span></h3>";
echo "<p><a href='?reset=1' style='color:#60a5fa;'>🔄 Qaytadan to'liq skaner qilish (Reset)</a> | <a href='../admin/' style='color:#4ade80;'>👉 Admin panelga o'tish</a></p>";
echo "</div>";
echo "</body></html>";
