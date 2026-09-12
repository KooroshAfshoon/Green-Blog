<?php
require_once __DIR__ . '/db.php';

// logs a view, either site-wide (post_id = NULL) or for one post.
// used two ways: just requiring this file tracks a global hit,
// calling trackPostView($pdo, $postId) tracks that specific post.

function getVisitorIp() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
    return trim(explode(',', $ip)[0]);
}

function trackPostView($pdo, $postId) {
    session_start();
    if (isset($_SESSION['admin'])) return;

    $ip = getVisitorIp();

    $check = $pdo->prepare("SELECT id FROM views WHERE ip = ? AND post_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $check->execute([$ip, $postId]);
    if (!$check->fetch()) {
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $pdo->prepare("INSERT INTO views (ip, post_id, user_agent) VALUES (?, ?, ?)")->execute([$ip, $postId, $ua]);
    }
}

// legacy behavior when the file is required directly, logs a global hit
if (!defined('SKIP_GLOBAL_VIEW_TRACK')) {
    session_start();
    if (!isset($_SESSION['admin'])) {
        $ip = getVisitorIp();
        $check = $pdo->prepare("SELECT id FROM views WHERE ip = ? AND post_id IS NULL AND viewed_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $check->execute([$ip]);
        if (!$check->fetch()) {
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $pdo->prepare("INSERT INTO views (ip, post_id, user_agent) VALUES (?, NULL, ?)")->execute([$ip, $ua]);
        }
    }
}