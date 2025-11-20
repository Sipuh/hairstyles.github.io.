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

// Получаем статистику причесок
$stats = [
    'total_hairstyles' => $pdo->query("SELECT COUNT(*) FROM hairstyles")->fetchColumn(),
    'women_hairstyles' => $pdo->query("SELECT COUNT(*) FROM hairstyles WHERE category = 'women'")->fetchColumn(),
    'men_hairstyles' => $pdo->query("SELECT COUNT(*) FROM hairstyles WHERE category = 'men'")->fetchColumn(),
    'wedding_hairstyles' => $pdo->query("SELECT COUNT(*) FROM hairstyles WHERE category = 'wedding'")->fetchColumn(),
    'recent_hairstyles' => $pdo->query("SELECT COUNT(*) FROM hairstyles WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'average_difficulty' => $pdo->query("SELECT AVG(difficulty) FROM hairstyles")->fetchColumn()
];

// Получаем параметры фильтрации и поиска
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$length = $_GET['length'] ?? '';
$difficulty = $_GET['difficulty'] ?? '';

// Формируем SQL запрос с фильтрами
$sql = "
    SELECT h.*, 
           COUNT(r.id) as review_count
    FROM hairstyles h 
    LEFT JOIN reviews r ON h.id = r.hairstyle_id 
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (h.name LIKE ? OR h.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $sql .= " AND h.category = ?";
    $params[] = $category;
}

if (!empty($length)) {
    $sql .= " AND h.length = ?";
    $params[] = $length;
}

if (!empty($difficulty)) {
    $sql .= " AND h.difficulty = ?";
    $params[] = $difficulty;
}

$sql .= " GROUP BY h.id ORDER BY h.created_at DESC";

// Выполняем запрос
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hairstyles = $stmt->fetchAll();

// Получаем уникальные категории для фильтра
$categories = $pdo->query("SELECT DISTINCT category FROM hairstyles ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
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

        /* Статистика */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s;
            border: 1px solid var(--border);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }

        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card:nth-child(6) .stat-icon { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }

        .stat-icon i {
            color: white;
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }

        .stat-info p {
            margin: 0.25rem 0 0 0;
            color: #6b7280;
            font-weight: 500;
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

        /* Фильтры */
        .filters {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
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
            text-decoration: none;
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
            cursor: pointer;
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
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .hairstyle-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--light);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }

        .hairstyle-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .hairstyle-stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: #6b7280;
        }

        .hairstyle-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .hairstyle-description {
            font-size: 0.9rem;
            color: #6b7280;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .hairstyle-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
            font-size: 0.75rem;
            color: #9ca3af;
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

        /* Адаптивность */
        @media (max-width: 1024px) {
            .hairstyles-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .filters-form {
                grid-template-columns: 1fr;
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
            
            .dashboard-stats {
                grid-template-columns: 1fr;
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
            
            .filters {
                padding: 1rem;
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
                <li class="menu-item active">
                    <a href="hairstyles.php">
                        <i class="fas fa-cut"></i>
                        <span>Прически</span>
                    </a>
                </li>
                <li class="menu-item">
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

            <!-- Статистика причесок -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-cut"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_hairstyles'] ?></h3>
                        <p>Всего причесок</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-female"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['women_hairstyles'] ?></h3>
                        <p>Женские прически</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-male"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['men_hairstyles'] ?></h3>
                        <p>Мужские прически</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-ring"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['wedding_hairstyles'] ?></h3>
                        <p>Свадебные прически</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['recent_hairstyles'] ?></h3>
                        <p>Новых за неделю</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['average_difficulty'], 1) ?></h3>
                        <p>Средняя сложность</p>
                    </div>
                </div>
            </div>

            <!-- Фильтры и поиск -->
            <section class="admin-section">
                <h2><i class="fas fa-filter"></i> Фильтры и поиск</h2>
                <div class="filters">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <label for="search">Поиск по названию</label>
                            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Введите название прически...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="category">Категория</label>
                            <select id="category" name="category">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="length">Длина волос</label>
                            <select id="length" name="length">
                                <option value="">Все длины</option>
                                <option value="short" <?= $length === 'short' ? 'selected' : '' ?>>Короткие</option>
                                <option value="medium" <?= $length === 'medium' ? 'selected' : '' ?>>Средние</option>
                                <option value="long" <?= $length === 'long' ? 'selected' : '' ?>>Длинные</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="difficulty">Сложность</label>
                            <select id="difficulty" name="difficulty">
                                <option value="">Любая сложность</option>
                                <option value="1" <?= $difficulty === '1' ? 'selected' : '' ?>>1 - Очень легко</option>
                                <option value="2" <?= $difficulty === '2' ? 'selected' : '' ?>>2 - Легко</option>
                                <option value="3" <?= $difficulty === '3' ? 'selected' : '' ?>>3 - Средне</option>
                                <option value="4" <?= $difficulty === '4' ? 'selected' : '' ?>>4 - Сложно</option>
                                <option value="5" <?= $difficulty === '5' ? 'selected' : '' ?>>5 - Очень сложно</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Применить
                            </button>
                            <a href="hairstyles.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Сбросить
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Список причесок -->
            <section class="admin-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2><i class="fas fa-cut"></i> Все прически (<?= count($hairstyles) ?>)</h2>
                    <a href="admin.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Добавить прическу
                    </a>
                </div>
                
                <?php if (empty($hairstyles)): ?>
                    <div style="text-align: center; padding: 3rem; color: #6b7280;">
                        <i class="fas fa-cut" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 0.5rem;">Прически не найдены</h3>
                        <p>Попробуйте изменить параметры фильтра или добавьте первую прическу.</p>
                    </div>
                <?php else: ?>
                    <div class="hairstyles-grid">
                        <?php foreach ($hairstyles as $hairstyle): ?>
                        <div class="hairstyle-admin-card" id="hairstyle-<?= $hairstyle['id'] ?>">
                            <div class="hairstyle-actions">
                                <button class="btn-danger delete-btn" 
                                        data-id="<?= $hairstyle['id'] ?>" 
                                        data-name="<?= htmlspecialchars($hairstyle['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <img src="../<?= htmlspecialchars($hairstyle['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($hairstyle['name']) ?>"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+'">
                            
                            <h4><?= htmlspecialchars($hairstyle['name']) ?></h4>
                            
                            <div class="hairstyle-meta">
                                <span><i class="fas fa-tag"></i> <?= htmlspecialchars($hairstyle['category']) ?></span>
                                <span><i class="fas fa-ruler"></i> <?= htmlspecialchars($hairstyle['length']) ?></span>
                                <span><i class="fas fa-star"></i> <?= $hairstyle['difficulty'] ?>/5</span>
                            </div>
                            
                            <div class="hairstyle-stats">
                                <div class="hairstyle-stat">
                                    <i class="fas fa-comment"></i>
                                    <span><?= $hairstyle['review_count'] ?> отзывов</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($hairstyle['description'])): ?>
                                <div class="hairstyle-description">
                                    <?= htmlspecialchars($hairstyle['description']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="hairstyle-footer">
                                <span>ID: #<?= $hairstyle['id'] ?></span>
                                <span><?= date('d.m.Y', strtotime($hairstyle['created_at'])) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
            const alertDiv = document.getElementById('alert');
            const deleteModal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmDelete = document.getElementById('confirmDelete');
            const cancelDelete = document.getElementById('cancelDelete');

            let currentDeleteId = null;

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
                    
                    const response = await fetch('hairstyles.php', {
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