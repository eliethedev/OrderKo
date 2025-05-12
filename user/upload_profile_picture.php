<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Define allowed file types and max file size
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file type
$file_info = finfo_open(FILEINFO_MIME_TYPE);
$file_type = finfo_file($file_info, $_FILES['profile_picture']['tmp_name']);
finfo_close($file_info);

if (!in_array($file_type, $allowed_types)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
    exit;
}

// Validate file size
if ($_FILES['profile_picture']['size'] > $max_size) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'File size exceeds the maximum limit of 5MB.']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
$new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Move uploaded file to destination
if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

// Update user profile in database
$relative_path = 'uploads/profile_pictures/' . $new_filename;
$stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
$result = $stmt->execute([$relative_path, $_SESSION['user_id']]);

if (!$result) {
    // Delete the uploaded file if database update fails
    unlink($upload_path);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update profile picture in database.']);
    exit;
}

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'message' => 'Profile picture updated successfully.',
    'profile_picture_url' => $relative_path
]);
exit;
?>
