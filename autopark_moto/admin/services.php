<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /autopark_moto/auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Получение всех услуг с информацией о мотоциклах и шаблонах
$services = $db->query("
    SELECT 
        ms.*,
        m.make as motorcycle_make,
        m.model as motorcycle_model,
        m.plate as motorcycle_plate,
        m.odometer as current_odometer,
        st.name as service_name,
        st.interval_km,
        st.interval_days
    FROM motorcycle_services ms
    JOIN motorcycles m ON ms.motorcycle_id = m.id
    JOIN service_templates st ON ms.template_id = st.id
    ORDER BY 
        CASE ms.status 
            WHEN 'overdue' THEN 1
            WHEN 'upcoming' THEN 2
            WHEN 'completed' THEN 3
            ELSE 4
        END,
        ms.next_service_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Получение статистики
$stats = [
    'total_services' => count($services),
    'overdue_services' => $db->query("SELECT COUNT(*) FROM motorcycle_services WHERE status = 'overdue'")->fetchColumn(),
    'upcoming_services' => $db->query("SELECT COUNT(*) FROM motorcycle_services WHERE status = 'upcoming'")->fetchColumn(),
    'completed_services' => $db->query("SELECT COUNT(*) FROM motorcycle_services WHERE status = 'completed'")->fetchColumn(),
    'total_cost' => $db->query("SELECT COALESCE(SUM(cost), 0) FROM motorcycle_services WHERE status = 'completed'")->fetchColumn(),
    'avg_cost' => $db->query("SELECT COALESCE(AVG(cost), 0) FROM motorcycle_services WHERE status = 'completed'")->fetchColumn(),
];

// Получение статистики по месяцам
$services_by_month = $db->query("
    SELECT 
        DATE_FORMAT(last_service_date, '%Y-%m') as month,
        COUNT(*) as completed_count,
        SUM(cost) as total_cost
    FROM motorcycle_services 
    WHERE status = 'completed' AND last_service_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(last_service_date, '%Y-%m')
    ORDER BY month
")->fetchAll(PDO::FETCH_ASSOC);

// Получение самых частых типов обслуживания
$service_types = $db->query("
    SELECT 
        st.name,
        COUNT(ms.id) as service_count,
        AVG(ms.cost) as avg_cost
    FROM motorcycle_services ms
    JOIN service_templates st ON ms.template_id = st.id
    WHERE ms.status = 'completed'
    GROUP BY st.name
    ORDER BY service_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Получение мотоциклов для фильтра
$motorcycles = $db->query("
    SELECT id, CONCAT(make, ' ', model, ' (', plate, ')') as name 
    FROM motorcycles 
    ORDER BY make, model
")->fetchAll(PDO::FETCH_ASSOC);

// Получение шаблонов для фильтра
$templates = $db->query("
    SELECT id, name FROM service_templates ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Обработка фильтров
$filter_motorcycle = $_GET['motorcycle'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_template = $_GET['template'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Построение запроса с фильтрами
$query = "
    SELECT 
        ms.*,
        m.make as motorcycle_make,
        m.model as motorcycle_model,
        m.plate as motorcycle_plate,
        m.odometer as current_odometer,
        st.name as service_name,
        st.interval_km,
        st.interval_days
    FROM motorcycle_services ms
    JOIN motorcycles m ON ms.motorcycle_id = m.id
    JOIN service_templates st ON ms.template_id = st.id
    WHERE 1=1
";

$params = [];

if ($filter_motorcycle) {
    $query .= " AND ms.motorcycle_id = ?";
    $params[] = $filter_motorcycle;
}

if ($filter_status) {
    $query .= " AND ms.status = ?";
    $params[] = $filter_status;
}

if ($filter_template) {
    $query .= " AND ms.template_id = ?";
    $params[] = $filter_template;
}

if ($filter_date_from) {
    $query .= " AND ms.next_service_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $query .= " AND ms.next_service_date <= ?";
    $params[] = $filter_date_to;
}

$query .= " ORDER BY 
    CASE ms.status 
        WHEN 'overdue' THEN 1
        WHEN 'upcoming' THEN 2
        WHEN 'completed' THEN 3
        ELSE 4
    END,
    ms.next_service_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$filtered_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Техобслуживание - Админ панель AUTOPARK MOTO</title>
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
        .card-overdue::before { background: linear-gradient(135deg, #dc3545, #a71d2a); }
        .card-upcoming::before { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .card-completed::before { background: linear-gradient(135deg, #28a745, #1e7e34); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.total .stat-icon { color: #007bff; }
        .stat-card.overdue .stat-icon { color: #dc3545; }
        .stat-card.upcoming .stat-icon { color: #ffc107; }
        .stat-card.completed .stat-icon { color: #28a745; }

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

        /* Статусы */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-overdue {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .status-upcoming {
            background: rgba(255, 193, 7, 0.1);
            color: #e0a800;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
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

        .action-btn.complete {
            background: #28a745;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Прогресс-бар пробега */
        .mileage-progress {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .mileage-fill {
            height: 100%;
            border-radius: 4px;
        }

        .progress-overdue { background: linear-gradient(90deg, #dc3545, #a71d2a); }
        .progress-upcoming { background: linear-gradient(90deg, #ffc107, #e0a800); }
        .progress-completed { background: linear-gradient(90deg, #28a745, #1e7e34); }

        /* Цена */
        .price {
            font-weight: 600;
            color: #333;
        }

        .price-completed {
            color: #28a745;
        }

        /* Адаптивность */
        @media (max-width: 1200px) {
            .filters {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        /* Хлебные крошки */
        .breadcrumb {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #d60000;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb span {
            color: #666;
        }
        
        /* Индикатор критичности */
        .criticality {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .criticality-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .critical-high { background: #dc3545; }
        .critical-medium { background: #ffc107; }
        .critical-low { background: #28a745; }
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
                        <a href="services.php" class="menu-link active">
                            <i class="fas fa-wrench"></i>
                            <span>Техобслуживание</span>
                            <span class="menu-badge"><?php echo $stats['overdue_services'] + $stats['upcoming_services']; ?></span>
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
                <h1 class="page-title">Техобслуживание</h1>
                <p class="page-subtitle">Управление сервисным обслуживанием мотоциклов</p>
            </div>
            <div class="topbar-actions">
                <a href="services_add.php" class="btn-admin btn-primary btn-add">
                    <i class="fas fa-plus"></i>
                    Добавить сервис
                </a>
                <a href="service_templates.php" class="btn-admin btn-secondary">
                    <i class="fas fa-cogs"></i>
                    Шаблоны
                </a>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $stats['overdue_services']; ?></span>
                </button>
            </div>
        </div>

        <!-- Карточки статистики -->
        <div class="stats-grid">
            <div class="stat-card total fade-in">
                <div class="stat-icon">
                    <i class="fas fa-wrench"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_services']; ?></div>
                <div class="stat-label">Всего сервисов</div>
                <div class="stat-trend">
                    <span>Затраты: <?php echo number_format($stats['total_cost']); ?> ₽</span>
                </div>
            </div>

            <div class="stat-card overdue fade-in">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['overdue_services']; ?></div>
                <div class="stat-label">Просрочено</div>
                <div class="stat-trend">
                    <span class="trend-down">Требуют внимания</span>
                </div>
            </div>

            <div class="stat-card upcoming fade-in">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-value"><?php echo $stats['upcoming_services']; ?></div>
                <div class="stat-label">Предстоящие</div>
                <div class="stat-trend">
                    <span>Запланированы</span>
                </div>
            </div>

            <div class="stat-card completed fade-in">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['completed_services']; ?></div>
                <div class="stat-label">Выполнено</div>
                <div class="stat-trend">
                    <span class="trend-up">Ср. стоимость: <?php echo number_format($stats['avg_cost'], 0); ?> ₽</span>
                </div>
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
                <label class="filter-label">Статус</label>
                <select class="filter-select" name="status">
                    <option value="">Все статусы</option>
                    <option value="overdue" <?php echo $filter_status == 'overdue' ? 'selected' : ''; ?>>Просрочено</option>
                    <option value="upcoming" <?php echo $filter_status == 'upcoming' ? 'selected' : ''; ?>>Предстоящее</option>
                    <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Выполнено</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Тип обслуживания</label>
                <select class="filter-select" name="template">
                    <option value="">Все типы</option>
                    <?php foreach ($templates as $template): ?>
                    <option value="<?php echo $template['id']; ?>" <?php echo $filter_template == $template['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($template['name']); ?>
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
                <a href="services.php" class="clear-btn">
                    <i class="fas fa-times"></i> Сбросить
                </a>
            </div>
        </form>

        <!-- Таблица сервисов -->
        <div class="admin-table fade-in">
            <div class="table-header">
                <h2 class="table-title">Сервисное обслуживание (<?php echo count($filtered_services); ?>)</h2>
                <div>
                    <a href="#" class="table-link" onclick="exportServices()">
                        <i class="fas fa-download"></i>
                        Экспорт
                    </a>
                    <a href="#" class="table-link" onclick="printServices()" style="margin-left: 15px;">
                        <i class="fas fa-print"></i>
                        Печать
                    </a>
                </div>
            </div>
            <div class="table-content">
                <table id="servicesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Мотоцикл</th>
                            <th>Тип обслуживания</th>
                            <th>Последнее</th>
                            <th>Следующее</th>
                            <th>Пробег</th>
                            <th>Статус</th>
                            <th>Стоимость</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_services as $service): 
                            // Расчет прогресса пробега
                            $current_odometer = $service['current_odometer'];
                            $last_service_odometer = $service['last_service_odometer'];
                            $next_service_mileage = $service['next_service_mileage'];
                            $interval_km = $service['interval_km'];
                            
                            if ($service['status'] == 'completed') {
                                $progress = 100;
                            } else {
                                $progress = $interval_km > 0 ? 
                                    min(100, (($current_odometer - $last_service_odometer) / $interval_km) * 100) : 0;
                            }
                            
                            // Определение критичности
                            if ($service['status'] == 'overdue') {
                                $criticality = 'high';
                            } elseif ($progress > 80) {
                                $criticality = 'medium';
                            } else {
                                $criticality = 'low';
                            }
                        ?>
                        <tr>
                            <td><?php echo $service['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($service['motorcycle_make'] . ' ' . $service['motorcycle_model']); ?></strong><br>
                                <small><?php echo htmlspecialchars($service['motorcycle_plate']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                            <td>
                                <?php if ($service['last_service_date']): ?>
                                <?php echo date('d.m.Y', strtotime($service['last_service_date'])); ?><br>
                                <small><?php echo number_format($service['last_service_odometer']); ?> км</small>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($service['next_service_date']): ?>
                                <?php echo date('d.m.Y', strtotime($service['next_service_date'])); ?><br>
                                <small>до <?php echo number_format($service['next_service_mileage']); ?> км</small>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="criticality">
                                    <span class="criticality-dot critical-<?php echo $criticality; ?>"></span>
                                    <?php echo number_format($current_odometer); ?> км
                                </div>
                                <div class="mileage-progress">
                                    <div class="mileage-fill progress-<?php echo $service['status']; ?>" 
                                         style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <small>
                                    <?php echo number_format($last_service_odometer); ?> → 
                                    <?php echo number_format($next_service_mileage); ?> км
                                </small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $service['status']; ?>">
                                    <?php 
                                    switch ($service['status']) {
                                        case 'overdue': echo 'Просрочено'; break;
                                        case 'upcoming': echo 'Предстоящее'; break;
                                        case 'completed': echo 'Выполнено'; break;
                                        default: echo $service['status'];
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($service['status'] == 'completed' && $service['cost']): ?>
                                <span class="price price-completed">
                                    <?php echo number_format($service['cost']); ?> ₽
                                </span>
                                <?php elseif ($service['cost']): ?>
                                <span class="price">
                                    <?php echo number_format($service['cost']); ?> ₽
                                </span>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="services_view.php?id=<?php echo $service['id']; ?>" class="action-btn view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="services_edit.php?id=<?php echo $service['id']; ?>" class="action-btn edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($service['status'] != 'completed'): ?>
                                    <a href="services_complete.php?id=<?php echo $service['id']; ?>" class="action-btn complete">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="services_delete.php?id=<?php echo $service['id']; ?>" class="action-btn delete" 
                                       onclick="return confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars(addslashes($service['service_name'] . ' для ' . $service['motorcycle_make'] . ' ' . $service['motorcycle_model'])); ?>')">
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

        <!-- Сводка по типам обслуживания -->
        <div class="admin-table fade-in">
            <div class="table-header">
                <h2 class="table-title">Популярные типы обслуживания</h2>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Тип обслуживания</th>
                            <th>Количество</th>
                            <th>Средняя стоимость</th>
                            <th>Общая стоимость</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($service_types as $type): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                            <td><?php echo $type['service_count']; ?></td>
                            <td><?php echo number_format($type['avg_cost'], 0); ?> ₽</td>
                            <td><strong><?php echo number_format($type['avg_cost'] * $type['service_count'], 0); ?> ₽</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Футер -->
        <div class="admin-footer">
            <p>AUTOPARK MOTO Services &copy; <?php echo date('Y'); ?> | 
                Просрочено: <?php echo $stats['overdue_services']; ?> | 
                Предстоящих: <?php echo $stats['upcoming_services']; ?> | 
                Выполнено: <?php echo $stats['completed_services']; ?>
            </p>
        </div>
    </div>

    <script>
        // Подтверждение удаления
        function confirmDelete(id, name) {
            return confirm('Вы уверены, что хотите удалить сервис "' + name + '" (ID: ' + id + ')?\n\nЭто действие нельзя отменить!');
        }
        
        // Экспорт в CSV
        function exportServices() {
            let csv = [];
            const rows = document.querySelectorAll('#servicesTable tr');
            
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
            link.setAttribute("download", "services_export_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Печать
        function printServices() {
            const printContent = document.querySelector('.admin-table').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <html>
                    <head>
                        <title>Отчет по техобслуживанию - AUTOPARK MOTO</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #f2f2f2; }
                            .status-badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; }
                            .status-overdue { background: #f8d7da; color: #721c24; }
                            .status-upcoming { background: #fff3cd; color: #856404; }
                            .status-completed { background: #d4edda; color: #155724; }
                            h1 { color: #333; }
                            .print-header { text-align: center; margin-bottom: 30px; }
                            .print-date { text-align: right; margin-bottom: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h1>AUTOPARK MOTO - Техобслуживание</h1>
                            <p>Отчет сгенерирован: ${new Date().toLocaleString('ru-RU')}</p>
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
        document.querySelectorAll('#servicesTable th').forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(index);
            });
        });
        
        function sortTable(column) {
            const table = document.getElementById('servicesTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const isAsc = table.getAttribute('data-sort') === 'asc' && parseInt(table.getAttribute('data-column')) === column;
            
            rows.sort((a, b) => {
                const aText = a.cells[column].textContent;
                const bText = b.cells[column].textContent;
                
                if (column === 0 || column === 6 || column === 7) { // ID, статус, стоимость
                    return isAsc ? bText.localeCompare(aText) : aText.localeCompare(bText);
                } else {
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
        
        // Автоматическое обновление статусов
        function updateServiceStatuses() {
            const overdueBadge = document.querySelector('.notification-badge');
            if (overdueBadge) {
                // Здесь можно добавить AJAX запрос для обновления данных
                console.log('Обновление статусов сервисов...');
            }
        }
        
        // Обновляем каждые 5 минут
        setInterval(updateServiceStatuses, 300000);
    </script>
</body>
</html>