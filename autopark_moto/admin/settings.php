<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /autopark_moto/auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Проверяем существование таблицы settings, создаем если нет
$db->exec("
    CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_group VARCHAR(50) DEFAULT 'general',
        setting_type VARCHAR(20) DEFAULT 'text',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Базовые настройки по умолчанию
$default_settings = [
    // Общие настройки
    ['site_name', 'AUTOPARK MOTO', 'general', 'text', 'Название системы'],
    ['site_description', 'Система управления мотоциклами', 'general', 'text', 'Описание системы'],
    ['admin_email', 'admin@autopark-moto.ru', 'general', 'email', 'Email администратора'],
    ['timezone', 'Europe/Moscow', 'general', 'select', 'Часовой пояс'],
    ['date_format', 'd.m.Y', 'general', 'select', 'Формат даты'],
    ['items_per_page', '20', 'general', 'number', 'Элементов на странице'],
    
    // Настройки уведомлений
    ['notify_overdue', '1', 'notifications', 'checkbox', 'Уведомлять о просроченном ТО'],
    ['notify_upcoming', '1', 'notifications', 'checkbox', 'Уведомлять о предстоящем ТО'],
    ['notify_new_trip', '1', 'notifications', 'checkbox', 'Уведомлять о новых поездках'],
    ['notify_new_user', '1', 'notifications', 'checkbox', 'Уведомлять о новых пользователях'],
    ['email_notifications', '1', 'notifications', 'checkbox', 'Email уведомления'],
    ['push_notifications', '1', 'notifications', 'checkbox', 'Push уведомления'],
    
    // Настройки безопасности
    ['login_attempts', '5', 'security', 'number', 'Максимум попыток входа'],
    ['session_timeout', '30', 'security', 'number', 'Таймаут сессии (минуты)'],
    ['password_min_length', '8', 'security', 'number', 'Минимальная длина пароля'],
    ['password_complexity', '1', 'security', 'checkbox', 'Требовать сложный пароль'],
    ['two_factor_auth', '0', 'security', 'checkbox', 'Двухфакторная аутентификация'],
    ['ip_whitelist', '', 'security', 'textarea', 'Белый список IP (по одному на строку)'],
    
    // Настройки обслуживания
    ['default_service_interval', '5000', 'maintenance', 'number', 'Интервал ТО по умолчанию (км)'],
    ['default_service_days', '180', 'maintenance', 'number', 'Интервал ТО по умолчанию (дни)'],
    ['notify_before_days', '7', 'maintenance', 'number', 'Уведомлять за дней до ТО'],
    ['auto_generate_services', '1', 'maintenance', 'checkbox', 'Автоматически создавать ТО'],
    ['service_cost_tracking', '1', 'maintenance', 'checkbox', 'Отслеживать стоимость ТО'],
    
    // Настройки интеграций
    ['google_maps_api', '', 'integrations', 'text', 'Google Maps API ключ'],
    ['smtp_enabled', '0', 'integrations', 'checkbox', 'Включить SMTP'],
    ['smtp_host', '', 'integrations', 'text', 'SMTP хост'],
    ['smtp_port', '587', 'integrations', 'number', 'SMTP порт'],
    ['smtp_username', '', 'integrations', 'text', 'SMTP пользователь'],
    ['smtp_password', '', 'integrations', 'password', 'SMTP пароль'],
    
    // Настройки резервного копирования
    ['backup_enabled', '1', 'backup', 'checkbox', 'Включить резервное копирование'],
    ['backup_interval', 'daily', 'backup', 'select', 'Интервал резервного копирования'],
    ['backup_keep_days', '30', 'backup', 'number', 'Хранить резервные копии (дней)'],
    ['backup_email', '', 'backup', 'email', 'Email для уведомлений о бэкапах'],
    ['backup_compress', '1', 'backup', 'checkbox', 'Сжимать резервные копии'],
];

// Вставляем настройки по умолчанию если их нет
foreach ($default_settings as $setting) {
    $check = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check->execute([$setting[0]]);
    
    if (!$check->fetch()) {
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_group, setting_type, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute($setting);
    }
}

// Получаем все настройки
$settings_result = $db->query("SELECT * FROM settings ORDER BY setting_group, setting_key");
$all_settings = $settings_result->fetchAll(PDO::FETCH_ASSOC);

// Группируем настройки по группам
$settings_by_group = [];
foreach ($all_settings as $setting) {
    $settings_by_group[$setting['setting_group']][] = $setting;
}

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8);
            $stmt = $db->prepare("
                UPDATE settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_key = ?
            ");
            $stmt->execute([$value, $setting_key]);
        }
    }
    
    $success_message = "Настройки успешно сохранены!";
}

// Получаем актуальные настройки после сохранения
$settings_result = $db->query("SELECT * FROM settings ORDER BY setting_group, setting_key");
$all_settings = $settings_result->fetchAll(PDO::FETCH_ASSOC);
$settings_by_group = [];
foreach ($all_settings as $setting) {
    $settings_by_group[$setting['setting_group']][] = $setting;
}

// Получаем статистику для боковой панели
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_motorcycles' => $db->query("SELECT COUNT(*) FROM motorcycles")->fetchColumn(),
    'overdue_services' => $db->query("SELECT COUNT(*) FROM motorcycle_services WHERE status = 'overdue'")->fetchColumn(),
    'total_trips' => $db->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - Админ панель AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для админ панели */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
            display: flex;
        }

        /* Боковая панель */
        .admin-sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 30px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid #333;
            text-align: center;
        }

        .sidebar-logo {
            font-size: 24px;
            font-weight: 900;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .sidebar-logo span {
            color: #d60000;
        }

        .sidebar-subtitle {
            font-size: 12px;
            color: #aaa;
            font-weight: 300;
        }

        .sidebar-user {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #333;
            background: rgba(214, 0, 0, 0.1);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: #d60000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            color: white;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .user-role {
            font-size: 12px;
            color: #aaa;
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            margin-bottom: 20px;
        }

        .menu-title {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 20px 10px;
            font-weight: 600;
        }

        .menu-items {
            list-style: none;
        }

        .menu-item {
            margin-bottom: 5px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #ddd;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .menu-link:hover {
            background: rgba(214, 0, 0, 0.1);
            color: white;
            border-left-color: #d60000;
        }

        .menu-link.active {
            background: rgba(214, 0, 0, 0.2);
            color: white;
            border-left-color: #d60000;
        }

        .menu-link i {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .menu-badge {
            margin-left: auto;
            background: #d60000;
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Основной контент */
        .admin-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            background: #f5f5f5;
            min-height: 100vh;
        }

        /* Верхняя панель */
        .admin-topbar {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .page-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .topbar-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-admin {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #d60000;
            color: white;
        }

        .btn-primary:hover {
            background: #a00000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(214, 0, 0, 0.3);
        }

        .btn-secondary {
            background: #333;
            color: white;
        }

        .btn-secondary:hover {
            background: #444;
            transform: translateY(-2px);
        }

        .btn-save {
            background: #28a745;
            color: white;
        }
        
        .btn-save:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-reset {
            background: #ffc107;
            color: white;
        }
        
        .btn-reset:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #d60000;
            color: white;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Вкладки настроек */
        .settings-tabs {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tabs-header {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
        }

        .tab-btn:hover {
            background: #e9ecef;
            color: #333;
        }

        .tab-btn.active {
            color: #d60000;
            border-bottom-color: #d60000;
            background: white;
        }

        .tab-content {
            display: none;
            padding: 30px;
        }

        .tab-content.active {
            display: block;
        }

        /* Формы настроек */
        .settings-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .settings-group {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
        }

        .group-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .group-icon {
            font-size: 20px;
            color: #d60000;
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .group-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .form-row {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-label small {
            font-weight: normal;
            color: #666;
            font-size: 12px;
            display: block;
            margin-top: 3px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #d60000;
            box-shadow: 0 0 0 3px rgba(214, 0, 0, 0.1);
        }

        .form-control[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
            margin-right: 8px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }

        .checkbox-label:hover {
            color: #d60000;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: block;
        }

        /* Переключатель */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #28a745;
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        /* Кнопки действий */
        .form-actions {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
        }

        /* Сообщения */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .alert-info {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border: 1px solid rgba(23, 162, 184, 0.3);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Карточки статистики системы */
        .system-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .system-stat {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #d60000;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Адаптивность */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 70px;
            }
            
            .admin-sidebar .sidebar-logo,
            .admin-sidebar .sidebar-subtitle,
            .admin-sidebar .user-info,
            .admin-sidebar .menu-title,
            .admin-sidebar .menu-link span {
                display: none;
            }
            
            .admin-sidebar .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .admin-content {
                margin-left: 70px;
            }
            
            .settings-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .admin-content {
                padding: 20px;
            }
            
            .admin-topbar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .tabs-header {
                flex-direction: column;
            }
            
            .tab-btn {
                width: 100%;
                text-align: left;
                border-bottom: 1px solid #eee;
                border-right: 3px solid transparent;
            }
            
            .tab-btn.active {
                border-right-color: #d60000;
                border-bottom-color: #eee;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .admin-sidebar {
                width: 60px;
            }
            
            .admin-content {
                margin-left: 60px;
                padding: 15px;
            }
            
            .tab-content {
                padding: 20px;
            }
            
            .settings-group {
                padding: 20px;
            }
            
            .system-stats {
                grid-template-columns: 1fr;
            }
        }

        /* Футер админки */
        .admin-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 12px;
        }

        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        /* Загрузка */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #d60000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Расширенные настройки */
        .advanced-settings {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid #d60000;
        }
        
        .advanced-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .danger-zone {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .danger-title {
            color: #721c24;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .danger-text {
            color: #721c24;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        /* Информационная панель */
        .info-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .info-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Боковая панель -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">AUTOPARK <span>MOTO</span></div>
            <div class="sidebar-subtitle">Административная панель</div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">
                <?php 
                $username = $_SESSION['user']['username'];
                echo strtoupper(substr($username, 0, 1)); 
                ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user']['full_name'] ?: $username); ?></div>
                <div class="user-role">Администратор</div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Главное</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="index.php" class="menu-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Дашборд</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="analytics.php" class="menu-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Аналитика</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="menu-section">
                <div class="menu-title">Управление</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="users.php" class="menu-link">
                            <i class="fas fa-users"></i>
                            <span>Пользователи</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="motorcycles.php" class="menu-link">
                            <i class="fas fa-motorcycle"></i>
                            <span>Мотоциклы</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="services.php" class="menu-link">
                            <i class="fas fa-wrench"></i>
                            <span>Техобслуживание</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="trips.php" class="menu-link">
                            <i class="fas fa-route"></i>
                            <span>Поездки</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="menu-section">
                <div class="menu-title">Настройки</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="settings.php" class="menu-link active">
                            <i class="fas fa-cog"></i>
                            <span>Настройки системы</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="notifications.php" class="menu-link">
                            <i class="fas fa-bell"></i>
                            <span>Уведомления</span>
                        </a>
                    </li>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>

    <!-- Основной контент -->
    <div class="admin-content">
        <!-- Верхняя панель -->
        <div class="admin-topbar">
            <div>
                <h1 class="page-title">Настройки системы</h1>
                <p class="page-subtitle">Управление параметрами и конфигурацией AUTOPARK MOTO</p>
            </div>
            <div class="topbar-actions">
                <button type="button" class="btn-admin btn-primary" onclick="saveSettings()">
                    <i class="fas fa-save"></i>
                    Сохранить все
                </button>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $stats['overdue_services']; ?></span>
                </button>
            </div>
        </div>

        <!-- Сообщения -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <!-- Статистика системы -->
        <div class="system-stats fade-in">
            <div class="system-stat">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Пользователей</div>
            </div>
            <div class="system-stat">
                <div class="stat-value"><?php echo $stats['total_motorcycles']; ?></div>
                <div class="stat-label">Мотоциклов</div>
            </div>
            <div class="system-stat">
                <div class="stat-value"><?php echo $stats['total_trips']; ?></div>
                <div class="stat-label">Поездок</div>
            </div>
            <div class="system-stat">
                <div class="stat-value"><?php echo $stats['overdue_services']; ?></div>
                <div class="stat-label">Просрочено ТО</div>
            </div>
        </div>

        <!-- Информационная панель -->
        <div class="info-panel fade-in">
            <div class="info-title">
                <i class="fas fa-info-circle"></i>
                Информация о системе
            </div>
            <div class="info-content">
                <div class="info-item">
                    <span class="info-label">Версия системы</span>
                    <span class="info-value">2.1.0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">PHP версия</span>
                    <span class="info-value"><?php echo phpversion(); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Сервер БД</span>
                    <span class="info-value">MySQL</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Время сервера</span>
                    <span class="info-value"><?php echo date('d.m.Y H:i:s'); ?></span>
                </div>
            </div>
        </div>

        <!-- Вкладки настроек -->
        <form method="POST" action="" id="settingsForm" class="fade-in">
            <div class="settings-tabs">
                <!-- Заголовки вкладок -->
                <div class="tabs-header">
                    <button type="button" class="tab-btn active" data-tab="general">
                        <i class="fas fa-cog"></i> Общие
                    </button>
                    <button type="button" class="tab-btn" data-tab="notifications">
                        <i class="fas fa-bell"></i> Уведомления
                    </button>
                    <button type="button" class="tab-btn" data-tab="security">
                        <i class="fas fa-shield-alt"></i> Безопасность
                    </button>
                    <button type="button" class="tab-btn" data-tab="maintenance">
                        <i class="fas fa-wrench"></i> Обслуживание
                    </button>
                    <button type="button" class="tab-btn" data-tab="integrations">
                        <i class="fas fa-plug"></i> Интеграции
                    </button>
                    <button type="button" class="tab-btn" data-tab="backup">
                        <i class="fas fa-database"></i> Резервное копирование
                    </button>
                </div>

                <!-- Общие настройки -->
                <div class="tab-content active" id="general-tab">
                    <div class="settings-form">
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <div class="group-title">Основные настройки</div>
                            </div>
                            
                            <?php if (isset($settings_by_group['general'])): ?>
                            <?php foreach ($settings_by_group['general'] as $setting): ?>
                            <div class="form-row">
                                <label class="form-label">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                    <?php if ($setting['setting_type'] == 'checkbox'): ?>
                                    <br>
                                    <label class="checkbox-label">
                                        <input type="checkbox" 
                                               name="setting_<?php echo $setting['setting_key']; ?>" 
                                               value="1" 
                                               <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>
                                               class="form-control">
                                        Включено
                                    </label>
                                    <?php elseif ($setting['setting_type'] == 'select' && $setting['setting_key'] == 'timezone'): ?>
                                    <select name="setting_<?php echo $setting['setting_key']; ?>" class="form-control">
                                        <option value="Europe/Moscow" <?php echo $setting['setting_value'] == 'Europe/Moscow' ? 'selected' : ''; ?>>Москва (UTC+3)</option>
                                        <option value="Europe/Kaliningrad" <?php echo $setting['setting_value'] == 'Europe/Kaliningrad' ? 'selected' : ''; ?>>Калининград (UTC+2)</option>
                                        <option value="Asia/Yekaterinburg" <?php echo $setting['setting_value'] == 'Asia/Yekaterinburg' ? 'selected' : ''; ?>>Екатеринбург (UTC+5)</option>
                                        <option value="Asia/Novosibirsk" <?php echo $setting['setting_value'] == 'Asia/Novosibirsk' ? 'selected' : ''; ?>>Новосибирск (UTC+7)</option>
                                    </select>
                                    <?php elseif ($setting['setting_type'] == 'select' && $setting['setting_key'] == 'date_format'): ?>
                                    <select name="setting_<?php echo $setting['setting_key']; ?>" class="form-control">
                                        <option value="d.m.Y" <?php echo $setting['setting_value'] == 'd.m.Y' ? 'selected' : ''; ?>>дд.мм.гггг</option>
                                        <option value="Y-m-d" <?php echo $setting['setting_value'] == 'Y-m-d' ? 'selected' : ''; ?>>гггг-мм-дд</option>
                                        <option value="m/d/Y" <?php echo $setting['setting_value'] == 'm/d/Y' ? 'selected' : ''; ?>>мм/дд/гггг</option>
                                    </select>
                                    <?php else: ?>
                                    <input type="<?php echo $setting['setting_type']; ?>" 
                                           name="setting_<?php echo $setting['setting_key']; ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                           class="form-control">
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <div class="group-title">Настройки интерфейса</div>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Тема оформления
                                    <select name="theme" class="form-control">
                                        <option value="light">Светлая</option>
                                        <option value="dark">Темная</option>
                                        <option value="auto">Автоматически</option>
                                    </select>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Язык интерфейса
                                    <select name="language" class="form-control">
                                        <option value="ru">Русский</option>
                                        <option value="en">English</option>
                                    </select>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Логотип системы
                                    <input type="file" name="logo" class="form-control" accept="image/*">
                                    <span class="form-help">Рекомендуемый размер: 200x50px</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Настройки уведомлений -->
                <div class="tab-content" id="notifications-tab">
                    <div class="settings-form">
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="group-title">Настройки уведомлений</div>
                            </div>
                            
                            <?php if (isset($settings_by_group['notifications'])): ?>
                            <?php foreach ($settings_by_group['notifications'] as $setting): ?>
                            <div class="form-row">
                                <label class="form-label">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                    <?php if ($setting['setting_type'] == 'checkbox'): ?>
                                    <br>
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="setting_<?php echo $setting['setting_key']; ?>" 
                                               value="1" 
                                               <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span style="margin-left: 10px;"><?php echo $setting['setting_value'] == '1' ? 'Включено' : 'Выключено'; ?></span>
                                    <?php else: ?>
                                    <input type="<?php echo $setting['setting_type']; ?>" 
                                           name="setting_<?php echo $setting['setting_key']; ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                           class="form-control">
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="group-title">Email уведомления</div>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Шаблон email
                                    <select name="email_template" class="form-control">
                                        <option value="default">По умолчанию</option>
                                        <option value="minimal">Минималистичный</option>
                                        <option value="detailed">Детализированный</option>
                                    </select>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Отправитель
                                    <input type="email" name="sender_email" class="form-control" placeholder="noreply@autopark-moto.ru">
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Частота уведомлений
                                    <select name="notification_frequency" class="form-control">
                                        <option value="immediately">Немедленно</option>
                                        <option value="daily">Ежедневно</option>
                                        <option value="weekly">Еженедельно</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Настройки безопасности -->
                <div class="tab-content" id="security-tab">
                    <div class="settings-form">
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="group-title">Настройки безопасности</div>
                            </div>
                            
                            <?php if (isset($settings_by_group['security'])): ?>
                            <?php foreach ($settings_by_group['security'] as $setting): ?>
                            <div class="form-row">
                                <label class="form-label">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                    <?php if ($setting['setting_type'] == 'checkbox'): ?>
                                    <br>
                                    <label class="checkbox-label">
                                        <input type="checkbox" 
                                               name="setting_<?php echo $setting['setting_key']; ?>" 
                                               value="1" 
                                               <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>
                                               class="form-control">
                                        Включено
                                    </label>
                                    <?php elseif ($setting['setting_type'] == 'textarea'): ?>
                                    <textarea name="setting_<?php echo $setting['setting_key']; ?>" 
                                              class="form-control" 
                                              rows="5"
                                              placeholder="Введите IP адреса, по одному на строку"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                    <?php else: ?>
                                    <input type="<?php echo $setting['setting_type']; ?>" 
                                           name="setting_<?php echo $setting['setting_key']; ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                           class="form-control">
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-user-lock"></i>
                                </div>
                                <div class="group-title">Политика паролей</div>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Срок действия пароля (дней)
                                    <input type="number" name="password_expiry" class="form-control" min="0" value="90">
                                    <span class="form-help">0 = неограниченно</span>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    История паролей
                                    <input type="number" name="password_history" class="form-control" min="0" max="10" value="3">
                                    <span class="form-help">Количество предыдущих паролей, которые нельзя использовать</span>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Блокировка при неудачных попытках (минут)
                                    <input type="number" name="lockout_duration" class="form-control" min="1" value="15">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Настройки обслуживания -->
                <div class="tab-content" id="maintenance-tab">
                    <div class="settings-form">
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <div class="group-title">Настройки обслуживания</div>
                            </div>
                            
                            <?php if (isset($settings_by_group['maintenance'])): ?>
                            <?php foreach ($settings_by_group['maintenance'] as $setting): ?>
                            <div class="form-row">
                                <label class="form-label">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                    <?php if ($setting['setting_type'] == 'checkbox'): ?>
                                    <br>
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="setting_<?php echo $setting['setting_key']; ?>" 
                                               value="1" 
                                               <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span style="margin-left: 10px;"><?php echo $setting['setting_value'] == '1' ? 'Включено' : 'Выключено'; ?></span>
                                    <?php else: ?>
                                    <input type="<?php echo $setting['setting_type']; ?>" 
                                           name="setting_<?php echo $setting['setting_key']; ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                           class="form-control">
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-oil-can"></i>
                                </div>
                                <div class="group-title">Параметры ТО</div>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Стандартная стоимость ТО
                                    <input type="number" name="default_service_cost" class="form-control" min="0" step="100" value="3000">
                                    <span class="form-help">Средняя стоимость стандартного ТО (руб.)</span>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Автоматическое продление ТО
                                    <br>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="auto_renew_services" value="1" checked class="form-control">
                                        Включено
                                    </label>
                                    <span class="form-help">Автоматически создавать следующее ТО после завершения текущего</span>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Категории ТО
                                    <textarea name="service_categories" class="form-control" rows="4" placeholder="Масло и фильтр
Тормозная система
Электрика
Ходовая часть"></textarea>
                                    <span class="form-help">Категории обслуживания, по одной на строку</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Интеграции -->
                <div class="tab-content" id="integrations-tab">
                    <div class="settings-form">
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-plug"></i>
                                </div>
                                <div class="group-title">Интеграции и API</div>
                            </div>
                            
                            <?php if (isset($settings_by_group['integrations'])): ?>
                            <?php foreach ($settings_by_group['integrations'] as $setting): ?>
                            <div class="form-row">
                                <label class="form-label">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                    <?php if ($setting['setting_type'] == 'checkbox'): ?>
                                    <br>
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="setting_<?php echo $setting['setting_key']; ?>" 
                                               value="1" 
                                               <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span style="margin-left: 10px;"><?php echo $setting['setting_value'] == '1' ? 'Включено' : 'Выключено'; ?></span>
                                    <?php else: ?>
                                    <input type="<?php echo $setting['setting_type']; ?>" 
                                           name="setting_<?php echo $setting['setting_key']; ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                           class="form-control">
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-map-marked-alt"></i>
                                </div>
                                <div class="group-title">Карты и геолокация</div>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Провайдер карт
                                    <select name="map_provider" class="form-control">
                                        <option value="google">Google Maps</option>
                                        <option value="yandex">Яндекс.Карты</option>
                                        <option value="openstreet">OpenStreetMap</option>
                                    </select>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Трекер GPS
                                    <br>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="gps_tracking" value="1" class="form-control">
                                        Включено
                                    </label>
                                    <span class="form-help">Отслеживание местоположения мотоциклов</span>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    API ключ для погоды
                                    <input type="text" name="weather_api" class="form-control" placeholder="Введите API ключ">
                                    <span class="form-help">Для отображения погоды в поездках</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Резервное копирование -->
                <div class="tab-content" id="backup-tab">
                    <div class="settings-form">
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="group-title">Настройки резервного копирования</div>
                            </div>
                            
                            <?php if (isset($settings_by_group['backup'])): ?>
                            <?php foreach ($settings_by_group['backup'] as $setting): ?>
                            <div class="form-row">
                                <label class="form-label">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                    <?php if ($setting['setting_type'] == 'checkbox'): ?>
                                    <br>
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="setting_<?php echo $setting['setting_key']; ?>" 
                                               value="1" 
                                               <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span style="margin-left: 10px;"><?php echo $setting['setting_value'] == '1' ? 'Включено' : 'Выключено'; ?></span>
                                    <?php elseif ($setting['setting_type'] == 'select' && $setting['setting_key'] == 'backup_interval'): ?>
                                    <select name="setting_<?php echo $setting['setting_key']; ?>" class="form-control">
                                        <option value="hourly" <?php echo $setting['setting_value'] == 'hourly' ? 'selected' : ''; ?>>Ежечасно</option>
                                        <option value="daily" <?php echo $setting['setting_value'] == 'daily' ? 'selected' : ''; ?>>Ежедневно</option>
                                        <option value="weekly" <?php echo $setting['setting_value'] == 'weekly' ? 'selected' : ''; ?>>Еженедельно</option>
                                        <option value="monthly" <?php echo $setting['setting_value'] == 'monthly' ? 'selected' : ''; ?>>Ежемесячно</option>
                                    </select>
                                    <?php else: ?>
                                    <input type="<?php echo $setting['setting_type']; ?>" 
                                           name="setting_<?php echo $setting['setting_key']; ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                           class="form-control">
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="settings-group">
                            <div class="group-header">
                                <div class="group-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="group-title">Облачное хранение</div>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Облачный провайдер
                                    <select name="cloud_provider" class="form-control">
                                        <option value="none">Не использовать</option>
                                        <option value="google">Google Drive</option>
                                        <option value="dropbox">Dropbox</option>
                                        <option value="yandex">Яндекс.Диск</option>
                                    </select>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    API ключ для облака
                                    <input type="text" name="cloud_api_key" class="form-control" placeholder="Введите API ключ">
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">
                                    Папка в облаке
                                    <input type="text" name="cloud_folder" class="form-control" placeholder="autopark-backups">
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Действия с бэкапами -->
                    <div class="advanced-settings">
                        <div class="advanced-title">
                            <i class="fas fa-cogs"></i>
                            Управление резервными копиями
                        </div>
                        
                        <div class="form-row">
                            <button type="button" class="btn-admin btn-secondary" onclick="createBackup()">
                                <i class="fas fa-plus"></i>
                                Создать резервную копию сейчас
                            </button>
                            <button type="button" class="btn-admin btn-secondary" onclick="listBackups()">
                                <i class="fas fa-list"></i>
                                Показать список бэкапов
                            </button>
                            <button type="button" class="btn-admin btn-primary" onclick="testBackup()">
                                <i class="fas fa-vial"></i>
                                Тест резервного копирования
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Кнопки действий -->
            <div class="form-actions fade-in">
                <div>
                    <span style="font-weight: 600; color: #333;">Несохраненные изменения: <span id="unsavedCount">0</span></span>
                </div>
                <div class="action-buttons">
                    <button type="button" class="btn-admin btn-reset" onclick="resetSettings()">
                        <i class="fas fa-undo"></i>
                        Сбросить
                    </button>
                    <button type="submit" name="save_settings" class="btn-admin btn-save">
                        <i class="fas fa-save"></i>
                        Сохранить изменения
                    </button>
                </div>
            </div>
        </form>

        <!-- Зона опасных действий -->
        <div class="danger-zone fade-in">
            <div class="danger-title">
                <i class="fas fa-exclamation-triangle"></i>
                Опасная зона
            </div>
            <div class="danger-text">
                Действия в этом разделе необратимы. Будьте осторожны!
            </div>
            <div class="action-buttons">
                <button type="button" class="btn-danger" onclick="clearLogs()">
                    <i class="fas fa-trash"></i>
                    Очистить логи
                </button>
                <button type="button" class="btn-danger" onclick="resetStatistics()">
                    <i class="fas fa-chart-bar"></i>
                    Сбросить статистику
                </button>
                <button type="button" class="btn-danger" onclick="clearCache()">
                    <i class="fas fa-broom"></i>
                    Очистить кеш
                </button>
                <button type="button" class="btn-danger" onclick="exportAllData()">
                    <i class="fas fa-file-export"></i>
                    Экспорт всех данных
                </button>
            </div>
        </div>

        <!-- Футер -->
        <div class="admin-footer">
            <p>AUTOPARK MOTO Settings &copy; <?php echo date('Y'); ?> | Версия 2.1.0 | Последнее обновление: <?php echo date('d.m.Y H:i'); ?></p>
        </div>
    </div>

    <script>
        // Управление вкладками
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                // Скрываем все вкладки
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Убираем активный класс у всех кнопок
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Показываем выбранную вкладку
                const tabId = button.getAttribute('data-tab');
                document.getElementById(`${tabId}-tab`).classList.add('active');
                button.classList.add('active');
                
                // Сохраняем выбранную вкладку в localStorage
                localStorage.setItem('selectedTab', tabId);
            });
        });
        
        // Восстанавливаем выбранную вкладку
        const savedTab = localStorage.getItem('selectedTab');
        if (savedTab) {
            const tabButton = document.querySelector(`.tab-btn[data-tab="${savedTab}"]`);
            if (tabButton) {
                tabButton.click();
            }
        }
        
        // Отслеживание изменений в форме
        let unsavedChanges = new Set();
        const form = document.getElementById('settingsForm');
        
        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('change', () => {
                unsavedChanges.add(input.name);
                updateUnsavedCount();
            });
        });
        
        function updateUnsavedCount() {
            document.getElementById('unsavedCount').textContent = unsavedChanges.size;
        }
        
        // Подтверждение при закрытии страницы с несохраненными изменениями
        window.addEventListener('beforeunload', (e) => {
            if (unsavedChanges.size > 0) {
                e.preventDefault();
                e.returnValue = 'У вас есть несохраненные изменения. Вы уверены, что хотите уйти?';
            }
        });
        
        // Сохранение настроек
        function saveSettings() {
            const saveBtn = document.querySelector('button[name="save_settings"]');
            const originalText = saveBtn.innerHTML;
            
            // Показываем индикатор загрузки
            saveBtn.innerHTML = '<div class="loading"></div> Сохранение...';
            saveBtn.disabled = true;
            
            // Имитируем задержку сохранения
            setTimeout(() => {
                form.submit();
                unsavedChanges.clear();
                updateUnsavedCount();
            }, 1000);
        }
        
        // Сброс настроек
        function resetSettings() {
            if (confirm('Вы уверены, что хотите сбросить все настройки к значениям по умолчанию? Это действие нельзя отменить.')) {
                location.reload();
            }
        }
        
        // Управление резервным копированием
        function createBackup() {
            if (confirm('Создать резервную копию системы сейчас?')) {
                alert('Резервное копирование запущено. Вы получите уведомление по завершении.');
                // Здесь можно добавить AJAX запрос
            }
        }
        
        function listBackups() {
            alert('Список резервных копий будет отображен в отдельном окне.');
            // Здесь можно добавить отображение списка бэкапов
        }
        
        function testBackup() {
            alert('Тест резервного копирования запущен. Проверьте логи для результатов.');
            // Здесь можно добавить AJAX запрос для теста
        }
        
        // Опасные действия
        function clearLogs() {
            if (confirm('ВНИМАНИЕ! Вы собираетесь очистить все логи системы. Это действие нельзя отменить. Продолжить?')) {
                alert('Логи будут очищены. Пожалуйста, подождите...');
                // Здесь можно добавить AJAX запрос
            }
        }
        
        function resetStatistics() {
            if (confirm('ВНИМАНИЕ! Вы собираетесь сбросить всю статистику системы. Это действие нельзя отменить. Продолжить?')) {
                alert('Статистика будет сброшена. Пожалуйста, подождите...');
                // Здесь можно добавить AJAX запрос
            }
        }
        
        function clearCache() {
            if (confirm('Очистить кеш системы? Это может временно замедлить работу.')) {
                alert('Кеш очищается. Пожалуйста, подождите...');
                // Здесь можно добавить AJAX запрос
            }
        }
        
        function exportAllData() {
            if (confirm('Экспортировать все данные системы? Это может занять некоторое время.')) {
                alert('Экспорт данных запущен. Скачивание начнется автоматически по завершении.');
                // Здесь можно добавить AJAX запрос или редирект
            }
        }
        
        // Проверка подключения к базе данных
        function testDatabaseConnection() {
            fetch('/autopark_moto/admin/test_db.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Подключение к базе данных успешно!');
                    } else {
                        alert('Ошибка подключения к базе данных: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Ошибка при проверке подключения: ' + error.message);
                });
        }
        
        // Автоматическое сохранение каждые 5 минут
        let autoSaveInterval = setInterval(() => {
            if (unsavedChanges.size > 0) {
                console.log('Автоматическое сохранение настроек...');
                // Здесь можно добавить AJAX запрос для автосохранения
            }
        }, 300000);
        
        // Инициализация при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            // Добавляем кнопку теста БД в верхнюю панель
            const testDbBtn = document.createElement('button');
            testDbBtn.className = 'btn-admin btn-secondary';
            testDbBtn.innerHTML = '<i class="fas fa-database"></i> Тест БД';
            testDbBtn.onclick = testDatabaseConnection;
            document.querySelector('.topbar-actions').insertBefore(testDbBtn, document.querySelector('.notification-btn'));
            
            // Восстанавливаем значения из localStorage для несохраненных полей
            form.querySelectorAll('input, select, textarea').forEach(input => {
                const savedValue = localStorage.getItem(`setting_${input.name}`);
                if (savedValue !== null && input.value !== savedValue) {
                    input.value = savedValue;
                    unsavedChanges.add(input.name);
                }
                
                input.addEventListener('change', () => {
                    localStorage.setItem(`setting_${input.name}`, input.value);
                });
            });
            
            updateUnsavedCount();
            
            // Добавляем подсказки для полей
            form.querySelectorAll('.form-control').forEach(input => {
                input.addEventListener('focus', () => {
                    const helpText = input.getAttribute('data-help');
                    if (helpText) {
                        // Можно добавить отображение подсказки
                    }
                });
            });
        });
    </script>
</body>
</html>