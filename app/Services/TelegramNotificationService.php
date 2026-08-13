<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        if (empty($this->botToken)) {
            $this->botToken = env('TELEGRAM_BOT_TOKEN', '');
        }
        if (empty($this->botToken)) {
            $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        }
        if (empty($this->botToken)) {
            $this->botToken = $_SERVER['TELEGRAM_BOT_TOKEN'] ?? '';
        }
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send a message to a single chat ID
     */
    public function sendMessage(string $chatId, string $message): bool
    {
        if (empty($this->botToken)) {
            Log::warning('Telegram bot token not configured');
            return false;
        }

        if (empty($chatId)) {
            Log::warning('Telegram chat ID is empty');
            return false;
        }

        try {
            $response = Http::post("{$this->apiUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful()) {
                Log::info("Telegram message sent to chat ID: {$chatId}");
                return true;
            }

            Log::error("Telegram API error: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Telegram send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a message to multiple chat IDs
     */
    public function sendMessageToMultiple(array $chatIds, string $message): array
    {
        $results = [];

        foreach ($chatIds as $chatId) {
            $results[$chatId] = $this->sendMessage($chatId, $message);
        }

        return $results;
    }

    /**
     * Validate chat ID format
     */
    public function validateChatId(string $chatId): bool
    {
        // Chat IDs can be numeric (for users) or string (for groups/channels)
        // Numeric: 123456789
        // Group/Channel: -1001234567890
        return preg_match('/^-?\d+$/', $chatId) === 1;
    }

    /**
     * Format a message with data
     */
    public function formatMessage(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Check if Telegram is configured
     */
    public function isConfigured(): bool
    {
        $token = config('services.telegram.bot_token', '');
        if (empty($token)) {
            $token = env('TELEGRAM_BOT_TOKEN', '');
        }
        if (empty($token)) {
            $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        }
        if (empty($token)) {
            $token = $_SERVER['TELEGRAM_BOT_TOKEN'] ?? '';
        }
        return !empty($token);
    }
}
