<?php
require_once 'includes/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Получаем популярные прически для главной страницы
$stmt = $pdo->query(
    "SELECT h.*, 
            GROUP_CONCAT(t.name) as tags,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as review_count
     FROM hairstyles h
     LEFT JOIN hairstyle_tags ht ON h.id = ht.hairstyle_id
     LEFT JOIN tags t ON ht.tag_id = t.id
     LEFT JOIN reviews r ON h.id = r.hairstyle_id
     GROUP BY h.id 
     ORDER BY h.created_at DESC 
     LIMIT 8"
);
$featuredHairstyles = $stmt->fetchAll();

// Форматируем теги
foreach ($featuredHairstyles as &$hairstyle) {
    $hairstyle['tags'] = $hairstyle['tags'] ? explode(',', $hairstyle['tags']) : [];
    $hairstyle['avg_rating'] = round($hairstyle['avg_rating'], 1);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HairStyle Pro - Каталог причесок</title>
    <meta name="description" content="Каталог стильных причесок для любого случая. Найдите свой идеальный образ среди тысяч вариантов.">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        
        .toast {
            position: fixed;
            top: 100px;
            right: 20px;
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
            z-index: 1000;
            font-weight: 500;
            font-size: 14px;
            max-width: 300px;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            border-left: 4px solid #A78BFA;
        }

        .toast:not(.hidden) {
            transform: translateX(0);
        }

        .toast.hidden {
            transform: translateX(400px);
        }

        .HAIR-LOGO {
            color: black;
        }
        .menu-nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #374151;
            transition: all 0.3s ease;
            background: white;
            border: 1px solid #E5E7EB;
        }

        .menu-item:hover {
            background: #F3F4F6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            color: #8B5CF6;
            font-size: 18px;
        }

       
        .profile-content,
        .settings-content,
        .about-content {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
            margin-bottom: 24px;
        }

        .profile-avatar {
            font-size: 80px;
            color: #8B5CF6;
            margin-bottom: 16px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 24px;
        }

        .stat-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #F3F4F6;
        }

        .stat-number {
            display: block;
            font-size: 32px;
            font-weight: 700;
            color: #8B5CF6;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6B7280;
            font-size: 14px;
        }

        .login-btn {
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 16px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
        }

        .settings-group {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
        }

        .settings-group h3 {
            margin-bottom: 20px;
            color: #374151;
            font-weight: 600;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #F3F4F6;
        }

        .setting-item:last-child {
            border-bottom: none;
        }

       
        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #D1D5DB;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

      
        .app-info {
            text-align: center;
            background: white;
            border-radius: 16px;
            padding: 40px 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
        }

        .app-logo-large {
            font-size: 80px;
            color: #8B5CF6;
            margin-bottom: 20px;
        }

        .app-version {
            color: #6B7280;
            font-size: 14px;
            margin-top: 8px;
        }

        .about-text {
            color: black;
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
            line-height: 1.6;
        }

        .contact-info {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
        }

        .contact-info h4 {
            color: #374151;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .contact-info p {
            margin: 8px 0;
            color: #6B7280;
        }

       
        .profile-header,
        .settings-header,
        .about-header {
            padding: 20px;
            text-align: center;
            background: white;
            margin-bottom: 0;
            border-bottom: 1px solid #E5E7EB;
        }

        .profile-header h2,
        .settings-header h2,
        .about-header h2 {
            margin: 0;
            color: #374151;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div id="app" class="app">
        <header class="header">
            <div class="header-content">
                <button class="menu-btn" id="menuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="logo"><span>Эпатаж</span></h1>
                <div class="header-actions">
                    <button class="search-btn" id="searchBtn">
                        <i class="fas fa-search"></i>
                    </button>
                    <button class="favorite-btn" id="favoriteBtn">
                        <i class="far fa-heart"></i>
                        <span class="favorite-count" id="favoriteCount">0</span>
                    </button>
                </div>
            </div>
            
            <div id="searchOverlay" class="search-overlay hidden">
                <div class="search-container">
                    <div class="search-header">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchInput" class="search-input" placeholder="Поиск причесок...">
                            <button class="clear-search" id="clearSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <button class="close-search" id="closeSearch">
                            Отмена
                        </button>
                    </div>
                    <div class="search-results" id="searchResults">
                        <div class="search-suggestions">
                            <div class="recent-searches">
                                <h3>Недавние запросы</h3>
                                <div id="recentSearches" class="tags-container"></div>
                            </div>
                            <div class="popular-tags">
                                <h3>Популярные теги</h3>
                                <div class="tags-container" id="popularTags">
                                    <span class="tag" data-tag="вечерняя">вечерняя</span>
                                    <span class="tag" data-tag="свадебная">свадебная</span>
                                    <span class="tag" data-tag="повседневная">повседневная</span>
                                    <span class="tag" data-tag="летняя">летняя</span>
                                    <span class="tag" data-tag="короткая">короткая</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div id="sideMenu" class="side-menu">
            <div class="menu-overlay" id="menuOverlay"></div>
            <div class="menu-content">
                <div class="menu-header">
                    <h3>Меню</h3>
                    <button class="menu-close" id="menuClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <nav class="menu-nav">
                    <a href="#" class="menu-item" id="profileMenuItem">
                        <i class="fas fa-user"></i>
                        Профиль
                    </a>
                    <a href="#" class="menu-item" id="settingsMenuItem">
                        <i class="fas fa-cog"></i>
                        Настройки
                    </a>
                    <a href="#" class="menu-item" id="aboutMenuItem">
                        <i class="fas fa-info-circle"></i>
                        О приложении
                    </a>
                </nav>
            </div>
        </div>

        <nav class="bottom-nav">
            <button class="nav-item active" data-page="home">
                <i class="fas fa-home"></i>
                <span>Главная</span>
            </button>
            <button class="nav-item" data-page="catalog">
                <i class="fas fa-th-large"></i>
                <span>Каталог</span>
            </button>
            <button class="nav-item" data-page="categories">
                <i class="fas fa-tags"></i>
                <span>Категории</span>
            </button>
            <button class="nav-item" data-page="favorites">
                <i class="fas fa-heart"></i>
                <span>Избранное</span>
            </button>
        </nav>

        <main class="main-content">
            <section id="homePage" class="page active">
                <div class="hero-section">
                    <div class="hero-content">
                        <h2>Найди свой идеальный образ</h2>
                        <p>Более 1000 стильных причесок для любого случая</p>
                        <button class="cta-button" id="exploreBtn">
                            Начать поиск
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div class="featured-section">
                    <div class="section-header">
                        <h3>Популярные сейчас</h3>
                        <a href="#" class="see-all" id="seeAllFeatured">Все</a>
                    </div>
                    <div class="featured-grid" id="featuredGrid">
                        <?php foreach ($featuredHairstyles as $hairstyle): ?>
                        <div class="hair-card" data-id="<?= $hairstyle['id'] ?>">
                            <img src="<?= htmlspecialchars($hairstyle['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($hairstyle['name']) ?>" 
                                 class="card-image"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+'">
                            <button class="favorite-toggle" data-id="<?= $hairstyle['id'] ?>">
                                <i class="far fa-heart"></i>
                            </button>
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($hairstyle['name']) ?></h3>
                                <div class="card-meta">
                                    <span class="category">
                                        <?= $hairstyle['category'] === 'women' ? 'Женская' : 
                                             ($hairstyle['category'] === 'men' ? 'Мужская' :
                                             ($hairstyle['category'] === 'wedding' ? 'Свадебная' : 'Вечерняя')) ?>
                                    </span>
                                    <div class="difficulty">
                                        <?= str_repeat('★', $hairstyle['difficulty']) . str_repeat('☆', 5 - $hairstyle['difficulty']) ?>
                                    </div>
                                </div>
                                <?php if ($hairstyle['avg_rating'] > 0): ?>
                                <div class="card-rating">
                                    <span class="rating-stars">
                                        <?= str_repeat('★', floor($hairstyle['avg_rating'])) . 
                                            ($hairstyle['avg_rating'] - floor($hairstyle['avg_rating']) >= 0.5 ? '½' : '') . 
                                            str_repeat('☆', 5 - ceil($hairstyle['avg_rating'])) ?>
                                    </span>
                                    <span class="rating-value"><?= $hairstyle['avg_rating'] ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="categories-preview">
                    <div class="section-header">
                        <h3>Категории</h3>
                    </div>
                    <div class="categories-grid">
                        <div class="category-card" data-category="women">
                            <div class="category-icon">
                                <i class="fas fa-female"></i>
                            </div>
                            <span>Женские</span>
                        </div>
                        <div class="category-card" data-category="men">
                            <div class="category-icon">
                                <i class="fas fa-male"></i>
                            </div>
                            <span>Мужские</span>
                        </div>
                        <div class="category-card" data-category="wedding">
                            <div class="category-icon">
                                <i class="fas fa-ring"></i>
                            </div>
                            <span>Свадебные</span>
                        </div>
                        <div class="category-card" data-category="evening">
                            <div class="category-icon">
                                <i class="fas fa-moon"></i>
                            </div>
                            <span>Вечерние</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="catalogPage" class="page">
                <div class="catalog-header">
                    <div class="filter-controls">
                        <button class="filter-btn" id="filterBtn">
                            <i class="fas fa-sliders-h"></i>
                            Фильтры
                        </button>
                        <button class="sort-btn" id="sortBtn">
                            <i class="fas fa-sort"></i>
                            Сортировка
                        </button>
                    </div>
                </div>

                <div id="filterPanel" class="filter-panel">
                    <div class="filter-header">
                        <h3>Фильтры</h3>
                        <div class="filter-actions">
                            <button class="reset-filters" id="resetFilters">Сбросить</button>
                            <button class="close-filters" id="closeFilters">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="filter-content">
                        <div class="filter-group">
                            <label>Категория</label>
                            <select id="categoryFilter" class="filter-select">
                                <option value="">Все категории</option>
                                <option value="women">Женские</option>
                                <option value="men">Мужские</option>
                                <option value="wedding">Свадебные</option>
                                <option value="evening">Вечерние</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Длина волос</label>
                            <div class="filter-tags" id="lengthFilter">
                                <button class="filter-tag" data-value="short">Короткие</button>
                                <button class="filter-tag" data-value="medium">Средние</button>
                                <button class="filter-tag" data-value="long">Длинные</button>
                                <button class="filter-tag" data-value="extra-long">Очень длинные</button>
                            </div>
                        </div>
                        
                        <div class="filter-group">
                            <label>Сложность</label>
                            <div class="difficulty-filter">
                                <button class="difficulty-btn" data-level="1">1 ★</button>
                                <button class="difficulty-btn" data-level="2">2 ★★</button>
                                <button class="difficulty-btn" data-level="3">3 ★★★</button>
                                <button class="difficulty-btn" data-level="4">4 ★★★★</button>
                                <button class="difficulty-btn" data-level="5">5 ★★★★★</button>
                            </div>
                        </div>
                        
                        <button class="apply-filters" id="applyFilters">Применить фильтры</button>
                    </div>
                </div>

                <div class="catalog-grid" id="catalogGrid"></div>
                
                <div class="loading-indicator hidden" id="loadingIndicator">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Загрузка...</span>
                </div>
                
                <div class="load-more-container hidden" id="loadMoreContainer">
                    <button class="load-more-btn" id="loadMore">Загрузить еще</button>
                </div>
                
                <div class="empty-state hidden" id="emptyCatalog">
                    <i class="fas fa-search"></i>
                    <h3>Ничего не найдено</h3>
                    <p>Попробуйте изменить параметры фильтрации</p>
                    <button class="reset-all-filters" id="resetAllFilters">Сбросить все фильтры</button>
                </div>
            </section>

            <section id="categoriesPage" class="page">
                <div class="categories-full">
                    <h2>Все категории</h2>
                    <div class="categories-grid-full">
                        <div class="category-card-large" data-category="women">
                            <div class="category-image">
                                <i class="fas fa-female"></i>
                            </div>
                            <div class="category-info">
                                <h3>Женские прически</h3>
                                <span class="category-description">От повседневных до вечерних образов</span>
                            </div>
                        </div>
                        <div class="category-card-large" data-category="men">
                            <div class="category-image">
                                <i class="fas fa-male"></i>
                            </div>
                            <div class="category-info">
                                <h3>Мужские стрижки</h3>
                                <span class="category-description">Классические и современные варианты</span>
                            </div>
                        </div>
                        <div class="category-card-large" data-category="wedding">
                            <div class="category-image">
                                <i class="fas fa-ring"></i>
                            </div>
                            <div class="category-info">
                                <h3>Свадебные прически</h3>
                                <span class="category-description">Для самого особенного дня</span>
                            </div>
                        </div>
                        <div class="category-card-large" data-category="evening">
                            <div class="category-image">
                                <i class="fas fa-moon"></i>
                            </div>
                            <div class="category-info">
                                <h3>Вечерние образы</h3>
                                <span class="category-description">Для торжественных мероприятий</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="favoritesPage" class="page">
                <div class="favorites-header">
                    <h2>Избранное</h2>
                    <button class="clear-favorites" id="clearFavorites">
                        Очистить все
                    </button>
                </div>
                <div class="favorites-grid" id="favoritesGrid"></div>
                <div class="empty-state hidden" id="emptyFavorites">
                    <i class="far fa-heart"></i>
                    <h3>Нет избранных причесок</h3>
                    <p>Добавляйте понравившиеся прически в избранное</p>
                    <button class="browse-catalog" id="browseCatalog">Перейти в каталог</button>
                </div>
            </section>

            <!-- НОВЫЕ СЕКЦИИ ДЛЯ ПРОФИЛЯ, НАСТРОЕК И О ПРИЛОЖЕНИИ -->
            <section id="profilePage" class="page">
                <div class="profile-header">
                    <h2>Профиль</h2>
                </div>
                <div class="profile-content">
                    <div class="profile-card">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3>Гость</h3>
                        <p>Войдите в аккаунт для сохранения настроек</p>
                        <button class="login-btn">Войти</button>
                    </div>
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-number" id="favoriteStat">0</span>
                            <span class="stat-label">В избранном</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" id="viewedStat">0</span>
                            <span class="stat-label">Просмотрено</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="settingsPage" class="page">
                <div class="settings-header">
                    <h2>Настройки</h2>
                </div>
                <div class="settings-content">
                    <div class="settings-group">
                        <h3>Уведомления</h3>
                        <div class="setting-item">
                            <span>Новые прически</span>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <span>Специальные предложения</span>
                            <label class="switch">
                                <input type="checkbox">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="settings-group">
                        <h3>Внешний вид</h3>
                        <div class="setting-item">
                            <span>Темная тема</span>
                            <label class="switch">
                                <input type="checkbox" id="darkTheme">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <section id="aboutPage" class="page">
                <div class="about-header">
                    <h2>О приложении</h2>
                </div>
                <div class="about-content">
                    <div class="app-info">
                        <div class="app-logo-large">
                            <i class="fas fa-scissors"></i>
                        </div>
                        <h3 class = "HAIR-LOGO">Эпатаж</h3>
                        <p class="app-version">Версия 1.0.0</p>
                    </div>
                    <div class="about-text">
                        <p>Эпатаж - это современное приложение для поиска и сохранения причесок на все случаи жизни.</p>
                        <p>В нашей базе собраны тысячи стильных образов от профессиональных стилистов.</p>
                    </div>
                    <div class="contact-info">
                        <h4>Контакты</h4>
                        <p>support@hairstylepro.ru</p>
                        <p>+7 (999) 123-45-67</p>
                    </div>
                </div>
            </section>
        </main>

        <div id="detailModal" class="modal hidden">
            <div class="modal-content">
                <button class="modal-close" id="modalClose">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-body" id="modalBody"></div>
            </div>
        </div>

        <div id="toast" class="toast hidden"></div>
    </div>

    <script>
        class HairStyleApp {
            constructor() {
                this.currentPage = 'home';
                this.currentFilters = {};
                this.favorites = new Set();
                this.hairstyles = [];
                this.allHairstyles = [];
                this.currentPageIndex = 1;
                this.itemsPerPage = 12;
                this.hasMore = true;
                this.isLoading = false;
                this.currentRating = 0;
                
                this.initializeApp();
            }

            async initializeApp() {
                this.initializeEventListeners();
                await this.loadHairstyles();
                this.loadFavorites();
                this.updateFavoriteCount();
            }

            initializeEventListeners() {
                // Бургер меню
                document.getElementById('menuBtn').addEventListener('click', () => this.toggleMenu());
                document.getElementById('menuClose').addEventListener('click', () => this.toggleMenu());
                document.getElementById('menuOverlay').addEventListener('click', () => this.toggleMenu());

                // Навигация в боковом меню
                document.getElementById('profileMenuItem').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.navigateTo('profile');
                    this.toggleMenu();
                });

                document.getElementById('settingsMenuItem').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.navigateTo('settings');
                    this.toggleMenu();
                });

                document.getElementById('aboutMenuItem').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.navigateTo('about');
                    this.toggleMenu();
                });

                // Навигация
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', (e) => {
                        const page = e.currentTarget.dataset.page;
                        this.navigateTo(page);
                    });
                });

                // Поиск
                document.getElementById('searchBtn').addEventListener('click', () => this.toggleSearch());
                document.getElementById('closeSearch').addEventListener('click', () => this.toggleSearch());
                document.getElementById('clearSearch').addEventListener('click', () => this.clearSearch());
                document.getElementById('searchInput').addEventListener('input', (e) => {
                    this.debouncedSearch(e.target.value);
                });

                // Фильтры
                document.getElementById('filterBtn').addEventListener('click', () => this.toggleFilters());
                document.getElementById('closeFilters').addEventListener('click', () => this.toggleFilters());
                document.getElementById('applyFilters').addEventListener('click', () => this.applyFilters());
                document.getElementById('resetFilters').addEventListener('click', () => this.resetFilters());
                document.getElementById('resetAllFilters').addEventListener('click', () => this.resetAllFilters());

                // Избранное
                document.getElementById('clearFavorites').addEventListener('click', () => this.clearFavorites());
                document.getElementById('browseCatalog').addEventListener('click', () => this.navigateTo('catalog'));

                // Кнопка "Начать поиск"
                document.getElementById('exploreBtn').addEventListener('click', () => this.navigateTo('catalog'));
                document.getElementById('seeAllFeatured').addEventListener('click', (e) => {
                    e.preventDefault();
                    this.navigateTo('catalog');
                });

                // Категории
                document.querySelectorAll('.category-card, .category-card-large').forEach(card => {
                    card.addEventListener('click', () => {
                        const category = card.dataset.category;
                        this.navigateToCatalogWithFilter(category);
                    });
                });

                // Модальное окно
                document.getElementById('modalClose').addEventListener('click', () => this.closeModal());
                document.getElementById('detailModal').addEventListener('click', (e) => {
                    if (e.target === document.getElementById('detailModal')) {
                        this.closeModal();
                    }
                });

                // Загрузка еще
                document.getElementById('loadMore').addEventListener('click', () => this.loadMoreItems());

                // Настройки
                document.getElementById('darkTheme').addEventListener('change', (e) => {
                    this.toggleDarkTheme(e.target.checked);
                });
            }

            debouncedSearch = this.debounce((query) => {
                this.handleSearch(query);
            }, 300);

            debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            async loadHairstyles(reset = true) {
                if (this.isLoading) return;
                
                this.isLoading = true;
                
                if (reset) {
                    this.currentPageIndex = 1;
                    this.hasMore = true;
                }

                try {
                    const params = new URLSearchParams({
                        page: this.currentPageIndex,
                        limit: this.itemsPerPage,
                        ...this.currentFilters
                    });

                    const response = await fetch(`api/hairstyles.php?${params}`);
                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.error);
                    }

                    if (reset) {
                        this.hairstyles = data.hairstyles;
                        this.allHairstyles = data.hairstyles;
                    } else {
                        this.hairstyles.push(...data.hairstyles);
                        this.allHairstyles.push(...data.hairstyles);
                    }

                    this.hasMore = this.currentPageIndex < data.pagination.pages;
                    
                    this.renderCatalog();
                    this.updateFilterCount();

                } catch (error) {
                    console.error('Error loading hairstyles:', error);
                    this.showToast('Ошибка загрузки данных');
                } finally {
                    this.isLoading = false;
                }
            }

            async handleSearch(query) {
                this.currentFilters.search = query;
                await this.loadHairstyles(true);
                
                if (query.trim()) {
                    this.saveSearchQuery(query);
                }
            }

            async applyFilters() {
                const category = document.getElementById('categoryFilter').value;
                const selectedLengths = Array.from(document.querySelectorAll('#lengthFilter .filter-tag.active'))
                    .map(tag => tag.dataset.value);
                const selectedDifficulty = Array.from(document.querySelectorAll('.difficulty-btn.active'))
                    .map(btn => parseInt(btn.dataset.level));

                this.currentFilters = {};
                
                if (category) this.currentFilters.category = category;
                if (selectedLengths.length > 0) this.currentFilters.length = selectedLengths.join(',');
                if (selectedDifficulty.length > 0) this.currentFilters.difficulty = selectedDifficulty.join(',');

                await this.loadHairstyles(true);
                this.toggleFilters();
            }

            resetFilters() {
                document.getElementById('categoryFilter').value = '';
                document.querySelectorAll('.filter-tag.active, .difficulty-btn.active').forEach(btn => {
                    btn.classList.remove('active');
                });
            }

            resetAllFilters() {
                this.currentFilters = {};
                this.resetFilters();
                this.loadHairstyles(true);
            }

            updateFilterCount() {
                const filterBtn = document.getElementById('filterBtn');
                const activeFilters = Object.keys(this.currentFilters).filter(key => 
                    key !== 'search' && this.currentFilters[key]
                ).length;
                
                if (activeFilters > 0) {
                    filterBtn.innerHTML = `<i class="fas fa-sliders-h"></i> Фильтры (${activeFilters})`;
                } else {
                    filterBtn.innerHTML = `<i class="fas fa-sliders-h"></i> Фильтры`;
                }
            }

            async showHairstyleDetail(id) {
                try {
                    const response = await fetch(`api/hairstyles.php?id=${id}`);
                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.error);
                    }

                    const hairstyle = data.hairstyle;
                    const modalBody = document.getElementById('modalBody');
                    modalBody.innerHTML = this.createDetailView(hairstyle);
                    
                    await this.loadReviews(id);
                    document.getElementById('detailModal').classList.remove('hidden');
                    
                } catch (error) {
                    console.error('Error loading hairstyle details:', error);
                    this.showToast('Ошибка загрузки данных');
                }
            }

            createDetailView(hairstyle) {
                const isFavorite = this.favorites.has(hairstyle.id.toString());
                
                return `
                    <div class="hairstyle-detail">
                        <img src="${hairstyle.image_path}" alt="${hairstyle.name}" class="detail-image"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+'">
                        <div class="detail-content">
                            <h2>${hairstyle.name}</h2>
                            
                            <div class="detail-meta">
                                <span class="category-badge">${this.getCategoryName(hairstyle.category)}</span>
                                <span class="length-badge">${this.getLengthName(hairstyle.length)}</span>
                                <div class="difficulty">
                                    Сложность: ${'★'.repeat(hairstyle.difficulty)}${'☆'.repeat(5 - hairstyle.difficulty)}
                                </div>
                            </div>
                            
                            <div class="rating-section">
                                <div class="average-rating">
                                    <span class="rating-stars">
                                        ${this.generateStarRating(hairstyle.avg_rating || 0)}
                                    </span>
                                    <span class="rating-value">${hairstyle.avg_rating || 'Нет оценок'}</span>
                                    <span class="review-count">(${hairstyle.review_count || 0} отзывов)</span>
                                </div>
                            </div>
                            
                            <p class="detail-description">${hairstyle.description || 'Описание отсутствует'}</p>
                            
                            <div class="detail-tags">
                                ${(hairstyle.tags || []).map(tag => `<span class="tag">#${tag}</span>`).join('')}
                            </div>
                            
                            <div class="detail-actions">
                                <button class="action-btn favorite-btn ${isFavorite ? 'active' : ''}" 
                                        onclick="app.toggleFavorite('${hairstyle.id}')">
                                    <i class="${isFavorite ? 'fas' : 'far'} fa-heart"></i>
                                    ${isFavorite ? 'В избранном' : 'В избранное'}
                                </button>
                            </div>
                            
                            <div class="review-form">
                                <h4>Оставить отзыв</h4>
                                <div class="rating-input">
                                    <span>Оценка:</span>
                                    <div class="star-rating">
                                        ${[1,2,3,4,5].map(i => `
                                            <i class="far fa-star" data-rating="${i}" 
                                               onmouseover="app.hoverStars(${i})" 
                                               onmouseout="app.resetStars()"
                                               onclick="app.setRating(${i})"></i>
                                        `).join('')}
                                    </div>
                                </div>
                                <input type="text" id="reviewName" class="review-input" placeholder="Ваше имя">
                                <textarea id="reviewComment" class="review-textarea" placeholder="Ваш отзыв..."></textarea>
                                <button class="submit-review-btn" onclick="app.submitReview(${hairstyle.id})">
                                    Отправить отзыв
                                </button>
                            </div>
                            
                            <div class="reviews-section">
                                <h4>Отзывы</h4>
                                <div id="reviewsList" class="reviews-list"></div>
                            </div>
                        </div>
                    </div>
                `;
            }

            async loadReviews(hairstyleId) {
                try {
                    const response = await fetch(`api/reviews.php?hairstyle_id=${hairstyleId}`);
                    const data = await response.json();
                    
                    console.log('API Response:', data); // Добавьте это для отладки
                    
                    // Получаем отзывы из правильного поля
                    const reviews = data.reviews || [];
                    
                    const reviewsList = document.getElementById('reviewsList');
                    if (reviewsList) {
                        reviewsList.innerHTML = reviews.length > 0 ? 
                            reviews.map(review => this.createReviewView(review)).join('') :
                            '<p class="no-reviews">Пока нет отзывов. Будьте первым!</p>';
                    }
                                    
                } catch (error) {
                    console.error('Error loading reviews:', error);
                    const reviewsList = document.getElementById('reviewsList');
                    if (reviewsList) {
                        reviewsList.innerHTML = '<p class="no-reviews">Ошибка загрузки отзывов</p>';
                    }
                }
            }

            createReviewView(review) {
                return `
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author">${review.user_name}</span>
                            <span class="review-date">${new Date(review.created_at).toLocaleDateString()}</span>
                        </div>
                        <div class="review-rating">
                            ${this.generateStarRating(review.rating)}
                        </div>
                        <p class="review-comment">${review.comment}</p>
                    </div>
                `;
            }

            async submitReview(hairstyleId) {
                const rating = this.currentRating;
                const userName = document.getElementById('reviewName').value || 'Аноним';
                const comment = document.getElementById('reviewComment').value;

                if (!rating) {
                    this.showToast('Пожалуйста, поставьте оценку');
                    return;
                }

                try {
                    const response = await fetch('api/reviews.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            hairstyle_id: hairstyleId,
                            user_name: userName,
                            rating: rating,
                            comment: comment
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showToast('Отзыв добавлен!');
                        document.getElementById('reviewName').value = '';
                        document.getElementById('reviewComment').value = '';
                        this.resetStars();
                        await this.loadReviews(hairstyleId);
                    } else {
                        this.showToast('Ошибка при добавлении отзыва');
                    }
                } catch (error) {
                    console.error('Error submitting review:', error);
                    this.showToast('Ошибка при добавлении отзыва');
                }
            }

            // Методы для звезд рейтинга
            hoverStars(rating) {
                const stars = document.querySelectorAll('.star-rating .fa-star');
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.classList.add('fas', 'hover');
                        star.classList.remove('far');
                    }
                });
            }

            resetStars() {
                const stars = document.querySelectorAll('.star-rating .fa-star');
                stars.forEach((star, index) => {
                    if (index < (this.currentRating || 0)) {
                        star.classList.add('fas');
                        star.classList.remove('far', 'hover');
                    } else {
                        star.classList.add('far');
                        star.classList.remove('fas', 'hover');
                    }
                });
            }

            setRating(rating) {
                this.currentRating = rating;
                this.resetStars();
            }

            generateStarRating(rating) {
                const fullStars = Math.floor(rating);
                const hasHalfStar = rating % 1 >= 0.5;
                
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= fullStars) {
                        stars += '<i class="fas fa-star"></i>';
                    } else if (i === fullStars + 1 && hasHalfStar) {
                        stars += '<i class="fas fa-star-half-alt"></i>';
                    } else {
                        stars += '<i class="far fa-star"></i>';
                    }
                }
                return stars;
            }

            // Навигация
            navigateTo(page) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Обновляем навигацию только для основных страниц
                if (['home', 'catalog', 'categories', 'favorites'].includes(page)) {
                    document.querySelector(`[data-page="${page}"]`).classList.add('active');
                }

                document.querySelectorAll('.page').forEach(pageEl => {
                    pageEl.classList.remove('active');
                });
                document.getElementById(`${page}Page`).classList.add('active');

                this.currentPage = page;

                if (page === 'favorites') {
                    this.renderFavorites();
                } else if (page === 'catalog') {
                    this.loadHairstyles(true);
                } else if (page === 'profile') {
                    this.updateProfileStats();
                }
            }

            // Обновление статистики профиля
            updateProfileStats() {
                document.getElementById('favoriteStat').textContent = this.favorites.size;
                // Здесь можно добавить логику для подсчета просмотренных причесок
            }

            // Темная тема
            toggleDarkTheme(enabled) {
                if (enabled) {
                    document.body.classList.add('dark-theme');
                    this.showToast('Темная тема включена');
                } else {
                    document.body.classList.remove('dark-theme');
                    this.showToast('Темная тема выключена');
                }
            }

            // Бургер меню
            toggleMenu() {
                document.getElementById('sideMenu').classList.toggle('active');
            }

            // Поиск
            toggleSearch() {
                const searchOverlay = document.getElementById('searchOverlay');
                searchOverlay.classList.toggle('hidden');
                
                if (!searchOverlay.classList.contains('hidden')) {
                    document.getElementById('searchInput').focus();
                }
            }

            clearSearch() {
                document.getElementById('searchInput').value = '';
                this.handleSearch('');
            }

            // Фильтры
            toggleFilters() {
                document.getElementById('filterPanel').classList.toggle('active');
            }

            // Рендер каталога
            renderCatalog() {
                const catalogGrid = document.getElementById('catalogGrid');
                catalogGrid.innerHTML = this.hairstyles.map(hairstyle => 
                    this.createHairstyleCard(hairstyle)
                ).join('');
                
                this.attachCardEventListeners();
                
                const loadMoreContainer = document.getElementById('loadMoreContainer');
                if (this.hasMore) {
                    loadMoreContainer.classList.remove('hidden');
                } else {
                    loadMoreContainer.classList.add('hidden');
                }

                // Показываем/скрываем состояние пустоты
                const emptyState = document.getElementById('emptyCatalog');
                if (this.hairstyles.length === 0) {
                    emptyState.classList.remove('hidden');
                } else {
                    emptyState.classList.add('hidden');
                }
            }

            // Создание карточки прически
            createHairstyleCard(hairstyle) {
                const isFavorite = this.favorites.has(hairstyle.id.toString());
                
                return `
                    <div class="hair-card" data-id="${hairstyle.id}">
                        <img src="${hairstyle.image_path}" 
                             alt="${hairstyle.name}" 
                             class="card-image"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+'">
                        <button class="favorite-toggle ${isFavorite ? 'active' : ''}" 
                                data-id="${hairstyle.id}">
                            <i class="${isFavorite ? 'fas' : 'far'} fa-heart"></i>
                        </button>
                        <div class="card-content">
                            <h3 class="card-title">${hairstyle.name}</h3>
                            <div class="card-meta">
                                <span class="category">${this.getCategoryName(hairstyle.category)}</span>
                                <div class="difficulty">
                                    ${'★'.repeat(hairstyle.difficulty)}${'☆'.repeat(5 - hairstyle.difficulty)}
                                </div>
                            </div>
                            ${hairstyle.avg_rating > 0 ? `
                            <div class="card-rating">
                                <span class="rating-stars">
                                    ${this.generateStarRating(hairstyle.avg_rating)}
                                </span>
                                <span class="rating-value">${hairstyle.avg_rating}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }

            // Прикрепление обработчиков событий к карточкам
            attachCardEventListeners() {
                document.querySelectorAll('.hair-card').forEach(card => {
                    card.addEventListener('click', (e) => {
                        if (!e.target.closest('.favorite-toggle')) {
                            const id = card.dataset.id;
                            this.showHairstyleDetail(id);
                        }
                    });
                });

                document.querySelectorAll('.favorite-toggle').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const id = btn.dataset.id;
                        this.toggleFavorite(id, btn);
                    });
                });
            }

            // Переключение избранного
            toggleFavorite(id, button = null) {
                if (this.favorites.has(id)) {
                    this.favorites.delete(id);
                    this.showToast('Удалено из избранного');
                } else {
                    this.favorites.add(id);
                    this.showToast('Добавлено в избранное');
                }
                
                this.saveFavorites();
                this.updateFavoriteCount();
                
                if (button) {
                    const isFavorite = this.favorites.has(id);
                    button.classList.toggle('active', isFavorite);
                    button.innerHTML = `<i class="${isFavorite ? 'fas' : 'far'} fa-heart"></i>`;
                }
                
                if (this.currentPage === 'favorites') {
                    this.renderFavorites();
                }
            }

            // Загрузка избранного из localStorage
            loadFavorites() {
                const saved = localStorage.getItem('hairstyle-favorites');
                if (saved) {
                    this.favorites = new Set(JSON.parse(saved));
                }
            }

            // Сохранение избранного в localStorage
            saveFavorites() {
                localStorage.setItem('hairstyle-favorites', JSON.stringify([...this.favorites]));
            }

            // Обновление счетчика избранного
            updateFavoriteCount() {
                const favoriteCount = document.getElementById('favoriteCount');
                if (favoriteCount) {
                    favoriteCount.textContent = this.favorites.size;
                }
            }

            // Рендер страницы избранного
            renderFavorites() {
                const favoritesGrid = document.getElementById('favoritesGrid');
                const emptyState = document.getElementById('emptyFavorites');
                
                if (this.favorites.size === 0) {
                    favoritesGrid.innerHTML = '';
                    favoritesGrid.classList.add('hidden');
                    emptyState.classList.remove('hidden');
                    return;
                }
                
                const favoriteHairstyles = this.allHairstyles.filter(h => 
                    this.favorites.has(h.id.toString())
                );
                
                if (favoriteHairstyles.length === 0) {
                    favoritesGrid.innerHTML = '';
                    favoritesGrid.classList.add('hidden');
                    emptyState.classList.remove('hidden');
                } else {
                    favoritesGrid.innerHTML = favoriteHairstyles.map(hairstyle => 
                        this.createHairstyleCard(hairstyle)
                    ).join('');
                    this.attachCardEventListeners();
                    favoritesGrid.classList.remove('hidden');
                    emptyState.classList.add('hidden');
                }
            }

            // Очистка избранного
            clearFavorites() {
                if (this.favorites.size === 0) return;
                
                if (confirm('Очистить все избранные прически?')) {
                    this.favorites.clear();
                    this.saveFavorites();
                    this.updateFavoriteCount();
                    this.renderFavorites();
                    this.showToast('Избранное очищено');
                }
            }

            // Загрузка дополнительных элементов
            loadMoreItems() {
                this.currentPageIndex++;
                this.loadHairstyles(false);
            }

            // Навигация по категориям
            navigateToCatalogWithFilter(category) {
                this.navigateTo('catalog');
                document.getElementById('categoryFilter').value = category;
                this.currentFilters.category = category;
                this.currentPageIndex = 1;
                this.loadHairstyles(true);
            }

            // Вспомогательные методы
            getCategoryName(category) {
                const names = {
                    'women': 'Женская',
                    'men': 'Мужская',
                    'wedding': 'Свадебная',
                    'evening': 'Вечерняя'
                };
                return names[category] || category;
            }

            getLengthName(length) {
                const names = {
                    'short': 'Короткие',
                    'medium': 'Средние',
                    'long': 'Длинные',
                    'extra-long': 'Очень длинные'
                };
                return names[length] || length;
            }

            saveSearchQuery(query) {
                let recentSearches = JSON.parse(localStorage.getItem('recent-searches') || '[]');
                recentSearches = recentSearches.filter(item => item !== query);
                recentSearches.unshift(query);
                recentSearches = recentSearches.slice(0, 5);
                localStorage.setItem('recent-searches', JSON.stringify(recentSearches));
            }

            closeModal() {
                document.getElementById('detailModal').classList.add('hidden');
            }

            showToast(message) {
                const toast = document.getElementById('toast');
                toast.textContent = message;
                toast.classList.remove('hidden');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 3000);
            }
        }

        // Инициализация приложения
        document.addEventListener('DOMContentLoaded', () => {
            window.app = new HairStyleApp();
        });
    </script>
</body>
</html>
