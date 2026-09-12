<?php
session_start();
require_once 'db.php'; // provides $config

if (isset($_POST['login'])) {
    $inputUser = $_POST['user'] ?? '';
    $inputPass = $_POST['pass'] ?? '';

    if (
        hash_equals($config['admin_user'], $inputUser) &&
        password_verify($inputPass, $config['admin_pass_hash'])
    ) {
        $_SESSION['admin'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "رمز اشتباه است!";
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><link rel="stylesheet" href="style.css"></head>
<body>
    <div class="container" style="text-align: center; margin-top: 100px;">
        <form method="POST" class="post">
            <h2>ورود به ترمینال Green Blog</h2>
            <input type="text" name="user" id="title" placeholder="نام کاربری">
            <input type="password" name="pass" id="title" placeholder="رمز عبور"><br>
            <button type="submit" id="btn"name="login" style="background:var(--primary); color:white; border:none; padding:10px 20px; cursor:pointer;">Login</button>
            <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        </form>
    </div>
</body>
</html>
