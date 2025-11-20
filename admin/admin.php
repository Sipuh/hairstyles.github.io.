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

// Обработка удаления прически
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $hairstyleId = (int)$_POST['delete_id'];
        
        // Получаем информацию о прическе для удаления файла
        $stmt = $pdo->prepare("SELECT image_path FROM hairstyles WHERE id = ?");
        $stmt->execute([$hairstyleId]);
        $hairstyle = $stmt->fetch();
        
        if (!$hairstyle) {
            throw new Exception("Прическа не найдена");
        }
        
        // Удаляем связанные отзывы
        $pdo->prepare("DELETE FROM reviews WHERE hairstyle_id = ?")->execute([$hairstyleId]);
        
        // Удаляем из избранного
        $pdo->prepare("DELETE FROM favorites WHERE hairstyle_id = ?")->execute([$hairstyleId]);
        
        // Удаляем прическу
        $stmt = $pdo->prepare("DELETE FROM hairstyles WHERE id = ?");
        $stmt->execute([$hairstyleId]);
        
        // Удаляем файл изображения
        $imagePath = '../' . $hairstyle['image_path'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        $response['success'] = true;
        $response['message'] = "Прическа успешно удалена!";
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Обработка добавления прически
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Проверяем обязательные поля
        $required = ['name', 'category', 'length', 'difficulty'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Поле {$field} обязательно для заполнения");
            }
        }

        // Проверяем загрузку файла
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Ошибка загрузки изображения");
        }

        $uploadedFile = $_FILES['image'];
        
        // Проверяем тип файла
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($uploadedFile['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Недопустимый тип файла. Разрешены: JPEG, PNG, GIF, WebP");
        }

        // Проверяем размер файла (максимум 5MB)
        if ($uploadedFile['size'] > 5 * 1024 * 1024) {
            throw new Exception("Файл слишком большой. Максимальный размер: 5MB");
        }

        // Создаем папку uploads если не существует
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерируем уникальное имя файла
        $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        // Сохраняем файл
        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            throw new Exception("Ошибка сохранения файла");
        }

        // Подготавливаем данные для базы
        $name = trim($_POST['name']);
        $category = $_POST['category'];
        $length = $_POST['length'];
        $difficulty = (int)$_POST['difficulty'];
        $description = trim($_POST['description'] ?? '');
        $imagePath = 'uploads/' . $fileName; // Путь для базы данных

        // Вставляем данные в базу
        $stmt = $pdo->prepare(
            "INSERT INTO hairstyles (name, category, length, difficulty, image_path, description, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $stmt->execute([$name, $category, $length, $difficulty, $imagePath, $description]);
        $hairstyleId = $pdo->lastInsertId();

        $response['success'] = true;
        $response['message'] = "Прическа успешно добавлена!";
        $response['id'] = $hairstyleId;

    } catch (Exception $e) {
        // Удаляем загруженный файл при ошибке
        if (isset($filePath) && file_exists($filePath)) {
            unlink($filePath);
        }
        
        $response['message'] = $e->getMessage();
    }

    // Отправляем JSON ответ
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Получаем список причесок для отображения
$stmt = $pdo->query(
    "SELECT h.*, COUNT(r.id) as review_count
     FROM hairstyles h
     LEFT JOIN reviews r ON h.id = r.hairstyle_id
     GROUP BY h.id 
     ORDER BY h.created_at DESC"
);
$hairstyles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление прическами - HairStyle Pro</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            grid-template-rows: auto 1fr;
            grid-template-areas: 
                "sidebar header"
                "sidebar main";
            min-height: 100vh;
        }

        .admin-header {
            grid-area: header;
            background: white;
            box-shadow: var(--shadow);
            z-index: 10;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .header-content h1 {
            color: var(--dark);
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-content h1 i {
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .admin-name {
            font-weight: 600;
            color: var(--dark);
            padding: 0.5rem 1rem;
            background: var(--light);
            border-radius: 8px;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .admin-sidebar {
            grid-area: sidebar;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem 0;
        }

        .admin-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu-item {
            margin-bottom: 0.5rem;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .menu-item a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: rgba(255, 255, 255, 0.3);
        }

        .menu-item.active a {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: white;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        .admin-main {
            grid-area: main;
            padding: 2rem;
            overflow-y: auto;
        }

        /* Контейнеры и секции */
        .admin-section {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .admin-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-section h2 i {
            color: var(--primary);
        }

        /* Формы */
        .hairstyle-form {
            max-width: 600px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .file-preview {
            margin-top: 0.5rem;
            border-radius: 8px;
            overflow: hidden;
            max-width: 200px;
            display: none;
            border: 2px solid var(--border);
        }

        .file-preview img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Кнопки */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 2px solid var(--border);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        /* Сетка причесок */
        .hairstyles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .hairstyle-admin-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            transition: all 0.3s;
            box-shadow: var(--shadow);
            position: relative;
        }

        .hairstyle-admin-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .hairstyle-admin-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        .hairstyle-admin-card h4 {
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
            padding-right: 2rem;
        }

        .hairstyle-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .hairstyle-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .hairstyle-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        /* Уведомления */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Вспомогательные тексты */
        small {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Адаптивность */
        @media (max-width: 1024px) {
            .hairstyles-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .admin-layout {
                grid-template-columns: 1fr;
                grid-template-areas: 
                    "header"
                    "main";
            }
            
            .admin-sidebar {
                display: none;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .hairstyles-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-main {
                padding: 1rem;
            }
            
            .hairstyle-actions {
                position: static;
                margin-top: 1rem;
                justify-content: flex-start;
            }
        }

        @media (max-width: 480px) {
            .admin-section {
                padding: 1.5rem;
            }
            
            .hairstyle-admin-card {
                padding: 1rem;
            }
            
            .modal-content {
                margin: 20% auto;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <header class="admin-header">
            <div class="header-content">
                <h1><i class="fas fa-crown"></i> Панель администратора</h1>
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
                    <a href="admin.php">
                        <i class="fas fa-plus-circle"></i>
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
            <!-- Уведомления -->
            <div id="alert" class="alert"></div>

            <!-- Форма добавления прически -->
            <section class="admin-section">
                <h2><i class="fas fa-plus-circle"></i> Добавить новую прическу</h2>
                <form id="addHairstyleForm" class="hairstyle-form" enctype="multipart/form-data" method="POST">
                    <div class="form-group">
                        <label for="name">Название прически *</label>
                        <input type="text" id="name" name="name" required 
                               placeholder="Введите название прически">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Категория *</label>
                            <select id="category" name="category" required>
                                <option value="">Выберите категорию</option>
                                <option value="women">Женские</option>
                                <option value="men">Мужские</option>
                                <option value="wedding">Свадебные</option>
                                <option value="evening">Вечерние</option>
                                <option value="casual">Повседневные</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="length">Длина волос *</label>
                            <select id="length" name="length" required>
                                <option value="">Выберите длину</option>
                                <option value="short">Короткие</option>
                                <option value="medium">Средние</option>
                                <option value="long">Длинные</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="difficulty">Сложность *</label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="">Выберите сложность</option>
                                <option value="1">1 - Очень легко</option>
                                <option value="2">2 - Легко</option>
                                <option value="3">3 - Средне</option>
                                <option value="4">4 - Сложно</option>
                                <option value="5">5 - Очень сложно</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="image">Изображение *</label>
                        <input type="file" id="image" name="image" accept="image/*" required>
                        <small>Разрешены: JPG, PNG, GIF, WebP (максимум 5MB)</small>
                        <div class="file-preview" id="filePreview"></div>
                    </div>

                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description" rows="4" 
                                  placeholder="Описание прически..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-plus"></i> Добавить прическу
                    </button>
                </form>
            </section>

            <!-- Список причесок -->
            <section class="admin-section">
                <h2><i class="fas fa-cut"></i> Все прически (<?= count($hairstyles) ?>)</h2>
                <div class="hairstyles-grid" id="hairstylesContainer">
                    <?php if (empty($hairstyles)): ?>
                        <div style="text-align: center; padding: 2rem; color: #6b7280;">
                            <i class="fas fa-cut" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Пока нет причесок. Добавьте первую!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($hairstyles as $hairstyle): ?>
                        <div class="hairstyle-admin-card" id="hairstyle-<?= $hairstyle['id'] ?>">
                            <div class="hairstyle-actions">
                                <button class="btn-danger delete-btn" data-id="<?= $hairstyle['id'] ?>" data-name="<?= htmlspecialchars($hairstyle['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <img src="../<?= htmlspecialchars($hairstyle['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($hairstyle['name']) ?>"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+'">
                            <h4><?= htmlspecialchars($hairstyle['name']) ?></h4>
                            <div class="hairstyle-meta">
                                <span><i class="fas fa-tag"></i> <?= htmlspecialchars($hairstyle['category']) ?></span>
                                <span><i class="fas fa-star"></i> <?= $hairstyle['difficulty'] ?>/5</span>
                                <span><i class="fas fa-comment"></i> <?= $hairstyle['review_count'] ?></span>
                            </div>
                            <?php if (!empty($hairstyle['description'])): ?>
                                <p style="font-size: 0.9rem; color: #6b7280; margin-bottom: 0.5rem; line-height: 1.4;">
                                    <?= htmlspecialchars($hairstyle['description']) ?>
                                </p>
                            <?php endif; ?>
                            <div class="hairstyle-meta">
                                <small><i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($hairstyle['created_at'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Подтверждение удаления</h3>
            <p id="deleteMessage">Вы уверены, что хотите удалить эту прическу?</p>
            <div class="modal-actions">
                <button id="confirmDelete" class="btn-danger">Удалить</button>
                <button id="cancelDelete" class="btn-secondary">Отмена</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addHairstyleForm');
            const alertDiv = document.getElementById('alert');
            const submitBtn = document.getElementById('submitBtn');
            const fileInput = document.getElementById('image');
            const filePreview = document.getElementById('filePreview');
            const deleteModal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmDelete = document.getElementById('confirmDelete');
            const cancelDelete = document.getElementById('cancelDelete');

            let currentDeleteId = null;

            // Предпросмотр изображения
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        filePreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        filePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    filePreview.style.display = 'none';
                }
            });

            // Обработка отправки формы добавления
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                // Показываем загрузку
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Добавление...';
                form.classList.add('loading');
                
                try {
                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showAlert(result.message, 'success');
                        form.reset();
                        filePreview.style.display = 'none';
                        
                        // Обновляем список причесок через 1 секунду
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showAlert(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showAlert('Ошибка при отправке формы', 'error');
                } finally {
                    // Восстанавливаем кнопку
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-plus"></i> Добавить прическу';
                    form.classList.remove('loading');
                }
            });

            // Обработка кликов по кнопкам удаления
            document.addEventListener('click', function(e) {
                if (e.target.closest('.delete-btn')) {
                    const button = e.target.closest('.delete-btn');
                    const hairstyleId = button.dataset.id;
                    const hairstyleName = button.dataset.name;
                    
                    currentDeleteId = hairstyleId;
                    deleteMessage.textContent = `Вы уверены, что хотите удалить прическу "${hairstyleName}"? Это действие нельзя отменить.`;
                    deleteModal.style.display = 'block';
                }
            });

            // Подтверждение удаления
            confirmDelete.addEventListener('click', async function() {
                if (!currentDeleteId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('delete_id', currentDeleteId);
                    
                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showAlert(result.message, 'success');
                        // Удаляем карточку из DOM
                        const card = document.getElementById(`hairstyle-${currentDeleteId}`);
                        if (card) {
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(100px)';
                            setTimeout(() => {
                                card.remove();
                                // Обновляем счетчик
                                const countElement = document.querySelector('.admin-section h2');
                                const currentCount = parseInt(countElement.textContent.match(/\d+/)[0]);
                                countElement.innerHTML = countElement.innerHTML.replace(
                                    `(${currentCount})`, 
                                    `(${currentCount - 1})`
                                );
                            }, 300);
                        }
                    } else {
                        showAlert(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showAlert('Ошибка при удалении прически', 'error');
                } finally {
                    deleteModal.style.display = 'none';
                    currentDeleteId = null;
                }
            });

            // Отмена удаления
            cancelDelete.addEventListener('click', function() {
                deleteModal.style.display = 'none';
                currentDeleteId = null;
            });

            // Закрытие модального окна при клике вне его
            window.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    deleteModal.style.display = 'none';
                    currentDeleteId = null;
                }
            });

            function showAlert(message, type) {
                alertDiv.textContent = message;
                alertDiv.className = `alert alert-${type}`;
                alertDiv.style.display = 'block';
                
                // Скрываем уведомление через 5 секунд
                setTimeout(() => {
                    alertDiv.style.display = 'none';
                }, 5000);
            }

            // Анимация появления элементов
            const cards = document.querySelectorAll('.hairstyle-admin-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>