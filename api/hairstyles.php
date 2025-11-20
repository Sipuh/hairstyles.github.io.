<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getHairstyles($pdo);
        break;
    case 'POST':
        addHairstyle($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getHairstyles($pdo) {
    try {
        // Валидация и преобразование параметров
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 12;
        $offset = ($page - 1) * $limit;

        $category = $_GET['category'] ?? '';
        $length = $_GET['length'] ?? '';
        $difficulty = $_GET['difficulty'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // Если запрашивают конкретную прическу по ID
        $hairstyleId = $_GET['id'] ?? null;
        if ($hairstyleId) {
            getHairstyleById($pdo, intval($hairstyleId));
            return;
        }
        
        $sql = "SELECT h.*, 
                       GROUP_CONCAT(DISTINCT t.name) as tags,
                       COALESCE(AVG(r.rating), 0) as avg_rating,
                       COUNT(DISTINCT r.id) as review_count
                FROM hairstyles h
                LEFT JOIN hairstyle_tags ht ON h.id = ht.hairstyle_id
                LEFT JOIN tags t ON ht.tag_id = t.id
                LEFT JOIN reviews r ON h.id = r.hairstyle_id
                WHERE 1=1";
        
        $params = [];
        $whereConditions = [];
        
        if (!empty($category)) {
            $whereConditions[] = "h.category = ?";
            $params[] = $category;
        }
        
        if (!empty($length)) {
            $lengths = explode(',', $length);
            $placeholders = str_repeat('?,', count($lengths) - 1) . '?';
            $whereConditions[] = "h.length IN ($placeholders)";
            $params = array_merge($params, $lengths);
        }
        
        if (!empty($difficulty)) {
            $difficulties = explode(',', $difficulty);
            $placeholders = str_repeat('?,', count($difficulties) - 1) . '?';
            $whereConditions[] = "h.difficulty IN ($placeholders)";
            $params = array_merge($params, $difficulties);
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(h.name LIKE ? OR h.description LIKE ? OR t.name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($whereConditions)) {
            $sql .= " AND " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " GROUP BY h.id 
                  ORDER BY h.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Привязываем параметры с указанием типа
        foreach ($params as $key => $value) {
            $stmt->bindValue(($key + 1), $value);
        }
        
        // Привязываем LIMIT и OFFSET как целые числа
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $hairstyles = $stmt->fetchAll();
        
        // Форматируем теги в массив
        foreach ($hairstyles as &$hairstyle) {
            $hairstyle['tags'] = $hairstyle['tags'] ? explode(',', $hairstyle['tags']) : [];
            $hairstyle['avg_rating'] = round((float)$hairstyle['avg_rating'], 1);
            $hairstyle['review_count'] = (int)$hairstyle['review_count'];
        }
        
        // Получаем общее количество для пагинации
        $countSql = "SELECT COUNT(DISTINCT h.id) as total 
                     FROM hairstyles h
                     LEFT JOIN hairstyle_tags ht ON h.id = ht.hairstyle_id
                     LEFT JOIN tags t ON ht.tag_id = t.id
                     LEFT JOIN reviews r ON h.id = r.hairstyle_id
                     WHERE 1=1";
        
        if (!empty($whereConditions)) {
            $countSql .= " AND " . implode(" AND ", $whereConditions);
        }
        
        $countStmt = $pdo->prepare($countSql);
        
        // Привязываем параметры для count запроса
        foreach ($params as $key => $value) {
            $countStmt->bindValue(($key + 1), $value);
        }
        
        $countStmt->execute();
        $totalResult = $countStmt->fetch();
        $total = (int)($totalResult['total'] ?? 0);
        
        echo json_encode([
            'success' => true,
            'hairstyles' => $hairstyles,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage(),
            'hairstyles' => [],
            'pagination' => [
                'page' => 1,
                'limit' => 12,
                'total' => 0,
                'pages' => 0
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
}

function getHairstyleById($pdo, $id) {
    try {
        $stmt = $pdo->prepare(
            "SELECT h.*, 
                    GROUP_CONCAT(DISTINCT t.name) as tags,
                    COALESCE(AVG(r.rating), 0) as avg_rating,
                    COUNT(DISTINCT r.id) as review_count
             FROM hairstyles h
             LEFT JOIN hairstyle_tags ht ON h.id = ht.hairstyle_id
             LEFT JOIN tags t ON ht.tag_id = t.id
             LEFT JOIN reviews r ON h.id = r.hairstyle_id
             WHERE h.id = ?
             GROUP BY h.id"
        );
        
        $stmt->execute([$id]);
        $hairstyle = $stmt->fetch();
        
        if (!$hairstyle) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Hairstyle not found']);
            return;
        }
        
        // Форматируем теги
        $hairstyle['tags'] = $hairstyle['tags'] ? explode(',', $hairstyle['tags']) : [];
        $hairstyle['avg_rating'] = round((float)$hairstyle['avg_rating'], 1);
        $hairstyle['review_count'] = (int)$hairstyle['review_count'];
        
        echo json_encode([
            'success' => true,
            'hairstyle' => $hairstyle
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function addHairstyle($pdo) {
    // Для админ-панели
    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'Use admin.php for adding hairstyles']);
}
?>