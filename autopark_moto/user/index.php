<?php
// user/index.php - Пользовательская панель

session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    header('Location: /autopark_moto/auth/login.php');
    exit();
}

// Если пользователь администратор, перенаправляем в админку
if ($_SESSION['user']['role'] === 'admin') {
    header('Location: /autopark_moto/admin/index.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

$user_id = $_SESSION['user']['id'];

// Получаем статистику для текущего пользователя - упрощенная версия
function getUserStats($db, $user_id) {
    $stats = [];
    
    // Для начала просто установим базовые значения
    $stats['my_motorcycles'] = 0;
    $stats['overdue_services'] = 0;
    $stats['upcoming_services'] = 0;
    $stats['total_mileage'] = 0;
    $stats['recent_trips'] = 0;
    $stats['total_service_cost'] = 0;
    $stats['last_refuel'] = null;
    
    try {
        // Проверяем структуру таблицы motorcycles
        $stmt = $db->query("DESCRIBE motorcycles");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Определяем, какой столбец связывает мотоцикл с пользователем
        $user_column = null;
        if (in_array('user_id', $columns)) {
            $user_column = 'user_id';
        } elseif (in_array('owner_id', $columns)) {
            $user_column = 'owner_id';
        } elseif (in_array('created_by', $columns)) {
            $user_column = 'created_by';
        }
        
        if ($user_column) {
            // Количество мотоциклов пользователя
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM motorcycles WHERE $user_column = ?");
            $stmt->execute([$user_id]);
            $stats['my_motorcycles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Общий пробег мотоциклов пользователя
            $stmt = $db->prepare("SELECT SUM(odometer) as total FROM motorcycles WHERE $user_column = ?");
            $stmt->execute([$user_id]);
            $stats['total_mileage'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        }
        
        // Для остальной статистики будем использовать упрощенный подход
        // или показывать 0, если данные недоступны
        
    } catch (Exception $e) {
        // В случае ошибки оставляем значения по умолчанию
    }
    
    return $stats;
}

$stats = getUserStats($db, $user_id);

// Упрощенная версия для получения активностей
function getUserRecentActivity($db, $user_id) {
    $activities = ['services' => [], 'trips' => [], 'fuel_logs' => []];
    
    try {
        // Для демонстрации создадим тестовые данные
        $activities['services'] = [
            [
                'id' => 1,
                'motorcycle' => 'Yamaha MT-07',
                'service_type' => 'Замена масла',
                'last_service_date' => date('Y-m-d', strtotime('-30 days')),
                'last_service_odometer' => 10000,
                'cost' => 5000,
                'next_service_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'upcoming'
            ]
        ];
        
        $activities['trips'] = [
            [
                'id' => 1,
                'motorcycle' => 'Yamaha MT-07',
                'trip_date' => date('Y-m-d', strtotime('-7 days')),
                'route' => 'Москва - Санкт-Петербург',
                'distance' => 700,
                'duration' => 10
            ]
        ];
        
    } catch (Exception $e) {
        // Оставляем пустые массивы
    }
    
    return $activities;
}

$recent = getUserRecentActivity($db, $user_id);

// Получаем мотоциклы пользователя для быстрого доступа
$my_motorcycles = [];
try {
    // Пробуем разные варианты столбцов
    $stmt = $db->query("DESCRIBE motorcycles");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('user_id', $columns)) {
        $stmt = $db->prepare("SELECT id, make, model, year, odometer FROM motorcycles WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $my_motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array('owner_id', $columns)) {
        $stmt = $db->prepare("SELECT id, make, model, year, odometer FROM motorcycles WHERE owner_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $my_motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array('created_by', $columns)) {
        $stmt = $db->prepare("SELECT id, make, model, year, odometer FROM motorcycles WHERE created_by = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $my_motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Оставляем пустой массив
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для пользовательской панели */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
        }

        /* Боковая панель */
        .user-sidebar {
            width: 250px;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 20px rgba(0,0,0,0.1);
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .sidebar-header {
            padding: 30px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            text-align: center;
            color: white;
        }

        .sidebar-logo {
            font-size: 24px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .sidebar-subtitle {
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            font-weight: 300;
        }

        .sidebar-user {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #eee;
            background: rgba(102, 126, 234, 0.1);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 3px;
            color: #333;
        }

        .user-role {
            font-size: 12px;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            margin-bottom: 20px;
        }

        .menu-title {
            font-size: 12px;
            color: #667eea;
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
            color: #555;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            background: white;
            margin: 0 10px;
            border-radius: 8px;
        }

        .menu-link:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea;
            border-left-color: #667eea;
            transform: translateX(5px);
        }

        .menu-link.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            color: #667eea;
            border-left-color: #667eea;
            font-weight: 600;
        }

        .menu-link i {
            width: 20px;
            text-align: center;
            font-size: 18px;
            color: #667eea;
        }

        .menu-badge {
            margin-left: auto;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Основной контент */
        .user-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            min-height: 100vh;
            backdrop-filter: blur(10px);
        }

        /* Верхняя панель */
        .user-topbar {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .page-subtitle {
            font-size: 14px;
            color: #667eea;
            margin-top: 5px;
            font-weight: 500;
        }

        .topbar-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-user {
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #dee2e6;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 20px;
            color: #667eea;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
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
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .card-motorcycles::before { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-services::before { background: linear-gradient(135deg, #ff6b6b 0%, #ff4757 100%); }
        .card-trips::before { background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%); }
        .card-fuel::before { background: linear-gradient(135deg, #ff9f43 0%, #feca57 100%); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .stat-card.motorcycles .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-card.services .stat-icon { background: linear-gradient(135deg, #ff6b6b 0%, #ff4757 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-card.trips .stat-icon { background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-card.fuel .stat-icon { background: linear-gradient(135deg, #ff9f43 0%, #feca57 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .stat-value {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #333 0%, #555 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            color: #1dd1a1;
        }

        .trend-down {
            color: #ff4757;
        }

        /* Быстрый доступ к мотоциклам */
        .quick-access {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .quick-access-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .quick-access-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .motorcycle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .motorcycle-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
            cursor: pointer;
        }

        .motorcycle-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .motorcycle-make {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .motorcycle-model {
            font-size: 14px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .motorcycle-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }

        .add-motorcycle-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px dashed #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-weight: 600;
        }

        .add-motorcycle-card:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        }

        /* Таблицы */
        .user-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .table-header {
            padding: 20px 30px;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .table-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .table-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }

        .table-link:hover {
            color: #764ba2;
        }

        .table-content {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
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
        }

        table tbody tr {
            transition: background 0.3s;
        }

        table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
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
            background: rgba(29, 209, 161, 0.1);
            color: #10ac84;
            border: 1px solid rgba(29, 209, 161, 0.3);
        }

        .status-overdue {
            background: rgba(255, 71, 87, 0.1);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }

        .status-upcoming {
            background: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
            border: 1px solid rgba(255, 159, 67, 0.3);
        }

        .status-done {
            background: rgba(29, 209, 161, 0.1);
            color: #10ac84;
            border: 1px solid rgba(29, 209, 161, 0.3);
        }

        /* Действия */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .action-btn.view {
            background: #667eea;
        }

        .action-btn.edit {
            background: #ff9f43;
        }

        .action-btn.delete {
            background: #ff4757;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        /* Адаптивность */
        @media (max-width: 1024px) {
            .user-sidebar {
                width: 70px;
            }
            
            .user-sidebar .sidebar-logo,
            .user-sidebar .sidebar-subtitle,
            .user-sidebar .user-info,
            .user-sidebar .menu-title,
            .user-sidebar .menu-link span {
                display: none;
            }
            
            .user-sidebar .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .user-content {
                margin-left: 70px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .motorcycle-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .user-content {
                padding: 20px;
            }
            
            .user-topbar {
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
        }

        @media (max-width: 480px) {
            .user-sidebar {
                width: 60px;
            }
            
            .user-content {
                margin-left: 60px;
                padding: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 30px;
            }
        }

        /* Футер */
        .user-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(102, 126, 234, 0.1);
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
        
        /* Пустое состояние */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .info-message {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            color: #667eea;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Боковая панель -->
    <aside class="user-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">AUTOPARK MOTO</div>
            <div class="sidebar-subtitle">Личный кабинет</div>
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
                <div class="user-role">Мотоциклист</div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Основное</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="index.php" class="menu-link active">
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
                <div class="menu-title">Мои данные</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="motorcycles.php" class="menu-link">
                            <i class="fas fa-motorcycle"></i>
                            <span>Мотоциклы</span>
                            <?php if ($stats['my_motorcycles'] > 0): ?>
                            <span class="menu-badge"><?php echo $stats['my_motorcycles']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="services.php" class="menu-link">
                            <i class="fas fa-wrench"></i>
                            <span>Техобслуживание</span>
                            <?php if (($stats['overdue_services'] + $stats['upcoming_services']) > 0): ?>
                            <span class="menu-badge"><?php echo $stats['overdue_services'] + $stats['upcoming_services']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="trips.php" class="menu-link">
                            <i class="fas fa-route"></i>
                            <span>Поездки</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="fuel.php" class="menu-link">
                            <i class="fas fa-gas-pump"></i>
                            <span>Заправки</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="menu-section">
                <div class="menu-title">Настройки</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="profile.php" class="menu-link">
                            <i class="fas fa-user"></i>
                            <span>Профиль</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="notifications.php" class="menu-link">
                            <i class="fas fa-bell"></i>
                            <span>Уведомления</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="settings.php" class="menu-link">
                            <i class="fas fa-cog"></i>
                            <span>Настройки</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="menu-section">
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="/autopark_moto/index.php" class="menu-link">
                            <i class="fas fa-external-link-alt"></i>
                            <span>На главную</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/autopark_moto/auth/logout.php" class="menu-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Выход</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>

    <!-- Основной контент -->
    <div class="user-content">
        <!-- Верхняя панель -->
        <div class="user-topbar fade-in">
            <div>
                <h1 class="page-title">Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?: $_SESSION['user']['username']); ?>!</h1>
                <p class="page-subtitle">Управляйте своими мотоциклами и поездками</p>
            </div>
            <div class="topbar-actions">
                <button class="btn-user btn-secondary" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Обновить
                </button>
                <button class="btn-user btn-primary" onclick="location.href='motorcycles.php?action=add'">
                    <i class="fas fa-plus"></i> Добавить мотоцикл
                </button>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <?php if (($stats['overdue_services'] + $stats['upcoming_services']) > 0): ?>
                    <span class="notification-badge"><?php echo $stats['overdue_services'] + $stats['upcoming_services']; ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Информационное сообщение -->
        <div class="info-message fade-in">
            <i class="fas fa-info-circle"></i>
            <strong>Личный кабинет в разработке</strong>
            <p>Некоторые функции могут быть временно недоступны. Мы работаем над интеграцией с вашими данными.</p>
        </div>

        <!-- Карточки статистики -->
        <div class="stats-grid fade-in" style="animation-delay: 0.1s;">
            <div class="stat-card motorcycles card-motorcycles">
                <div class="stat-icon">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['my_motorcycles']; ?></div>
                <div class="stat-label">МОИХ МОТОЦИКЛОВ</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i> Всего пробег: <?php echo number_format($stats['total_mileage'], 0, '', ' '); ?> км
                </div>
            </div>

            <div class="stat-card services card-services">
                <div class="stat-icon">
                    <i class="fas fa-wrench"></i>
                </div>
                <div class="stat-value"><?php echo $stats['overdue_services'] + $stats['upcoming_services']; ?></div>
                <div class="stat-label">ТРЕБУЕТ ВНИМАНИЯ</div>
                <div class="stat-trend <?php echo $stats['overdue_services'] > 0 ? 'trend-down' : 'trend-up'; ?>">
                    <?php if ($stats['overdue_services'] > 0): ?>
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $stats['overdue_services']; ?> просрочено
                    <?php else: ?>
                    <i class="fas fa-check"></i> Все в порядке
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card trips card-trips">
                <div class="stat-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="stat-value"><?php echo $stats['recent_trips']; ?></div>
                <div class="stat-label">ПОЕЗДОК ЗА 30 ДНЕЙ</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-road"></i> Активность хорошая
                </div>
            </div>

            <div class="stat-card fuel card-fuel">
                <div class="stat-icon">
                    <i class="fas fa-gas-pump"></i>
                </div>
                <div class="stat-value">
                    <?php if ($stats['last_refuel']): ?>
                    <?php echo date('d.m', strtotime($stats['last_refuel'])); ?>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </div>
                <div class="stat-label">ПОСЛЕДНЯЯ ЗАПРАВКА</div>
                <div class="stat-trend">
                    <?php if ($stats['last_refuel']): ?>
                    <i class="fas fa-calendar"></i> <?php echo date('d.m.Y', strtotime($stats['last_refuel'])); ?>
                    <?php else: ?>
                    <i class="fas fa-info-circle"></i> Нет данных
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Быстрый доступ к мотоциклам -->
        <div class="quick-access fade-in" style="animation-delay: 0.2s;">
            <div class="quick-access-header">
                <h2 class="quick-access-title">Мои мотоциклы</h2>
                <a href="motorcycles.php" class="table-link">
                    Все мотоциклы <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="motorcycle-grid">
                <?php if (count($my_motorcycles) > 0): ?>
                    <?php foreach ($my_motorcycles as $moto): ?>
                    <div class="motorcycle-card" onclick="location.href='motorcycles.php?action=view&id=<?php echo $moto['id']; ?>'">
                        <div class="motorcycle-make"><?php echo htmlspecialchars($moto['make']); ?></div>
                        <div class="motorcycle-model"><?php echo htmlspecialchars($moto['model']); ?> (<?php echo $moto['year']; ?>)</div>
                        <div class="motorcycle-info">
                            <span>Пробег:</span>
                            <span><?php echo number_format($moto['odometer'], 0, '', ' '); ?> км</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="motorcycle-card add-motorcycle-card" onclick="location.href='motorcycles.php?action=add'">
                        <div style="text-align: center;">
                            <i class="fas fa-plus" style="font-size: 24px; margin-bottom: 10px;"></i>
                            <div>Добавить мотоцикл</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Последние ТО -->
        <div class="user-table fade-in" style="animation-delay: 0.3s;">
            <div class="table-header">
                <h2 class="table-title">Последнее техобслуживание</h2>
                <a href="services.php" class="table-link">
                    Все ТО <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Мотоцикл</th>
                            <th>Вид работ</th>
                            <th>Дата</th>
                            <th>Пробег</th>
                            <th>Стоимость</th>
                            <th>Следующее</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent['services'])): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-wrench"></i>
                                <div>Нет данных о техобслуживании</div>
                                <a href="services.php?action=add" style="color: #667eea; margin-top: 10px; display: inline-block;">Добавить ТО</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent['services'] as $service): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['motorcycle'] ?? 'Неизвестно'); ?></td>
                            <td><?php echo htmlspecialchars($service['service_type'] ?? '-'); ?></td>
                            <td><?php echo !empty($service['last_service_date']) ? date('d.m.Y', strtotime($service['last_service_date'])) : '-'; ?></td>
                            <td><?php echo !empty($service['last_service_odometer']) ? number_format($service['last_service_odometer'], 0, '', ' ') . ' км' : '-'; ?></td>
                            <td><?php echo !empty($service['cost']) ? number_format($service['cost'], 2, ',', ' ') . ' ₽' : '0,00 ₽'; ?></td>
                            <td><?php echo !empty($service['next_service_date']) ? date('d.m.Y', strtotime($service['next_service_date'])) : '-'; ?></td>
                            <td>
                                <?php if (isset($service['status'])): ?>
                                <?php 
                                $status_classes = [
                                    'upcoming' => 'status-upcoming',
                                    'overdue' => 'status-overdue', 
                                    'done' => 'status-done'
                                ];
                                $status_text = [
                                    'upcoming' => 'Предстоит',
                                    'overdue' => 'Просрочено',
                                    'done' => 'Выполнено'
                                ];
                                ?>
                                <span class="status-badge <?php echo $status_classes[$service['status']] ?? 'status-upcoming'; ?>">
                                    <?php echo $status_text[$service['status']] ?? $service['status']; ?>
                                </span>
                                <?php else: ?>
                                <span class="status-badge status-upcoming">Не указан</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view" title="Просмотр" onclick="location.href='services.php?action=view&id=<?php echo $service['id']; ?>'">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" title="Редактировать" onclick="location.href='services.php?action=edit&id=<?php echo $service['id']; ?>'">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Последние поездки -->
        <?php if (!empty($recent['trips'])): ?>
        <div class="user-table fade-in" style="animation-delay: 0.4s;">
            <div class="table-header">
                <h2 class="table-title">Последние поездки</h2>
                <a href="trips.php" class="table-link">
                    Все поездки <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Мотоцикл</th>
                            <th>Дата</th>
                            <th>Маршрут</th>
                            <th>Расстояние</th>
                            <th>Время</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent['trips'] as $trip): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trip['motorcycle'] ?? 'Неизвестно'); ?></td>
                            <td><?php echo !empty($trip['trip_date']) ? date('d.m.Y', strtotime($trip['trip_date'])) : '-'; ?></td>
                            <td><?php echo !empty($trip['route']) ? htmlspecialchars(substr($trip['route'], 0, 30)) . (strlen($trip['route']) > 30 ? '...' : '') : '-'; ?></td>
                            <td><?php echo !empty($trip['distance']) ? number_format($trip['distance'], 0, '', ' ') . ' км' : '0 км'; ?></td>
                            <td><?php echo !empty($trip['duration']) ? $trip['duration'] . ' ч' : '-'; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view" title="Просмотр" onclick="location.href='trips.php?action=view&id=<?php echo $trip['id']; ?>'">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" title="Редактировать" onclick="location.href='trips.php?action=edit&id=<?php echo $trip['id']; ?>'">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Футер -->
        <div class="user-footer fade-in" style="animation-delay: 0.5s;">
            <p>© <?php echo date('Y'); ?> AUTOPARK MOTO - Личный кабинет</p>
            <p>Ваша статистика: <?php echo $stats['my_motorcycles']; ?> мотоцикла, 
                <?php echo $stats['recent_trips']; ?> поездок за месяц, 
                общий пробег <?php echo number_format($stats['total_mileage'], 0, '', ' '); ?> км</p>
        </div>
    </div>

    <script>
        // Анимации при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация для карточек статистики
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = (0.1 + index * 0.05) + 's';
            });
            
            // Hover эффект для строк таблицы
            const tableRows = document.querySelectorAll('table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'transform 0.3s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
            
            // Кнопка обновления
            const refreshBtn = document.querySelector('.btn-secondary');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    refreshDashboard();
                });
            }
            
            // Кнопка уведомлений
            const notificationBtn = document.querySelector('.notification-btn');
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    showNotifications();
                });
            }
            
            // Быстрые подсказки
            const motorcycleCards = document.querySelectorAll('.motorcycle-card');
            motorcycleCards.forEach(card => {
                card.addEventListener('click', function() {
                    if (this.classList.contains('add-motorcycle-card')) {
                        return;
                    }
                    const motorcycleName = this.querySelector('.motorcycle-make').textContent;
                    console.log(`Открываем мотоцикл: ${motorcycleName}`);
                });
            });
        });
        
        function refreshDashboard() {
            const btn = document.querySelector('.btn-secondary');
            const icon = btn.querySelector('i');
            const originalHTML = btn.innerHTML;
            
            btn.disabled = true;
            icon.className = 'fas fa-spinner fa-spin';
            
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                
                // Показываем уведомление об обновлении
                showToast('Данные обновлены!', 'success');
                
                // Перезагружаем страницу
                location.reload();
            }, 1000);
        }
        
        function showNotifications() {
            // В реальном приложении здесь был бы запрос к серверу
            const notifications = [];
            
            <?php if ($stats['overdue_services'] > 0): ?>
            notifications.push('⚠️ У вас <?php echo $stats['overdue_services']; ?> просроченных ТО!');
            <?php endif; ?>
            
            <?php if ($stats['upcoming_services'] > 0): ?>
            notifications.push('📅 Запланировано <?php echo $stats['upcoming_services']; ?> ТО');
            <?php endif; ?>
            
            <?php if ($stats['my_motorcycles'] == 0): ?>
            notifications.push('🏍️ Добавьте свой первый мотоцикл!');
            <?php endif; ?>
            
            if (notifications.length === 0) {
                notifications.push('✅ Все в порядке, новых уведомлений нет');
            }
            
            alert('Уведомления:\n\n' + notifications.join('\n'));
        }
        
        function showToast(message, type = 'info') {
            // Создаем элемент тоста
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#1dd1a1' : type === 'error' ? '#ff4757' : '#667eea'};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 1000;
                animation: fadeIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                max-width: 300px;
            `;
            
            const icon = type === 'success' ? 'fas fa-check-circle' : 
                         type === 'error' ? 'fas fa-exclamation-circle' : 
                         'fas fa-info-circle';
            
            toast.innerHTML = `
                <i class="${icon}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(toast);
            
            // Удаляем тост через 3 секунды
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Добавляем CSS для анимации fadeOut
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(style);
        
        // Обработка нажатия клавиш
        document.addEventListener('keydown', function(e) {
            // Ctrl+R для обновления
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshDashboard();
            }
            
            // Esc для закрытия
            if (e.key === 'Escape') {
                const toasts = document.querySelectorAll('.toast-notification');
                toasts.forEach(toast => toast.remove());
            }
        });
    </script>
</body>
</html>