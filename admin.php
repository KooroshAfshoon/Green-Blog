<?php
session_start();
require 'db.php';
require_once 'helpers.php';
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit; }

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content']);
    $code    = trim($_POST['code_snippet'] ?? '');
    $pinned  = isset($_POST['is_pinned']) ? 1 : 0;

    foreach (['uploads', 'ascii'] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    $imagePaths = [];

    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($_FILES['images']['error'][$i] !== 0) continue;
            if (empty($name)) continue;

            $ext        = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $uid        = uniqid($i . '_', true);
            $path       = 'uploads/' . $uid . '.' . $ext;
            $renderType = $_POST['render_types'][$i] ?? 'normal';

            if (!move_uploaded_file($_FILES['images']['tmp_name'][$i], $path)) {
                $error = "خطا در آپلود فایل: $name";
                break;
            }

            if ($renderType === 'ascii') {
                include_once 'ascii_converter.php';
                $ascii = imageToAscii($path);
                if (strlen($ascii) < 200) {
                    $error = "خطا در تبدیل ASCII: $ascii";
                    if (file_exists($path)) unlink($path);
                    break;
                }
                $asciiPath = 'ascii/' . uniqid($i . '_', true) . '.txt';
                if (file_put_contents($asciiPath, $ascii) === false) {
                    $error = "خطا در ذخیره فایل ASCII";
                    if (file_exists($path)) unlink($path);
                    break;
                }
                if (file_exists($path)) unlink($path);
                $path = $asciiPath;
            }

            $imagePaths[] = $path;
        }
    }

    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content, image_path, render_type, is_pinned, code_snippet) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title ?: null,
                $content,
                json_encode($imagePaths),
                'multiple',
                $pinned,
                $code ?: null
            ]);
            $newPostId = $pdo->lastInsertId();
            syncPostTags($pdo, $newPostId, $content);
            $success = true;
        } catch (Exception $e) {
            $error = "خطا در ذخیره پست: " . $e->getMessage();
        }
    }
}

// sidebar stats
$today      = $pdo->query("SELECT COUNT(*) FROM views WHERE DATE(viewed_at) = CURDATE()")->fetchColumn();
$yesterday  = $pdo->query("SELECT COUNT(*) FROM views WHERE DATE(viewed_at) = CURDATE() - INTERVAL 1 DAY")->fetchColumn();
$week       = $pdo->query("SELECT COUNT(*) FROM views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$month      = $pdo->query("SELECT COUNT(*) FROM views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$total      = $pdo->query("SELECT COUNT(*) FROM views")->fetchColumn();
$postCount  = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$totalLikes = $pdo->query("SELECT COALESCE(SUM(likes),0) FROM posts")->fetchColumn();

// last 14 days of site-wide views, for the chart
$chartRows = $pdo->query("
    SELECT DATE(viewed_at) as day, COUNT(*) as cnt
    FROM views
    WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(viewed_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// fill in the days with no views so the chart doesn't have gaps
$chartLabels = [];
$chartData   = [];
for ($d = 13; $d >= 0; $d--) {
    $date          = date('Y-m-d', strtotime("-$d days"));
    $chartLabels[] = date('m/d', strtotime($date));
    $chartData[]   = (int)($chartRows[$date] ?? 0);
}

// per-post views/likes, plus most-viewed ranking
$postStats = $pdo->query("
    SELECT p.id, p.title, p.likes, p.created_at,
           COALESCE(v.view_count, 0) AS view_count
    FROM posts p
    LEFT JOIN (
        SELECT post_id, COUNT(*) AS view_count
        FROM views
        WHERE post_id IS NOT NULL
        GROUP BY post_id
    ) v ON v.post_id = p.id
    ORDER BY view_count DESC, p.likes DESC
")->fetchAll();

$topPosts = array_slice($postStats, 0, 5);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Green Blog</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .post-stats-table {
            width: 100%; border-collapse: collapse; font-size: 0.82rem;
            font-family: var(--mono, monospace);
        }
        .post-stats-table th, .post-stats-table td {
            text-align: right; padding: 6px 8px; border-bottom: 1px solid #2e2c4a;
            color: #c9c7e8;
        }
        .post-stats-table th { color: #7c7a9a; font-weight: normal; font-size: 0.75rem; }
        .post-stats-table tr:hover { background: rgba(139,92,246,0.05); }
        .post-stats-table a { color: #a78bfa; text-decoration: none; }
        .rank-badge {
            display: inline-block; width: 20px; height: 20px; line-height: 20px;
            text-align: center; border-radius: 4px; font-size: 0.7rem;
            background: rgba(139,92,246,0.15); color: #a78bfa; margin-left: 4px;
        }
        .table-wrap { overflow-x: auto; }
    </style>
</head>
<body>

<header>
    <div class="inner">
        <h1>
            <span class="prompt">root@green-blog:~$ </span><span class="cmd">admin-panel</span><span class="cursor"></span>
        </h1>
        <a href="index.php" class="admin-link">← بازگشت به بلاگ</a>
    </div>
</header>

<div class="admin-layout">

    <!-- sidebar -->
    <aside class="admin-sidebar">

        <div class="sidebar-section">
            <div class="sidebar-title">// stats</div>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today; ?></div>
                    <div class="stat-label">امروز</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $yesterday; ?></div>
                    <div class="stat-label">دیروز</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $week; ?></div>
                    <div class="stat-label">7 روز</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $month; ?></div>
                    <div class="stat-label">30 روز</div>
                </div>
            </div>

            <div class="stat-total">
                <span class="stat-total-label">// total views</span>
                <span class="stat-total-val"><?php echo number_format($total); ?></span>
            </div>
            <div class="stat-total" style="margin-top:6px">
                <span class="stat-total-label">// total posts</span>
                <span class="stat-total-val"><?php echo $postCount; ?></span>
            </div>
            <div class="stat-total" style="margin-top:6px">
                <span class="stat-total-label">// total likes</span>
                <span class="stat-total-val"><?php echo number_format($totalLikes); ?></span>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-title">// views — 14 days</div>
            <div class="chart-wrap">
                <canvas id="viewsChart"></canvas>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-title">// top posts (by views)</div>
            <div class="table-wrap">
                <table class="post-stats-table">
                    <thead>
                        <tr><th>#</th><th>عنوان</th><th>👁</th><th>♥</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topPosts)): ?>
                            <tr><td colspan="4" style="color:#5c5a7a;">— پستی نیست —</td></tr>
                        <?php endif; ?>
                        <?php foreach ($topPosts as $rank => $p): ?>
                            <tr>
                                <td><span class="rank-badge"><?php echo $rank + 1; ?></span></td>
                                <td><a href="post.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title'] ?: '(بدون عنوان #' . $p['id'] . ')'); ?></a></td>
                                <td><?php echo (int)$p['view_count']; ?></td>
                                <td><?php echo (int)$p['likes']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-title">// all posts — views &amp; likes</div>
            <div class="table-wrap">
                <table class="post-stats-table">
                    <thead>
                        <tr><th>عنوان</th><th>👁</th><th>♥</th><th>تاریخ</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($postStats)): ?>
                            <tr><td colspan="4" style="color:#5c5a7a;">— پستی نیست —</td></tr>
                        <?php endif; ?>
                        <?php foreach ($postStats as $p): ?>
                            <tr>
                                <td><a href="post.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title'] ?: '(بدون عنوان #' . $p['id'] . ')'); ?></a></td>
                                <td><?php echo (int)$p['view_count']; ?></td>
                                <td><?php echo (int)$p['likes']; ?></td>
                                <td style="color:#7c7a9a; font-size:0.72rem;"><?php echo date('m/d', strtotime($p['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </aside>

    <!-- main form -->
    <main class="admin-wrap">

        <?php if ($success): ?>
            <div class="alert-success">✓ پست با موفقیت منتشر شد — post deployed to production.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error">✗ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label class="form-label" for="title">// title (اختیاری)</label>
                <input type="text" id="title" name="title"
                       value="<?php echo isset($_POST['title']) && !$success ? htmlspecialchars($_POST['title']) : ''; ?>"
                       placeholder="عنوان پست...">
            </div>

            <div class="form-group">
                <label class="form-label" for="content">// content — از [IMAGE 1] برای جای عکس و #tag برای تگ استفاده کن</label>
                <textarea id="content" name="content" rows="10"
                          placeholder="متن اصلی پست... مثال: #php #linux #security" required><?php echo isset($_POST['content']) && !$success ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="code_snippet">// code snippet (اختیاری)</label>
                <textarea id="code_snippet" name="code_snippet" rows="5"
                          style="font-family: var(--mono, monospace); font-size: 0.82rem;"
                          placeholder="کد اضافه..."><?php echo isset($_POST['code_snippet']) && !$success ? htmlspecialchars($_POST['code_snippet']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">// images (اختیاری)</label>
                <div id="image-container">
                    <div class="image-group">
                        <input type="file" name="images[]" accept="image/*">
                        <select name="render_types[]">
                            <option value="normal">Normal</option>
                            <option value="ascii">ASCII</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn btn-add" onclick="addImageField()">+ افزودن تصویر</button>
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="is_pinned">
                📌 پین شود در بالای صفحه
            </label>

            <button type="submit" class="btn btn-primary">▶ EXECUTE POST</button>

        </form>
    </main>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
function addImageField() {
    const container = document.getElementById('image-container');
    const div = document.createElement('div');
    div.classList.add('image-group');
    div.innerHTML = `
        <input type="file" name="images[]" accept="image/*">
        <select name="render_types[]">
            <option value="normal">Normal</option>
            <option value="ascii">ASCII</option>
        </select>
        <button type="button" onclick="this.parentElement.remove()"
                style="background:transparent;border:none;color:#f87171;cursor:pointer;font-size:1rem;padding:0 4px;">✕</button>
    `;
    container.appendChild(div);
}

const ctx = document.getElementById('viewsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($chartData); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139,92,246,0.08)',
            borderWidth: 2,
            pointBackgroundColor: '#8b5cf6',
            pointRadius: 3,
            pointHoverRadius: 5,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: {
                ticks: { color: '#7c7a9a', font: { family: 'monospace', size: 10 } },
                grid:  { color: 'rgba(255,255,255,0.04)' }
            },
            y: {
                beginAtZero: true,
                ticks: { color: '#7c7a9a', font: { family: 'monospace', size: 10 }, precision: 0 },
                grid:  { color: 'rgba(255,255,255,0.04)' }
            }
        }
    }
});
</script>
</body>
</html>