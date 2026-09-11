<?php

declare(strict_types=1);

class AIParser
{
    private string $apiKey;

    public function __construct(string $apiKey = '')
    {
        $this->apiKey = trim($apiKey);
    }

    /**
     * Imkoniyatni tahlil qilish (Aqlli gibrid tizim: Mahalliy NLP + AI Fallback)
     */
    public function analyzeOpportunity(string $rawText, ?string $sourceUrl = null, ?string $extractedImageUrl = null): ?array
    {
        // 1. Matn juda qisqa bo'lsa o'tkazib yuboramiz
        if (mb_strlen($rawText) < 40) {
            return null;
        }

        // 2. Agar reklama yoki oddiy e'lon bo'lsa
        $lower = mb_strtolower($rawText, 'UTF-8');
        if (
            str_contains($lower, 'reklama') && !str_contains($lower, 'grant') ||
            str_contains($lower, 'obuna bo\'ling') && mb_strlen($rawText) < 100
        ) {
            return null;
        }

        // 3. Kategoriyani aniqlash (Hashtag va kalit so'zlar bo'yicha)
        $categoryId = $this->detectCategory($lower);

        // 4. Sarlavhani ajratib olish (Birinchi mazmunli qator)
        $title = $this->extractTitle($rawText);
        if (empty($title)) {
            $title = "Yoshlar uchun yangi imkoniyat";
        }

        // 5. Tavsifni tozalash (2-4 gap)
        $description = $this->extractDescription($rawText);

        // 6. Hududni aniqlash
        $region = $this->detectRegion($lower);

        // 7. Tashkilotchini aniqlash
        $organizer = $this->detectOrganizer($rawText);

        // 8. Deadlineni topish (Sanalar)
        $deadline = $this->detectDeadline($rawText);

        // 9. Ariza havolasini topish (Link)
        $url = $this->extractUrl($rawText, $sourceUrl);

        // 10. Rasm
        $imageUrl = $extractedImageUrl;
        if (empty($imageUrl)) {
            $imageUrl = $this->generateDefaultBanner($categoryId);
        }

        return [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'region' => $region,
            'organizer' => $organizer,
            'deadline' => $deadline,
            'url' => $url,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Kategoriya aniqlagich
     */
    private function detectCategory(string $lower): int
    {
        if (str_contains($lower, 'grant') || str_contains($lower, 'stipendiya') || str_contains($lower, 'scholarship') || str_contains($lower, 'moliya')) {
            return 1; // Grantlar
        }
        if (str_contains($lower, 'tanlov') || str_contains($lower, 'musobaqa') || str_contains($lower, 'contest') || str_contains($lower, 'festival')) {
            return 2; // Tanlovlar
        }
        if (str_contains($lower, 'stajirovka') || str_contains($lower, 'amaliyot') || str_contains($lower, 'internship') || str_contains($lower, 'vakansiya') || str_contains($lower, 'ish o\'rni')) {
            return 3; // Stajirovkalar
        }
        if (str_contains($lower, 'volontyor') || str_contains($lower, 'ko\'ngilli') || str_contains($lower, 'volunteer')) {
            return 4; // Volontyorlik
        }
        if (str_contains($lower, 'kurs') || str_contains($lower, 'vebinar') || str_contains($lower, 'trening') || str_contains($lower, 'dars') || str_contains($lower, 'o\'qitish')) {
            return 5; // Kurslar
        }
        if (str_contains($lower, 'startap') || str_contains($lower, 'startup') || str_contains($lower, 'akselerator') || str_contains($lower, 'inkubats')) {
            return 6; // Startaplar
        }
        if (str_contains($lower, 'olimpiada') || str_contains($lower, 'xakaton') || str_contains($lower, 'hackathon')) {
            return 7; // Olimpiadalar
        }

        return 1; // Default: Grantlar
    }

    /**
     * Sarlavhani ajratish
     */
    private function extractTitle(string $text): string
    {
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[📌🚀🔥⚡️🏆🎓💼🤝📚🧠✨🎯📢❗️❓👉]+\s*/u', '', trim($line)));
            if (mb_strlen($line) >= 10 && !str_starts_with($line, '#') && !str_starts_with($line, 'http')) {
                // Agar juda uzun bo'lsa qisqartiramiz
                if (mb_strlen($line) > 100) {
                    $line = mb_substr($line, 0, 97) . '...';
                }
                return $line;
            }
        }

        return "Yangi imkoniyat";
    }

    /**
     * Qisqa va mazmunli tavsif ajratish
     */
    private function extractDescription(string $text): string
    {
        $clean = preg_replace('/https?:\/\/\S+/i', '', $text);
        $clean = preg_replace('/#\w+/u', '', $clean);
        $lines = array_filter(array_map('trim', explode("\n", $clean)));

        // Birinchi 3-4 ta mazmunli qatorni birlashtiramiz
        $descLines = array_slice($lines, 1, 4);
        $desc = implode("\n", $descLines);

        if (mb_strlen($desc) < 20) {
            $desc = implode("\n", array_slice($lines, 0, 3));
        }

        if (mb_strlen($desc) > 500) {
            $desc = mb_substr($desc, 0, 497) . '...';
        }

        return trim($desc);
    }

    /**
     * Hududni aniqlash
     */
    private function detectRegion(string $lower): string
    {
        $regions = [
            'toshkent' => 'Toshkent',
            'samarqand' => 'Samarqand',
            'buxoro' => 'Buxoro',
            'andijon' => 'Andijon',
            'farg\'ona' => 'Farg\'ona',
            'namangan' => 'Namangan',
            'qashqadaryo' => 'Qashqadaryo',
            'surxondaryo' => 'Surxondaryo',
            'jizzax' => 'Jizzax',
            'sirdaryo' => 'Sirdaryo',
            'xorazm' => 'Xorazm',
            'navoiy' => 'Navoiy',
            'qoraqalpog\'iston' => 'Qoraqalpog\'iston',
            'xitoy' => 'Xitoy',
            'germaniya' => 'Germaniya',
            'aqsh' => 'AQSh',
            'yaponiya' => 'Yaponiya',
            'koreya' => 'Janubiy Koreya',
            'buyuk britaniya' => 'Buyuk Britaniya',
            'saudiya' => 'Saudiya Arabistoni',
            'turkiya' => 'Turkiya',
            'online' => 'Masofaviy (Online)',
            'masofaviy' => 'Masofaviy (Online)',
        ];

        foreach ($regions as $key => $name) {
            if (str_contains($lower, $key)) {
                return $name;
            }
        }

        return "O'zbekiston";
    }

    /**
     * Tashkilotchini aniqlash
     */
    private function detectOrganizer(string $text): ?string
    {
        if (preg_match('/(?:tashkilotchi|organizer|kim tomonidan|tomonidan)\s*[:—–-]?\s*([^\n.,]+)/iu', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/([A-ZА-ЯЁ][\w\s\'"«»-]{2,40}\s*(?:vazirligi|agentligi|universiteti|instituti|markazi|fondi|dasturi))/iu', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Deadline sanasini aniqlash
     */
    private function detectDeadline(string $text): ?string
    {
        $months = [
            'yanvar' => '01', 'fevral' => '02', 'mart' => '03', 'aprel' => '04',
            'may' => '05', 'iyun' => '06', 'iyul' => '07', 'avgust' => '08',
            'sentabr' => '09', 'sentyabr' => '09', 'oktabr' => '10', 'oktyabr' => '10',
            'noyabr' => '11', 'dekabr' => '12',
        ];

        // Format: "25-oktabr 2026" yoki "25-oktabrgacha"
        $monthPattern = implode('|', array_keys($months));
        if (preg_match('/(\d{1,2})[-. ]\s*(' . $monthPattern . ')(?:gacha|\s+(\d{4}))?/iu', $text, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = $months[mb_strtolower($m[2], 'UTF-8')] ?? '10';
            $year = !empty($m[3]) ? $m[3] : (date('Y'));
            return "{$year}-{$month}-{$day} 23:59:00";
        }

        // Format: "25.10.2026"
        if (preg_match('/(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})/u', $text, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year = $m[3];
            return "{$year}-{$month}-{$day} 23:59:00";
        }

        return null;
    }

    /**
     * Linkni topish
     */
    private function extractUrl(string $text, ?string $fallback): ?string
    {
        if (preg_match('/https?:\/\/(?!t\.me\/s\/)[^\s<>"\'\)]+/i', $text, $m)) {
            return $m[0];
        }

        return $fallback;
    }

    /**
     * Kategoriya posteri
     */
    private function generateDefaultBanner(int $categoryId): string
    {
        $covers = [
            1 => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=1000&q=80', // Grantlar
            2 => 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=1000&q=80', // Tanlovlar
            3 => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=1000&q=80', // Stajirovkalar
            4 => 'https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=1000&q=80', // Volontyorlik
            5 => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1000&q=80', // Kurslar
            6 => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=1000&q=80', // Startaplar
            7 => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=1000&q=80', // Olimpiadalar
        ];

        return $covers[$categoryId] ?? 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=1000&q=80';
    }
}
