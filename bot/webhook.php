<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Telegram.php';
require __DIR__ . '/../src/Opportunity.php';

$db = new Database($config)->pdo();
$telegram = new Telegram($config['bot_token']);
$opportunity = new Opportunity($db);

$update = json_decode(file_get_contents('php://input'), true);

if (!is_array($update)) {
    exit;
}

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

    // Deadline (chiroyli sana va qolgan kunlar hisoblagichi)
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
            $countdown = $days > 0 ? " (⏳ <i>{$days} kun qoldi</i>)" : " (⏳ <i>Bugun so'nggi kun!</i>)";
        } else {
            $countdown = " (⚠️ <i>Muddati tugagan</i>)";
        }

        $formattedDeadline = "{$day}-{$month}, {$year} {$hour}" . $countdown;
        $text .= "🗓 <b>Muddati:</b> {$formattedDeadline}\n";
    }

    // Ajratuvchi chiziq va hashtaglar
    $tag = preg_replace('/\s+/', '', $categoryName);
    $text .= "────────────────────\n";
    $text .= "🏷 <i>#{$tag} #YoshlarHub</i>";

    return $text;
}

/**
 * Musiqa boti uslubidagi interaktiv raqamli katalog yaratish
 */
function renderCategoryCatalog(Opportunity $oppModel, int $categoryId, int $page = 1): array
{
    $categories = $oppModel->categories();
    $currentCategory = null;
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $categoryId) {
            $currentCategory = $cat;
            break;
        }
    }

    $categoryName = $currentCategory['name'] ?? 'Imkoniyatlar';
    $categoryEmoji = $currentCategory['emoji'] ?? '📂';

    $data = $oppModel->paginateByCategory($categoryId, $page, 5);
    $items = $data['items'];
    $total = $data['total'];
    $totalPages = max(1, $data['totalPages']);

    if (empty($items)) {
        return [
            'text' => "{$categoryEmoji} <b>" . mb_strtoupper($categoryName, 'UTF-8') . " BO'LIMI</b>\n\n"
                    . "😔 Hozircha bu yo'nalishda faol e'lonlar mavjud emas.\n"
                    . "Tez orada yangi imkoniyatlar qo'shiladi!",
            'keyboard' => null,
            'total' => 0
        ];
    }

    // Raqamlar emojisi
    $numberEmojis = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟'];

    $text = "{$categoryEmoji} <b>" . mb_strtoupper($categoryName, 'UTF-8') . " BO'LIMI</b>\n";
    $text .= "<i>O'zingizga qiziq bo'lgan imkoniyat raqamini tanlang:</i>\n";
    $text .= "────────────────────\n\n";

    $numberRow = [];
    foreach ($items as $idx => $item) {
        $num = $idx + 1;
        $numEmoji = $numberEmojis[$idx] ?? "{$num}.";

        $deadlineStr = '';
        if (!empty($item['deadline'])) {
            $deadlineStr = ' | ⏰ ' . date('d.m.Y', strtotime($item['deadline']));
        }

        $regionStr = !empty($item['region']) ? htmlspecialchars($item['region']) : "O'zbekiston";

        $text .= "{$numEmoji} <b>" . htmlspecialchars($item['title']) . "</b>\n";
        $text .= "📍 <i>{$regionStr}</i>{$deadlineStr}\n\n";

        $numberRow[] = [
            'text' => $numEmoji,
            'callback_data' => "view:{$item['id']}:{$categoryId}:{$page}",
        ];
    }

    $text .= "────────────────────\n";
    $text .= "📊 <i>Jami: {$total} ta imkoniyat | Sahifa: {$page}/{$totalPages}</i>";

    $inlineKeyboard = [];
    $inlineKeyboard[] = $numberRow;

    // Sahifalash (agar 5 tadan ko'p bo'lsa)
    if ($totalPages > 1) {
        $navRow = [];
        if ($page > 1) {
            $prev = $page - 1;
            $navRow[] = [
                'text' => '⬅️ Oldingi',
                'callback_data' => "catpage:{$categoryId}:{$prev}",
            ];
        }

        $navRow[] = [
            'text' => "📄 {$page}/{$totalPages}",
            'callback_data' => 'noop',
        ];

        if ($page < $totalPages) {
            $next = $page + 1;
            $navRow[] = [
                'text' => 'Keyingi ➡️',
                'callback_data' => "catpage:{$categoryId}:{$next}",
            ];
        }
        $inlineKeyboard[] = $navRow;
    }

    return [
        'text' => $text,
        'keyboard' => ['inline_keyboard' => $inlineKeyboard],
        'total' => $total,
    ];
}

/*
|--------------------------------------------------------------------------
| Callback Query (Tugmalar bosilganda)
|--------------------------------------------------------------------------
*/

if (isset($update['callback_query'])) {

    $callback = $update['callback_query'];
    $callbackId = $callback['id'];
    $data = $callback['data'] ?? '';

    $from = $callback['from'];
    $userId = (int) $from['id'];
    $chatId = (int) ($callback['message']['chat']['id'] ?? $userId);
    $messageId = (int) ($callback['message']['message_id'] ?? 0);

    // Bo'sh callback (noop)
    if ($data === 'noop') {
        $telegram->request('answerCallbackQuery', ['callback_query_id' => $callbackId]);
        exit;
    }

    // 1. Sahifalash (Katalog ro'yxati)
    if (str_starts_with($data, 'catpage:')) {
        $parts = explode(':', $data);
        $categoryId = (int) ($parts[1] ?? 1);
        $page = (int) ($parts[2] ?? 1);

        $catalog = renderCategoryCatalog($opportunity, $categoryId, $page);

        $telegram->editMessageText(
            $chatId,
            $messageId,
            $catalog['text'],
            $catalog['keyboard']
        );

        $telegram->request('answerCallbackQuery', ['callback_query_id' => $callbackId]);
        exit;
    }

    // 2. Raqam bosilganda: Karta ochiladi (Rasm bilan!)
    if (str_starts_with($data, 'view:')) {
        $parts = explode(':', $data);
        $opportunityId = (int) ($parts[1] ?? 0);
        $categoryId = (int) ($parts[2] ?? 1);
        $page = (int) ($parts[3] ?? 1);

        $item = $opportunity->find($opportunityId);

        if (!$item) {
            $telegram->request('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => 'Bu imkoniyat topilmadi yoki o‘chirilgan.',
                'show_alert' => true,
            ]);
            exit;
        }

        $cardText = formatOpportunityCard($item);

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
                'text' => '🔗 Ariza topshirish ↗️',
                'url' => $item['url'],
            ];
        }

        // Do'stlarga ulashish
        $shareText = urlencode("Qarang, YoshlarHub'da yangi imkoniyat chiqibdi:\n" . $item['title']);
        $shareUrl = !empty($item['url']) ? urlencode($item['url']) : 'https://t.me/yoshlarhub';

        $inlineKeyboard[] = [
            [
                'text' => '🔙 Ro\'yxatga qaytish',
                'callback_data' => "catpage:{$categoryId}:{$page}",
            ],
            [
                'text' => '📤 Ulashish',
                'url' => "https://t.me/share/url?url={$shareUrl}&text={$shareText}",
            ]
        ];

        // Agar rasm bo'lsa -> Rasm bilan yuboramiz
        if (!empty($item['image_url'])) {
            // Eski ro'yxatni o'chirib, rasm bilan yangi jo'natamiz (chunki matnni rasmga aylantirib bo'lmaydi)
            $telegram->deleteMessage($chatId, $messageId);
            $telegram->sendPhoto(
                $chatId,
                $item['image_url'],
                $cardText,
                ['inline_keyboard' => $inlineKeyboard]
            );
        } else {
            // Rasm bo'lmasa matnni chiroyli almashtiramiz
            $telegram->editMessageText(
                $chatId,
                $messageId,
                $cardText,
                ['inline_keyboard' => $inlineKeyboard]
            );
        }

        $telegram->request('answerCallbackQuery', ['callback_query_id' => $callbackId]);
        exit;
    }

    // 3. Saqlash (Bookmark)
    if (str_starts_with($data, 'save:')) {
        $opportunityId = (int) str_replace('save:', '', $data);
        $opportunity->addBookmark($userId, $opportunityId);

        $telegram->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => '⭐ Saqlanganlarga muvaffaqiyatli qo‘shildi!',
            'show_alert' => false,
        ]);
        exit;
    }

    // 4. Saqlanganlardan o'chirish
    if (str_starts_with($data, 'remove:')) {
        $opportunityId = (int) str_replace('remove:', '', $data);
        $opportunity->removeBookmark($userId, $opportunityId);

        $telegram->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => '🗑 Saqlanganlardan olib tashlandi.',
            'show_alert' => false,
        ]);

        $telegram->deleteMessage($chatId, $messageId);
        exit;
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| Oddiy Xabar (Message)
|--------------------------------------------------------------------------
*/

$message = $update['message'] ?? null;

if (!$message) {
    exit;
}

$chatId = (int) $message['chat']['id'];
$user = $message['from'];

$userId = (int) $user['id'];
$firstName = htmlspecialchars(trim($user['first_name'] ?? ''));
$lastName = htmlspecialchars(trim($user['last_name'] ?? ''));
$fullName = trim("{$firstName} {$lastName}");
if ($fullName === '') {
    $fullName = "Do'stim";
}
$username = $user['username'] ?? null;

// Foydalanuvchini bazaga yozish / yangilash
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
    ':first_name' => $fullName,
    ':username' => $username,
]);

/*
|--------------------------------------------------------------------------
| Asosiy Menyu Tugmalari
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

$text = trim($message['text'] ?? '');

/*
|--------------------------------------------------------------------------
| /start (Ism va familiya bilan shaxsiy salomlashuv)
|--------------------------------------------------------------------------
*/

if ($text === '/start') {

    $welcomeText =
        "Assalomu alaykum, <b>{$fullName}</b>! 🌟\n\n" .
        "🇺🇿 <b>YoshlarHub</b> — O‘zbekiston yoshlari uchun eng sara imkoniyatlar maydoniga xush kelibsiz!\n\n" .
        "Bu yerda siz o‘zingizga mos:\n" .
        "🎓 <b>Grantlar</b> va xalqaro stipendiyalar\n" .
        "🏆 <b>Tanlovlar</b> va nufuzli musobaqalar\n" .
        "💼 <b>Stajirovkalar</b> va amaliyot dasturlari\n" .
        "🤝 <b>Volontyorlik</b> harakatlari\n" .
        "📚 Zamonaviy <b>kurslar</b>ni topishingiz mumkin.\n\n" .
        "<i>Kerakli bo‘limni tanlang:</i> 👇";

    $telegram->sendMessage(
        $chatId,
        $welcomeText,
        mainKeyboard()
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Kategoriyalar (Musiqa boti uslubidagi interaktiv katalog)
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
    $catalog = renderCategoryCatalog($opportunity, $categoryId, 1);

    $telegram->sendMessage(
        $chatId,
        $catalog['text'],
        $catalog['keyboard']
    );

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
            "⭐ <b>Saqlanganlar ro'yxati bo'sh</b>\n\n" .
            "Sizga yoqqan imkoniyatlardagi <b>[⭐ Saqlab qo'yish]</b> tugmasini bossangiz, ular shu yerda saqlanadi!",
            mainKeyboard()
        );
        exit;
    }

    $telegram->sendMessage(
        $chatId,
        "⭐ <b>Saqlangan imkoniyatlaringiz:</b> (" . count($items) . " ta)",
        mainKeyboard()
    );

    foreach ($items as $item) {
        $cardText = formatOpportunityCard($item);

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

        if (!empty($item['image_url'])) {
            $telegram->sendPhoto(
                $chatId,
                $item['image_url'],
                $cardText,
                ['inline_keyboard' => $inlineKeyboard]
            );
        } else {
            $telegram->sendMessage(
                $chatId,
                $cardText,
                ['inline_keyboard' => $inlineKeyboard]
            );
        }
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
        "🔎 <b>Imkoniyatlarni qidirish</b>\n\n" .
        "O‘zingiz qiziqqan yo‘nalish yoki kalit so‘zni yozib yuboring:\n\n" .
        "Masalan:\n" .
        "• <code>Python</code> yoki <code>Frontend</code>\n" .
        "• <code>Grant</code> yoki <code>Stipendiya</code>\n" .
        "• <code>Startap</code>\n" .
        "• <code>Ingliz tili</code>",
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
            age,
            created_at
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([$userId]);
    $profile = $stmt->fetch();

    $name = htmlspecialchars($profile['first_name'] ?? $fullName);
    $usernameText = !empty($profile['username'])
        ? '@' . htmlspecialchars($profile['username'])
        : 'Ko‘rsatilmagan';

    $region = !empty($profile['region'])
        ? htmlspecialchars($profile['region'])
        : 'Belgilanmagan';

    $age = !empty($profile['age']) ? $profile['age'] . ' yosh' : 'Belgilanmagan';
    $joinedDate = !empty($profile['created_at']) ? date('d.m.Y', strtotime($profile['created_at'])) : date('d.m.Y');

    $profileText =
        "👤 <b>FOYDALANUVCHI PROFILI</b>\n" .
        "────────────────────\n" .
        "🧑 <b>F.I.SH:</b> {$name}\n" .
        "📱 <b>Username:</b> {$usernameText}\n" .
        "🆔 <b>Telegram ID:</b> <code>{$userId}</code>\n" .
        "📍 <b>Hudud:</b> {$region}\n" .
        "🎂 <b>Yosh:</b> {$age}\n" .
        "🗓 <b>A'zo bo'lgan sana:</b> {$joinedDate}\n" .
        "────────────────────\n" .
        "💡 <i>Profil ma'lumotlarini to'ldirish orqali o'zingizga mos tavsiyalarni olasiz!</i>";

    $telegram->sendMessage(
        $chatId,
        $profileText,
        mainKeyboard()
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Qidiruv natijalari
|--------------------------------------------------------------------------
*/

if ($text !== '') {

    $items = $opportunity->search($text, 10);

    if (!$items) {
        $telegram->sendMessage(
            $chatId,
            "🔎 <b>Natija topilmadi</b>\n\n" .
            "«{$text}» bo‘yicha hech qanday imkoniyat topilmadi. Boshqa so‘z bilan qidirib ko‘ring.",
            mainKeyboard()
        );
        exit;
    }

    $telegram->sendMessage(
        $chatId,
        "🔎 <b>«{$text}» bo‘yicha topilgan imkoniyatlar:</b> (" . count($items) . " ta)",
        mainKeyboard()
    );

    foreach ($items as $item) {
        $cardText = formatOpportunityCard($item);

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
                'text' => '🔗 Ariza topshirish ↗️',
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

        if (!empty($item['image_url'])) {
            $telegram->sendPhoto(
                $chatId,
                $item['image_url'],
                $cardText,
                ['inline_keyboard' => $inlineKeyboard]
            );
        } else {
            $telegram->sendMessage(
                $chatId,
                $cardText,
                ['inline_keyboard' => $inlineKeyboard]
            );
        }
    }
}
