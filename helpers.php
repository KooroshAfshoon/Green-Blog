<?php
// shared helper functions

// pulls #word tags out of the post text, e.g. #php #linux #security
function extractTags($content) {
    preg_match_all('/#([a-zA-Z0-9_\x{0600}-\x{06FF}]+)/u', $content, $matches);
    $tags = array_unique($matches[1]);
    return array_values($tags);
}

// removes #tags from the text so they're not shown twice (tags get rendered as chips separately)
function stripTags($content) {
    $clean = preg_replace('/#([a-zA-Z0-9_\x{0600}-\x{06FF}]+)/u', '', $content);
    return trim($clean);
}

function syncPostTags($pdo, $postId, $content) {
    $tags = extractTags($content);
    $pdo->prepare("DELETE FROM post_tags WHERE post_id = ?")->execute([$postId]);
    if (!empty($tags)) {
        $stmt = $pdo->prepare("INSERT INTO post_tags (post_id, tag) VALUES (?, ?)");
        foreach ($tags as $tag) {
            $stmt->execute([$postId, mb_strtolower($tag)]);
        }
    }
}

// rough estimate, ~150 words/min works fine for Persian and mixed code content
function readingTime($content, $codeSnippet = '') {
    $text = stripTags($content) . ' ' . ($codeSnippet ?? '');
    $wordCount = preg_match_all('/[\p{L}\p{N}_]+/u', $text, $m);
    $minutes = max(1, ceil($wordCount / 150));
    return $minutes;
}

function renderTagChips($tags) {
    if (empty($tags)) return '';
    $html = '<div class="tag-list">';
    foreach ($tags as $tag) {
        $safe = htmlspecialchars($tag);
        $html .= "<span class=\"tag-chip\" data-tag=\"" . mb_strtolower($safe) . "\" onclick=\"filterByTag('" . mb_strtolower($safe) . "')\">#$safe</span>";
    }
    $html .= '</div>';
    return $html;
}