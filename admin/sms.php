<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Get all users with phone numbers
$stmt = $pdo->query("SELECT id, full_name, phone_number, email FROM users WHERE phone_number IS NOT NULL AND phone_number != '' ORDER BY full_name");
$users = $stmt->fetchAll();

// Handle SMS sending
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_POST['user_id'];
    $message = $_POST['message'];

    // Sanitize message
    $message = preg_replace('/[^\x20-\x7E]/', '', $message); // Only printable ASCII

    // Get user details
    $stmt = $pdo->prepare("SELECT full_name, phone_number FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        // Format phone number
        $raw_number = preg_replace('/[^0-9]/', '', $user['phone_number']);
        if (substr($raw_number, 0, 1) === '0') {
            $phone_number = '+63' . substr($raw_number, 1);
        } elseif (substr($raw_number, 0, 2) === '63') {
            $phone_number = '+' . $raw_number;
        } elseif (substr($raw_number, 0, 3) !== '+63') {
            $phone_number = '+63' . $raw_number;
        } else {
            $phone_number = $raw_number;
        }

        // Prepare data for PhilSMS
        $send_data = [
            "sender_id" => "PhilSMS", // Or your registered Sender ID
            "recipient" => $phone_number,
            "message" => $message
        ];
        $token = "1847|Zk0AT8WIbd72MXKWzNleRsFaT89hVxBs8bxQJ52n"; // Replace with your actual PhilSMS token

        $parameters = json_encode($send_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://app.philsms.com/api/v3/sms/send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        // Log full API response
        error_log("PhilSMS API Response: " . json_encode($result));

        if (isset($result['status']) && $result['status'] === 'success') {
            $stmt = $pdo->prepare("INSERT INTO sms_logs (user_id, admin_id, message, status, sent_at) VALUES (?, ?, ?, 'sent', NOW())");
            $stmt->execute([$userId, $_SESSION['user_id'], $message]);

            $_SESSION['message'] = "SMS sent successfully to " . $user['full_name'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO sms_logs (user_id, admin_id, message, status, sent_at) VALUES (?, ?, ?, 'failed', NOW())");
            $stmt->execute([$userId, $_SESSION['user_id'], $message]);

            $_SESSION['message'] = "Failed to send SMS: " . ($result['message'] ?? 'Unknown error');
        }

        header('Location: sms.php');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Management - OrderKo</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sms-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sms-form {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .user-select {
            flex: 1;
        }
        .message-box {
            flex: 2;
        }
        .user-select select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .message-box textarea {
            width: 100%;
            height: 100px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
        }
        .send-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .send-button:hover {
            background-color: #45a049;
        }
        .sms-history {
            margin-top: 20px;
        }
        .sms-history h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .sms-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sms-table th,
        .sms-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .sms-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-badge.sent {
            background: #4CAF50;
            color: white;
        }
        .status-badge.failed {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>SMS Management</h1>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($admin['full_name']); ?></span>
                </div>
            </div>

            <!-- Message Alert -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert success">
                    <?php 
                        echo htmlspecialchars($_SESSION['message']);
                        unset($_SESSION['message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- SMS Sending Form -->
            <div class="sms-container">
                <h3>Send SMS</h3>
                <form method="POST" class="sms-form">
                    <div class="user-select">
                        <label for="user_id">Select User:</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">Select a user...</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?> - <?php echo htmlspecialchars($user['phone_number']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="message-box">
                        <label for="message">Message:</label>
                        <textarea name="message" id="message" required placeholder="Enter your message here..." maxlength="160"></textarea>
                    </div>
                    <button type="submit" class="send-button">
                        <i class="fas fa-paper-plane"></i> Send SMS
                    </button>
                </form>
            </div>

            <!-- SMS History -->
            <div class="sms-container sms-history">
                <h3>SMS History</h3>
                <div class="table-responsive">
                    <table class="sms-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Sent At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get recent SMS logs
                            $stmt = $pdo->query("SELECT 
                                users.full_name,
                                sms_logs.message,
                                sms_logs.status,
                                sms_logs.sent_at
                                FROM sms_logs
                                JOIN users ON sms_logs.user_id = users.id
                                ORDER BY sms_logs.sent_at DESC
                                LIMIT 50");
                            $smsLogs = $stmt->fetchAll();
                            
                            foreach ($smsLogs as $log):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($log['message']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($log['sent_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>