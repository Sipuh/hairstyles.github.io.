<?php
require_once '../includes/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = new Database();
$pdo = $db->getConnection();

$query = $_GET['q'] ?? '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

// Поиск причесок
$stmt = $pdo->prepare(
    "SELECT DISTINCT h.id, h.name, h.image_path 
     FROM hairstyles h
     LEFT JOIN hairstyle_tags ht ON h.id = ht.hairstyle_id
     LEFT JOIN tags t ON ht.tag_id = t.id
     WHERE h.name LIKE ? OR h.description LIKE ? OR t.name LIKE ?
     LIMIT 10"
);

$searchTerm = "%$query%";
$stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
$results = $stmt->fetchAll();

// Популярные теги
$tagStmt = $pdo->prepare(
    "SELECT name, COUNT(*) as count 
     FROM tags t
     JOIN hairstyle_tags ht ON t.id = ht.tag_id
     WHERE t.name LIKE ?
     GROUP BY t.id
     ORDER BY count DESC
     LIMIT 5"
);

$tagStmt->execute([$searchTerm]);
$tags = $tagStmt->fetchAll();

echo json_encode([
    'hairstyles' => $results,
    'tags' => $tags
]);
?>