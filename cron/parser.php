<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Telegram.php';
require __DIR__ . '/../src/ChannelScraper.php';
require __DIR__ . '/../src/AIParser.php';

$db = new Database($config)->pdo();
$scraper = new ChannelScraper();
$geminiApiKey = $config['gemini_api_key'] ?? getenv('GEMINI_API_KEY') ?: '';
$aiParser = new AIParser($geminiApiKey);

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

echo "🚀 YoshlarHub Avtomatik Parser ishga tushdi...\n\n";

$addedCount = 0;

foreach ($sources as $source) {
    echo "🔍 Tekshirilmoqda: {$source['name']} ({$source['type']})...\n";

    $posts = [];
    if ($source['type'] === 'telegram') {
        $posts = $scraper->scrapeTelegramChannel($source['target'], 3);
    } elseif ($source['type'] === 'rss') {
        $posts = $scraper->scrapeRssFeed($source['target'], $source['name'], 3);
    }

    foreach ($posts as $post) {
        $identifier = $post['post_id'];

        // Avval ko'rilganmi tekshirish
        $checkStmt = $db->prepare("SELECT id FROM parsed_sources WHERE post_identifier = ?");
        $checkStmt->execute([$identifier]);
        if ($checkStmt->fetch()) {
            continue; // Allaqachon tahlil qilingan
        }

        // Boshqa tahlil qilinmasligi uchun saqlab qo'yamiz
        $insParsed = $db->prepare("INSERT IGNORE INTO parsed_sources (source_type, source_name, post_identifier) VALUES (?, ?, ?)");
        $insParsed->execute([$post['source_type'], $post['source_name'], $identifier]);

        echo "  🤖 AI tahlil qilmoqda: " . mb_substr($post['text'], 0, 50) . "...\n";

        // Gemini AI orqali tahlil qilish
        $analyzed = $aiParser->analyzeOpportunity(
            $post['text'],
            $post['url'],
            $post['image_url']
        );

        if (!$analyzed) {
            echo "  ⏩ O'tkazib yuborildi (imkoniyat emas yoki xatolik).\n";
            continue;
        }

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
        echo "  ✅ QO'SHILDI: {$analyzed['title']} ({$analyzed['region']})\n";

        // Gemini API limitiga tushmaslik uchun 1 soniya kutish
        sleep(1);
    }
}

echo "\n🎉 Jarayon yakunlandi! Jami yangi qo'shilgan imkoniyatlar: {$addedCount} ta.\n";
