<?php

declare(strict_types=1);

class ChannelScraper
{
    /**
     * Telegram ochiq kanallaridan so'nggi postlarni o'qish (t.me/s/kanal_nomi)
     */
    public function scrapeTelegramChannel(string $channelUsername, int $limit = 5): array
    {
        $channel = ltrim($channelUsername, '@');
        $url = "https://t.me/s/{$channel}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            return [];
        }

        $posts = [];

        // Postlarni ajratib olish
        if (preg_match_all('/<div class="tgme_widget_message\b[^>]*data-post="([^"]+)"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
            $reversed = array_reverse($matches); // Eng so'nggi postlar birinchi bo'lishi uchun
            $count = 0;

            foreach ($reversed as $match) {
                if ($count >= $limit) {
                    break;
                }

                $postId = $match[1]; // Masalan: grantlar/12345
                $content = $match[2];

                // Matnni olish
                $text = '';
                if (preg_match('/<div class="tgme_widget_message_text\b[^>]*>(.*?)<\/div>/is', $content, $textMatch)) {
                    $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $textMatch[1]));
                    $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }

                if (empty($text) || mb_strlen($text) < 30) {
                    continue;
                }

                // Rasmni olish (background-image:url('...'))
                $imageUrl = null;
                if (preg_match('/background-image:url\(\'([^\']+)\'\)/i', $content, $imgMatch)) {
                    $imageUrl = $imgMatch[1];
                }

                $posts[] = [
                    'source_type' => 'telegram',
                    'source_name' => '@' . $channel,
                    'post_id' => $postId,
                    'text' => $text,
                    'image_url' => $imageUrl,
                    'url' => "https://t.me/{$postId}",
                ];

                $count++;
            }
        }

        return $posts;
    }

    /**
     * Saytlarning RSS lentasi orqali yangi maqolalarni o'qish
     */
    public function scrapeRssFeed(string $feedUrl, string $sourceName, int $limit = 5): array
    {
        $ch = curl_init($feedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) YoshlarHub/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $xmlString = curl_exec($ch);
        curl_close($ch);

        if (!$xmlString) {
            return [];
        }

        $posts = [];

        try {
            $xml = @simplexml_load_string($xmlString);
            if (!$xml || !isset($xml->channel->item)) {
                return [];
            }

            $count = 0;
            foreach ($xml->channel->item as $item) {
                if ($count >= $limit) {
                    break;
                }

                $title = (string)$item->title;
                $link = (string)$item->link;
                $desc = strip_tags((string)$item->description);
                $content = strip_tags((string)($item->children('content', true)->encoded ?? ''));

                $fullText = $title . "\n\n" . (!empty($content) ? $content : $desc);
                $fullText = trim(html_entity_decode($fullText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // Rasmni topish (enclosure yoki media:content)
                $imageUrl = null;
                if (isset($item->enclosure['url'])) {
                    $imageUrl = (string)$item->enclosure['url'];
                }

                $posts[] = [
                    'source_type' => 'web',
                    'source_name' => $sourceName,
                    'post_id' => md5($link),
                    'text' => $fullText,
                    'image_url' => $imageUrl,
                    'url' => $link,
                ];

                $count++;
            }
        } catch (Throwable $e) {
            error_log("RSS parsing error: " . $e->getMessage());
        }

        return $posts;
    }
}
