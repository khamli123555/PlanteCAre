<?php
session_start();
require_once 'config.php';

echo "<pre>";
echo "=== SESSION DATA ===\n";
print_r($_SESSION);
echo "\n";

if (isset($_SESSION['user']['id_utilisateur'])) {
    echo "=== DATABASE USER DATA ===\n";
    try {
        $stmt = $conn->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$_SESSION['user']['id_utilisateur']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        print_r($user);
        
        if ($user && $user['photo_profil']) {
            echo "\n=== PHOTO FILE CHECK ===\n";
            $base_path = UPLOAD_DIR_PROFILES . $user['photo_profil'];
            $jpg_path = $base_path . '.jpg';
            
            echo "Photo from DB: " . $user['photo_profil'] . "\n";
            echo "Base path exists: " . (file_exists($base_path) ? 'yes' : 'no') . "\n";
            echo "JPG path exists: " . (file_exists($jpg_path) ? 'yes' : 'no') . "\n";
            
            echo "\nFull directory listing of " . UPLOAD_DIR_PROFILES . ":\n";
            $files = scandir(UPLOAD_DIR_PROFILES);
            print_r($files);
            
            echo "\nAbsolute path: " . realpath(UPLOAD_DIR_PROFILES) . "\n";
            echo "Current script directory: " . __DIR__ . "\n";
        }
    } catch(PDOException $e) {
        echo "Database Error: " . $e->getMessage() . "\n";
    }
}

// Test direct file access
echo "\n=== DIRECT FILE ACCESS TEST ===\n";
$test_file = "uploads/profiles/445809aa6f675b6da1ff924e2a1cb9c0ec8f7301.jpg";
echo "Test file exists: " . (file_exists($test_file) ? 'yes' : 'no') . "\n";
echo "Test file readable: " . (is_readable($test_file) ? 'yes' : 'no') . "\n";
if (file_exists($test_file)) {
    echo "File permissions: " . substr(sprintf('%o', fileperms($test_file)), -4) . "\n";
    echo "File owner: " . fileowner($test_file) . "\n";
    echo "File group: " . filegroup($test_file) . "\n";
}

// Test directory permissions
echo "\n=== DIRECTORY PERMISSIONS ===\n";
$upload_dir = "uploads";
$profiles_dir = "uploads/profiles";
echo "Upload dir exists: " . (is_dir($upload_dir) ? 'yes' : 'no') . "\n";
echo "Profiles dir exists: " . (is_dir($profiles_dir) ? 'yes' : 'no') . "\n";
if (is_dir($upload_dir)) {
    echo "Upload dir permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "\n";
}
if (is_dir($profiles_dir)) {
    echo "Profiles dir permissions: " . substr(sprintf('%o', fileperms($profiles_dir)), -4) . "\n";
}

echo "</pre>";
?> 