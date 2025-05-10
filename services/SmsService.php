<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sms_config.php';

class SmsService {
    private $config;
    private $pdo;
    private $httpClient;

    public function __construct($pdo) {
        $this->config = require __DIR__ . '/../config/sms_config.php';
        $this->pdo = $pdo;
        $this->httpClient = new GuzzleHttp\Client([
            'base_uri' => $this->config['infobip']['base_url'],
            'headers' => [
                'Authorization' => 'App ' . $this->config['infobip']['api_key'],
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    private function sendSms($to, $message, $type = 'text') {
        try {
            $response = $this->httpClient->post('/sms/2/text/advanced', [
                'json' => [
                    'messages' => [
                        [
                            'from' => $this->config['infobip']['sender_id'],
                            'to' => $to,
                            'text' => $message,
                            'type' => $type,
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            // Log the SMS
            $stmt = $this->pdo->prepare("INSERT INTO sms_notifications (phone_number, message, status, sent_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$to, $message, $result['messages'][0]['status']['name']]);

            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'details' => $result
            ];
        } catch (Exception $e) {
            // Log the error
            $stmt = $this->pdo->prepare("INSERT INTO sms_notifications (phone_number, message, status, error_message, sent_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$to, $message, 'FAILED', $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS',
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendOrderConfirmation($order, $customer) {
        $message = "Order Confirmation\n\n" .
                   "Thank you for your order at " . $order['business_name'] . "!\n" .
                   "Order ID: " . $order['id'] . "\n" .
                   "Total Amount: ₱" . number_format($order['total_amount'], 2) . "\n" .
                   "Pickup Date: " . date('F j, Y', strtotime($order['pickup_date'])) . "\n" .
                   "\nPlease arrive at the scheduled time for pickup.";

        return $this->sendSms($customer['phone_number'], $message);
    }

    public function sendOrderReadyNotification($order, $customer) {
        $message = "Order Ready for Pickup\n\n" .
                   "Your order at " . $order['business_name'] . " is ready!\n" .
                   "Order ID: " . $order['id'] . "\n" .
                   "Pickup Time: " . date('h:i A', strtotime($order['pickup_date'])) . "\n" .
                   "\nPlease show this message when picking up your order.";

        return $this->sendSms($customer['phone_number'], $message);
    }

    public function sendPickupReminder($order, $customer) {
        $message = "Pickup Reminder\n\n" .
                   "Reminder: Your order at " . $order['business_name'] . " is ready!\n" .
                   "Order ID: " . $order['id'] . "\n" .
                   "Pickup Time: " . date('h:i A', strtotime($order['pickup_date'])) . "\n" .
                   "Location: " . $order['business_address'] . "\n" .
                   "\nDon't forget to pick up your order.";

        return $this->sendSms($customer['phone_number'], $message);
    }

    public function sendCustomNotification($to, $message, $type = 'text') {
        return $this->sendSms($to, $message, $type);
    }

    public function getNotificationHistory($orderId = null) {
        $sql = "SELECT * FROM sms_notifications WHERE 1=1";
        $params = [];

        if ($orderId) {
            $sql .= " AND order_id = ?";
            $params[] = $orderId;
        }

        $sql .= " ORDER BY sent_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
