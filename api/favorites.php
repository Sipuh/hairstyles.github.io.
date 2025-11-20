<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

// Безопасное получение идентификатора сессии пользователя
$userSession = $_GET['session_id'] ?? $_POST['session_id'] ?? $_COOKIE['user_session'] ?? 'default_user';
$userSession = substr(trim($userSession), 0, 64); // Ограничиваем длину

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getFavorites($pdo, $userSession);
            break;
        case 'POST':
            addToFavorites($pdo, $userSession);
            break;
        case 'DELETE':
            removeFromFavorites($pdo, $userSession);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function getFavorites($pdo, $userSession) {
    $stmt = $pdo->prepare(
        "SELECT h.*, 
                GROUP_CONCAT(DISTINCT t.name) as tags,
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(DISTINCT r.id) as review_count
         FROM favorites f
         JOIN hairstyles h ON f.hairstyle_id = h.id
         LEFT JOIN hairstyle_tags ht ON h.id = ht.hairstyle_id
         LEFT JOIN tags t ON ht.tag_id = t.id
         LEFT JOIN reviews r ON h.id = r.hairstyle_id
         WHERE f.user_session = ?
         GROUP BY h.id 
         ORDER BY f.created_at DESC"
    );
    
    $stmt->execute([$userSession]);
    $favorites = $stmt->fetchAll();

    foreach ($favorites as &$hairstyle) {
        $hairstyle['tags'] = $hairstyle['tags'] ? explode(',', $hairstyle['tags']) : [];
        $hairstyle['avg_rating'] = round((float)$hairstyle['avg_rating'], 1);
        $hairstyle['review_count'] = (int)$hairstyle['review_count'];
    }

    echo json_encode([
        'success' => true,
        'favorites' => $favorites,
        'count' => count($favorites)
    ], JSON_UNESCAPED_UNICODE);
}

function addToFavorites($pdo, $userSession) {
    $input = json_decode(file_get_contents('php://input'), true);
    $hairstyleId = filter_var($input['hairstyle_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$hairstyleId || $hairstyleId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid hairstyle ID is required']);
        return;
    }

    // Проверяем существование прически
    $checkStmt = $pdo->prepare("SELECT id FROM hairstyles WHERE id = ?");
    $checkStmt->execute([$hairstyleId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Hairstyle not found']);
        return;
    }

    // Проверяем, не добавлена ли уже
    $existingStmt = $pdo->prepare(
        "SELECT id FROM favorites WHERE user_session = ? AND hairstyle_id = ?"
    );
    $existingStmt->execute([$userSession, $hairstyleId]);
    
    if ($existingStmt->fetch()) {
        echo json_encode([
            'success' => true,
            'message' => 'Already in favorites',
            'action' => 'already_exists'
        ]);
        return;
    }

    // Добавляем в избранное
    $stmt = $pdo->prepare(
        "INSERT INTO favorites (user_session, hairstyle_id) VALUES (?, ?)"
    );
    $stmt->execute([$userSession, $hairstyleId]);

    echo json_encode([
        'success' => true,
        'message' => 'Added to favorites',
        'action' => 'added'
    ]);
}

function removeFromFavorites($pdo, $userSession) {
    $input = json_decode(file_get_contents('php://input'), true);
    $hairstyleId = filter_var($input['hairstyle_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$hairstyleId || $hairstyleId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid hairstyle ID is required']);
        return;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM favorites WHERE user_session = ? AND hairstyle_id = ?"
    );
    $stmt->execute([$userSession, $hairstyleId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Removed from favorites',
            'action' => 'removed'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Not found in favorites',
            'action' => 'not_found'
        ]);
    }
}
?>