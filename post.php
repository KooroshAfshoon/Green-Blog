<?php
session_start();
require 'db.php';
require_once 'helpers.php';

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: index.php");
    exit;
}

define('SKIP_GLOBAL_VIEW_TRACK', true);
require_once 'track_view.php';
trackPostView($pdo, $id);

// same image/code rendering logic as index.php
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

$contentClean   = stripTags($post['content']);
$tags           = extractTags($post['content']);
$readMinutes    = readingTime($post['content'], $post['code_snippet'] ?? '');
$renderedHtml   = renderPostContent($contentClean, $mediaMap);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title'] ?: 'Post #' . $post['id']); ?> | Green Blog</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .reading-progress-bar {
            position: fixed; top: 0; left: 0; height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #22c55e);
            width: 0%; z-index: 999; transition: width 0.1s ease-out;
        }
        .post-meta-row {
            direction:ltr;
            display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;
            margin-bottom: 0.75rem; font-size: 0.8rem; color: #7c7a9a;
        }
        .read-time::before { content: "⏱ "; }
        .tag-list { display: flex; flex-wrap: wrap; gap: 6px; margin: 0.75rem 0; }
        .tag-chip {
            background: rgba(139,92,246,0.12); color: #a78bfa;
            border: 1px solid rgba(139,92,246,0.3);
            border-radius: 999px; padding: 2px 10px; font-size: 0.78rem;
            cursor: pointer; font-family: var(--mono, monospace);
            transition: all 0.15s;
        }
        .tag-chip:hover { background: rgba(139,92,246,0.25); }
        #typed-content { white-space: normal; }
        #typed-content .typing-cursor {
            display: inline-block; width: 8px; height: 1.1em;
            background: #8b5cf6; margin-left: 2px; vertical-align: middle;
            animation: blink 0.8s step-end infinite;
        }
        @keyframes blink { 50% { opacity: 0; } }
        .back-link { display: inline-block; margin-bottom: 1rem; }
    </style>
</head>
<body>

<div class="reading-progress-bar" id="readingProgress"></div>

<header>
    <div class="inner">
        <h1>
            <span class="prompt">root@green-blog:~$ </span><span class="cmd">view-post --id <?php echo (int)$post['id']; ?></span><span class="cursor"></span>
        </h1>
        <?php if (isset($_SESSION['admin'])): ?>
            <a href="admin.php" class="admin-link">[ ADMIN ]</a>
        <?php endif; ?>
    </div>
</header>

<div class="container">
    <a href="index.php" class="like-btn back-link">&#x2190; بازگشت به لیست</a>

    <article class="post <?php echo $post['is_pinned'] ? 'pinned' : ''; ?>" id="post-<?php echo $post['id']; ?>">

        <?php if ($post['is_pinned']): ?>
            <div class="pin-badge">&#x1F4CC; pinned</div>
        <?php endif; ?>

        <div class="meta">[<?php echo htmlspecialchars($post['created_at']); ?>]</div>
        <h2><?php echo htmlspecialchars($post['title']); ?></h2>

        <div class="post-meta-row">
            <span class="read-time"><?php echo $readMinutes; ?> min read</span>
        </div>

        <?php echo renderTagChips($tags); ?>

        <div class="post-content" id="post-content-wrapper">
            <div id="typed-content"></div>
            <div id="real-content" style="display:none;"><?php echo $renderedHtml; ?></div>
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

function filterByTag(tag) {
    window.location.href = 'index.php?tag=' + encodeURIComponent(tag);
}

// fake terminal-style typing effect for the post body
(function typeContent() {
    const realEl = document.getElementById('real-content');
    const typedEl = document.getElementById('typed-content');
    const html = realEl.innerHTML;

    // type faster for long posts (lots of images/ascii art)
    const speed = html.length > 1500 ? 2 : 8; // ms per char
    const charsPerTick = html.length > 3000 ? 5 : 1;

    let i = 0;
    typedEl.innerHTML = '<span class="typing-cursor"></span>';

    function tick() {
        if (i >= html.length) {
            typedEl.innerHTML = html; // swap in the real markup at the end, avoids half-typed tags
            return;
        }

        // add whole tags in one go so we never split one mid-way through
        let chunk = '';
        let count = 0;
        while (count < charsPerTick && i < html.length) {
            if (html[i] === '<') {
                const close = html.indexOf('>', i);
                if (close !== -1) {
                    chunk += html.substring(i, close + 1);
                    i = close + 1;
                } else {
                    chunk += html[i];
                    i++;
                }
            } else {
                chunk += html[i];
                i++;
            }
            count++;
        }

        typedEl.innerHTML = typedEl.innerHTML.replace('<span class="typing-cursor"></span>', '') + chunk + '<span class="typing-cursor"></span>';
        setTimeout(tick, speed);
    }

    tick();
})();

document.addEventListener('DOMContentLoaded', () => {
    const likedPosts = JSON.parse(localStorage.getItem('likedPosts') || '[]');
    if (likedPosts.includes(<?php echo (int)$post['id']; ?>)) {
        document.querySelector('.like-btn[data-id]').classList.add('is-liked');
    }
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