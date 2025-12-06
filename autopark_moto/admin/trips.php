<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /autopark_moto/auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Получение всех поездок с информацией о мотоциклах и пользователях
$trips = $db->query("
    SELECT 
        t.*,
        m.make as motorcycle_make,
        m.model as motorcycle_model,
        m.plate as motorcycle_plate,
        u.username as user_name,
        u.full_name as user_full_name
    FROM trips t
    JOIN motorcycles m ON t.motorcycle_id = m.id
    JOIN users u ON t.user_id = u.id
    ORDER BY t.trip_date DESC, t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$stats = [
    'total_trips' => count($trips),
    'total_distance' => $db->query("SELECT COALESCE(SUM(distance), 0) FROM trips")->fetchColumn(),
    'avg_distance' => $db->query("SELECT COALESCE(AVG(distance), 0) FROM trips")->fetchColumn(),
    'unique_motorcycles' => $db->query("SELECT COUNT(DISTINCT motorcycle_id) FROM trips")->fetchColumn(),
    'unique_users' => $db->query("SELECT COUNT(DISTINCT user_id) FROM trips")->fetchColumn(),
    'this_month_trips' => $db->query("SELECT COUNT(*) FROM trips WHERE MONTH(trip_date) = MONTH(CURRENT_DATE()) AND YEAR(trip_date) = YEAR(CURRENT_DATE())")->fetchColumn(),
    'this_month_distance' => $db->query("SELECT COALESCE(SUM(distance), 0) FROM trips WHERE MONTH(trip_date) = MONTH(CURRENT_DATE()) AND YEAR(trip_date) = YEAR(CURRENT_DATE())")->fetchColumn(),
];

// Получение статистики по месяцам
$monthly_stats = $db->query("
    SELECT 
        DATE_FORMAT(trip_date, '%Y-%m') as month,
        COUNT(*) as trip_count,
        SUM(distance) as total_distance,
        AVG(distance) as avg_distance
    FROM trips 
    WHERE trip_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(trip_date, '%Y-%m')
    ORDER BY month DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Получение самых активных мотоциклов
$top_motorcycles = $db->query("
    SELECT 
        m.make,
        m.model,
        m.plate,
        COUNT(t.id) as trip_count,
        SUM(t.distance) as total_distance
    FROM trips t
    JOIN motorcycles m ON t.motorcycle_id = m.id
    GROUP BY t.motorcycle_id
    ORDER BY total_distance DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Получение самых активных пользователей
$top_users = $db->query("
    SELECT 
        u.username,
        u.full_name,
        COUNT(t.id) as trip_count,
        SUM(t.distance) as total_distance
    FROM trips t
    JOIN users u ON t.user_id = u.id
    GROUP BY t.user_id
    ORDER BY total_distance DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Получение мотоциклов для фильтра
$motorcycles = $db->query("
    SELECT id, CONCAT(make, ' ', model, ' (', plate, ')') as name 
    FROM motorcycles 
    ORDER BY make, model
")->fetchAll(PDO::FETCH_ASSOC);

// Получение пользователей для фильтра
$users = $db->query("
    SELECT id, username, full_name FROM users ORDER BY username
")->fetchAll(PDO::FETCH_ASSOC);

// Обработка фильтров
$filter_motorcycle = $_GET['motorcycle'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_distance_min = $_GET['distance_min'] ?? '';
$filter_distance_max = $_GET['distance_max'] ?? '';

// Построение запроса с фильтрами
$query = "
    SELECT 
        t.*,
        m.make as motorcycle_make,
        m.model as motorcycle_model,
        m.plate as motorcycle_plate,
        u.username as user_name,
        u.full_name as user_full_name
    FROM trips t
    JOIN motorcycles m ON t.motorcycle_id = m.id
    JOIN users u ON t.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($filter_motorcycle) {
    $query .= " AND t.motorcycle_id = ?";
    $params[] = $filter_motorcycle;
}

if ($filter_user) {
    $query .= " AND t.user_id = ?";
    $params[] = $filter_user;
}

if ($filter_date_from) {
    $query .= " AND t.trip_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $query .= " AND t.trip_date <= ?";
    $params[] = $filter_date_to;
}

if ($filter_distance_min) {
    $query .= " AND t.distance >= ?";
    $params[] = $filter_distance_min;
}

if ($filter_distance_max) {
    $query .= " AND t.distance <= ?";
    $params[] = $filter_distance_max;
}

$query .= " ORDER BY t.trip_date DESC, t.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$filtered_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поездки - Админ панель AUTOPARK MOTO</title>
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
        .card-distance::before { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .card-avg::before { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .card-month::before { background: linear-gradient(135deg, #17a2b8, #138496); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.total .stat-icon { color: #007bff; }
        .stat-card.distance .stat-icon { color: #28a745; }
        .stat-card.avg .stat-icon { color: #ffc107; }
        .stat-card.month .stat-icon { color: #17a2b8; }

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

        /* Таблицы */
        .admin-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .table-header {
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .table-link {
            color: #d60000;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }

        .table-link:hover {
            color: #a00000;
        }

        .table-content {
            overflow-x: auto;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        table thead {
            background: #f8f9fa;
        }

        table th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            border-bottom: 1px solid #eee;
        }

        table td {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        table tbody tr {
            transition: background 0.3s;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Действия */
        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 12px;
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

        .action-btn.view {
            background: #17a2b8;
        }

        .action-btn.edit {
            background: #ffc107;
        }

        .action-btn.delete {
            background: #dc3545;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Дистанция */
        .distance-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .distance-small {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.3);
        }

        .distance-medium {
            background: rgba(255, 193, 7, 0.1);
            color: #e0a800;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .distance-large {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Описание */
        .trip-description {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .trip-description:hover {
            white-space: normal;
            overflow: visible;
            position: absolute;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 100;
            max-width: 300px;
        }

        /* Графики и дополнительные таблицы */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .analytics-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .analytics-table {
            width: 100%;
            border-collapse: collapse;
        }

        .analytics-table th,
        .analytics-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .analytics-table th {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }

        /* Адаптивность */
        @media (max-width: 1200px) {
            .filters {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
            
            .analytics-grid {
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
            
            .table-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
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
            
            table th, table td {
                padding: 10px 15px;
            }
            
            .action-btn {
                padding: 6px 10px;
                font-size: 11px;
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
        
        /* Индикатор дня недели */
        .day-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        .day-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .day-weekend {
            background: #ffc107;
        }
        
        .day-weekday {
            background: #6c757d;
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
                        <a href="trips.php" class="menu-link active">
                            <i class="fas fa-route"></i>
                            <span>Поездки</span>
                            <span class="menu-badge"><?php echo $stats['total_trips']; ?></span>
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
                        <a href="notifications.php" class="menu-link">
                            <i class="fas fa-bell"></i>
                            <span>Уведомления</span>
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
                <h1 class="page-title">Поездки</h1>
                <p class="page-subtitle">История и управление поездками на мотоциклах</p>
            </div>
            <div class="topbar-actions">
                <a href="trips_add.php" class="btn-admin btn-primary btn-add">
                    <i class="fas fa-plus"></i>
                    Добавить поездку
                </a>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $stats['this_month_trips']; ?></span>
                </button>
            </div>
        </div>

        <!-- Карточки статистики -->
        <div class="stats-grid">
            <div class="stat-card total fade-in">
                <div class="stat-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_trips']; ?></div>
                <div class="stat-label">Всего поездок</div>
                <div class="stat-trend">
                    <span>В этом месяце: <?php echo $stats['this_month_trips']; ?></span>
                </div>
            </div>

            <div class="stat-card distance fade-in">
                <div class="stat-icon">
                    <i class="fas fa-road"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_distance'] / 1000, 1); ?>ккм</div>
                <div class="stat-label">Общий пробег</div>
                <div class="stat-trend">
                    <span>В этом месяце: <?php echo number_format($stats['this_month_distance'] / 1000, 1); ?>ккм</span>
                </div>
            </div>

            <div class="stat-card avg fade-in">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['avg_distance'], 0); ?>км</div>
                <div class="stat-label">Средняя дистанция</div>
                <div class="stat-trend">
                    <span>На <?php echo $stats['unique_motorcycles']; ?> мотоциклах</span>
                </div>
            </div>

            <div class="stat-card month fade-in">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-value"><?php echo $stats['unique_users']; ?></div>
                <div class="stat-label">Активных пользователей</div>
                <div class="stat-trend">
                    <span class="trend-up"><?php echo count($monthly_stats); ?> месяцев активности</span>
                </div>
            </div>
        </div>

        <!-- Аналитика -->
        <div class="analytics-grid fade-in">
            <!-- Топ мотоциклов -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <h3 class="analytics-title">Самые активные мотоциклы</h3>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Мотоцикл</th>
                            <th>Поездок</th>
                            <th>Дистанция</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_motorcycles as $moto): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($moto['make'] . ' ' . $moto['model']); ?></strong><br>
                                <small><?php echo htmlspecialchars($moto['plate']); ?></small>
                            </td>
                            <td><?php echo $moto['trip_count']; ?></td>
                            <td><strong><?php echo number_format($moto['total_distance']); ?> км</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Топ пользователей -->
            <div class="analytics-card">
                <div class="analytics-header">
                    <h3 class="analytics-title">Самые активные пользователи</h3>
                </div>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Поездок</th>
                            <th>Дистанция</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong><br>
                                <small><?php echo htmlspecialchars($user['username']); ?></small>
                            </td>
                            <td><?php echo $user['trip_count']; ?></td>
                            <td><strong><?php echo number_format($user['total_distance']); ?> км</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Фильтры -->
        <form method="GET" action="" class="filters fade-in">
            <div class="filter-group">
                <label class="filter-label">Мотоцикл</label>
                <select class="filter-select" name="motorcycle">
                    <option value="">Все мотоциклы</option>
                    <?php foreach ($motorcycles as $moto): ?>
                    <option value="<?php echo $moto['id']; ?>" <?php echo $filter_motorcycle == $moto['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($moto['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Пользователь</label>
                <select class="filter-select" name="user">
                    <option value="">Все пользователи</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
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
            
            <div class="filter-group">
                <label class="filter-label">Дистанция от (км)</label>
                <input type="number" class="filter-input" name="distance_min" value="<?php echo $filter_distance_min; ?>" min="0" placeholder="0">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Дистанция до (км)</label>
                <input type="number" class="filter-input" name="distance_max" value="<?php echo $filter_distance_max; ?>" min="0" placeholder="1000">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Применить
                </button>
                <a href="trips.php" class="clear-btn">
                    <i class="fas fa-times"></i> Сбросить
                </a>
            </div>
        </form>

        <!-- Таблица поездок -->
        <div class="admin-table fade-in">
            <div class="table-header">
                <h2 class="table-title">История поездок (<?php echo count($filtered_trips); ?>)</h2>
                <div>
                    <a href="#" class="table-link" onclick="exportTrips()">
                        <i class="fas fa-download"></i>
                        Экспорт
                    </a>
                    <a href="#" class="table-link" onclick="printTrips()" style="margin-left: 15px;">
                        <i class="fas fa-print"></i>
                        Печать
                    </a>
                </div>
            </div>
            <div class="table-content">
                <table id="tripsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Дата</th>
                            <th>Мотоцикл</th>
                            <th>Пользователь</th>
                            <th>Пробег</th>
                            <th>Дистанция</th>
                            <th>Описание</th>
                            <th>Добавлена</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_trips as $trip): 
                            // Определение класса для дистанции
                            $distance_class = 'distance-small';
                            if ($trip['distance'] >= 100) {
                                $distance_class = 'distance-medium';
                            }
                            if ($trip['distance'] >= 500) {
                                $distance_class = 'distance-large';
                            }
                            
                            // Определение дня недели
                            $day_of_week = date('N', strtotime($trip['trip_date']));
                            $is_weekend = ($day_of_week >= 6);
                        ?>
                        <tr>
                            <td><?php echo $trip['id']; ?></td>
                            <td>
                                <strong><?php echo date('d.m.Y', strtotime($trip['trip_date'])); ?></strong>
                                <div class="day-indicator">
                                    <span class="day-dot <?php echo $is_weekend ? 'day-weekend' : 'day-weekday'; ?>"></span>
                                    <?php 
                                    $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                                    echo $days[$day_of_week - 1]; 
                                    ?>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($trip['motorcycle_make'] . ' ' . $trip['motorcycle_model']); ?></strong><br>
                                <small><?php echo htmlspecialchars($trip['motorcycle_plate']); ?></small>
                            </td>
                            <td>
                                <?php if ($trip['user_full_name']): ?>
                                <strong><?php echo htmlspecialchars($trip['user_full_name']); ?></strong><br>
                                <?php endif; ?>
                                <small><?php echo htmlspecialchars($trip['user_name']); ?></small>
                            </td>
                            <td>
                                <?php echo number_format($trip['start_odometer']); ?> → 
                                <strong><?php echo number_format($trip['end_odometer']); ?></strong> км<br>
                                <small>+<?php echo number_format($trip['end_odometer'] - $trip['start_odometer']); ?> км</small>
                            </td>
                            <td>
                                <span class="distance-badge <?php echo $distance_class; ?>">
                                    <?php echo number_format($trip['distance']); ?> км
                                </span>
                                <?php if ($trip['distance'] > 0): ?>
                                <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                    ≈ <?php echo round($trip['distance'] / 60, 1); ?> ч
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="trip-description" title="<?php echo htmlspecialchars($trip['description']); ?>">
                                    <?php echo $trip['description'] ? htmlspecialchars(substr($trip['description'], 0, 30)) . '...' : '—'; ?>
                                </div>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($trip['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="trips_view.php?id=<?php echo $trip['id']; ?>" class="action-btn view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="trips_edit.php?id=<?php echo $trip['id']; ?>" class="action-btn edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="trips_delete.php?id=<?php echo $trip['id']; ?>" class="action-btn delete" 
                                       onclick="return confirmDelete(<?php echo $trip['id']; ?>, '<?php echo htmlspecialchars(addslashes('Поездка от ' . date('d.m.Y', strtotime($trip['trip_date'])) . ' на ' . $trip['motorcycle_make'] . ' ' . $trip['motorcycle_model'])); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Статистика по месяцам -->
        <div class="admin-table fade-in">
            <div class="table-header">
                <h2 class="table-title">Статистика по месяцам (последние 6 месяцев)</h2>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Месяц</th>
                            <th>Количество поездок</th>
                            <th>Общая дистанция</th>
                            <th>Средняя дистанция</th>
                            <th>Активность</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_stats as $month): 
                            $month_name = date('F Y', strtotime($month['month'] . '-01'));
                            $activity_level = $month['trip_count'] > 20 ? 'Высокая' : ($month['trip_count'] > 10 ? 'Средняя' : 'Низкая');
                            $activity_class = $month['trip_count'] > 20 ? 'distance-large' : ($month['trip_count'] > 10 ? 'distance-medium' : 'distance-small');
                        ?>
                        <tr>
                            <td><strong><?php echo $month_name; ?></strong></td>
                            <td><?php echo $month['trip_count']; ?></td>
                            <td><strong><?php echo number_format($month['total_distance']); ?> км</strong></td>
                            <td><?php echo number_format($month['avg_distance'], 0); ?> км</td>
                            <td>
                                <span class="distance-badge <?php echo $activity_class; ?>">
                                    <?php echo $activity_level; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Футер -->
        <div class="admin-footer">
            <p>AUTOPARK MOTO Trips &copy; <?php echo date('Y'); ?> | 
                Всего поездок: <?php echo $stats['total_trips']; ?> | 
                Общий пробег: <?php echo number_format($stats['total_distance'] / 1000, 1); ?>ккм | 
                Средняя дистанция: <?php echo number_format($stats['avg_distance'], 0); ?> км
            </p>
        </div>
    </div>

    <script>
        // Подтверждение удаления
        function confirmDelete(id, name) {
            return confirm('Вы уверены, что хотите удалить поездку "' + name + '" (ID: ' + id + ')?\n\nЭто действие нельзя отменить!');
        }
        
        // Экспорт в CSV
        function exportTrips() {
            let csv = [];
            const rows = document.querySelectorAll('#tripsTable tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length - 1; j++) { // -1 чтобы исключить колонку действий
                    row.push(cols[j].innerText.replace(/\n/g, ' ').trim());
                }
                
                csv.push(row.join(','));
            }
            
            // Скачивание файла
            const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "trips_export_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Печать
        function printTrips() {
            const printContent = document.querySelector('.admin-table').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <html>
                    <head>
                        <title>Отчет по поездкам - AUTOPARK MOTO</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #f2f2f2; }
                            h1 { color: #333; }
                            .print-header { text-align: center; margin-bottom: 30px; }
                            .print-date { text-align: right; margin-bottom: 20px; }
                            .distance-badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; }
                            .distance-small { background: #d1ecf1; color: #0c5460; }
                            .distance-medium { background: #fff3cd; color: #856404; }
                            .distance-large { background: #f8d7da; color: #721c24; }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h1>AUTOPARK MOTO - История поездок</h1>
                            <p>Отчет сгенерирован: ${new Date().toLocaleString('ru-RU')}</p>
                            <p>Всего поездок: <?php echo $stats['total_trips']; ?> | Общий пробег: <?php echo number_format($stats['total_distance'] / 1000, 1); ?>ккм</p>
                        </div>
                        ${printContent}
                    </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        // Сортировка таблицы
        document.querySelectorAll('#tripsTable th').forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(index);
            });
        });
        
        function sortTable(column) {
            const table = document.getElementById('tripsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const isAsc = table.getAttribute('data-sort') === 'asc' && parseInt(table.getAttribute('data-column')) === column;
            
            rows.sort((a, b) => {
                let aText, bText;
                
                if (column === 0 || column === 4 || column === 5) { // ID, пробег, дистанция - числовые
                    aText = parseFloat(a.cells[column].textContent.replace(/[^\d]/g, '')) || 0;
                    bText = parseFloat(b.cells[column].textContent.replace(/[^\d]/g, '')) || 0;
                    return isAsc ? bText - aText : aText - bText;
                } else if (column === 1) { // Дата
                    aText = new Date(a.cells[column].querySelector('strong').textContent.split('.').reverse().join('-'));
                    bText = new Date(b.cells[column].querySelector('strong').textContent.split('.').reverse().join('-'));
                    return isAsc ? bText - aText : aText - bText;
                } else {
                    aText = a.cells[column].textContent;
                    bText = b.cells[column].textContent;
                    return isAsc ? bText.localeCompare(aText, 'ru') : aText.localeCompare(bText, 'ru');
                }
            });
            
            // Удаляем старые строки
            rows.forEach(row => tbody.removeChild(row));
            
            // Добавляем отсортированные строки
            rows.forEach(row => tbody.appendChild(row));
            
            // Сохраняем состояние сортировки
            table.setAttribute('data-sort', isAsc ? 'desc' : 'asc');
            table.setAttribute('data-column', column);
        }
        
        // Быстрый просмотр описания
        document.querySelectorAll('.trip-description').forEach(desc => {
            desc.addEventListener('mouseenter', function() {
                if (this.scrollWidth > this.clientWidth) {
                    this.style.position = 'absolute';
                    this.style.background = 'white';
                    this.style.padding = '10px';
                    this.style.borderRadius = '8px';
                    this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
                    this.style.zIndex = '100';
                    this.style.maxWidth = '300px';
                }
            });
            
            desc.addEventListener('mouseleave', function() {
                this.style.position = '';
                this.style.background = '';
                this.style.padding = '';
                this.style.borderRadius = '';
                this.style.boxShadow = '';
                this.style.zIndex = '';
                this.style.maxWidth = '';
            });
        });
        
        // Автоматическое обновление счетчика
        function updateTripCounter() {
            const notificationBadge = document.querySelector('.notification-badge');
            if (notificationBadge) {
                // Здесь можно добавить AJAX запрос для обновления данных
                console.log('Обновление счетчика поездок...');
            }
        }
        
        // Обновляем каждые 10 минут
        setInterval(updateTripCounter, 600000);
    </script>
</body>
</html>