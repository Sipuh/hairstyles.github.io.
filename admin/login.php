<?php
session_start();
require_once __DIR__. '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверные учетные данные';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-form">
            <div class="login-header">
                <i class="fas fa-crown"></i>
                <h1>HairStyle Pro</h1>
                <p>Админ-панель</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Логин</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Войти
                </button>
            </form>
            
            <div class="login-footer">
                <p>Демо доступ: admin / admin123</p>
            </div>
        </div>
    </div>
</body>
</html>