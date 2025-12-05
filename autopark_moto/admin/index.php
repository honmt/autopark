  <?php
// admin/index.php - Административная панель

session_start();

// Проверяем, авторизован ли пользователь и является ли он администратором
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /autopark_moto/auth/login.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Получаем статистику для дашборда
function getAdminStats($db) {
    $stats = [];
    
    // Количество пользователей
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Количество активных мотоциклов
    $stmt = $db->query("SELECT COUNT(*) as count FROM motorcycles");
    $stats['active_motorcycles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Количество просроченных ТО
    $stmt = $db->query("SELECT COUNT(*) as count FROM motorcycle_services WHERE status = 'planned' AND next_service_date < CURDATE()");
    $stats['overdue_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Количество предстоящих ТО (в течение 30 дней)
    $stmt = $db->query("SELECT COUNT(*) as count FROM motorcycle_services WHERE status = 'planned' AND next_service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stats['upcoming_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Общий пробег всех мотоциклов  
$stmt = $db->query("SELECT SUM(mileage) as total FROM motorcycles");
    $stats['total_mileage'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Общая стоимость всех ТО
    $stmt = $db->query("SELECT SUM(cost) as total FROM motorcycle_services");
    $stats['total_service_cost'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Количество поездок за последние 30 дней
    $stmt = $db->query("SELECT COUNT(*) as count FROM trips WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['recent_trips'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}

$stats = getAdminStats($db);

// Получаем последние активность
function getRecentActivity($db) {
    $activities = [];
    
    // Последние 5 ТО
    $stmt = $db->query("SELECT s.*, CONCAT(m.brand, ' ', m.model) as motorcycle FROM motorcycle_services s LEFT JOIN motorcycles m ON s.motorcycle_id = m.id ORDER BY s.created_at DESC LIMIT 5");
    $activities['services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Последние 5 пользователей
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $activities['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Последние 5 поездок
    $stmt = $db->query("SELECT t.*, CONCAT(m.brand, ' ', m.model) as motorcycle FROM trips t LEFT JOIN motorcycles m ON t.motorcycle_id = m.id ORDER BY t.start_date DESC LIMIT 5");
    $activities['trips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $activities;
}

$recent = getRecentActivity($db);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель - AUTOPARK MOTO</title>
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
        .card-services::before { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .card-trips::before { background: linear-gradient(135deg, #17a2b8, #138496); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.users .stat-icon { color: #007bff; }
        .stat-card.motorcycles .stat-icon { color: #28a745; }
        .stat-card.services .stat-icon { color: #ffc107; }
        .stat-card.trips .stat-icon { color: #17a2b8; }

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

        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .status-pending {
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
            background: #17a2b8;
        }

        .action-btn.edit {
            background: #ffc107;
        }

        .action-btn.delete {
            background: #dc3545;
        }

        .action-btn:hover {
            transform: scale(1.1);
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
                <div class="menu-title">Управление</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="users.php" class="menu-link">
                            <i class="fas fa-users"></i>
                            <span>Пользователи</span>
                            <span class="menu-badge"><?php echo $stats['total_users']; ?></span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="motorcycles.php" class="menu-link">
                            <i class="fas fa-motorcycle"></i>
                            <span>Мотоциклы</span>
                            <span class="menu-badge"><?php echo $stats['active_motorcycles']; ?></span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="services.php" class="menu-link">
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
                            <span class="menu-badge">3</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="logs.php" class="menu-link">
                            <i class="fas fa-history"></i>
                            <span>Логи системы</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="menu-section">
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="/autopark_moto/index.php" class="menu-link">
                            <i class="fas fa-external-link-alt"></i>
                            <span>На сайт</span>
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
    <div class="admin-content">
        <!-- Верхняя панель -->
        <div class="admin-topbar fade-in">
            <div>
                <h1 class="page-title">Административная панель</h1>
                <p class="page-subtitle">Управление системой AUTOPARK MOTO</p>
            </div>
            <div class="topbar-actions">
                <button class="btn-admin btn-secondary">
                    <i class="fas fa-sync-alt"></i> Обновить
                </button>
                <button class="btn-admin btn-primary" onclick="location.href='users.php?action=add'">
                    <i class="fas fa-plus"></i> Добавить
                </button>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
            </div>
        </div>

        <!-- Карточки статистики -->
        <div class="stats-grid fade-in" style="animation-delay: 0.1s;">
            <div class="stat-card users card-users">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Пользователей</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i> +12%
                </div>
            </div>

            <div class="stat-card motorcycles card-motorcycles">
                <div class="stat-icon">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_motorcycles']; ?></div>
                <div class="stat-label">Мотоциклов</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i> +5%
                </div>
            </div>

            <div class="stat-card services card-services">
                <div class="stat-icon">
                    <i class="fas fa-wrench"></i>
                </div>
                <div class="stat-value"><?php echo $stats['overdue_services'] + $stats['upcoming_services']; ?></div>
                <div class="stat-label">Требуют ТО</div>
                <div class="stat-trend trend-down">
                    <i class="fas fa-arrow-down"></i> -3%
                </div>
            </div>

            <div class="stat-card trips card-trips">
                <div class="stat-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="stat-value"><?php echo $stats['recent_trips']; ?></div>
                <div class="stat-label">Поездок (30 дней)</div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i> +18%
                </div>
            </div>
        </div>

        <!-- Последние пользователи -->
        <div class="admin-table fade-in" style="animation-delay: 0.2s;">
            <div class="table-header">
                <h2 class="table-title">Последние пользователи</h2>
                <a href="users.php" class="table-link">
                    Все пользователи <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя пользователя</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent['users'] as $user): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['role'] == 'admin' ? 'status-active' : 'status-pending'; ?>">
                                    <?php echo $user['role']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Последние ТО -->
        <div class="admin-table fade-in" style="animation-delay: 0.3s;">
            <div class="table-header">
                <h2 class="table-title">Последние ТО</h2>
                <a href="services.php" class="table-link">
                    Все ТО <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Мотоцикл</th>
                            <th>Дата ТО</th>
                            <th>Пробег</th>
                            <th>Стоимость</th>
                            <th>Следующее ТО</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent['services'] as $service): ?>
                        <tr>
                            <td>#<?php echo $service['id']; ?></td>
                            <td><?php echo htmlspecialchars($service['motorcycle']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($service['service_date'])); ?></td>
                            <td><?php echo number_format($service['mileage'], 0, '', ' '); ?> км</td>
                            <td><?php echo number_format($service['cost'], 0, '', ' '); ?> ₽</td>
                            <td><?php echo date('d.m.Y', strtotime($service['next_service_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $service['status'] == 'completed' ? 'status-completed' : 'status-pending'; ?>">
                                    <?php echo $service['status'] == 'completed' ? 'Выполнено' : 'Запланировано'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Футер -->
        <div class="admin-footer fade-in" style="animation-delay: 0.4s;">
            <p>© <?php echo date('Y'); ?> AUTOPARK MOTO - Административная панель v1.0</p>
            <p>Версия системы: 1.0.0 | Последнее обновление: <?php echo date('d.m.Y H:i'); ?></p>
        </div>
    </div>

    <script>
        // Анимации при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            // Добавляем анимацию для карточек статистики
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = (0.1 + index * 0.05) + 's';
            });
            
            // Добавляем hover эффект для строк таблицы
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
            
            // Кнопки действий
            const actionButtons = document.querySelectorAll('.action-btn');
            actionButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.classList.contains('view') ? 'просмотр' :
                                  this.classList.contains('edit') ? 'редактирование' : 'удаление';
                    const row = this.closest('tr');
                    const id = row.cells[0].textContent;
                    
                    alert(`${action} записи ${id}\nВ реальной системе здесь будет действие.`);
                });
            });
            
            // Кнопка обновления
            document.querySelector('.btn-secondary').addEventListener('click', function() {
                const btn = this;
                const icon = btn.querySelector('i');
                const originalHTML = btn.innerHTML;
                
                btn.disabled = true;
                icon.className = 'fas fa-spinner fa-spin';
                
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    alert('Данные обновлены!');
                }, 1000);
            });
            
            // Уведомления
            document.querySelector('.notification-btn').addEventListener('click', function() {
                alert('У вас 3 новых уведомления:\n\n1. Новый пользователь зарегистрирован\n2. Требуется ТО для BMW GS 850\n3. Обновление системы доступно');
            });
        });
    </script>
</body>
</html>