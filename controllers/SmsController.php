<?php
require_once __DIR__ . '/../services/SmsService.php';

class SmsController {
    private $smsService;

    public function __construct($pdo) {
        $this->smsService = new SmsService($pdo);
    }

    public function sendOrderConfirmation($order, $customer) {
        return $this->smsService->sendOrderConfirmation($order, $customer);
    }

    public function sendOrderReadyNotification($order, $customer) {
        return $this->smsService->sendOrderReadyNotification($order, $customer);
    }

    public function sendPickupReminder($order, $customer) {
        return $this->smsService->sendPickupReminder($order, $customer);
    }

    public function sendCustomNotification($data) {
        return $this->smsService->sendCustomNotification(
            $data['to'],
            $data['message'],
            $data['type'] ?? 'text'
        );
    }

    public function getNotificationHistory($orderId = null) {
        return $this->smsService->getNotificationHistory($orderId);
    }
}
?>
