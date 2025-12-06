<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /autopark_moto/auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Получение общей статистики
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_motorcycles' => $db->query("SELECT COUNT(*) FROM motorcycles")->fetchColumn(),
    'active_users' => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_trips' => $db->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
    'total_distance' => $db->query("SELECT COALESCE(SUM(distance), 0) FROM trips")->fetchColumn(),
    'total_services' => $db->query("SELECT COUNT(*) FROM motorcycle_services")->fetchColumn(),
    'overdue_services' => $db->query("SELECT COUNT(*) FROM motorcycle_services WHERE status = 'overdue'")->fetchColumn(),
    'upcoming_services' => $db->query("SELECT COUNT(*) FROM motorcycle_services WHERE status = 'upcoming'")->fetchColumn(),
];

// Статистика по месяцам (пользователи)
$users_by_month = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
")->fetchAll(PDO::FETCH_ASSOC);

// Статистика по месяцам (поездки)
$trips_by_month = $db->query("
    SELECT DATE_FORMAT(trip_date, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(distance), 0) as distance
    FROM trips 
    WHERE trip_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(trip_date, '%Y-%m')
    ORDER BY month
")->fetchAll(PDO::FETCH_ASSOC);

// Статистика по мотоциклам (пробег)
$motorcycles_mileage = $db->query("
    SELECT m.make, m.model, m.odometer, COUNT(t.id) as trip_count, COALESCE(SUM(t.distance), 0) as total_distance
    FROM motorcycles m
    LEFT JOIN trips t ON m.id = t.motorcycle_id
    GROUP BY m.id
    ORDER BY m.odometer DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Статистика по пользователям (активность)
$user_activity = $db->query("
    SELECT u.username, u.full_name, COUNT(t.id) as trip_count, COALESCE(SUM(t.distance), 0) as total_distance,
           MAX(t.trip_date) as last_trip
    FROM users u
    LEFT JOIN trips t ON u.id = t.user_id
    GROUP BY u.id
    ORDER BY trip_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Статистика по обслуживанию
$services_stats = $db->query("
    SELECT 
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
        AVG(cost) as avg_cost
    FROM motorcycle_services
")->fetch(PDO::FETCH_ASSOC);

// Подготовка данных для графиков
$months = [];
$user_counts = [];
$trip_counts = [];
$trip_distances = [];

// Заполняем данные за последние 6 месяцев
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime($month));
    
    $user_count = 0;
    foreach ($users_by_month as $row) {
        if ($row['month'] == $month) {
            $user_count = $row['count'];
            break;
        }
    }
    $user_counts[] = $user_count;
    
    $trip_count = 0;
    $trip_distance = 0;
    foreach ($trips_by_month as $row) {
        if ($row['month'] == $month) {
            $trip_count = $row['count'];
            $trip_distance = $row['distance'];
            break;
        }
    }
    $trip_counts[] = $trip_count;
    $trip_distances[] = $trip_distance;
}

// Статистика по ролям пользователей
$users_by_role = $db->query("
    SELECT role, COUNT(*) as count 
    FROM users 
    GROUP BY role
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика - Админ панель AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .btn-export {
            background: #28a745;
            color: white;
        }
        
        .btn-export:hover {
            background: #218838;
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

        .card-users::before { background: linear-gradient(135deg, #007bff, #0056b3); }
        .card-motorcycles::before { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .card-trips::before { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .card-distance::before { background: linear-gradient(135deg, #17a2b8, #138496); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.users .stat-icon { color: #007bff; }
        .stat-card.motorcycles .stat-icon { color: #28a745; }
        .stat-card.trips .stat-icon { color: #ffc107; }
        .stat-card.distance .stat-icon { color: #17a2b8; }

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

        /* Сетка графиков */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .chart-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Таблицы аналитики */
        .analytics-tables {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .analytics-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .table-content {
            overflow-x: auto;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 400px;
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
            padding: 12px 20px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        table tbody tr {
            transition: background 0.3s;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Статусы */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-high { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-medium { background: rgba(255, 193, 7, 0.1); color: #e0a800; }
        .status-low { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

        /* Прогресс-бар */
        .progress-bar {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
        }

        .progress-users { background: linear-gradient(90deg, #007bff, #0056b3); }
        .progress-trips { background: linear-gradient(90deg, #ffc107, #e0a800); }
        .progress-services { background: linear-gradient(90deg, #28a745, #1e7e34); }

        /* Адаптивность */
        @media (max-width: 1200px) {
            .charts-grid,
            .analytics-tables {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                padding: 20px;
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
            
            .chart-container {
                height: 250px;
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
            
            .chart-card {
                padding: 15px;
            }
            
            .chart-container {
                height: 200px;
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
        
        /* Период анализа */
        .period-selector {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .period-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .period-btn.active {
            background: #d60000;
            color: white;
            border-color: #d60000;
        }
        
        .period-btn:hover {
            background: #f5f5f5;
        }
        
        .period-btn.active:hover {
            background: #a00000;
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
                        <a href="analytics.php" class="menu-link active">
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
                        <a href="notifications.php" class="menu-link">
                            <i class="fas fa-bell"></i>
                            <span>Уведомления</span>
                        </a>
                    </li>
            </div>
        </nav>
    </aside>

    <!-- Основной контент -->
    <div class="admin-content">
        <!-- Верхняя панель -->
        <div class="admin-topbar">
            <div>
                <h1 class="page-title">Аналитика системы</h1>
                <p class="page-subtitle">Статистика и аналитические данные AUTOPARK MOTO</p>
            </div>
            <div class="topbar-actions">
                <button class="btn-admin btn-export" onclick="exportAnalytics()">
                    <i class="fas fa-download"></i>
                    Экспорт отчета
                </button>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $stats['overdue_services']; ?></span>
                </button>
            </div>
        </div>

        <!-- Выбор периода -->
        <div class="period-selector">
            <span style="font-weight: 600;">Период анализа:</span>
            <button class="period-btn active" onclick="setPeriod(6)">6 месяцев</button>
            <button class="period-btn" onclick="setPeriod(12)">1 год</button>
            <button class="period-btn" onclick="setPeriod(3)">3 месяца</button>
            <button class="period-btn" onclick="setPeriod(1)">1 месяц</button>
        </div>

        <!-- Карточки статистики -->
        <div class="stats-grid">
            <div class="stat-card users fade-in">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Всего пользователей</div>
                <div class="progress-bar">
                    <div class="progress-fill progress-users" style="width: <?php echo min(100, ($stats['active_users'] / $stats['total_users']) * 100); ?>%"></div>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">Активных: <?php echo $stats['active_users']; ?></span>
                </div>
            </div>

            <div class="stat-card motorcycles fade-in">
                <div class="stat-icon">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_motorcycles']; ?></div>
                <div class="stat-label">Мотоциклов в системе</div>
                <div class="progress-bar">
                    <div class="progress-fill progress-services" style="width: <?php echo min(100, ($stats['total_services'] > 0 ? 70 : 0)); ?>%"></div>
                </div>
                <div class="stat-trend">
                    <span>Обслуживаний: <?php echo $stats['total_services']; ?></span>
                </div>
            </div>

            <div class="stat-card trips fade-in">
                <div class="stat-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_trips']; ?></div>
                <div class="stat-label">Совершено поездок</div>
                <div class="progress-bar">
                    <div class="progress-fill progress-trips" style="width: <?php echo min(100, ($stats['total_trips'] > 0 ? 60 : 0)); ?>%"></div>
                </div>
                <div class="stat-trend">
                    <span class="trend-up"><?php echo count($trips_by_month); ?> за период</span>
                </div>
            </div>

            <div class="stat-card distance fade-in">
                <div class="stat-icon">
                    <i class="fas fa-road"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_distance'] / 1000, 1); ?>км</div>
                <div class="stat-label">Общий пробег</div>
                <div class="progress-bar">
                    <div class="progress-fill progress-users" style="width: <?php echo min(100, ($stats['total_distance'] > 0 ? 85 : 0)); ?>%"></div>
                </div>
                <div class="stat-trend">
                    <span class="trend-up"><?php echo number_format(array_sum($trip_distances) / 1000, 1); ?>км за период</span>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="charts-grid">
            <!-- График пользователей -->
            <div class="chart-card fade-in">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title">Регистрация пользователей</h3>
                        <p class="chart-subtitle">Динамика за последние 6 месяцев</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>

            <!-- График поездок -->
            <div class="chart-card fade-in">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title">Активность поездок</h3>
                        <p class="chart-subtitle">Количество и пробег по месяцам</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="tripsChart"></canvas>
                </div>
            </div>

            <!-- Круговая диаграмма статусов сервиса -->
            <div class="chart-card fade-in">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title">Статусы обслуживания</h3>
                        <p class="chart-subtitle">Распределение по категориям</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="servicesChart"></canvas>
                </div>
            </div>

            <!-- Диаграмма распределения по ролям -->
            <div class="chart-card fade-in">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title">Распределение по ролям</h3>
                        <p class="chart-subtitle">Статистика пользователей</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="rolesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Таблицы аналитики -->
        <div class="analytics-tables">
            <!-- Топ мотоциклов по пробегу -->
            <div class="analytics-table fade-in">
                <div class="table-header">
                    <h3 class="table-title">Топ мотоциклов по пробегу</h3>
                    <span class="stat-trend">Всего: <?php echo $stats['total_motorcycles']; ?></span>
                </div>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Мотоцикл</th>
                                <th>Пробег (км)</th>
                                <th>Поездок</th>
                                <th>Дистанция</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($motorcycles_mileage as $moto): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($moto['make'] . ' ' . $moto['model']); ?></strong></td>
                                <td><?php echo number_format($moto['odometer']); ?></td>
                                <td><?php echo $moto['trip_count']; ?></td>
                                <td><?php echo number_format($moto['total_distance']); ?> км</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Топ пользователей по активности -->
            <div class="analytics-table fade-in">
                <div class="table-header">
                    <h3 class="table-title">Самые активные пользователи</h3>
                    <span class="stat-trend">Всего поездок: <?php echo $stats['total_trips']; ?></span>
                </div>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Пользователь</th>
                                <th>Поездок</th>
                                <th>Дистанция</th>
                                <th>Последняя</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_activity as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></strong><br>
                                    <small><?php echo $user['username']; ?></small>
                                </td>
                                <td><?php echo $user['trip_count']; ?></td>
                                <td><?php echo number_format($user['total_distance']); ?> км</td>
                                <td><?php echo $user['last_trip'] ? date('d.m.Y', strtotime($user['last_trip'])) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Футер -->
        <div class="admin-footer">
            <p>AUTOPARK MOTO Analytics &copy; <?php echo date('Y'); ?> | Отчет сгенерирован: <?php echo date('d.m.Y H:i'); ?></p>
        </div>
    </div>

    <script>
        // Инициализация графиков
        document.addEventListener('DOMContentLoaded', function() {
            // График пользователей
            const usersCtx = document.getElementById('usersChart').getContext('2d');
            const usersChart = new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Новых пользователей',
                        data: <?php echo json_encode($user_counts); ?>,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Количество'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Месяц'
                            }
                        }
                    }
                }
            });

            // График поездок
            const tripsCtx = document.getElementById('tripsChart').getContext('2d');
            const tripsChart = new Chart(tripsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [
                        {
                            label: 'Количество поездок',
                            data: <?php echo json_encode($trip_counts); ?>,
                            backgroundColor: 'rgba(255, 193, 7, 0.6)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Пробег (км)',
                            data: <?php echo json_encode(array_map(function($d) { return $d / 1000; }, $trip_distances)); ?>,
                            type: 'line',
                            borderColor: '#d60000',
                            backgroundColor: 'rgba(214, 0, 0, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Количество поездок'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Пробег (км)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });

            // Круговая диаграмма статусов сервиса
            const servicesCtx = document.getElementById('servicesChart').getContext('2d');
            const servicesChart = new Chart(servicesCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Выполнено', 'Предстоящие', 'Просрочено'],
                    datasets: [{
                        data: [
                            <?php echo $services_stats['completed'] ?? 0; ?>,
                            <?php echo $services_stats['upcoming'] ?? 0; ?>,
                            <?php echo $services_stats['overdue'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(220, 53, 69, 0.8)'
                        ],
                        borderColor: [
                            '#28a745',
                            '#ffc107',
                            '#dc3545'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Диаграмма распределения по ролям
            const rolesCtx = document.getElementById('rolesChart').getContext('2d');
            const rolesChart = new Chart(rolesCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($users_by_role, 'role')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($users_by_role, 'count')); ?>,
                        backgroundColor: [
                            'rgba(214, 0, 0, 0.8)',
                            'rgba(0, 123, 255, 0.8)',
                            'rgba(40, 167, 69, 0.8)'
                        ],
                        borderColor: [
                            '#d60000',
                            '#007bff',
                            '#28a745'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = <?php echo $stats['total_users']; ?>;
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });

        // Управление периодом анализа
        function setPeriod(months) {
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Здесь можно добавить AJAX запрос для загрузки данных за выбранный период
            console.log(`Загрузка данных за ${months} месяцев`);
        }

        // Экспорт отчета
        function exportAnalytics() {
            const reportData = {
                date: new Date().toLocaleString('ru-RU'),
                stats: {
                    total_users: <?php echo $stats['total_users']; ?>,
                    total_motorcycles: <?php echo $stats['total_motorcycles']; ?>,
                    total_trips: <?php echo $stats['total_trips']; ?>,
                    total_distance: <?php echo $stats['total_distance']; ?>,
                    total_services: <?php echo $stats['total_services']; ?>,
                    overdue_services: <?php echo $stats['overdue_services']; ?>,
                    upcoming_services: <?php echo $stats['upcoming_services']; ?>
                }
            };
            
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(reportData, null, 2));
            const downloadAnchor = document.createElement('a');
            downloadAnchor.setAttribute("href", dataStr);
            downloadAnchor.setAttribute("download", "analytics_report_" + new Date().toISOString().slice(0,10) + ".json");
            document.body.appendChild(downloadAnchor);
            downloadAnchor.click();
            document.body.removeChild(downloadAnchor);
            
            alert('Отчет успешно экспортирован в формате JSON');
        }

        // Автоматическое обновление каждые 5 минут
        setInterval(() => {
            console.log('Обновление данных аналитики...');
            // Здесь можно добавить AJAX запрос для обновления данных
        }, 300000);
    </script>
</body>
</html>