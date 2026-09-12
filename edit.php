<?php
session_start();
require 'db.php';
require_once 'helpers.php';
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit; }
if (!isset($_GET['id'])) { header("Location: index.php"); exit; }

$id   = (int)$_GET['id'];
$post = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$post->execute([$id]);
$post = $post->fetch();
if (!$post) { header("Location: index.php"); exit; }

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content']);
    $code    = trim($_POST['code_snippet'] ?? '');
    $pinned  = isset($_POST['is_pinned']) ? 1 : 0;

    if (!function_exists('str_ends_with')) {
        function str_ends_with($h, $n) { return substr($h, -strlen($n)) === $n; }
    }

    foreach (['uploads', 'ascii'] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    $imagePaths = json_decode($post['image_path'], true) ?: [];

    if (!empty($_FILES['images']['name'][0])) {
        // wipe the old files from disk too if replace_images was checked
        if (isset($_POST['replace_images'])) {
            foreach ($imagePaths as $oldPath) {
                if (file_exists($oldPath)) unlink($oldPath);
            }
            $imagePaths = [];
        }

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
            $stmt = $pdo->prepare("UPDATE posts SET title=?, content=?, image_path=?, is_pinned=?, code_snippet=? WHERE id=?");
            $stmt->execute([
                $title ?: null,
                $content,
                json_encode($imagePaths),
                $pinned,
                $code ?: null,
                $id
            ]);

            syncPostTags($pdo, $id, $content);

            $success = true;

            // reload so the form shows the updated values
            $post = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $post->execute([$id]);
            $post = $post->fetch();
        } catch (Exception $e) {
            $error = "خطا در ذخیره پست: " . $e->getMessage();
        }
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($h, $n) { return substr($h, -strlen($n)) === $n; }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post — Green Blog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="inner">
        <h1>
            <span class="prompt">root@green-blog:~$ </span><span class="cmd">edit-post --id=<?php echo $id; ?></span><span class="cursor"></span>
        </h1>
        <a href="index.php" class="admin-link">← بازگشت</a>
    </div>
</header>

<div class="admin-wrap">

    <?php if ($success): ?>
        <div class="alert-success">✓ پست با موفقیت ویرایش شد — changes committed.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-error">✗ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
            <label class="form-label" for="title">// title</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="content">// content — از [IMAGE 1] برای جای عکس و #tag برای تگ استفاده کن</label>
            <textarea id="content" name="content" rows="12" required><?php echo htmlspecialchars($post['content']); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="code_snippet">// code snippet (اختیاری)</label>
            <textarea id="code_snippet" name="code_snippet" rows="5" style="font-family: var(--mono, monospace); font-size: 0.82rem;"><?php echo htmlspecialchars($post['code_snippet'] ?? ''); ?></textarea>
        </div>

        <?php
        $existingImages = json_decode($post['image_path'], true) ?: [];
        if (!empty($existingImages)):
        ?>
        <div class="form-group">
            <label class="form-label">// تصاویر فعلی</label>
            <div class="existing-images">
                <?php foreach ($existingImages as $imgPath): ?>
                    <div class="existing-img-item">
                        <?php if (str_ends_with($imgPath, '.txt')): ?>
                            <span class="existing-img-label">📄 ASCII: <?php echo htmlspecialchars(basename($imgPath)); ?></span>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="post image" style="max-height:60px; border-radius:4px; border:1px solid var(--border);">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <label class="checkbox-row" style="margin-top:0.6rem;">
                <input type="checkbox" name="replace_images">
                جایگزین کردن تصاویر قدیمی با تصاویر جدید
            </label>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">// <?php echo empty($existingImages) ? 'images' : 'افزودن تصویر جدید'; ?></label>
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
            <input type="checkbox" name="is_pinned" <?php echo $post['is_pinned'] ? 'checked' : ''; ?>>
            📌 پین شود در بالای صفحه
        </label>

        <button type="submit" class="btn btn-primary">&#x270E; COMMIT CHANGES</button>

    </form>
</div>

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
        <button type="button" onclick="this.parentElement.remove()" style="background:transparent;border:none;color:#f87171;cursor:pointer;font-size:1rem;padding:0 4px;">✕</button>
    `;
    container.appendChild(div);
}
</script>
</body>
</html>