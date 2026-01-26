<?php
if (!empty($_POST["post"])) {
    $photoid = '';
    
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] == 0) {
        $photoid = uniqid() . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['photo']['tmp_name'], '/uploads/' . $photoid);
    }

    $db = new mysqli(
        getenv('DB_HOST') ?: 'mysql',
        getenv('DB_USER') ?: 'extagram_admin',
        getenv('DB_PASS') ?: 'pass123',
        getenv('DB_NAME') ?: 'extagram_db'
    );

    $stmt = $db->prepare("INSERT INTO posts (post, photourl) VALUES (?, ?)");
    $stmt->bind_param("ss", $_POST["post"], $photoid);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

header("Location: /");
?>
