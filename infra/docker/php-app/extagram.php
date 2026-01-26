<!DOCTYPE html>
<link rel="stylesheet" href="/static/style.css">

<form method="POST" enctype="multipart/form-data" action="upload.php">
    <input type="text" name="post" placeholder="Write something..." required>
    <input id="file" type="file" name="photo" accept="image/*"
        onchange="document.getElementById('preview').src=window.URL.createObjectURL(event.target.files[0])">
    <label for="file">
        <img id="preview" src="/static/preview.svg">
    </label>
    <input type="submit" value="Publish">
</form>

<?php
$db = new mysqli(
    getenv('DB_HOST') ?: 'mysql',
    getenv('DB_USER') ?: 'extagram_admin',
    getenv('DB_PASS') ?: 'pass123',
    getenv('DB_NAME') ?: 'extagram_db'
);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$result = $db->query("SELECT * FROM posts ORDER BY id DESC LIMIT 50");

while ($fila = $result->fetch_assoc()) {
    echo "<div class='post'>";
    echo "<p>" . htmlspecialchars($fila['post']) . "</p>";
    if (!empty($fila['photourl'])) {
        echo "<img src='/images/" . htmlspecialchars($fila['photourl']) . "'>";
    }
    echo "</div>";
}

$db->close();
?>
