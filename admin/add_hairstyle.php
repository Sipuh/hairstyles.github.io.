<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

$error = '';
$success = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $length = trim($_POST['length'] ?? '');
    $difficulty = intval($_POST['difficulty'] ?? 1);
    $description = trim($_POST['description'] ?? '');

    // Валидация
    if (empty($name) || empty($category) || empty($length)) {
        $error = 'Пожалуйста, заполните все обязательные поля';
    } else {
        try {
            // Обработка загрузки изображения
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
                $target_path = $upload_dir . $filename;

                // Проверка типа файла
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($file_extension), $allowed_types)) {
                    throw new Exception('Разрешены только JPG, PNG, GIF и WebP файлы');
                }

                // Проверка размера файла (максимум 5MB)
                if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Размер файла не должен превышать 5MB');
                }

                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = 'uploads/' . $filename;
                } else {
                    throw new Exception('Ошибка при загрузке файла');
                }
            } else {
                $error = 'Пожалуйста, выберите изображение';
                throw new Exception($error);
            }

            // Вставка в базу данных
            $stmt = $pdo->prepare("
                INSERT INTO hairstyles (name, category, length, difficulty, image_path, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$name, $category, $length, $difficulty, $image_path, $description]);
            
            $success = 'Прическа успешно добавлена!';
            
            // Очистка полей после успешного добавления
            $name = $category = $length = $description = '';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Получаем существующие категории для подсказок
$categories = $pdo->query("SELECT DISTINCT category FROM hairstyles ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить прическу - HairStyle Pro</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-layout">
        <header class="admin-header">
            <div class="header-content">
                <h1><i class="fas fa-plus-circle"></i> Добавить прическу</h1>
                <div class="header-actions">
                    <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Администратор') ?></span>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </div>
        </header>

        <nav class="admin-sidebar">
            <ul class="admin-menu">
                <li class="menu-item">
                    <a href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Дашборд</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="hairstyles.php">
                        <i class="fas fa-cut"></i>
                        <span>Прически</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="add_hairstyle.php">
                        <i class="fas fa-plus"></i>
                        <span>Добавить прическу</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="categories.php">
                        <i class="fas fa-tags"></i>
                        <span>Категории</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reviews.php">
                        <i class="fas fa-comments"></i>
                        <span>Отзывы</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../" target="_blank">
                        <i class="fas fa-external-link-alt"></i>
                        <span>На сайт</span>
                    </a>
                </li>
            </ul>
        </nav>

        <main class="admin-main">
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="hairstyle-form">
                    <div class="form-group">
                        <label for="name">Название прически *</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Категория *</label>
                        <input type="text" id="category" name="category" list="categories" value="<?= htmlspecialchars($category ?? '') ?>" required>
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="length">Длина волос *</label>
                            <select id="length" name="length" required>
                                <option value="">Выберите длину</option>
                                <option value="short" <?= ($length ?? '') === 'short' ? 'selected' : '' ?>>Короткие</option>
                                <option value="medium" <?= ($length ?? '') === 'medium' ? 'selected' : '' ?>>Средние</option>
                                <option value="long" <?= ($length ?? '') === 'long' ? 'selected' : '' ?>>Длинные</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="difficulty">Сложность (1-5) *</label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="1" <?= ($difficulty ?? 1) == 1 ? 'selected' : '' ?>>1 - Очень легко</option>
                                <option value="2" <?= ($difficulty ?? 1) == 2 ? 'selected' : '' ?>>2 - Легко</option>
                                <option value="3" <?= ($difficulty ?? 1) == 3 ? 'selected' : '' ?>>3 - Средне</option>
                                <option value="4" <?= ($difficulty ?? 1) == 4 ? 'selected' : '' ?>>4 - Сложно</option>
                                <option value="5" <?= ($difficulty ?? 1) == 5 ? 'selected' : '' ?>>5 - Очень сложно</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="image">Изображение *</label>
                        <input type="file" id="image" name="image" accept="image/*" required>
                        <small>Разрешены: JPG, PNG, GIF, WebP (макс. 5MB)</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($description ?? '') ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Добавить прическу
                        </button>
                        <a href="hairstyles.php" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Предпросмотр изображения
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Удаляем старый предпросмотр если есть
                    const oldPreview = document.getElementById('image-preview');
                    if (oldPreview) {
                        oldPreview.remove();
                    }

                    // Создаем новый предпросмотр
                    const preview = document.createElement('div');
                    preview.id = 'image-preview';
                    preview.className = 'image-preview';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Предпросмотр">
                        <button type="button" class="remove-preview">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    this.parentNode.appendChild(preview);

                    // Обработчик удаления предпросмотра
                    preview.querySelector('.remove-preview').addEventListener('click', function() {
                        preview.remove();
                        document.getElementById('image').value = '';
                    });
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>