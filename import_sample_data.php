<?php
// Import sample data into the database
require_once 'config/database.php';

echo "<h1>Importing Sample Data</h1>";

try {
    // Read the SQL file
    $sql = file_get_contents('sample_data.sql');
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');
    
    // Execute each statement
    $successCount = 0;
    foreach ($statements as $statement) {
        $result = $pdo->exec($statement);
        if ($result !== false) {
            $successCount++;
            echo "<p style='color: green;'>Successfully executed: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
        } else {
            echo "<p style='color: red;'>Error executing: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
            echo "<p>Error info: " . print_r($pdo->errorInfo(), true) . "</p>";
        }
    }
    
    echo "<h2>Import Complete</h2>";
    echo "<p>Successfully executed $successCount out of " . count($statements) . " statements.</p>";
    echo "<p><a href='user/businesses.php'>Go to Businesses Page</a></p>";
    
} catch (PDOException $e) {
    echo "<h2>Error</h2>";
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>
