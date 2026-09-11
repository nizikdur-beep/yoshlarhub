<?php

declare(strict_types=1);

class AIParser
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = trim($apiKey);
    }

    /**
     * Gemini AI orqali matnni tahlil qilib, strukturalangan ma'lumot olish
     */
    public function analyzeOpportunity(string $rawText, ?string $sourceUrl = null, ?string $extractedImageUrl = null): ?array
    {
        if (empty($this->apiKey)) {
            echo " <b style='color:red;'>[XATO: GEMINI_API_KEY topilmadi! .env faylni tekshiring]</b> ";
            return null;
        }

        $systemPrompt = <<<PROMPT
Siz O'zbekiston yoshlari uchun grantlar, tanlovlar, stajirovkalar, volontyorlik, kurslar, startaplar va olimpiadalarni tahlil qiluvchi AI tizimisiz.
Quyidagi berilgan e'lon matnini diqqat bilan o'rganing.

Vazifangiz:
1. Ushbu matn yoshlar uchun imkoniyat (grant, tanlov, stajirovka, volontyorlik, kurs, startap yoki olimpiada) ekanligini aniqlang.
   Agar bu shunchaki oddiy yangilik, tabrik yoki foydasiz xabar bo'lsa, "is_opportunity": false deb qaytaring.
2. Agar bu haqiqiy imkoniyat bo'lsa:
   - "title": Qisqa, aniq va jozibali sarlavha (maksimum 100 belgi).
   - "description": Imkoniyatning eng muhim mazmuni (qisqacha 2-4 gap, talablar va afzalliklar).
   - "category_id": Quyidagi 7 ta kategoriyadan eng mos birining raqami:
       1: Grantlar (stipendiyalar, grant dasturlari)
       2: Tanlovlar (musobaqalar, tanlovlar, festivallar)
       3: Stajirovkalar (ish, amaliyot, internship)
       4: Volontyorlik (ko'ngillilik loyihalari)
       5: Kurslar (bepul/pullik o'quv dasturlari, vebinarlar)
       6: Startaplar (akseleratorlar, inkubatsiya, startap grantlar)
       7: Olimpiadalar (fan olimpiadalari, xakatonlar)
   - "region": Hudud (masalan: "O'zbekiston", "Toshkent", "Online" yoki xalqaro davlat nomi).
   - "organizer": Tashkilotchi nomi (masalan: "Yoshlar Ishlari Agentligi", "IT Park", "ERASMUS+").
   - "deadline": Arizalar qabul qilishning oxirgi muddati (format: "YYYY-MM-DD HH:MM:SS"). Agar aniq sana topilmasa null qiling.
   - "url": Arizaga to'g'ridan-to'g'ri havola yoki rasmiy link.

Javobni FAQAT toza JSON formatida bering (hech qanday markdown yoki ```json belgilarsiz):
{
  "is_opportunity": true,
  "title": "...",
  "description": "...",
  "category_id": 1,
  "region": "...",
  "organizer": "...",
  "deadline": "YYYY-MM-DD 23:59:00",
  "url": "..."
}
PROMPT;

        $models = [
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.0-flash-exp',
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest',
            'gemini-pro'
        ];

        $successfulResponse = null;
        $lastError = '';

        foreach ($models as $model) {
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;

            $postData = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => $systemPrompt . "\n\nE'lon matni:\n" . $rawText
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1024,
                ]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $resJson = json_decode($response, true);
                if (isset($resJson['error'])) {
                    $lastError = $resJson['error']['message'] ?? 'Xato';
                    continue; // Keyingi modelni sinaymiz
                }

                $text = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (!empty($text)) {
                    $successfulResponse = $text;
                    break; // Muvaffaqiyatli!
                }
            }
        }

        if ($successfulResponse === null) {
            echo " <b style='color:red;'>[Gemini xatosi: " . htmlspecialchars($lastError ?: 'Javob olinmadi') . "]</b> ";
            return null;
        }

        // Tozalash (agar ```json bo'lsa)
        $cleanJson = trim($successfulResponse);
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
        $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
        $cleanJson = trim($cleanJson);

        $parsed = json_decode($cleanJson, true);

        if (!is_array($parsed) || empty($parsed['is_opportunity'])) {
            return null;
        }

        // Rasm mantig'i: agar rasm topilgan bo'lsa o'shani olamiz, bo'lmasa kategoriya bo'yicha chiroyli cover beramiz
        $imageUrl = $extractedImageUrl;
        if (empty($imageUrl)) {
            $imageUrl = $this->generateDefaultBanner((int)($parsed['category_id'] ?? 1));
        }

        return [
            'title' => $parsed['title'] ?? 'Yangi imkoniyat',
            'description' => $parsed['description'] ?? '',
            'category_id' => (int)($parsed['category_id'] ?? 1),
            'region' => $parsed['region'] ?? "O'zbekiston",
            'organizer' => $parsed['organizer'] ?? null,
            'deadline' => $parsed['deadline'] ?? null,
            'url' => !empty($parsed['url']) ? $parsed['url'] : $sourceUrl,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Agar e'londa rasm bo'lmasa, kategoriya bo'yicha jozibali poster yaratish
     */
    private function generateDefaultBanner(int $categoryId): string
    {
        $covers = [
            1 => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=1000&q=80', // Grantlar / Ta'lim
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
