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
        this.searchTimeout = null;
        
        this.initializeApp();
    }

    async initializeApp() {
        try {
            this.initializeEventListeners();
            this.initializeFilterListeners();
            this.loadFavorites();
            this.updateFavoriteCount();
            
            // Предзагружаем данные для главной страницы
            if (document.getElementById('homePage').classList.contains('active')) {
                await this.loadFeaturedHairstyles();
            }
            
            console.log('HairStyleApp initialized successfully');
        } catch (error) {
            console.error('Error initializing app:', error);
            this.showToast('Ошибка инициализации приложения');
        }
    }

    initializeEventListeners() {
        // Бургер меню
        this.safeAddEventListener('#menuBtn', 'click', () => this.toggleMenu());
        this.safeAddEventListener('#menuClose', 'click', () => this.toggleMenu());
        this.safeAddEventListener('#menuOverlay', 'click', () => this.toggleMenu());

        // Навигация
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const page = e.currentTarget.dataset.page;
                this.navigateTo(page);
            });
        });

        // Поиск
        this.safeAddEventListener('#searchBtn', 'click', () => this.toggleSearch());
        this.safeAddEventListener('#closeSearch', 'click', () => this.toggleSearch());
        this.safeAddEventListener('#clearSearch', 'click', () => this.clearSearch());
        
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
        }

        // Фильтры
        this.safeAddEventListener('#filterBtn', 'click', () => this.toggleFilters());
        this.safeAddEventListener('#closeFilters', 'click', () => this.toggleFilters());
        this.safeAddEventListener('#applyFilters', 'click', () => this.applyFilters());
        this.safeAddEventListener('#resetFilters', 'click', () => this.resetFilters());
        this.safeAddEventListener('#resetAllFilters', 'click', () => this.resetAllFilters());

        // Избранное
        this.safeAddEventListener('#clearFavorites', 'click', () => this.clearFavorites());
        this.safeAddEventListener('#browseCatalog', 'click', () => this.navigateTo('catalog'));

        // Главная страница
        this.safeAddEventListener('#exploreBtn', 'click', () => this.navigateTo('catalog'));
        this.safeAddEventListener('#seeAllFeatured', 'click', (e) => {
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
        this.safeAddEventListener('#modalClose', 'click', () => this.closeModal());
        this.safeAddEventListener('#detailModal', 'click', (e) => {
            if (e.target === document.getElementById('detailModal')) {
                this.closeModal();
            }
        });

        // Загрузка еще
        this.safeAddEventListener('#loadMore', 'click', () => this.loadMoreItems());

        // Обработчики для популярных тегов
        this.initializeTagListeners();
    }

    safeAddEventListener(selector, event, handler) {
        const element = document.querySelector(selector);
        if (element) {
            element.addEventListener(event, handler);
        }
    }

    initializeFilterListeners() {
        // Обработчики для кнопок длины волос
        document.querySelectorAll('#lengthFilter .filter-tag').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.classList.toggle('active');
            });
        });

        // Обработчики для кнопок сложности
        document.querySelectorAll('.difficulty-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.classList.toggle('active');
            });
        });

        // Обработчик для сброса фильтров в панели
        this.safeAddEventListener('#resetFilters', 'click', () => {
            this.resetFilters();
        });
    }

    initializeTagListeners() {
        document.querySelectorAll('.tag[data-tag]').forEach(tag => {
            tag.addEventListener('click', (e) => {
                const tagName = e.target.dataset.tag;
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = tagName;
                    this.handleSearch(tagName);
                }
                this.toggleSearch();
            });
        });
    }

    async loadFeaturedHairstyles() {
        try {
            const response = await fetch('api/hairstyles.php?limit=8');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success && data.hairstyles) {
                this.renderFeaturedHairstyles(data.hairstyles);
            }
        } catch (error) {
            console.error('Error loading featured hairstyles:', error);
        }
    }

    renderFeaturedHairstyles(hairstyles) {
        const featuredGrid = document.getElementById('featuredGrid');
        if (!featuredGrid) return;

        featuredGrid.innerHTML = hairstyles.map(hairstyle => 
            this.createHairstyleCard(hairstyle)
        ).join('');
        
        this.attachCardEventListeners();
    }

    async loadHairstyles(reset = true) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        if (reset) {
            this.currentPageIndex = 1;
            this.hasMore = true;
            this.showLoading(true);
        }

        try {
            const params = new URLSearchParams({
                page: this.currentPageIndex,
                limit: this.itemsPerPage
            });

            // Добавляем фильтры в параметры запроса
            Object.keys(this.currentFilters).forEach(key => {
                if (this.currentFilters[key] && this.currentFilters[key] !== '') {
                    params.append(key, this.currentFilters[key]);
                }
            });

            console.log('Loading hairstyles with params:', params.toString());

            const response = await fetch(`api/hairstyles.php?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Unknown error');
            }

            if (reset) {
                this.hairstyles = data.hairstyles || [];
                this.allHairstyles = data.hairstyles || [];
            } else {
                const newHairstyles = data.hairstyles || [];
                this.hairstyles.push(...newHairstyles);
                this.allHairstyles.push(...newHairstyles);
            }

            this.hasMore = this.currentPageIndex < (data.pagination?.pages || 1);
            
            this.renderCatalog();
            this.updateFilterCount();

        } catch (error) {
            console.error('Error loading hairstyles:', error);
            this.showToast('Ошибка загрузки данных');
            
            if (reset) {
                this.showEmptyState();
            }
        } finally {
            this.isLoading = false;
            this.showLoading(false);
        }
    }

    showLoading(show) {
        const loadingIndicator = document.getElementById('loadingIndicator');
        if (loadingIndicator) {
            loadingIndicator.classList.toggle('hidden', !show);
        }
    }

    showEmptyState() {
        const catalogGrid = document.getElementById('catalogGrid');
        const emptyState = document.getElementById('emptyCatalog');
        
        if (catalogGrid && emptyState) {
            catalogGrid.innerHTML = '';
            emptyState.classList.remove('hidden');
        }
    }

    renderCatalog() {
        const catalogGrid = document.getElementById('catalogGrid');
        if (!catalogGrid) return;

        catalogGrid.innerHTML = this.hairstyles.map(hairstyle => 
            this.createHairstyleCard(hairstyle)
        ).join('');
        
        this.attachCardEventListeners();
        
        const loadMoreContainer = document.getElementById('loadMoreContainer');
        if (loadMoreContainer) {
            loadMoreContainer.classList.toggle('hidden', !this.hasMore);
        }

        const emptyState = document.getElementById('emptyCatalog');
        if (emptyState) {
            emptyState.classList.toggle('hidden', this.hairstyles.length > 0);
        }
    }

    createHairstyleCard(hairstyle) {
        if (!hairstyle) return '';
        
        const isFavorite = this.favorites.has(hairstyle.id.toString());
        const imageUrl = hairstyle.image_path || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+';
        
        return `
            <div class="hair-card" data-id="${hairstyle.id}">
                <img src="${imageUrl}" 
                     alt="${hairstyle.name || 'Прическа'}" 
                     class="card-image"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+'">
                <button class="favorite-toggle ${isFavorite ? 'active' : ''}" 
                        data-id="${hairstyle.id}">
                    <i class="${isFavorite ? 'fas' : 'far'} fa-heart"></i>
                </button>
                <div class="card-content">
                    <h3 class="card-title">${hairstyle.name || 'Без названия'}</h3>
                    <div class="card-meta">
                        <span class="category">${this.getCategoryName(hairstyle.category)}</span>
                        <div class="difficulty">
                            ${'★'.repeat(hairstyle.difficulty || 1)}${'☆'.repeat(5 - (hairstyle.difficulty || 1))}
                        </div>
                    </div>
                    ${(hairstyle.avg_rating > 0) ? `
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

    async toggleFavorite(id, button = null) {
        const isCurrentlyFavorite = this.favorites.has(id.toString());
        
        try {
            if (isCurrentlyFavorite) {
                // Удаляем из избранного
                const response = await fetch('api/favorites.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        hairstyle_id: parseInt(id)
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.favorites.delete(id.toString());
                    this.showToast('Удалено из избранного');
                } else {
                    throw new Error(result.error || 'Failed to remove favorite');
                }
            } else {
                // Добавляем в избранное
                const response = await fetch('api/favorites.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        hairstyle_id: parseInt(id)
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.favorites.add(id.toString());
                    this.showToast('Добавлено в избранное');
                } else {
                    throw new Error(result.error || 'Failed to add favorite');
                }
            }
            
            this.saveFavorites();
            this.updateFavoriteCount();
            
            if (button) {
                const isFavorite = this.favorites.has(id.toString());
                button.classList.toggle('active', isFavorite);
                button.innerHTML = `<i class="${isFavorite ? 'fas' : 'far'} fa-heart"></i>`;
            }
            
            if (this.currentPage === 'favorites') {
                this.renderFavorites();
            }
            
        } catch (error) {
            console.error('Error toggling favorite:', error);
            this.showToast('Ошибка при обновлении избранного');
        }
    }

    handleSearch(query) {
        // Очищаем предыдущий таймаут
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        // Устанавливаем новый таймаут
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 500);
    }

    async performSearch(query) {
        if (query.trim()) {
            this.currentFilters.search = query.trim();
            this.saveSearchQuery(query.trim());
        } else {
            delete this.currentFilters.search;
        }
        
        await this.loadHairstyles(true);
    }

    async applyFilters() {
        const category = document.getElementById('categoryFilter')?.value || '';
        
        // Получаем выбранные длины волос
        const selectedLengths = Array.from(document.querySelectorAll('#lengthFilter .filter-tag.active'))
            .map(tag => tag.dataset.value)
            .filter(Boolean);
        
        // Получаем выбранные сложности
        const selectedDifficulty = Array.from(document.querySelectorAll('.difficulty-btn.active'))
            .map(btn => parseInt(btn.dataset.level))
            .filter(level => !isNaN(level));

        // Сбрасываем фильтры
        this.currentFilters = {};
        
        // Добавляем фильтры только если они выбраны
        if (category) {
            this.currentFilters.category = category;
        }
        
        if (selectedLengths.length > 0) {
            this.currentFilters.length = selectedLengths.join(',');
        }
        
        if (selectedDifficulty.length > 0) {
            this.currentFilters.difficulty = selectedDifficulty.join(',');
        }

        console.log('Applying filters:', this.currentFilters);
        
        await this.loadHairstyles(true);
        this.toggleFilters();
    }

    resetFilters() {
        // Сбрасываем выпадающий список
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.value = '';
        }
        
        // Сбрасываем кнопки длины волос
        document.querySelectorAll('#lengthFilter .filter-tag.active').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Сбрасываем кнопки сложности
        document.querySelectorAll('.difficulty-btn.active').forEach(btn => {
            btn.classList.remove('active');
        });
        
        console.log('Filters reset');
    }

    resetAllFilters() {
        this.currentFilters = {};
        this.resetFilters();
        this.loadHairstyles(true);
    }

    updateFilterCount() {
        const filterBtn = document.getElementById('filterBtn');
        if (!filterBtn) return;

        const activeFilters = Object.keys(this.currentFilters).filter(key => 
            this.currentFilters[key] && this.currentFilters[key] !== ''
        ).length;
        
        console.log('Active filters count:', activeFilters);
        
        if (activeFilters > 0) {
            filterBtn.innerHTML = `<i class="fas fa-sliders-h"></i> Фильтры (${activeFilters})`;
        } else {
            filterBtn.innerHTML = `<i class="fas fa-sliders-h"></i> Фильтры`;
        }
    }

    async showHairstyleDetail(id) {
        try {
            const response = await fetch(`api/hairstyles.php?id=${id}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();

            if (!data.success || !data.hairstyle) {
                throw new Error(data.error || 'Hairstyle not found');
            }

            const hairstyle = data.hairstyle;
            const modalBody = document.getElementById('modalBody');
            if (!modalBody) return;

            modalBody.innerHTML = this.createDetailView(hairstyle);
            
            await this.loadReviews(id);
            
            const modal = document.getElementById('detailModal');
            if (modal) {
                modal.classList.remove('hidden');
            }
            
        } catch (error) {
            console.error('Error loading hairstyle details:', error);
            this.showToast('Ошибка загрузки данных прически');
        }
    }

    createDetailView(hairstyle) {
        if (!hairstyle) return '<p>Ошибка загрузки данных</p>';
        
        const isFavorite = this.favorites.has(hairstyle.id.toString());
        const imageUrl = hairstyle.image_path || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+';
        
        return `
            <div class="hairstyle-detail">
                <img src="${imageUrl}" alt="${hairstyle.name || 'Прическа'}" class="detail-image"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPsOXIMOc0L3QtdC60YHQu9C+0LI8L3RleHQ+PC9zdmc+'>
                <div class="detail-content">
                    <h2>${hairstyle.name || 'Без названия'}</h2>
                    
                    <div class="detail-meta">
                        <span class="category-badge">${this.getCategoryName(hairstyle.category)}</span>
                        <span class="length-badge">${this.getLengthName(hairstyle.length)}</span>
                        <div class="difficulty">
                            Сложность: ${'★'.repeat(hairstyle.difficulty || 1)}${'☆'.repeat(5 - (hairstyle.difficulty || 1))}
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
                    
                    ${hairstyle.tags && hairstyle.tags.length > 0 ? `
                    <div class="detail-tags">
                        ${hairstyle.tags.map(tag => `<span class="tag">#${tag}</span>`).join('')}
                    </div>
                    ` : ''}
                    
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
            if (!response.ok) throw new Error('Network response was not ok');
            
            const reviews = await response.json();
            
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
        if (!review) return '';
        
        return `
            <div class="review-item">
                <div class="review-header">
                    <span class="review-author">${review.user_name || 'Аноним'}</span>
                    <span class="review-date">${review.created_at ? new Date(review.created_at).toLocaleDateString() : 'Неизвестно'}</span>
                </div>
                <div class="review-rating">
                    ${this.generateStarRating(review.rating || 0)}
                </div>
                <p class="review-comment">${review.comment || 'Без комментария'}</p>
            </div>
        `;
    }

    async submitReview(hairstyleId) {
        const rating = this.currentRating;
        const userName = document.getElementById('reviewName')?.value || 'Аноним';
        const comment = document.getElementById('reviewComment')?.value || '';

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
                    hairstyle_id: parseInt(hairstyleId),
                    user_name: userName.substring(0, 100),
                    rating: rating,
                    comment: comment.substring(0, 1000)
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showToast('Отзыв добавлен!');
                const reviewName = document.getElementById('reviewName');
                const reviewComment = document.getElementById('reviewComment');
                if (reviewName) reviewName.value = '';
                if (reviewComment) reviewComment.value = '';
                this.resetStars();
                await this.loadReviews(hairstyleId);
            } else {
                this.showToast(result.error || 'Ошибка при добавлении отзыва');
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
        // Обновляем активную кнопку навигации
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const activeNav = document.querySelector(`[data-page="${page}"]`);
        if (activeNav) {
            activeNav.classList.add('active');
        }

        // Показываем соответствующую страницу
        document.querySelectorAll('.page').forEach(pageEl => {
            pageEl.classList.remove('active');
        });
        
        const targetPage = document.getElementById(`${page}Page`);
        if (targetPage) {
            targetPage.classList.add('active');
        }

        this.currentPage = page;

        // Особые действия для страниц
        if (page === 'favorites') {
            this.renderFavorites();
        } else if (page === 'catalog') {
            this.loadHairstyles(true);
        } else if (page === 'home') {
            this.loadFeaturedHairstyles();
        }
    }

    // Бургер меню
    toggleMenu() {
        const sideMenu = document.getElementById('sideMenu');
        if (sideMenu) {
            sideMenu.classList.toggle('active');
        }
    }

    // Поиск
    toggleSearch() {
        const searchOverlay = document.getElementById('searchOverlay');
        if (searchOverlay) {
            searchOverlay.classList.toggle('hidden');
            
            if (!searchOverlay.classList.contains('hidden')) {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        }
    }

    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            this.handleSearch('');
        }
    }

    // Фильтры
    toggleFilters() {
        const filterPanel = document.getElementById('filterPanel');
        if (filterPanel) {
            filterPanel.classList.toggle('active');
        }
    }

    // Загрузка избранного из localStorage
    loadFavorites() {
        try {
            const saved = localStorage.getItem('hairstyle-favorites');
            if (saved) {
                this.favorites = new Set(JSON.parse(saved));
            }
        } catch (error) {
            console.error('Error loading favorites:', error);
            this.favorites = new Set();
        }
    }

    // Сохранение избранного в localStorage
    saveFavorites() {
        try {
            localStorage.setItem('hairstyle-favorites', JSON.stringify([...this.favorites]));
        } catch (error) {
            console.error('Error saving favorites:', error);
        }
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
        
        if (!favoritesGrid || !emptyState) return;
        
        if (this.favorites.size === 0) {
            favoritesGrid.innerHTML = '';
            favoritesGrid.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }
        
        const favoriteHairstyles = this.allHairstyles.filter(h => 
            h && this.favorites.has(h.id.toString())
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
        if (this.hasMore && !this.isLoading) {
            this.currentPageIndex++;
            this.loadHairstyles(false);
        }
    }

    // Навигация по категориям
    navigateToCatalogWithFilter(category) {
        this.navigateTo('catalog');
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.value = category;
        }
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
        return names[category] || category || 'Неизвестно';
    }

    getLengthName(length) {
        const names = {
            'short': 'Короткие',
            'medium': 'Средние',
            'long': 'Длинные',
            'extra-long': 'Очень длинные'
        };
        return names[length] || length || 'Неизвестно';
    }

    saveSearchQuery(query) {
        try {
            let recentSearches = JSON.parse(localStorage.getItem('recent-searches') || '[]');
            recentSearches = recentSearches.filter(item => item !== query);
            recentSearches.unshift(query);
            recentSearches = recentSearches.slice(0, 5);
            localStorage.setItem('recent-searches', JSON.stringify(recentSearches));
        } catch (error) {
            console.error('Error saving search query:', error);
        }
    }

    closeModal() {
        const modal = document.getElementById('detailModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    showToast(message) {
        let toast = document.getElementById('toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast hidden';
            document.body.appendChild(toast);
        }
        
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