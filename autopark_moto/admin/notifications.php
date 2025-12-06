<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /autopark_moto/auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Обработка действий с уведомлениями
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_as_read'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$notification_id]);
        $success_message = "Уведомление помечено как прочитанное";
    }
    
    if (isset($_POST['mark_as_unread'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 0 WHERE id = ?");
        $stmt->execute([$notification_id]);
        $success_message = "Уведомление помечено как непрочитанное";
    }
    
    if (isset($_POST['delete_notification'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$notification_id]);
        $success_message = "Уведомление удалено";
    }
    
    if (isset($_POST['delete_all_read'])) {
        $stmt = $db->prepare("DELETE FROM notifications WHERE is_read = 1");
        $stmt->execute();
        $success_message = "Все прочитанные уведомления удалены";
    }
    
    if (isset($_POST['mark_all_read'])) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1");
        $stmt->execute();
        $success_message = "Все уведомления помечены как прочитанные";
    }
    
    if (isset($_POST['send_notification'])) {
        $user_id = $_POST['user_id'];
        $motorcycle_id = $_POST['motorcycle_id'];
        $title = $_POST['title'];
        $message = $_POST['message'];
        
        $stmt = $db->prepare("INSERT INTO notifications (user_id, motorcycle_id, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$user_id, $motorcycle_id, $title, $message]);
        $success_message = "Новое уведомление отправлено";
    }
}

// Получение всех уведомлений с информацией о пользователях и мотоциклах
$notifications = $db->query("
    SELECT 
        n.*,
        u.username as user_name,
        u.full_name as user_full_name,
        m.make as motorcycle_make,
        m.model as motorcycle_model,
        m.plate as motorcycle_plate
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN motorcycles m ON n.motorcycle_id = m.id
    ORDER BY n.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$stats = [
    'total_notifications' => count($notifications),
    'unread_notifications' => $db->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn(),
    'today_notifications' => $db->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'week_notifications' => $db->query("SELECT COUNT(*) FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'system_notifications' => $db->query("SELECT COUNT(*) FROM notifications WHERE motorcycle_id IS NULL")->fetchColumn(),
    'service_notifications' => $db->query("SELECT COUNT(*) FROM notifications WHERE title LIKE '%сервис%' OR title LIKE '%обслуживание%'")->fetchColumn(),
];

// Получение пользователей для отправки уведомлений
$users = $db->query("SELECT id, username, full_name FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Получение мотоциклов для отправки уведомлений
$motorcycles = $db->query("SELECT id, CONCAT(make, ' ', model, ' (', plate, ')') as name FROM motorcycles ORDER BY make, model")->fetchAll(PDO::FETCH_ASSOC);

// Фильтрация
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Построение запроса с фильтрами
$query = "
    SELECT 
        n.*,
        u.username as user_name,
        u.full_name as user_full_name,
        m.make as motorcycle_make,
        m.model as motorcycle_model,
        m.plate as motorcycle_plate
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN motorcycles m ON n.motorcycle_id = m.id
    WHERE 1=1
";

$params = [];

if ($filter_status === 'read') {
    $query .= " AND n.is_read = 1";
} elseif ($filter_status === 'unread') {
    $query .= " AND n.is_read = 0";
}

if ($filter_type === 'system') {
    $query .= " AND n.motorcycle_id IS NULL";
} elseif ($filter_type === 'motorcycle') {
    $query .= " AND n.motorcycle_id IS NOT NULL";
} elseif ($filter_type === 'service') {
    $query .= " AND (n.title LIKE '%сервис%' OR n.title LIKE '%обслуживание%' OR n.message LIKE '%сервис%' OR n.message LIKE '%обслуживание%')";
}

if ($filter_user) {
    $query .= " AND n.user_id = ?";
    $params[] = $filter_user;
}

if ($filter_date_from) {
    $query .= " AND DATE(n.created_at) >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $query .= " AND DATE(n.created_at) <= ?";
    $params[] = $filter_date_to;
}

$query .= " ORDER BY n.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$filtered_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение популярных типов уведомлений
$notification_types = $db->query("
    SELECT 
        CASE 
            WHEN title LIKE '%сервис%' OR title LIKE '%обслуживание%' THEN 'Сервис'
            WHEN title LIKE '%поезд%' OR title LIKE '%трип%' THEN 'Поездки'
            WHEN title LIKE '%пользовател%' OR title LIKE '%регистрац%' THEN 'Пользователи'
            WHEN title LIKE '%систем%' OR title LIKE '%обновлен%' THEN 'Система'
            ELSE 'Прочие'
        END as type,
        COUNT(*) as count
    FROM notifications
    GROUP BY type
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Уведомления - Админ панель AUTOPARK MOTO</title>
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

        .btn-add {
            background: #28a745;
            color: white;
        }
        
        .btn-add:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
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

        /* Карточки статистики */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .card-total::before { background: linear-gradient(135deg, #007bff, #0056b3); }
        .card-unread::before { background: linear-gradient(135deg, #dc3545, #a71d2a); }
        .card-today::before { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .card-week::before { background: linear-gradient(135deg, #ffc107, #e0a800); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.total .stat-icon { color: #007bff; }
        .stat-card.unread .stat-icon { color: #dc3545; }
        .stat-card.today .stat-icon { color: #28a745; }
        .stat-card.week .stat-icon { color: #ffc107; }

        .stat-value {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            font-weight: 600;
        }

        .trend-up {
            color: #28a745;
        }

        .trend-down {
            color: #dc3545;
        }

        /* Фильтры */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }
        
        .filter-select,
        .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .filter-btn {
            padding: 8px 20px;
            background: #d60000;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            height: 38px;
        }
        
        .filter-btn:hover {
            background: #a00000;
        }
        
        .clear-btn {
            padding: 8px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            height: 38px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .clear-btn:hover {
            background: #545b62;
        }

        /* Уведомления */
        .notifications-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        .notification-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .notification-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .notification-item.unread {
            border-left: 5px solid #d60000;
            background: rgba(214, 0, 0, 0.02);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .notification-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }

        .notification-time {
            font-size: 12px;
            color: #666;
            white-space: nowrap;
            margin-left: 15px;
        }

        .notification-body {
            margin-bottom: 15px;
        }

        .notification-message {
            font-size: 14px;
            color: #555;
            line-height: 1.5;
        }

        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .notification-info {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #666;
        }

        .notification-type {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-system { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .type-service { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .type-motorcycle { background: rgba(255, 193, 7, 0.1); color: #e0a800; }
        .type-user { background: rgba(111, 66, 193, 0.1); color: #6f42c1; }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        .action-btn.read {
            background: #17a2b8;
        }

        .action-btn.unread {
            background: #6c757d;
        }

        .action-btn.delete {
            background: #dc3545;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Форма отправки уведомления */
        .send-notification {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-label .required {
            color: #dc3545;
        }

        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .form-control {
            padding: 10px 15px;
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

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn-send {
            background: #28a745;
            color: white;
        }

        /* Адаптивность */
        @media (max-width: 1200px) {
            .filters {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

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
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .filters {
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .notification-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .notification-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .notification-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .filter-actions {
                flex-direction: column;
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
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 30px;
            }
            
            .action-btn {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .notification-title {
                font-size: 16px;
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
        
        /* Индикатор непрочитанного */
        .unread-indicator {
            width: 10px;
            height: 10px;
            background: #d60000;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        /* Уведомления о действиях */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        /* Массовые действия */
        .bulk-actions {
            background: white;
            padding: 15px 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* Пустой список */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-icon {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-text {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .empty-subtext {
            font-size: 14px;
            color: #888;
        }
        
        /* Подсветка */
        .highlight {
            background: rgba(255, 193, 7, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid rgba(255, 193, 7, 0.3);
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
                        <a href="settings.php" class="menu-link">
                            <i class="fas fa-cog"></i>
                            <span>Настройки системы</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="notifications.php" class="menu-link active">
                            <i class="fas fa-bell"></i>
                            <span>Уведомления</span>
                            <span class="menu-badge"><?php echo $stats['unread_notifications']; ?></span>
                        </a>
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
                <h1 class="page-title">Уведомления</h1>
                <p class="page-subtitle">Управление системными уведомлениями</p>
            </div>
            <div class="topbar-actions">
                <button class="btn-admin btn-warning" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i>
                    Прочитать все
                </button>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $stats['unread_notifications']; ?></span>
                </button>
            </div>
        </div>

        <!-- Уведомление об успешном действии -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success fade-in" id="successAlert">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success_message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Карточки статистики -->
        <div class="stats-grid">
            <div class="stat-card total fade-in">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_notifications']; ?></div>
                <div class="stat-label">Всего уведомлений</div>
                <div class="stat-trend">
                    <span>За неделю: <?php echo $stats['week_notifications']; ?></span>
                </div>
            </div>

            <div class="stat-card unread fade-in">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?php echo $stats['unread_notifications']; ?></div>
                <div class="stat-label">Непрочитанных</div>
                <div class="stat-trend">
                    <span class="trend-down">Требуют внимания</span>
                </div>
            </div>

            <div class="stat-card today fade-in">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-value"><?php echo $stats['today_notifications']; ?></div>
                <div class="stat-label">Сегодня</div>
                <div class="stat-trend">
                    <span>За последние 24 часа</span>
                </div>
            </div>

            <div class="stat-card week fade-in">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo $stats['week_notifications']; ?></div>
                <div class="stat-label">За неделю</div>
                <div class="stat-trend">
                    <span>Сервисных: <?php echo $stats['service_notifications']; ?></span>
                </div>
            </div>
        </div>

        <!-- Форма отправки уведомления -->
        <div class="send-notification fade-in">
            <h3 class="form-title">
                <i class="fas fa-paper-plane"></i>
                Отправить новое уведомление
            </h3>
            
            <form method="POST" id="sendNotificationForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Получатель
                            <span class="required">*</span>
                        </label>
                        <select class="form-control" name="user_id" required>
                            <option value="">Все пользователи</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">Оставьте пустым для отправки всем пользователям</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Мотоцикл</label>
                        <select class="form-control" name="motorcycle_id">
                            <option value="">Не привязывать к мотоциклу</option>
                            <?php foreach ($motorcycles as $moto): ?>
                            <option value="<?php echo $moto['id']; ?>">
                                <?php echo htmlspecialchars($moto['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">Привязка уведомления к конкретному мотоциклу</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Заголовок уведомления
                        <span class="required">*</span>
                    </label>
                    <input type="text" class="form-control" name="title" placeholder="Введите заголовок уведомления" required>
                    <div class="form-help">Краткое описание уведомления</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Текст уведомления
                        <span class="required">*</span>
                    </label>
                    <textarea class="form-control" name="message" placeholder="Введите текст уведомления..." required></textarea>
                    <div class="form-help">Подробное сообщение для пользователя</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="send_notification" class="btn-admin btn-send">
                        <i class="fas fa-paper-plane"></i>
                        Отправить уведомление
                    </button>
                    <button type="button" class="btn-admin btn-secondary" onclick="resetSendForm()">
                        <i class="fas fa-undo"></i>
                        Очистить форму
                    </button>
                </div>
            </form>
        </div>

        <!-- Массовые действия -->
        <div class="bulk-actions fade-in">
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="btn-admin btn-secondary" onclick="return confirm('Отметить все уведомления как прочитанные?')">
                    <i class="fas fa-check-double"></i>
                    Прочитать все
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="delete_all_read" class="btn-admin btn-warning" onclick="return confirm('Удалить все прочитанные уведомления?')">
                    <i class="fas fa-trash-alt"></i>
                    Удалить прочитанные
                </button>
            </form>
            
            <button class="btn-admin btn-primary" onclick="exportNotifications()">
                <i class="fas fa-download"></i>
                Экспорт уведомлений
            </button>
        </div>

        <!-- Фильтры -->
        <form method="GET" action="" class="filters fade-in">
            <div class="filter-group">
                <label class="filter-label">Статус</label>
                <select class="filter-select" name="status">
                    <option value="">Все уведомления</option>
                    <option value="unread" <?php echo $filter_status == 'unread' ? 'selected' : ''; ?>>Непрочитанные</option>
                    <option value="read" <?php echo $filter_status == 'read' ? 'selected' : ''; ?>>Прочитанные</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Тип уведомления</label>
                <select class="filter-select" name="type">
                    <option value="">Все типы</option>
                    <option value="system" <?php echo $filter_type == 'system' ? 'selected' : ''; ?>>Системные</option>
                    <option value="service" <?php echo $filter_type == 'service' ? 'selected' : ''; ?>>Сервисные</option>
                    <option value="motorcycle" <?php echo $filter_type == 'motorcycle' ? 'selected' : ''; ?>>Мотоциклы</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Пользователь</label>
                <select class="filter-select" name="user">
                    <option value="">Все пользователи</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Дата от</label>
                <input type="date" class="filter-input" name="date_from" value="<?php echo $filter_date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Дата до</label>
                <input type="date" class="filter-input" name="date_to" value="<?php echo $filter_date_to; ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Применить
                </button>
                <a href="notifications.php" class="clear-btn">
                    <i class="fas fa-times"></i> Сбросить
                </a>
            </div>
        </form>

        <!-- Список уведомлений -->
        <div class="notifications-container fade-in">
            <?php if (count($filtered_notifications) > 0): ?>
                <?php foreach ($filtered_notifications as $notification): 
                    // Определение типа уведомления
                    $type = 'type-system';
                    $type_label = 'Система';
                    
                    if ($notification['motorcycle_id']) {
                        $type = 'type-motorcycle';
                        $type_label = 'Мотоцикл';
                    }
                    
                    if (stripos($notification['title'], 'сервис') !== false || stripos($notification['title'], 'обслуживание') !== false ||
                        stripos($notification['message'], 'сервис') !== false || stripos($notification['message'], 'обслуживание') !== false) {
                        $type = 'type-service';
                        $type_label = 'Сервис';
                    }
                    
                    if (stripos($notification['title'], 'пользовател') !== false || stripos($notification['title'], 'регистрац') !== false) {
                        $type = 'type-user';
                        $type_label = 'Пользователь';
                    }
                    
                    // Форматирование времени
                    $created_time = strtotime($notification['created_at']);
                    $time_ago = '';
                    $diff = time() - $created_time;
                    
                    if ($diff < 60) {
                        $time_ago = 'только что';
                    } elseif ($diff < 3600) {
                        $time_ago = floor($diff / 60) . ' мин. назад';
                    } elseif ($diff < 86400) {
                        $time_ago = floor($diff / 3600) . ' ч. назад';
                    } else {
                        $time_ago = date('d.m.Y H:i', $created_time);
                    }
                ?>
                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" id="notification-<?php echo $notification['id']; ?>">
                    <div class="notification-header">
                        <div class="notification-title">
                            <?php if (!$notification['is_read']): ?>
                            <span class="unread-indicator" title="Непрочитанное"></span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($notification['title']); ?>
                        </div>
                        <div class="notification-time" title="<?php echo date('d.m.Y H:i', $created_time); ?>">
                            <i class="far fa-clock"></i> <?php echo $time_ago; ?>
                        </div>
                    </div>
                    
                    <div class="notification-body">
                        <p class="notification-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                    </div>
                    
                    <div class="notification-footer">
                        <div class="notification-info">
                            <span class="notification-type <?php echo $type; ?>"><?php echo $type_label; ?></span>
                            
                            <?php if ($notification['user_name']): ?>
                            <span title="Получатель">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($notification['user_full_name'] ?: $notification['user_name']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($notification['motorcycle_make']): ?>
                            <span title="Мотоцикл">
                                <i class="fas fa-motorcycle"></i> <?php echo htmlspecialchars($notification['motorcycle_make'] . ' ' . $notification['motorcycle_model']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if ($notification['is_read']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="mark_as_unread" class="action-btn unread" title="Пометить как непрочитанное">
                                    <i class="fas fa-envelope"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="mark_as_read" class="action-btn read" title="Пометить как прочитанное">
                                    <i class="fas fa-envelope-open"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить это уведомление?')">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="delete_notification" class="action-btn delete" title="Удалить уведомление">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="far fa-bell-slash"></i>
                    </div>
                    <div class="empty-text">Уведомлений не найдено</div>
                    <div class="empty-subtext">Попробуйте изменить параметры фильтрации</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Статистика по типам -->
        <div class="admin-table fade-in">
            <div class="table-header">
                <h2 class="table-title">Статистика по типам уведомлений</h2>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Тип уведомления</th>
                            <th>Количество</th>
                            <th>Процент</th>
                            <th>Последнее</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notification_types as $type): 
                            $percentage = $stats['total_notifications'] > 0 ? round(($type['count'] / $stats['total_notifications']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($type['type']); ?></strong></td>
                            <td><?php echo $type['count']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 100px; height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo $percentage; ?>%; height: 100%; background: #d60000;"></div>
                                    </div>
                                    <span><?php echo $percentage; ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $last_notification = $db->query("
                                    SELECT title, created_at FROM notifications 
                                    WHERE (CASE 
                                        WHEN title LIKE '%сервис%' OR title LIKE '%обслуживание%' THEN 'Сервис'
                                        WHEN title LIKE '%поезд%' OR title LIKE '%трип%' THEN 'Поездки'
                                        WHEN title LIKE '%пользовател%' OR title LIKE '%регистрац%' THEN 'Пользователи'
                                        WHEN title LIKE '%систем%' OR title LIKE '%обновлен%' THEN 'Система'
                                        ELSE 'Прочие'
                                    END) = '{$type['type']}'
                                    ORDER BY created_at DESC LIMIT 1
                                ")->fetch(PDO::FETCH_ASSOC);
                                
                                if ($last_notification) {
                                    echo date('d.m.Y', strtotime($last_notification['created_at']));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Футер -->
        <div class="admin-footer">
            <p>AUTOPARK MOTO Notifications &copy; <?php echo date('Y'); ?> | 
                Всего: <?php echo $stats['total_notifications']; ?> | 
                Непрочитанных: <span class="highlight"><?php echo $stats['unread_notifications']; ?></span> | 
                Сегодня: <?php echo $stats['today_notifications']; ?>
            </p>
        </div>
    </div>

    <script>
        // Управление уведомлениями
        function markAllRead() {
            if (confirm('Отметить все уведомления как прочитанные?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="mark_all_read" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Экспорт уведомлений
        function exportNotifications() {
            let csv = [];
            const rows = document.querySelectorAll('.notification-item');
            
            csv.push(['ID', 'Заголовок', 'Сообщение', 'Тип', 'Получатель', 'Мотоцикл', 'Статус', 'Дата создания']);
            
            rows.forEach(row => {
                const id = row.id.replace('notification-', '');
                const title = row.querySelector('.notification-title').textContent.trim().replace('Непрочитанное', '').trim();
                const message = row.querySelector('.notification-message').textContent.trim();
                const type = row.querySelector('.notification-type').textContent.trim();
                const user = row.querySelector('.notification-info span[title="Получатель"]') ? 
                    row.querySelector('.notification-info span[title="Получатель"]').textContent.replace('Получатель', '').trim() : '';
                const motorcycle = row.querySelector('.notification-info span[title="Мотоцикл"]') ? 
                    row.querySelector('.notification-info span[title="Мотоцикл"]').textContent.replace('Мотоцикл', '').trim() : '';
                const status = row.classList.contains('unread') ? 'Непрочитанное' : 'Прочитанное';
                const date = row.querySelector('.notification-time').getAttribute('title');
                
                csv.push([id, title, message, type, user, motorcycle, status, date]);
            });
            
            // Скачивание файла
            const csvContent = "data:text/csv;charset=utf-8," + csv.map(row => row.join(';')).join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "notifications_export_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Сброс формы отправки
        function resetSendForm() {
            if (confirm('Очистить форму отправки уведомления?')) {
                document.getElementById('sendNotificationForm').reset();
            }
        }
        
        // Уведомления в реальном времени (эмуляция)
        function checkNewNotifications() {
            const unreadCount = <?php echo $stats['unread_notifications']; ?>;
            const badge = document.querySelector('.notification-badge');
            
            // В реальной системе здесь был бы AJAX запрос
            // Для демонстрации просто обновляем счетчик
            console.log('Проверка новых уведомлений...');
        }
        
        // Автоматическая проверка каждые 30 секунд
        setInterval(checkNewNotifications, 30000);
        
        // Анимация при клике на уведомление
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!e.target.closest('.notification-actions') && !e.target.closest('form')) {
                    this.style.transform = 'scale(0.99)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                }
            });
        });
        
        // Автоматическое скрытие уведомлений об успехе
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }, 5000);
        }
        
        // Поиск по уведомлениям
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Поиск по уведомлениям...';
        searchInput.style.cssText = `
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            width: 300px;
            margin-left: auto;
        `;
        
        const filters = document.querySelector('.filters');
        if (filters) {
            filters.parentNode.insertBefore(searchInput, filters.nextSibling);
            
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const notifications = document.querySelectorAll('.notification-item');
                
                notifications.forEach(notification => {
                    const text = notification.textContent.toLowerCase();
                    notification.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
        
        // Плавная прокрутка к новым уведомлениям
        document.addEventListener('DOMContentLoaded', function() {
            const unreadNotifications = document.querySelectorAll('.notification-item.unread');
            if (unreadNotifications.length > 0 && !window.location.hash) {
                unreadNotifications[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
        
        // Подтверждение массовых действий
        document.querySelectorAll('form').forEach(form => {
            const submitButtons = form.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(button => {
                if (button.name === 'delete_all_read' || button.name === 'mark_all_read') {
                    button.addEventListener('click', function(e) {
                        if (!confirm(this.getAttribute('data-confirm') || 'Вы уверены?')) {
                            e.preventDefault();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>