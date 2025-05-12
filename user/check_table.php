<?php
require_once '../config/database.php';

try {
    // Check addresses table structure
    $stmt = $pdo->prepare("DESCRIBE addresses");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Addresses Table Structure</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
