<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Подключение к базе данных
require_once __DIR__ . '/../includes/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Функция для безопасного получения статистики
function getSafeStat($pdo, $query, $default = 0) {
    try {
        return $pdo->query($query)->fetchColumn();
    } catch (PDOException $e) {
        error_log("Ошибка при выполнении запроса: " . $e->getMessage());
        return $default;
    }
}

// Получаем статистику с обработкой ошибок
$stats = [
    'total_hairstyles' => getSafeStat($pdo, "SELECT COUNT(*) FROM hairstyles"),
    'total_reviews' => getSafeStat($pdo, "SELECT COUNT(*) FROM reviews"),
    'total_favorites' => getSafeStat($pdo, "SELECT COUNT(*) FROM favorites"),
    'recent_hairstyles' => getSafeStat($pdo, "SELECT COUNT(*) FROM hairstyles WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    'total_users' => 0, // Убрали запрос к таблице users
    'popular_hairstyle' => ['name' => 'Нет данных', 'favorites_count' => 0]
];

// Попробуем получить популярную прическу
try {
    $popular = $pdo->query("
        SELECT h.name, COUNT(f.id) as favorites_count 
        FROM hairstyles h 
        LEFT JOIN favorites f ON h.id = f.hairstyle_id 
        GROUP BY h.id 
        ORDER BY favorites_count DESC 
        LIMIT 1
    ")->fetch();
    
    if ($popular) {
        $stats['popular_hairstyle'] = $popular;
    }
} catch (PDOException $e) {
    error_log("Ошибка при получении популярной прически: " . $e->getMessage());
}

// Получаем последние прически
try {
    $recentHairstyles = $pdo->query("
        SELECT h.*, COUNT(r.id) as review_count 
        FROM hairstyles h 
        LEFT JOIN reviews r ON h.id = r.hairstyle_id 
        GROUP BY h.id 
        ORDER BY h.created_at DESC 
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении последних причесок: " . $e->getMessage());
    $recentHairstyles = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - HairStyle Pro</title>
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
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            grid-template-rows: auto 1fr;
            grid-template-areas: 
                "sidebar header"
                "sidebar main";
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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

        /* Основной контент */
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .recent-activity, .quick-actions {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2rem;
            border: 1px solid var(--border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .activity-item:hover {
            background: var(--light);
            border-color: var(--border);
        }

        .activity-image {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
        }

        .activity-info {
            flex: 1;
        }

        .activity-info h4 {
            margin: 0 0 0.25rem 0;
            font-weight: 600;
            color: var(--dark);
        }

        .activity-info p {
            margin: 0;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .activity-date {
            color: #9ca3af;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Быстрые действия */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .action-btn {
            background: white;
            border: 2px solid var(--border);
            padding: 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .action-btn:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .action-btn i {
            font-size: 2rem;
            color: var(--primary);
        }

        .action-btn span {
            font-weight: 600;
        }

        .action-btn.admin-toggle {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
        }

        .action-btn.admin-toggle i {
            color: white;
        }

        /* Популярная прическа */
        .popular-hairstyle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1.5rem;
        }

        .popular-hairstyle h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
        }

        .popular-hairstyle p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.875rem;
        }

        /* Адаптивность */
        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
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
                <li class="menu-item active">
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
                    <a href="admin.php">
                        <i class="fas fa-plus-circle"></i>
                        <span>Добавить прическу</span>
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
            <!-- Статистика -->
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
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_reviews'] ?></h3>
                        <p>Отзывов</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_favorites'] ?></h3>
                        <p>В избранном</p>
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
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_hairstyles'] + $stats['total_reviews'] ?></h3>
                        <p>Общая активность</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['popular_hairstyle']['favorites_count'] ?></h3>
                        <p>Рекорд избранного</p>
                    </div>
                </div>
            </div>

            <!-- Основной контент -->
            <div class="dashboard-content">
                <!-- Последние прически -->
                <div class="recent-activity">
                    <div class="section-header">
                        <h2>Последние прически</h2>
                        <a href="hairstyles.php" class="view-all">
                            <span>Все прически</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recentHairstyles)): ?>
                            <div class="activity-item">
                                <div class="activity-info">
                                    <h4>Нет причесок</h4>
                                    <p>Добавьте первую прическу</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentHairstyles as $hairstyle): ?>
                            <div class="activity-item">
                                <img src="../<?= htmlspecialchars($hairstyle['image_path']) ?>" 
                                     alt="<?= htmlspecialchars($hairstyle['name']) ?>"
                                     class="activity-image"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YwZjBmMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM2NjYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj7DlyDDnNC90LXQutGB0LvQvtCyPC90ZXh0Pjwvc3ZnPg=='">
                                <div class="activity-info">
                                    <h4><?= htmlspecialchars($hairstyle['name']) ?></h4>
                                    <p><?= htmlspecialchars($hairstyle['category']) ?> • <?= $hairstyle['review_count'] ?> отзывов • Сложность: <?= $hairstyle['difficulty'] ?>/5</p>
                                </div>
                                <span class="activity-date">
                                    <?= date('d.m.Y', strtotime($hairstyle['created_at'])) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Быстрые действия -->
                <div class="quick-actions">
                    <div class="section-header">
                        <h2>Быстрые действия</h2>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="admin.php" class="action-btn admin-toggle">
                            <i class="fas fa-plus-circle"></i>
                            <span>Добавить прическу</span>
                        </a>
                        <a href="hairstyles.php" class="action-btn">
                            <i class="fas fa-cut"></i>
                            <span>Управление прическами</span>
                        </a>
                        <a href="categories.php" class="action-btn">
                            <i class="fas fa-tags"></i>
                            <span>Категории</span>
                        </a>
                        <a href="reviews.php" class="action-btn">
                            <i class="fas fa-comments"></i>
                            <span>Отзывы</span>
                        </a>
                    </div>

                    <!-- Популярная прическа -->
                    <?php if ($stats['popular_hairstyle']['favorites_count'] > 0): ?>
                    <div class="popular-hairstyle">
                        <h4>⭐ Самая популярная</h4>
                        <p><?= htmlspecialchars($stats['popular_hairstyle']['name']) ?> - <?= $stats['popular_hairstyle']['favorites_count'] ?> в избранном</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Анимация появления элементов
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .activity-item, .action-btn');
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