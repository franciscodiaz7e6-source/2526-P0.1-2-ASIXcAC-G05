<?php
$host = getenv('DB_HOST') ?: 'mysql';
$db = getenv('DB_NAME') ?: 'extagram_db';
$user = getenv('DB_USER') ?: 'extagram_user';
$pass = getenv('DB_PASS') ?: 'secure_password_123';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$message = $_GET['msg'] ?? '';
$msgType = $_GET['type'] ?? 'success';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extagram Board</title>
    <link rel="stylesheet" href="/static/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-images"></i>
                <h1>Extagram Board</h1>
            </div>
        </div>
    </header>

    <main class="main-container">
        <div class="content-wrapper">
            <?php if ($message): ?>
                <div class="alert alert-<?= $msgType ?>">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <div class="new-post-card">
                <form method="POST" action="upload.php" enctype="multipart/form-data" class="post-form">
                    <div class="form-group">
                        <textarea name="post" placeholder="Escribe tu mensaje..." required maxlength="500"></textarea>
                        <small class="char-counter">0/500</small>
                    </div>
                    
                    <div class="image-upload-area">
                        <input type="file" name="photo" id="photoInput" accept=".jpg,.jpeg,.png,.gif,.webp">
                        <label for="photoInput" class="upload-label">
                            <i class="fas fa-camera"></i> Adjuntar imagen
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Publicar
                        </button>
                    </div>
                </form>
            </div>

            <div class="board-grid">
                <?php
                try {
                    $stmt = $conn->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 50");
                    $postsCount = $stmt->rowCount();
                    
                    if ($postsCount > 0) {
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $timeAgo = getTimeAgo($row['created_at']);
                ?>
                            <article class="board-card">
                                <?php if (!empty($row['photourl'])): ?>
                                    <div class="board-image-container">
                                        <img src="/uploads/<?= htmlspecialchars($row['photourl']) ?>" 
                                             alt="Imagen"
                                             class="board-image">
                                    </div>
                                <?php endif; ?>

                                <div class="board-content">
                                    <p class="board-text"><?= nl2br(htmlspecialchars($row['post'])) ?></p>
                                    <time class="board-time">
                                        <i class="far fa-clock"></i> <?= $timeAgo ?>
                                    </time>
                                </div>
                            </article>
                <?php
                        }
                    } else {
                ?>
                        <div class="no-posts">
                            <i class="fas fa-image"></i>
                            <h3>No hay publicaciones aún</h3>
                            <p>¡Sé el primero en publicar algo!</p>
                        </div>
                <?php
                    }
                } catch(PDOException $e) {
                    echo '<div class="alert alert-error">Error al cargar posts: ' . $e->getMessage() . '</div>';
                }
                $conn = null;
                ?>
            </div>
        </div>
    </main>
<script src="/static/js/app.js"></script>
</body>
</html>

<?php
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Ahora mismo';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    if ($diff < 604800) return floor($diff / 86400) . ' días';
    return date('d/m/Y', $time);
}
?>
