<?php
session_start();
require 'db.php';
require_once 'helpers.php';
require_once 'track_view.php';

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (isset($_GET['del']) && isset($_SESSION['admin'])) {
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$_GET['del']]);
    header("Location: index.php");
    exit;
}

// tag filter, comes from the URL query string
$activeTag = isset($_GET['tag']) ? mb_strtolower(trim($_GET['tag'])) : null;

if ($activeTag) {
    $stmt = $pdo->prepare("
        SELECT p.* FROM posts p
        INNER JOIN post_tags t ON t.post_id = p.id
        WHERE t.tag = ?
        ORDER BY p.is_pinned DESC, p.created_at DESC
    ");
    $stmt->execute([$activeTag]);
    $posts = $stmt->fetchAll();
} else {
    $posts = $pdo->query("SELECT * FROM posts ORDER BY is_pinned DESC, created_at DESC")->fetchAll();
}

function renderPostContent($content, $mediaMap) {
    if (isset($mediaMap[1])) {
        $content = str_replace('[IMAGE]', $mediaMap[1], $content);
    }
    foreach ($mediaMap as $i => $media) {
        $content = str_replace("[IMAGE $i]", $media, $content);
    }
    $content = preg_replace_callback('/\[code\s*(.*?)\](.*?)\[\/code\]/s', function($matches) {
        $language = !empty($matches[1]) ? htmlspecialchars(trim($matches[1])) : 'code';
        $codeContent = htmlspecialchars(trim($matches[2]));
        return "<div class='code-container'><div class='code-header'>$language</div><pre class='code-tag'><code>$codeContent</code></pre></div>";
    }, $content);
    return nl2br($content);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Blog | Terminal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .reading-progress-bar {
            position: fixed; top: 0; left: 0; height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #22c55e);
            width: 0%; z-index: 999; transition: width 0.1s ease-out;
        }
        .toolbar {
            display: flex; gap: 0.75rem; flex-wrap: wrap;
            align-items: center; margin-bottom: 1.25rem;
        }
        #search-box {
            flex: 1; min-width: 180px;
            background: #1a1a2e; border: 1px solid #2e2c4a;
            color: #e5e3ff; border-radius: 6px;
            padding: 0.6rem 0.9rem; font-family: var(--mono, monospace);
            font-size: 0.85rem;
        }
        #search-box::placeholder { color: #5c5a7a; }
        #search-box:focus { outline: none; border-color: #8b5cf6; }
        .random-btn {
            background: #1a1a2e; border: 1px solid #8b5cf6; color: #a78bfa;
            border-radius: 6px; padding: 0.6rem 1rem; font-family: var(--mono, monospace);
            font-size: 0.85rem; cursor: pointer; white-space: nowrap;
            transition: all 0.15s;
        }
        .random-btn:hover { background: rgba(139,92,246,0.15); }
        .active-tag-banner {
            display: flex; align-items: center; gap: 0.5rem;
            background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.25);
            border-radius: 6px; padding: 0.5rem 1rem; margin-bottom: 1rem;
            font-size: 0.85rem; color: #a78bfa; font-family: var(--mono, monospace);
        }
        .active-tag-banner a { color: #f87171; text-decoration: none; margin-right: auto; }
        .post-meta-row {
            direction:ltr;
            display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;
            margin-bottom: 0.5rem; font-size: 0.8rem; color: #7c7a9a;
        }
        .read-time::before { content: "⏱ "; }
        .tag-list { display: flex; flex-wrap: wrap; gap: 6px; margin: 0.5rem 0 0.75rem; }
        .tag-chip {
            background: rgba(139,92,246,0.12); color: #a78bfa;
            border: 1px solid rgba(139,92,246,0.3);
            border-radius: 999px; padding: 2px 10px; font-size: 0.78rem;
            cursor: pointer; font-family: var(--mono, monospace);
            transition: all 0.15s;
        }
        .tag-chip:hover { background: rgba(139,92,246,0.25); }
        .post.hidden-by-search { display: none !important; }
        .open-link { float: left; }
    </style>
</head>
<body>

<div class="reading-progress-bar" id="readingProgress"></div>

<header>
    <div class="inner">
        <h1>
            <span class="prompt">root@green-blog:~$ </span><span class="cmd">list-posts</span><span class="cursor"></span>
        </h1>
        <?php if (isset($_SESSION['admin'])): ?>
            <a href="admin.php" class="admin-link">[ ADMIN ]</a>
        <?php endif; ?>
    </div>
</header>

<div class="container">

    <div class="toolbar">
        <input type="text" id="search-box" placeholder="search posts...">
        <button class="random-btn" onclick="goRandom()">[ RANDOM ]</button>
    </div>

    <?php if ($activeTag): ?>
        <div class="active-tag-banner">
            <span>// فیلتر: #<?php echo htmlspecialchars($activeTag); ?></span>
            <a href="index.php">✕ حذف فیلتر</a>
        </div>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <p>// هنوز پستی منتشر نشده</p>
            <p style="margin-top:0.5rem; color: #3a3858;">no posts found — be the first</p>
        </div>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
        <?php
            $tags = extractTags($post['content']);
            $contentClean = stripTags($post['content']);
            $readMinutes = readingTime($post['content'], $post['code_snippet'] ?? '');
        ?>
        <article class="post <?php echo $post['is_pinned'] ? 'pinned' : ''; ?>"
                 id="post-<?php echo $post['id']; ?>"
                 data-title="<?php echo htmlspecialchars(mb_strtolower($post['title'])); ?>"
                 data-content="<?php echo htmlspecialchars(mb_strtolower($contentClean)); ?>">

            <?php if ($post['is_pinned']): ?>
                <div class="pin-badge">&#x1F4CC; pinned</div>
            <?php endif; ?>

            <div class="meta">
                [<?php echo htmlspecialchars($post['created_at']); ?>]
                <a href="post.php?id=<?php echo $post['id']; ?>" class="open-link" style="color:#7c7a9a; text-decoration:none;">[ open &#x2197; ]</a>
            </div>
            <h2><a href="post.php?id=<?php echo $post['id']; ?>" style="color:inherit; text-decoration:none;"><?php echo htmlspecialchars($post['title']); ?></a></h2>

            <div class="post-meta-row">
                <span class="read-time"><?php echo $readMinutes; ?> min read</span>
            </div>

            <?php echo renderTagChips($tags); ?>

            <div class="post-content">
                <?php
                $mediaMap = [];
                if (!empty($post['image_path'])) {
                    $raw = $post['image_path'];
                    $images = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($images)) {
                        foreach ($images as $index => $path) {
                            if (str_ends_with($path, '.txt')) {
                                $ascii = file_exists($path) ? file_get_contents($path) : '';
                                $mediaMap[$index + 1] = "<pre class='ascii-art'>" . htmlspecialchars($ascii) . "</pre>";
                            } else {
                                $mediaMap[$index + 1] = "<img src='" . htmlspecialchars($path) . "' class='normal-img' loading='lazy'>";
                            }
                        }
                    } else {
                        if (str_ends_with($raw, '.txt')) {
                            $ascii = file_exists($raw) ? file_get_contents($raw) : '';
                            $mediaMap[1] = "<pre class='ascii-art'>" . htmlspecialchars($ascii) . "</pre>";
                        } else {
                            $mediaMap[1] = "<img src='" . htmlspecialchars($raw) . "' class='normal-img' loading='lazy'>";
                        }
                    }
                }
                echo renderPostContent($contentClean, $mediaMap);
                ?>
            </div>

            <?php if (!empty($post['code_snippet'])): ?>
                <div class="code-container" style="margin-top:1rem;">
                    <div class="code-header">snippet</div>
                    <pre class="code-tag"><code><?php echo htmlspecialchars($post['code_snippet']); ?></code></pre>
                </div>
            <?php endif; ?>

            <div class="post-actions">
                <button class="like-btn" data-id="<?php echo $post['id']; ?>" onclick="likePost(<?php echo $post['id']; ?>, this)">
                    &#x2665; <span class="count"><?php echo $post['likes']; ?></span>
                </button>

                <button class="like-btn share-btn" onclick="sharePost(<?php echo $post['id']; ?>, this)">
                    &#x2197; share
                </button>

                <?php if (isset($_SESSION['admin'])): ?>
                    <a href="edit.php?id=<?php echo $post['id']; ?>" class="like-btn edit-btn">&#x270E; edit</a>
                    <a href="index.php?del=<?php echo $post['id']; ?>" class="like-btn del-btn" onclick="return confirm('حذف شود؟')">&#x2715; delete</a>
                <?php endif; ?>
            </div>

        </article>
    <?php endforeach; ?>

    <div id="no-results" class="empty-state" style="display:none;">
        <p>// نتیجه‌ای یافت نشد</p>
    </div>
</div>

<!-- Share overlay -->
<div id="share-overlay" onclick="closeShare(event)">
    <div id="share-modal">
        <div id="share-preview-wrap">
            <canvas id="share-canvas"></canvas>
        </div>
        <div id="share-modal-actions">
            <button class="btn" id="share-copy-btn" onclick="copyToClipboard()">&#x2398; کپی در کلیپ‌بورد</button>
            <button class="btn" onclick="closeShare()">&#x2715; بستن</button>
        </div>
        <p id="share-hint">// rendering post snapshot...</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
window.addEventListener('scroll', () => {
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    document.getElementById('readingProgress').style.width = Math.min(100, Math.max(0, pct)) + '%';
});

// live search, filters posts as you type
const searchBox = document.getElementById('search-box');
const allPosts = Array.from(document.querySelectorAll('.post'));
const noResults = document.getElementById('no-results');

searchBox.addEventListener('input', () => {
    const q = searchBox.value.trim().toLowerCase();
    let visibleCount = 0;

    allPosts.forEach(post => {
        const title = post.dataset.title || '';
        const content = post.dataset.content || '';
        const match = !q || title.includes(q) || content.includes(q);
        post.classList.toggle('hidden-by-search', !match);
        if (match) visibleCount++;
    });

    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
});

function filterByTag(tag) {
    window.location.href = 'index.php?tag=' + encodeURIComponent(tag);
}

const allPostIds = <?php echo json_encode(array_map(fn($p) => (int)$p['id'], $posts)); ?>;
function goRandom() {
    if (allPostIds.length === 0) return;
    const randomId = allPostIds[Math.floor(Math.random() * allPostIds.length)];
    window.location.href = 'post.php?id=' + randomId;
}

document.addEventListener('DOMContentLoaded', () => {
    const likedPosts = JSON.parse(localStorage.getItem('likedPosts') || '[]');
    likedPosts.forEach(id => {
        const btn = document.querySelector(`.like-btn[data-id="${id}"]`);
        if (btn) btn.classList.add('is-liked');
    });
});

function likePost(id, btn) {
    let likedPosts = JSON.parse(localStorage.getItem('likedPosts') || '[]');
    const isLiked = likedPosts.includes(id);
    const action = isLiked ? 'unlike' : 'like';
    fetch(`like.php?id=${id}&action=${action}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.querySelector('.count').innerText = data.new_likes;
                if (isLiked) {
                    likedPosts = likedPosts.filter(p => p !== id);
                    btn.classList.remove('is-liked');
                } else {
                    likedPosts.push(id);
                    btn.classList.add('is-liked');
                }
                localStorage.setItem('likedPosts', JSON.stringify(likedPosts));
            }
        });
}

let shareBlob = null;

function sharePost(id, btn) {
    const article = document.getElementById('post-' + id);
    if (!article) return;

    const overlay = document.getElementById('share-overlay');
    const hint = document.getElementById('share-hint');
    const previewWrap = document.getElementById('share-preview-wrap');

    overlay.classList.add('active');
    hint.textContent = '// rendering post snapshot...';
    previewWrap.style.opacity = '0';
    shareBlob = null;

    const actions = article.querySelector('.post-actions');
    if (actions) actions.style.visibility = 'hidden';

    html2canvas(article, {
        backgroundColor: '#13131f',
        scale: 2,
        useCORS: true,
        logging: false,
        removeContainer: true
    }).then(canvas => {
        if (actions) actions.style.visibility = '';

        const previewCanvas = document.getElementById('share-canvas');
        previewCanvas.width = canvas.width;
        previewCanvas.height = canvas.height;
        const ctx = previewCanvas.getContext('2d');
        ctx.drawImage(canvas, 0, 0);

        previewWrap.style.opacity = '1';
        hint.textContent = '// snapshot ready — کپی کن یا ببند';

        canvas.toBlob(blob => { shareBlob = blob; }, 'image/png');
    }).catch(() => {
        if (actions) actions.style.visibility = '';
        hint.textContent = '// خطا در رندر تصویر';
    });
}

async function copyToClipboard() {
    const hint = document.getElementById('share-hint');
    const btn = document.getElementById('share-copy-btn');
    if (!shareBlob) return;
    try {
        await navigator.clipboard.write([
            new ClipboardItem({ 'image/png': shareBlob })
        ]);
        const orig = btn.innerHTML;
        btn.innerHTML = '&#x2714; کپی شد!';
        hint.textContent = '// copied to clipboard successfully';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    } catch (err) {
        const canvas = document.getElementById('share-canvas');
        const a = document.createElement('a');
        a.download = 'post-share.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
        hint.textContent = '// clipboard not available — downloading instead';
    }
}

function closeShare(e) {
    if (!e || e.target === document.getElementById('share-overlay')) {
        document.getElementById('share-overlay').classList.remove('active');
    }
}
</script>
</body>
</html>