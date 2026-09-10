<?php

declare(strict_types=1);

class Telegram
{
    private string $api;

    public function __construct(string $token)
    {
        if (!$token) {
            throw new RuntimeException('BOT_TOKEN topilmadi.');
        }

        $this->api = "https://api.telegram.org/bot{$token}/";
    }

    public function request(string $method, array $data = []): array
    {
        $ch = curl_init($this->api . $method);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new RuntimeException(curl_error($ch));
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (!is_array($result)) {
            throw new RuntimeException('Telegram javobi noto\'g\'ri.');
        }

        return $result;
    }

    public function sendMessage(
        int|string $chatId,
        string $text,
        ?array $keyboard = null
    ): array {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard !== null) {
            $data['reply_markup'] = json_encode(
                $keyboard,
                JSON_UNESCAPED_UNICODE
            );
        }

        return $this->request('sendMessage', $data);
    }
}
