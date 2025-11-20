<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getReviews($pdo);
        break;
    case 'POST':
        addReview($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function getReviews($pdo) {
    $hairstyleId = $_GET['hairstyle_id'] ?? null;
    
    if (!$hairstyleId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Hairstyle ID required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM reviews 
             WHERE hairstyle_id = ? 
             ORDER BY created_at DESC"
        );
        $stmt->execute([intval($hairstyleId)]);
        $reviews = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'reviews' => $reviews
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage(),
            'reviews' => []
        ]);
    }
}

function addReview($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $hairstyleId = isset($data['hairstyle_id']) ? intval($data['hairstyle_id']) : null;
    $userName = $data['user_name'] ?? 'Аноним';
    $rating = isset($data['rating']) ? intval($data['rating']) : null;
    $comment = $data['comment'] ?? '';
    
    if (!$hairstyleId || !$rating || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid hairstyle ID and rating (1-5) are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO reviews (hairstyle_id, user_name, rating, comment) 
             VALUES (?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $hairstyleId, 
            substr(trim($userName), 0, 100), 
            $rating, 
            substr(trim($comment), 0, 1000)
        ]);
        
        echo json_encode([
            'success' => true, 
            'id' => $pdo->lastInsertId(),
            'message' => 'Review added successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to add review: ' . $e->getMessage()
        ]);
    }
}
?>