<?php
// includes/config.php

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'autopark_moto');
define('DB_USER', 'root');
define('DB_PASS', '');

// Настройки сессии
session_start();

// Базовый URL
$base_url = 'http://localhost/autopark_moto/';

// Настройки сайта
define('SITE_NAME', 'AUTOPARK MOTO');
define('SITE_EMAIL', 'info@autopark-moto.ru');
define('ADMIN_EMAIL', 'admin@autopark-moto.ru');

// Пути
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/autopark_moto/uploads/');
define('IMG_PATH', $base_url . 'assets/img/');

// Настройки безопасности
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 минут в секундах

// Включаем отладку на время разработки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Автозагрузка классов (если понадобится)
spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    
    // Проверяем разные возможные расположения классов
    $paths = [
        __DIR__ . '/../models/' . $class . '.php',
        __DIR__ . '/../classes/' . $class . '.php',
        __DIR__ . '/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Функция для проверки авторизации
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /autopark_moto/auth/login.php');
        exit();
    }
}

// Функция для проверки роли администратора
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

// Функция для проверки роли менеджера
function isManager() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'manager']);
}

// Функция для получения настроек из базы данных
function getSettings($key = null) {
    $db = connectDB();
    $settings = [];
    
    try {
        if ($key) {
            $stmt = $db->prepare("SELECT value FROM settings WHERE setting_key = :key");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['value'] : null;
        } else {
            $stmt = $db->query("SELECT setting_key, value FROM settings");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['value'];
            }
            
            return $settings;
        }
    } catch (PDOException $e) {
        return $key ? null : [];
    }
}

// Функция для добавления уведомления
function addNotification($user_id, $title, $message, $type = 'system') {
    $db = connectDB();
    
    try {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                             VALUES (:user_id, :title, :message, :type)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        return false;
    }
}
?>