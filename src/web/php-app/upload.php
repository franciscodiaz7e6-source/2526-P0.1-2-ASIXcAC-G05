<?php
if (!empty($_POST["post"])) {
    // Variables de entorno
    $host = getenv('DB_HOST') ?: 'mysql';
    $db = getenv('DB_NAME') ?: 'extagram_db';
    $user = getenv('DB_USER') ?: 'extagram_user';
    $pass = getenv('DB_PASS') ?: 'secure_password_123';
    
    $photoid = '';
    
    // Procesar foto si existe
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $uploadDir = '/uploads/';
        $photoid = uniqid() . '.jpg';
        $target = $uploadDir . $photoid;
        
        // Log para debugging
        error_log("Upload attempt: " . $_FILES['photo']['tmp_name'] . " to " . $target);
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
            chmod($target, 0644);
            error_log("Upload SUCCESS: $photoid");
        } else {
            error_log("Upload FAIL for: " . $_FILES['photo']['tmp_name']);
            $photoid = '';
        }
    }
    
    // Guardar en base de datos
    try {
        $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("INSERT INTO posts (post, photourl) VALUES (:post, :photourl)");
        $stmt->execute([
            ':post' => $_POST["post"],
            ':photourl' => $photoid
        ]);
        
        error_log("Post saved successfully. ID: " . $conn->lastInsertId());
        
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
    
    $conn = null;
}

header("Location: /extagram.php");
exit;
?>
