<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $chatId;
    protected $apiUrl;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_CHAT_ID');
        
        // Fallback to reading directly from .env if running from artisan serve and config is not cached
        if (empty($this->botToken) && file_exists(base_path('.env'))) {
            $envPath = base_path('.env');
            $envContent = file_get_contents($envPath);
            if (preg_match('/^TELEGRAM_BOT_TOKEN=(.*?)$/m', $envContent, $matches)) {
                $this->botToken = trim(trim($matches[1]), '"\'');
            }
            if (preg_match('/^TELEGRAM_CHAT_ID=(.*?)$/m', $envContent, $matches)) {
                $this->chatId = trim(trim($matches[1]), '"\'');
            }
        }
        
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send a message to Telegram
     *
     * @param string $message
     * @param string|null $chatId
     * @param string $parseMode
     * @return array
     */
    public function sendMessage($message, $chatId = null, $parseMode = 'HTML')
    {
        try {
            $chatId = $chatId ?? $this->chatId;

            if (!$this->botToken) {
                Log::warning('Telegram bot token not configured');
                return [
                    'success' => false,
                    'message' => 'Telegram bot token not configured'
                ];
            }

            if (!$chatId) {
                Log::warning('Telegram chat ID not configured');
                return [
                    'success' => false,
                    'message' => 'Telegram chat ID not configured'
                ];
            }

            $response = Http::timeout(10)->withOptions([
                'verify' => config('app.env') === 'production' // Skip SSL verify on dev (XAMPP issue)
            ])->post("{$this->apiUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Message sent successfully',
                    'data' => $response->json()
                ];
            }

            Log::error('Telegram API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Telegram Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while sending message',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send payment confirmation notification
     *
     * @param \App\Models\Order $order
     * @return array
     */
    public function sendPaymentConfirmation($order)
    {
        try {
            Log::info('Starting sendPaymentConfirmation', [
                'order_id' => $order->id,
                'bot_token' => !empty($this->botToken) ? 'SET' : 'NOT SET',
                'chat_id' => !empty($this->chatId) ? 'SET' : 'NOT SET'
            ]);

            // Validate configuration
            if (!$this->botToken) {
                Log::error('Telegram bot token is not configured');
                return [
                    'success' => false,
                    'message' => 'Telegram bot token not configured',
                    'error' => 'BOT_TOKEN_MISSING'
                ];
            }

            if (!$this->chatId) {
                Log::error('Telegram chat ID is not configured');
                return [
                    'success' => false,
                    'message' => 'Telegram chat ID not configured',
                    'error' => 'CHAT_ID_MISSING'
                ];
            }

            // Load relationships if not already loaded
            if (!$order->relationLoaded('user')) {
                $order->load('user');
            }
            if (!$order->relationLoaded('items')) {
                $order->load('items.book');
            }

            // Reload the order with all necessary relationships to ensure total and user are fresh
            $order->refresh();
            $order->load(['user', 'items.book']);

            // Build items list with HTML escaping for safety
            $itemsList = '';
            foreach ($order->items as $index => $item) {
                $itemNumber = $index + 1;
                $bookTitle = $item->book ? htmlspecialchars($item->book->title) : 'Unknown Book';
                $itemsList .= "\n{$itemNumber}. {$bookTitle}";
                $itemsList .= "\n   Qty: {$item->quantity} × \${$item->price} = \${$item->total}";
            }

            // Build message with HTML escaping for user inputs
            $userName = $order->user ? htmlspecialchars($order->user->name) : 'Unknown Customer';
            $userEmail = $order->user ? htmlspecialchars($order->user->email) : 'N/A';
            
            $message = "🎉 <b>New Payment Received!</b>\n\n";
            $message .= "📦 <b>Order Details:</b>\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "Order ID: <code>#{$order->id}</code>\n";
            
            if ($order->payment_transaction_id) {
                $message .= "Transaction ID: <code>" . htmlspecialchars($order->payment_transaction_id) . "</code>\n";
            }
            $message .= "\n";

            $message .= "👤 <b>Customer:</b>\n";
            $message .= "Name: {$userName}\n";
            $message .= "Email: {$userEmail}\n\n";

            $message .= "📚 <b>Items:</b>{$itemsList}\n\n";

            $message .= "💰 <b>Payment Summary:</b>\n";
            $message .= "Subtotal: \${$order->subtotal}\n";

            if ($order->discount_amount && $order->discount_amount > 0) {
                $message .= "Discount: -\${$order->discount_amount}";
                if ($order->discount_code) {
                    $message .= " (" . htmlspecialchars($order->discount_code) . ")";
                }
                $message .= "\n";
            }

            if ($order->shipping_cost && $order->shipping_cost > 0) {
                $message .= "Shipping: \${$order->shipping_cost}\n";
            }

            if ($order->tax_amount && $order->tax_amount > 0) {
                $message .= "Tax: \${$order->tax_amount}\n";
            }

            $message .= "<b>Total: \${$order->total_amount}</b>\n\n";

            $message .= "💳 <b>Payment Method:</b> " . ucfirst($order->payment_method) . "\n";
            $message .= "✅ <b>Status:</b> Paid\n";
            $message .= "📅 <b>Date:</b> " . $order->created_at->format('M d, Y H:i:s') . "\n";

            // Add shipping address if available
            if ($order->shipping_address) {
                $address = $order->shipping_address;
                if (!is_array($address)) {
                    $address = json_decode((string)$address, true);
                }

                if ($address && is_array($address)) {
                    $message .= "\n📍 <b>Shipping Address:</b>\n";

                    if (isset($address['address'])) {
                        $message .= htmlspecialchars($address['address']) . "\n";
                    }

                    if (isset($address['city']) && isset($address['state']) && isset($address['zip'])) {
                        $message .= htmlspecialchars($address['city'] . ", " . $address['state'] . " " . $address['zip']) . "\n";
                    }

                    if (isset($address['phone'])) {
                        $message .= "Phone: " . htmlspecialchars($address['phone']) . "\n";
                    }
                }
            }

            Log::info('Sending Telegram message', [
                'order_id' => $order->id,
                'message_length' => strlen($message),
                'chat_id' => $this->chatId
            ]);

            $result = $this->sendMessage($message);

            Log::info('Telegram message result', [
                'order_id' => $order->id,
                'success' => $result['success'],
                'message' => $result['message'] ?? 'N/A',
                'error' => $result['error'] ?? null
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Error sending payment confirmation: ' . $e->getMessage(), [
                'order_id' => $order->id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to send payment confirmation',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get bot information
     *
     * @return array
     */
    public function getMe()
    {
        try {
            if (!$this->botToken) {
                return [
                    'success' => false,
                    'message' => 'Telegram bot token not configured'
                ];
            }

            $response = Http::timeout(10)->get("{$this->apiUrl}/getMe");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get bot info',
                'error' => $response->json()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get updates (to find chat ID)
     *
     * @return array
     */
    public function getUpdates()
    {
        try {
            if (!$this->botToken) {
                return [
                    'success' => false,
                    'message' => 'Telegram bot token not configured'
                ];
            }

            $response = Http::timeout(10)->get("{$this->apiUrl}/getUpdates");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get updates',
                'error' => $response->json()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test the Telegram bot connection
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            $botInfo = $this->getMe();

            if (!$botInfo['success']) {
                return $botInfo;
            }

            $testMessage = "🤖 <b>Bot Connection Test</b>\n\n";
            $testMessage .= "✅ Bot is connected and working!\n";
            $testMessage .= "Bot Name: {$botInfo['data']['result']['first_name']}\n";
            $testMessage .= "Username: @{$botInfo['data']['result']['username']}\n";
            $testMessage .= "Time: " . now()->format('Y-m-d H:i:s');

            return $this->sendMessage($testMessage);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a simple payment alert message
     *
     * @param array $paymentData
     * @return array
     */
    public function sendPaymentAlert($paymentData)
    {
        try {
            $message = "💰 <b>Payment Alert</b>\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";

            if (isset($paymentData['order_id'])) {
                $message .= "Order ID: <code>#{$paymentData['order_id']}</code>\n";
            }

            if (isset($paymentData['customer_name'])) {
                $message .= "Customer: {$paymentData['customer_name']}\n";
            }

            if (isset($paymentData['amount'])) {
                $currency = $paymentData['currency'] ?? 'USD';
                $message .= "Amount: <b>{$paymentData['amount']} {$currency}</b>\n";
            }

            if (isset($paymentData['status'])) {
                $statusEmoji = $paymentData['status'] === 'completed' ? '✅' : '⏳';
                $message .= "Status: {$statusEmoji} " . ucfirst($paymentData['status']) . "\n";
            }

            if (isset($paymentData['transaction_id'])) {
                $message .= "Transaction: <code>{$paymentData['transaction_id']}</code>\n";
            }

            if (isset($paymentData['timestamp'])) {
                $message .= "Time: {$paymentData['timestamp']}\n";
            }

            $message .= "━━━━━━━━━━━━━━━━━━━━";

            return $this->sendMessage($message);

        } catch (\Exception $e) {
            Log::error('Error sending payment alert: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send payment alert',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a simple payment received message
     *
     * @param string $orderNumber
     * @param float $amount
     * @param string $currency
     * @param string|null $customerName
     * @return array
     */
    public function sendSimplePaymentNotification($orderNumber, $amount, $currency = 'USD', $customerName = null)
    {
        try {
            $message = "✅ <b>Payment Received!</b>\n\n";
            $message .= "Order: <code>#{$orderNumber}</code>\n";

            if ($customerName) {
                $message .= "Customer: {$customerName}\n";
            }

            $message .= "Amount: <b>{$amount} {$currency}</b>\n";
            $message .= "Time: " . now()->format('M d, Y H:i:s');

            return $this->sendMessage($message);

        } catch (\Exception $e) {
            Log::error('Error sending simple payment notification: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ];
        }
    }
}
