<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Telegram.php';
require __DIR__ . '/../src/Opportunity.php';

$db = new Database($config)->pdo();
$telegram = new Telegram($config['bot_token']);
$opportunity = new Opportunity($db);

/**
 * Imkoniyat kartasini zamonaviy va chiroyli formatlash
 */
function formatOpportunityCard(array $item): string
{
    $emoji = $item['emoji'] ?? '📌';
    $title = htmlspecialchars(trim($item['title']));
    $desc = htmlspecialchars(trim($item['description']));
    $categoryName = htmlspecialchars($item['category_name'] ?? 'Imkoniyat');

    // Sarlavha
    $text = "{$emoji} <b>" . mb_strtoupper($title, 'UTF-8') . "</b>\n\n";

    // Tavsif (Telegram blockquote - vertikal chiziqli zamonaviy dizayn)
    $text .= "<blockquote>{$desc}</blockquote>\n\n";

    // Tashkilotchi
    if (!empty($item['organizer'])) {
        $text .= "🏛 <b>Tashkilotchi:</b> " . htmlspecialchars($item['organizer']) . "\n";
    }

    // Hudud
    if (!empty($item['region'])) {
        $text .= "📍 <b>Hudud:</b> " . htmlspecialchars($item['region']) . "\n";
    }

    // Deadline (chiroyli format va qolgan kunlar hisoblagichi)
    if (!empty($item['deadline'])) {
        $time = strtotime($item['deadline']);
        $months = [
            1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel', 5 => 'may', 6 => 'iyun',
            7 => 'iyul', 8 => 'avgust', 9 => 'sentabr', 10 => 'oktabr', 11 => 'noyabr', 12 => 'dekabr'
        ];
        $day = date('j', $time);
        $month = $months[(int)date('n', $time)] ?? date('m', $time);
        $year = date('Y', $time);
        $hour = date('H:i', $time);

        $now = time();
        $diff = $time - $now;
        if ($diff > 0) {
            $days = (int) floor($diff / 86400);
            $countdown = $days > 0 ? " (⏳ <i>{$days} kun qoldi</i>)" : " (⏳ <i>Bugun oxirgi kun!</i>)";
        } else {
            $countdown = " (⚠️ <i>Muddati tugagan</i>)";
        }

        $formattedDeadline = "{$day}-{$month}, {$year} {$hour}" . $countdown;
        $text .= "🗓 <b>Muddati:</b> {$formattedDeadline}\n";
    }

    // Chiziq va hashtaglar
    $tag = preg_replace('/\s+/', '', $categoryName);
    $text .= "────────────────────\n";
    $text .= "🏷 <i>#{$tag} #YoshlarHub</i>";

    return $text;
}

$update = json_decode(file_get_contents('php://input'), true);

if (!is_array($update)) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Callback Query
|--------------------------------------------------------------------------
*/

if (isset($update['callback_query'])) {

    $callback = $update['callback_query'];

    $callbackId = $callback['id'];
    $data = $callback['data'] ?? '';

    $from = $callback['from'];
    $userId = (int) $from['id'];

    $chatId = (int) ($callback['message']['chat']['id'] ?? $userId);

    if (str_starts_with($data, 'category:')) {

        $categoryId = (int) str_replace('category:', '', $data);

        $items = $opportunity->latestByCategory($categoryId, 10);

        if (!$items) {
            $telegram->request('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => 'Hozircha imkoniyat topilmadi.',
                'show_alert' => false,
            ]);

            exit;
        }

        foreach ($items as $item) {
            $text = formatOpportunityCard($item);

            $inlineKeyboard = [
                [
                    [
                        'text' => '⭐ Saqlab qo\'yish',
                        'callback_data' => 'save:' . $item['id'],
                    ],
                ]
            ];

            if (!empty($item['url'])) {
                $inlineKeyboard[0][] = [
                    'text' => '🔗 Batafsil ↗️',
                    'url' => $item['url'],
                ];
            }

            // Do'stlarga ulashish tugmasi
            $shareText = urlencode("Qarang, qiziq imkoniyat topdim:\n" . $item['title']);
            $shareUrl = !empty($item['url']) ? urlencode($item['url']) : 'https://t.me/yoshlarhub';
            $inlineKeyboard[] = [
                [
                    'text' => '📤 Do\'stlarga ulashish',
                    'url' => "https://t.me/share/url?url={$shareUrl}&text={$shareText}",
                ]
            ];

            $telegram->sendMessage(
                $chatId,
                $text,
                ['inline_keyboard' => $inlineKeyboard]
            );
        }

        $telegram->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    if (str_starts_with($data, 'save:')) {

        $opportunityId = (int) str_replace('save:', '', $data);

        $opportunity->addBookmark(
            $userId,
            $opportunityId
        );

        $telegram->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => '⭐ Saqlandi!',
            'show_alert' => false,
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Remove bookmark
    |--------------------------------------------------------------------------
    */

    if (str_starts_with($data, 'remove:')) {

        $opportunityId = (int) str_replace('remove:', '', $data);

        $opportunity->removeBookmark(
            $userId,
            $opportunityId
        );

        $telegram->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => '🗑 Saqlanganlardan olib tashlandi.',
            'show_alert' => false,
        ]);

        exit;
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Oddiy Message
|--------------------------------------------------------------------------
*/

$message = $update['message'] ?? null;

if (!$message) {
    exit;
}

$chatId = (int) $message['chat']['id'];
$user = $message['from'];

$userId = (int) $user['id'];
$firstName = $user['first_name'] ?? '';
$username = $user['username'] ?? null;

$stmt = $db->prepare("
    INSERT INTO users
        (id, first_name, username)
    VALUES
        (:id, :first_name, :username)
    ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name),
        username = VALUES(username)
");

$stmt->execute([
    ':id' => $userId,
    ':first_name' => $firstName,
    ':username' => $username,
]);


/*
|--------------------------------------------------------------------------
| Menyu
|--------------------------------------------------------------------------
*/

function mainKeyboard(): array
{
    return [
        'keyboard' => [
            [
                ['text' => '🎓 Grantlar'],
                ['text' => '🏆 Tanlovlar'],
            ],
            [
                ['text' => '💼 Stajirovkalar'],
                ['text' => '🤝 Volontyorlik'],
            ],
            [
                ['text' => '📚 Kurslar'],
                ['text' => '🚀 Startaplar'],
            ],
            [
                ['text' => '🧠 Olimpiadalar'],
                ['text' => '⭐ Saqlanganlar'],
            ],
            [
                ['text' => '🔎 Qidirish'],
                ['text' => '👤 Profil'],
            ],
        ],
        'resize_keyboard' => true,
    ];
}


/*
|--------------------------------------------------------------------------
| /start
|--------------------------------------------------------------------------
*/

$text = trim($message['text'] ?? '');

if ($text === '/start') {

    $telegram->sendMessage(
        $chatId,
        "👋 <b>YoshlarHub</b>ga xush kelibsiz!\n\n" .
        "🇺🇿 O'zbekiston yoshlariga grant, tanlov, " .
        "stajirovka, kurs, volontyorlik va boshqa " .
        "imkoniyatlarni topishda yordam beramiz.\n\n" .
        "Kerakli bo'limni tanlang:",
        mainKeyboard()
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categoryMap = [
    '🎓 Grantlar' => 1,
    '🏆 Tanlovlar' => 2,
    '💼 Stajirovkalar' => 3,
    '🤝 Volontyorlik' => 4,
    '📚 Kurslar' => 5,
    '🚀 Startaplar' => 6,
    '🧠 Olimpiadalar' => 7,
];

if (isset($categoryMap[$text])) {

    $categoryId = $categoryMap[$text];

    $items = $opportunity->latestByCategory(
        $categoryId,
        10
    );

    if (!$items) {

        $telegram->sendMessage(
            $chatId,
            "😔 Hozircha bu bo'limda imkoniyatlar yo'q.",
            mainKeyboard()
        );

        exit;
    }

    foreach ($items as $item) {
        $messageText = formatOpportunityCard($item);

        $inlineKeyboard = [
            [
                [
                    'text' => '⭐ Saqlab qo\'yish',
                    'callback_data' => 'save:' . $item['id'],
                ],
            ]
        ];

        if (!empty($item['url'])) {
            $inlineKeyboard[0][] = [
                'text' => '🔗 Batafsil ↗️',
                'url' => $item['url'],
            ];
        }

        // Do'stlarga ulashish tugmasi
        $shareText = urlencode("Qarang, YoshlarHub'da yangi imkoniyat chiqibdi:\n" . $item['title']);
        $shareUrl = !empty($item['url']) ? urlencode($item['url']) : 'https://t.me/yoshlarhub';
        $inlineKeyboard[] = [
            [
                'text' => '📤 Do\'stlarga ulashish',
                'url' => "https://t.me/share/url?url={$shareUrl}&text={$shareText}",
            ]
        ];

        $telegram->sendMessage(
            $chatId,
            $messageText,
            ['inline_keyboard' => $inlineKeyboard]
        );
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Saqlanganlar
|--------------------------------------------------------------------------
*/

if ($text === '⭐ Saqlanganlar') {

    $items = $opportunity->bookmarks($userId);

    if (!$items) {

        $telegram->sendMessage(
            $chatId,
            "⭐ <b>Saqlanganlar</b>\n\n" .
            "Hozircha hech narsa saqlanmagan.",
            mainKeyboard()
        );

        exit;
    }

    $telegram->sendMessage(
        $chatId,
        "⭐ <b>Saqlangan imkoniyatlaringiz:</b>",
        mainKeyboard()
    );

    foreach ($items as $item) {
        $messageText = formatOpportunityCard($item);

        $inlineKeyboard = [
            [
                [
                    'text' => '🗑 Olib tashlash',
                    'callback_data' => 'remove:' . $item['id'],
                ],
            ],
        ];

        if (!empty($item['url'])) {
            $inlineKeyboard[0][] = [
                'text' => '🔗 Ochish ↗️',
                'url' => $item['url'],
            ];
        }

        $telegram->sendMessage(
            $chatId,
            $messageText,
            ['inline_keyboard' => $inlineKeyboard]
        );
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Qidirish
|--------------------------------------------------------------------------
*/

if ($text === '🔎 Qidirish') {

    $telegram->sendMessage(
        $chatId,
        "🔎 <b>Qidiruv</b>\n\n" .
        "Imkoniyat nomi yoki kalit so'zni yuboring.\n\n" .
        "Masalan:\n" .
        "• Python\n" .
        "• grant\n" .
        "• startup\n" .
        "• ingliz tili",
        mainKeyboard()
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Profil
|--------------------------------------------------------------------------
*/

if ($text === '👤 Profil') {

    $stmt = $db->prepare("
        SELECT
            first_name,
            username,
            region,
            age
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([$userId]);

    $profile = $stmt->fetch();

    $name = htmlspecialchars(
        $profile['first_name'] ?? $firstName
    );

    $usernameText = !empty($profile['username'])
        ? '@' . htmlspecialchars($profile['username'])
        : 'Ko\'rsatilmagan';

    $region = !empty($profile['region'])
        ? htmlspecialchars($profile['region'])
        : 'Belgilanmagan';

    $age = $profile['age'] ?? 'Belgilanmagan';

    $telegram->sendMessage(
        $chatId,
        "👤 <b>Profil</b>\n\n" .
        "🧑 Ism: {$name}\n" .
        "📱 Username: {$usernameText}\n" .
        "📍 Hudud: {$region}\n" .
        "🎂 Yosh: {$age}",
        mainKeyboard()
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Search result
|--------------------------------------------------------------------------
*/

if ($text !== '') {

    $items = $opportunity->search(
        $text,
        10
    );

    if (!$items) {

        $telegram->sendMessage(
            $chatId,
            "🔎 <b>Natija topilmadi.</b>\n\n" .
            "Boshqa kalit so'z bilan urinib ko'ring.",
            mainKeyboard()
        );

        exit;
    }

    $telegram->sendMessage(
        $chatId,
        "🔎 <b>Qidiruv natijalari:</b>",
        mainKeyboard()
    );

    foreach ($items as $item) {
        $messageText = formatOpportunityCard($item);

        $inlineKeyboard = [
            [
                [
                    'text' => '⭐ Saqlab qo\'yish',
                    'callback_data' => 'save:' . $item['id'],
                ],
            ],
        ];

        if (!empty($item['url'])) {
            $inlineKeyboard[0][] = [
                'text' => '🔗 Batafsil ↗️',
                'url' => $item['url'],
            ];
        }

        $shareText = urlencode("Qarang, qiziq imkoniyat topdim:\n" . $item['title']);
        $shareUrl = !empty($item['url']) ? urlencode($item['url']) : 'https://t.me/yoshlarhub';
        $inlineKeyboard[] = [
            [
                'text' => '📤 Do\'stlarga ulashish',
                'url' => "https://t.me/share/url?url={$shareUrl}&text={$shareText}",
            ]
        ];

        $telegram->sendMessage(
            $chatId,
            $messageText,
            ['inline_keyboard' => $inlineKeyboard]
        );
    }
}
