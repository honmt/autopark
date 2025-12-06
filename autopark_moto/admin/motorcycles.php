<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /autopark_moto/auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Получение всех мотоциклов
$motorcycles = $db->query("
    SELECT * FROM motorcycles 
    ORDER BY CASE 
        WHEN make = 'Honda' THEN 1
        WHEN make = 'Yamaha' THEN 2
        WHEN make = 'BMW' THEN 3
        WHEN make = 'Kawasaki' THEN 4
        WHEN make = 'Suzuki' THEN 5
        ELSE 6
    END, make, model
")->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$stats = [
    'total_motorcycles' => count($motorcycles),
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_trips' => $db->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
    'total_distance' => $db->query("SELECT COALESCE(SUM(distance), 0) FROM trips")->fetchColumn(),
];

// Получение статистики по маркам
$brands_stats = $db->query("
    SELECT make, COUNT(*) as count, 
           AVG(odometer) as avg_mileage, 
           SUM(odometer) as total_mileage
    FROM motorcycles 
    GROUP BY make
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Получение последних поездок для мотоциклов
$recent_trips = $db->query("
    SELECT m.make, m.model, t.trip_date, t.distance, u.username
    FROM trips t
    JOIN motorcycles m ON t.motorcycle_id = m.id
    JOIN users u ON t.user_id = u.id
    ORDER BY t.trip_date DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мотоциклы - Админ панель AUTOPARK MOTO</title>
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

        .card-bikes::before { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .card-mileage::before { background: linear-gradient(135deg, #007bff, #0056b3); }
        .card-brands::before { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .card-avg::before { background: linear-gradient(135deg, #17a2b8, #138496); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.bikes .stat-icon { color: #28a745; }
        .stat-card.mileage .stat-icon { color: #007bff; }
        .stat-card.brands .stat-icon { color: #ffc107; }
        .stat-card.avg .stat-icon { color: #17a2b8; }

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
            min-width: 800px;
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

        /* Статусы */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-maintenance {
            background: rgba(255, 193, 7, 0.1);
            color: #e0a800;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-unavailable {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Действия */
        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: white;
            text-decoration: none;
            font-size: 14px;
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

        .action-btn.services {
            background: #28a745;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Карточки мотоциклов */
        .motorcycle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .motorcycle-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .motorcycle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .moto-header {
            padding: 20px;
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            color: white;
            position: relative;
        }

        .moto-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #d60000;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .moto-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .moto-subtitle {
            font-size: 14px;
            color: #aaa;
        }

        .moto-details {
            padding: 20px;
        }

        .moto-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .moto-detail:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 14px;
            color: #666;
        }

        .detail-value {
            font-weight: 600;
            color: #333;
        }

        .moto-actions {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            gap: 10px;
            border-top: 1px solid #eee;
        }

        /* Адаптивность */
        @media (max-width: 1200px) {
            .motorcycle-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            
            .table-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .motorcycle-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-wrap: wrap;
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
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .moto-header {
                padding: 15px;
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
        
        /* Фильтры */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
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
            min-width: 150px;
        }
        
        .filter-btn {
            padding: 8px 20px;
            background: #d60000;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
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
        }
        
        .clear-btn:hover {
            background: #545b62;
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
                        <a href="motorcycles.php" class="menu-link active">
                            <i class="fas fa-motorcycle"></i>
                            <span>Мотоциклы</span>
                            <span class="menu-badge"><?php echo $stats['total_motorcycles']; ?></span>
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
                </ul>
            </div>
        </nav>
    </aside>

    <!-- Основной контент -->
    <div class="admin-content">
        <!-- Верхняя панель -->
        <div class="admin-topbar">
            <div>
                <h1 class="page-title">Мотоциклы</h1>
                <p class="page-subtitle">Управление парком мотоциклов</p>
            </div>
            <div class="topbar-actions">
                <a href="motorcycles_add.php" class="btn-admin btn-primary btn-add">
                    <i class="fas fa-plus"></i>
                    Добавить мотоцикл
                </a>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $stats['total_trips']; ?></span>
                </button>
            </div>
        </div>

        <!-- Карточки статистики -->
        <div class="stats-grid">
            <div class="stat-card bikes fade-in">
                <div class="stat-icon">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_motorcycles']; ?></div>
                <div class="stat-label">Всего мотоциклов</div>
                <div class="stat-trend">
                    <span class="trend-up"><?php echo count($brands_stats); ?> марок</span>
                </div>
            </div>

            <div class="stat-card mileage fade-in">
                <div class="stat-icon">
                    <i class="fas fa-road"></i>
                </div>
                <div class="stat-value">
                    <?php 
                    $total_mileage = 0;
                    foreach ($motorcycles as $moto) {
                        $total_mileage += $moto['odometer'];
                    }
                    echo number_format($total_mileage / 1000, 1); 
                    ?>ккм
                </div>
                <div class="stat-label">Общий пробег</div>
                <div class="stat-trend">
                    <span class="trend-up">Ср. пробег: <?php echo $total_mileage > 0 ? number_format($total_mileage / count($motorcycles)) : 0; ?> км</span>
                </div>
            </div>

            <div class="stat-card brands fade-in">
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-value"><?php echo count($brands_stats); ?></div>
                <div class="stat-label">Брендов в парке</div>
                <div class="stat-trend">
                    <?php if (count($brands_stats) > 0): ?>
                    <span>Популярный: <?php echo $brands_stats[0]['make']; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card avg fade-in">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-value">
                    <?php 
                    $avg_year = 0;
                    foreach ($motorcycles as $moto) {
                        $avg_year += $moto['year'];
                    }
                    echo $avg_year > 0 ? round($avg_year / count($motorcycles)) : '—'; 
                    ?>
                </div>
                <div class="stat-label">Средний год выпуска</div>
                <div class="stat-trend">
                    <span>Старейший: <?php echo min(array_column($motorcycles, 'year')); ?></span>
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="filters fade-in">
            <div class="filter-group">
                <label class="filter-label">Марка</label>
                <select class="filter-select" id="filterMake">
                    <option value="">Все марки</option>
                    <?php foreach ($brands_stats as $brand): ?>
                    <option value="<?php echo htmlspecialchars($brand['make']); ?>">
                        <?php echo htmlspecialchars($brand['make']); ?> (<?php echo $brand['count']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Год от</label>
                <input type="number" class="filter-input" id="filterYearFrom" placeholder="2000" min="1900" max="2030">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Год до</label>
                <input type="number" class="filter-input" id="filterYearTo" placeholder="2024" min="1900" max="2030">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Пробег до</label>
                <input type="number" class="filter-input" id="filterMileage" placeholder="50000" min="0">
            </div>
            
            <button class="filter-btn" onclick="applyFilters()">
                <i class="fas fa-filter"></i> Применить
            </button>
            
            <button class="clear-btn" onclick="clearFilters()">
                <i class="fas fa-times"></i> Сбросить
            </button>
        </div>

        <!-- Таблица мотоциклов -->
        <div class="admin-table fade-in">
            <div class="table-header">
                <h2 class="table-title">Список мотоциклов (<?php echo count($motorcycles); ?>)</h2>
                <a href="#" class="table-link" onclick="exportMotorcycles()">
                    <i class="fas fa-download"></i>
                    Экспорт в CSV
                </a>
            </div>
            <div class="table-content">
                <table id="motorcyclesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Марка</th>
                            <th>Модель</th>
                            <th>Гос. номер</th>
                            <th>Год</th>
                            <th>Пробег</th>
                            <th>Примечания</th>
                            <th>Добавлен</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($motorcycles as $moto): ?>
                        <tr class="motorcycle-row" 
                            data-make="<?php echo htmlspecialchars($moto['make']); ?>"
                            data-year="<?php echo $moto['year']; ?>"
                            data-mileage="<?php echo $moto['odometer']; ?>">
                            <td><?= $moto['id'] ?></td>
                            <td><strong><?= htmlspecialchars($moto['make']) ?></strong></td>
                            <td><?= htmlspecialchars($moto['model']) ?></td>
                            <td>
                                <span class="status-badge status-active">
                                    <?= htmlspecialchars($moto['plate']) ?>
                                </span>
                            </td>
                            <td><?= $moto['year'] ?></td>
                            <td>
                                <strong><?= number_format($moto['odometer']) ?> км</strong>
                                <?php if ($moto['mileage']): ?>
                                <br><small>Ср.: <?= number_format($moto['mileage']) ?> км</small>
                                <?php endif; ?>
                            </td>
                            <td><?= $moto['notes'] ? htmlspecialchars(substr($moto['notes'], 0, 50)) . '...' : '—' ?></td>
                            <td><?= date('d.m.Y', strtotime($moto['created_at'])) ?></td>
                            <td>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Карточный вид -->
        <div class="motorcycle-grid" id="motorcycleCards">
            <?php foreach ($motorcycles as $moto): ?>
            <div class="motorcycle-card fade-in motorcycle-card-item"
                 data-make="<?php echo htmlspecialchars($moto['make']); ?>"
                 data-year="<?php echo $moto['year']; ?>"
                 data-mileage="<?php echo $moto['odometer']; ?>">
                <div class="moto-header">
                    <div class="moto-badge">ID: <?php echo $moto['id']; ?></div>
                    <div class="moto-title"><?php echo htmlspecialchars($moto['make'] . ' ' . $moto['model']); ?></div>
                    <div class="moto-subtitle">Гос. номер: <?php echo htmlspecialchars($moto['plate']); ?></div>
                </div>
                
                <div class="moto-details">
                    <div class="moto-detail">
                        <span class="detail-label">Год выпуска</span>
                        <span class="detail-value"><?php echo $moto['year']; ?></span>
                    </div>
                    <div class="moto-detail">
                        <span class="detail-label">Пробег</span>
                        <span class="detail-value"><?php echo number_format($moto['odometer']); ?> км</span>
                    </div>
                    <?php if ($moto['mileage']): ?>
                    <div class="moto-detail">
                        <span class="detail-label">Средний пробег</span>
                        <span class="detail-value"><?php echo number_format($moto['mileage']); ?> км</span>
                    </div>
                    <?php endif; ?>
                    <div class="moto-detail">
                        <span class="detail-label">Добавлен</span>
                        <span class="detail-value"><?php echo date('d.m.Y', strtotime($moto['created_at'])); ?></span>
                    </div>
                    <?php if ($moto['notes']): ?>
                    <div class="moto-detail">
                        <span class="detail-label">Примечания</span>
                        <span class="detail-value" title="<?php echo htmlspecialchars($moto['notes']); ?>">
                            <?php echo htmlspecialchars(substr($moto['notes'], 0, 30)); ?>...
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Футер -->
        <div class="admin-footer">
            <p>AUTOPARK MOTO Motorcycles &copy; <?php echo date('Y'); ?> | Всего мотоциклов: <?php echo count($motorcycles); ?></p>
        </div>
    </div>

    <script>
        // Переключение между табличным и карточным видом
        let isCardView = false;
        
        function toggleView() {
            isCardView = !isCardView;
            const tableView = document.getElementById('motorcyclesTable').closest('.admin-table');
            const cardView = document.getElementById('motorcycleCards');
            
            if (isCardView) {
                tableView.style.display = 'none';
                cardView.style.display = 'grid';
            } else {
                tableView.style.display = 'block';
                cardView.style.display = 'none';
            }
        }
        
        // Фильтрация мотоциклов
        function applyFilters() {
            const make = document.getElementById('filterMake').value;
            const yearFrom = parseInt(document.getElementById('filterYearFrom').value) || 0;
            const yearTo = parseInt(document.getElementById('filterYearTo').value) || 9999;
            const mileage = parseInt(document.getElementById('filterMileage').value) || 9999999;
            
            const rows = document.querySelectorAll('.motorcycle-row, .motorcycle-card-item');
            
            rows.forEach(row => {
                const rowMake = row.dataset.make;
                const rowYear = parseInt(row.dataset.year);
                const rowMileage = parseInt(row.dataset.mileage);
                
                const matchMake = !make || rowMake === make;
                const matchYear = rowYear >= yearFrom && rowYear <= yearTo;
                const matchMileage = rowMileage <= mileage;
                
                row.style.display = (matchMake && matchYear && matchMileage) ? '' : 'none';
            });
        }
        
        function clearFilters() {
            document.getElementById('filterMake').value = '';
            document.getElementById('filterYearFrom').value = '';
            document.getElementById('filterYearTo').value = '';
            document.getElementById('filterMileage').value = '';
            
            const rows = document.querySelectorAll('.motorcycle-row, .motorcycle-card-item');
            rows.forEach(row => {
                row.style.display = '';
            });
        }
        
        // Поиск мотоциклов
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Поиск мотоциклов...';
            searchInput.style.cssText = `
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 14px;
                width: 200px;
                margin-right: 10px;
            `;
            
            const tableHeader = document.querySelector('.table-header');
            tableHeader.insertBefore(searchInput, tableHeader.querySelector('.table-link'));
            
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.motorcycle-row, .motorcycle-card-item');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        });
        
        // Подтверждение удаления
        function confirmDelete(id, name) {
            return confirm('Вы уверены, что хотите удалить мотоцикл "' + name + '" (ID: ' + id + ')?\n\nЭто действие нельзя отменить!');
        }
        
        // Экспорт в CSV
        function exportMotorcycles() {
            let csv = [];
            const rows = document.querySelectorAll('#motorcyclesTable tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length - 1; j++) { // -1 чтобы исключить колонку действий
                    row.push(cols[j].innerText);
                }
                
                csv.push(row.join(','));
            }
            
            // Скачивание файла
            const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "motorcycles_export_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Сортировка таблицы
        document.querySelectorAll('#motorcyclesTable th').forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(index);
            });
        });
        
        function sortTable(column) {
            const table = document.getElementById('motorcyclesTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const isAsc = table.getAttribute('data-sort') === 'asc' && parseInt(table.getAttribute('data-column')) === column;
            
            rows.sort((a, b) => {
                let aText, bText;
                
                if (column === 4 || column === 5) { // Год и пробег - числовые
                    aText = parseFloat(a.cells[column].textContent.replace(/[^\d]/g, '')) || 0;
                    bText = parseFloat(b.cells[column].textContent.replace(/[^\d]/g, '')) || 0;
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
    </script>
</body>
</html>