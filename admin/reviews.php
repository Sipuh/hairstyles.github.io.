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

// Обработка удаления отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $reviewId = (int)$_POST['delete_id'];
        
        // Удаляем отзыв
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        
        $response['success'] = true;
        $response['message'] = "Отзыв успешно удален!";
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Получаем статистику отзывов
$stats = [
    'total_reviews' => $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'recent_reviews' => $pdo->query("SELECT COUNT(*) FROM reviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'average_rating' => $pdo->query("SELECT AVG(rating) FROM reviews")->fetchColumn(),
    'reviews_without_comments' => $pdo->query("SELECT COUNT(*) FROM reviews WHERE comment IS NULL OR comment = ''")->fetchColumn()
];

// Получаем список отзывов
$stmt = $pdo->query("
    SELECT r.*, h.name as hairstyle_name, h.image_path 
    FROM reviews r 
    LEFT JOIN hairstyles h ON r.hairstyle_id = h.id 
    ORDER BY r.created_at DESC
");
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление отзывами - HairStyle Pro</title>
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

        /* Список отзывов */
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .review-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s;
            box-shadow: var(--shadow);
            position: relative;
        }

        .review-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .review-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .review-hairstyle {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .review-hairstyle img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
        }

        .review-hairstyle-info h4 {
            margin: 0 0 0.25rem 0;
            font-weight: 600;
            color: var(--dark);
        }

        .review-hairstyle-info p {
            margin: 0;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .review-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .stars {
            display: flex;
            gap: 0.125rem;
        }

        .stars i {
            color: #fbbf24;
        }

        .stars i.far {
            color: #d1d5db;
        }

        .review-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .review-content {
            margin-bottom: 1rem;
        }

        .review-comment {
            background: var(--light);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .review-comment p {
            margin: 0;
            line-height: 1.5;
            color: var(--dark);
        }

        .no-comment {
            color: #6b7280;
            font-style: italic;
            padding: 1rem;
            text-align: center;
            background: var(--light);
            border-radius: 8px;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Кнопки */
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
            .dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            
            .review-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .review-actions {
                position: static;
                justify-content: flex-start;
            }
            
            .review-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .admin-main {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .admin-section {
                padding: 1.5rem;
            }
            
            .review-card {
                padding: 1rem;
            }
            
            .modal-content {
                margin: 20% auto;
                padding: 1.5rem;
            }
            
            .dashboard-stats {
                grid-template-columns: 1fr;
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
                <li class="menu-item active">
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

            <!-- Статистика отзывов -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_reviews'] ?></h3>
                        <p>Всего отзывов</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['recent_reviews'] ?></h3>
                        <p>Новых за неделю</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['average_rating'], 1) ?></h3>
                        <p>Средняя оценка</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-comment-slash"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['reviews_without_comments'] ?></h3>
                        <p>Без комментариев</p>
                    </div>
                </div>
            </div>

            <!-- Список отзывов -->
            <section class="admin-section">
                <h2><i class="fas fa-comments"></i> Все отзывы (<?= count($reviews) ?>)</h2>
                
                <?php if (empty($reviews)): ?>
                    <div style="text-align: center; padding: 3rem; color: #6b7280;">
                        <i class="fas fa-comments" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 0.5rem;">Отзывов пока нет</h3>
                        <p>Когда пользователи оставят отзывы, они появятся здесь.</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-card" id="review-<?= $review['id'] ?>">
                            <div class="review-actions">
                                <button class="btn-danger delete-review-btn" 
                                        data-id="<?= $review['id'] ?>" 
                                        data-rating="<?= $review['rating'] ?>"
                                        data-hairstyle="<?= htmlspecialchars($review['hairstyle_name'] ?? 'Неизвестная прическа') ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <div class="review-header">
                                <div class="review-meta">
                                    <?php if ($review['hairstyle_name']): ?>
                                    <div class="review-hairstyle">
                                        <img src="../<?= htmlspecialchars($review['image_path'] ?? '') ?>" 
                                             alt="<?= htmlspecialchars($review['hairstyle_name']) ?>"
                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2YwZjBmMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM2NjYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj7DlyDDnNC90LXQutGB0LvQvtCyPC90ZXh0Pjwvc3ZnPg=='">
                                        <div class="review-hairstyle-info">
                                            <h4><?= htmlspecialchars($review['hairstyle_name']) ?></h4>
                                            <p>Прическа</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="review-rating">
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span><?= $review['rating'] ?>/5</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="review-content">
                                <?php if (!empty($review['comment'])): ?>
                                    <div class="review-comment">
                                        <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="no-comment">
                                        <i class="fas fa-comment-slash"></i>
                                        <span>Пользователь не оставил комментарий</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="review-footer">
                                <span>ID отзыва: #<?= $review['id'] ?></span>
                                <span>Добавлен: <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?></span>
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
            <p id="deleteMessage">Вы уверены, что хотите удалить этот отзыв?</p>
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
                if (e.target.closest('.delete-review-btn')) {
                    const button = e.target.closest('.delete-review-btn');
                    const reviewId = button.dataset.id;
                    const rating = button.dataset.rating;
                    const hairstyle = button.dataset.hairstyle;
                    
                    currentDeleteId = reviewId;
                    deleteMessage.textContent = `Вы уверены, что хотите удалить отзыв с оценкой ${rating}/5 для прически "${hairstyle}"? Это действие нельзя отменить.`;
                    deleteModal.style.display = 'block';
                }
            });

            // Подтверждение удаления
            confirmDelete.addEventListener('click', async function() {
                if (!currentDeleteId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('delete_id', currentDeleteId);
                    
                    const response = await fetch('reviews.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showAlert(result.message, 'success');
                        // Удаляем карточку из DOM
                        const card = document.getElementById(`review-${currentDeleteId}`);
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
                    showAlert('Ошибка при удалении отзыва', 'error');
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
            const cards = document.querySelectorAll('.review-card');
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