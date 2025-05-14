<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

// Validate coordinates
if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    exit;
}

try {
    // Store coordinates in session
    $_SESSION['user_latitude'] = $latitude;
    $_SESSION['user_longitude'] = $longitude;
    
    // Try to get location name using reverse geocoding
    $location_name = 'Unknown Location';
    
    // Try to get location name from Google Maps API (if you have an API key)
    // For now, we'll use a simple approximation for Philippines
    if ($latitude >= 4.5 && $latitude <= 21.5 && $longitude >= 116 && $longitude <= 127) {
        // Get nearest city using a simple database query
        $stmt = $pdo->prepare("SELECT name, 
                              ROUND((
                                  6371 * acos(
                                      cos(radians(?)) * 
                                      cos(radians(latitude)) * 
                                      cos(radians(longitude) - radians(?)) + 
                                      sin(radians(?)) * 
                                      sin(radians(latitude))
                                  )
                              ), 1) as distance
                              FROM cities 
                              WHERE country_code = 'PH'
                              ORDER BY distance ASC
                              LIMIT 1");
        
        $stmt->execute([$latitude, $longitude, $latitude]);
        $city = $stmt->fetch();
        
        if ($city) {
            $location_name = $city['name'] . ', Philippines';
        } else {
            $location_name = 'Philippines';
        }
    }
    
    // Store location name in session
    $_SESSION['user_location'] = $location_name;
    
    // Check if the users table has the location columns
    try {
        // Update user's location in database (optional)
        $stmt = $pdo->prepare("UPDATE users SET latitude = ?, longitude = ?, last_location = ? WHERE id = ?");
        $stmt->execute([$latitude, $longitude, $location_name, $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // If columns don't exist, just ignore the database update
        // The location will still be stored in the session
        // Log the error for debugging purposes
        error_log('Could not update user location in database: ' . $e->getMessage());
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Location updated successfully', 'location' => $location_name]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating location: ' . $e->getMessage()]);
}
?>
