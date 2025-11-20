<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'hairstyle_catalog');
define('DB_USER', 'root');
define('DB_PASS', '');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Разрешенные типы файлов
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];