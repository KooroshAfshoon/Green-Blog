<?php
// no whitespace before the opening tag above, or the JSON output breaks
require 'db.php';

header('Content-Type: application/json');

if (isset($_GET['id']) && isset($_GET['action'])) {
    try {
        $id = (int)$_GET['id'];
        $action = $_GET['action'];

        if ($action === 'like') {
            $stmt = $pdo->prepare("UPDATE posts SET likes = likes + 1 WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE posts SET likes = GREATEST(0, likes - 1) WHERE id = ?");
        }
        
        if(!$stmt->execute([$id])) {
             throw new Exception("Update failed");
        }

        $getLikes = $pdo->prepare("SELECT likes FROM posts WHERE id = ?");
        $getLikes->execute([$id]);
        $newLikes = $getLikes->fetchColumn();
        
        echo json_encode(['success' => true, 'new_likes' => (int)$newLikes]);
    } catch (Exception $e) {
        // still return JSON on failure so the frontend doesn't choke on it
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
}
exit;