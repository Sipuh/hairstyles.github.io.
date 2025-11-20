<?php
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function login($username, $password) {
    // В реальном приложении здесь должна быть проверка из базы данных
    $adminUsername = 'admin';
    $adminPassword = 'admin123'; // В реальности должен быть хэш
    
    if ($username === $adminUsername && $password === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_name'] = 'Администратор';
        return true;
    }
    
    return false;
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>