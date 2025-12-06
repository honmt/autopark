<?php
// admin/services_view.php - Просмотр деталей ТО

session_start();

// Проверяем, авторизован ли пользователь и является ли он администратором
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /autopark_moto/auth/login.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Проверяем наличие ID в GET-параметрах
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Не указан ID записи ТО';
    header('Location: services.php');
    exit();
}

$service_id = intval($_GET['id']);

// Получаем данные о ТО
$stmt = $db->prepare("
    SELECT s.*, 
           CONCAT(m.make, ' ', m.model) as motorcycle_full,
           m.odometer as current_odometer
    FROM motorcycle_services s
    LEFT JOIN motorcycles m ON s.motorcycle_id = m.id
    WHERE s.id = ?
");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    $_SESSION['error'] = 'Запись ТО не найдена';
    header('Location: services.php');
    exit();
}

// Получаем историю ТО для этого мотоцикла
$history_stmt = $db->prepare("
    SELECT * FROM motorcycle_services 
    WHERE motorcycle_id = ? AND id != ?
    ORDER BY last_service_date DESC
    LIMIT 5
");
$history_stmt->execute([$service['motorcycle_id'], $service_id]);
$service_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем последние поездки для этого мотоцикла
$trips_stmt = $db->prepare("
    SELECT * FROM trips 
    WHERE motorcycle_id = ? 
    ORDER BY trip_date DESC 
    LIMIT 5
");
$trips_stmt->execute([$service['motorcycle_id']]);
$recent_trips = $trips_stmt->fetchAll(PDO::FETCH_ASSOC);

// Форматируем даты
function formatDate($date) {
    if (!$date) return '-';
    return date('d.m.Y', strtotime($date));
}

// Определяем цвет статуса
function getStatusColor($status) {
    switch ($status) {
        case 'done': return 'status-done';
        case 'overdue': return 'status-overdue';
        case 'upcoming': return 'status-upcoming';
        default: return 'status-pending';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'done': return 'Выполнено';
        case 'overdue': return 'Просрочено';
        case 'upcoming': return 'Предстоящее';
        default: return 'Не определено';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр ТО #<?php echo $service_id; ?> - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для страницы просмотра ТО */
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

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Карточка деталей */
        .service-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1024px) {
            .service-details {
                grid-template-columns: 1fr;
            }
        }

        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-title {
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }

        .card-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-done {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
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

        .status-pending {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        /* Детали записи */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        .detail-item {
            margin-bottom: 20px;
        }

        .detail-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .detail-value.numeric {
            font-family: 'Courier New', monospace;
            font-size: 18px;
        }

        .detail-value.large {
            font-size: 20px;
            font-weight: 700;
        }

        .detail-value.success {
            color: #28a745;
        }

        .detail-value.warning {
            color: #e0a800;
        }

        .detail-value.danger {
            color: #dc3545;
        }

        /* История ТО */
        .history-table {
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
            text-decoration: none;
        }

        .action-btn.view {
            background: #17a2b8;
        }

        .action-btn.edit {
            background: #ffc107;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        /* Информация о мотоцикле */
        .motorcycle-info {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .motorcycle-icon {
            font-size: 50px;
            color: #d60000;
            opacity: 0.8;
        }

        .motorcycle-details {
            flex: 1;
        }

        .motorcycle-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .motorcycle-meta {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
        }

        /* Сообщение об отсутствии истории */
        .no-data {
            text-align: center;
            padding: 50px 30px;
            color: #666;
        }

        .no-data i {
            font-size: 50px;
            margin-bottom: 20px;
            color: #ddd;
        }

        .no-data p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease;
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
            
            .motorcycle-info {
                flex-direction: column;
                text-align: center;
            }
            
            .motorcycle-meta {
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
            
            .detail-card {
                padding: 20px;
            }
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
                        <a href="services.php" class="menu-link active">
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
                <h1 class="page-title">Техническое обслуживание</h1>
                <p class="page-subtitle">Просмотр деталей записи ТО #<?php echo $service_id; ?></p>
            </div>
            <div class="topbar-actions">
                <a href="services.php" class="btn-admin btn-back">
                    <i class="fas fa-arrow-left"></i> Назад к списку
                </a>
                <a href="services_edit.php?id=<?php echo $service_id; ?>" class="btn-admin btn-primary">
                    <i class="fas fa-edit"></i> Редактировать
                </a>
            </div>
        </div>

        <!-- Информация о мотоцикле -->
        <div class="motorcycle-info fade-in">
            <div class="motorcycle-icon">
                <i class="fas fa-motorcycle"></i>
            </div>
            <div class="motorcycle-details">
                <div class="motorcycle-title">
                    <?php echo htmlspecialchars($service['motorcycle_full'] ?? 'Неизвестный мотоцикл'); ?>
                </div>
                <div class="motorcycle-meta">
                    <div class="meta-item">
                        <span class="meta-label">Текущий пробег</span>
                        <span class="meta-value"><?php echo number_format($service['current_odometer'] ?? 0, 0, '', ' ') . ' км'; ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <span class="meta-label">ID мотоцикла</span>
                        <span class="meta-value">#<?php echo $service['motorcycle_id']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Основная информация о ТО -->
        <div class="service-details fade-in">
            <!-- Карточка информации о ТО -->
            <div class="detail-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Информация о ТО</h2>
                        <div class="card-subtitle">Основные детали технического обслуживания</div>
                    </div>
                    <span class="status-badge <?php echo getStatusColor($service['status']); ?>">
                        <?php echo getStatusText($service['status']); ?>
                    </span>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Дата последнего ТО</div>
                        <div class="detail-value large">
                            <?php echo formatDate($service['last_service_date']); ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Пробег на момент ТО</div>
                        <div class="detail-value numeric">
                            <?php echo !empty($service['last_service_odometer']) ? number_format($service['last_service_odometer'], 0, '', ' ') . ' км' : '-'; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Дата следующего ТО</div>
                        <div class="detail-value large <?php 
                            if ($service['status'] == 'overdue') echo 'danger';
                            elseif ($service['status'] == 'upcoming') echo 'warning';
                            else echo 'success';
                        ?>">
                            <?php echo formatDate($service['next_service_date']); ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Пробег для следующего ТО</div>
                        <div class="detail-value numeric">
                            <?php echo !empty($service['next_service_mileage']) ? number_format($service['next_service_mileage'], 0, '', ' ') . ' км' : '-'; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Стоимость ТО</div>
                        <div class="detail-value large">
                            <?php echo !empty($service['cost']) ? number_format($service['cost'], 2, ',', ' ') . ' ₽' : '0,00 ₽'; ?>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Остаток до ТО</div>
                        <div class="detail-value <?php
                            if (!empty($service['next_service_mileage']) && !empty($service['current_odometer'])) {
                                $remaining = $service['next_service_mileage'] - $service['current_odometer'];
                                if ($remaining <= 0) {
                                    echo 'danger';
                                    $remaining = abs($remaining);
                                    echo ' numeric">Просрочено на ' . number_format($remaining, 0, '', ' ') . ' км';
                                } elseif ($remaining <= 500) {
                                    echo 'warning';
                                    echo ' numeric">' . number_format($remaining, 0, '', ' ') . ' км';
                                } else {
                                    echo 'success';
                                    echo ' numeric">' . number_format($remaining, 0, '', ' ') . ' км';
                                }
                            } else {
                                echo 'numeric">-';
                            }
                        ?></div>
                    </div>
                </div>
                
                <?php if (!empty($service['notes'])): ?>
                <div class="detail-item" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                    <div class="detail-label">Примечания</div>
                    <div class="detail-value" style="font-weight: normal; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($service['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Карточка метаданных -->
            <div class="detail-card">
                <div class="card-header">
                    <h2 class="card-title">Метаданные</h2>
                    <div class="card-subtitle">Дополнительная информация</div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">ID записи</div>
                        <div class="detail-value">#<?php echo $service_id; ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">ID мотоцикла</div>
                        <div class="detail-value">#<?php echo $service['motorcycle_id']; ?></div>
                    </div>
                    
                    <?php if (!empty($service['template_id'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">ID шаблона</div>
                        <div class="detail-value">#<?php echo $service['template_id']; ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <div class="detail-label">Дата создания</div>
                        <div class="detail-value"><?php echo date('d.m.Y H:i', strtotime($service['created_at'])); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Дата обновления</div>
                        <div class="detail-value">
                            <?php echo !empty($service['updated_at']) ? date('d.m.Y H:i', strtotime($service['updated_at'])) : '-'; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($service['service_type'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Тип обслуживания</div>
                        <div class="detail-value"><?php echo htmlspecialchars($service['service_type']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($service['service_center'])): ?>
                    <div class="detail-item">
                        <div class="detail-label">Сервисный центр</div>
                        <div class="detail-value"><?php echo htmlspecialchars($service['service_center']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- История ТО для этого мотоцикла -->
        <div class="history-table fade-in">
            <div class="table-header">
                <h2 class="table-title">История ТО для этого мотоцикла</h2>
                <a href="services.php?motorcycle_id=<?php echo $service['motorcycle_id']; ?>" class="table-link">
                    Вся история <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-content">
                <?php if (empty($service_history)): ?>
                <div class="no-data">
                    <i class="fas fa-history"></i>
                    <p>История ТО для этого мотоцикла отсутствует</p>
                    <p class="text-muted">Это первая запись ТО для данного мотоцикла</p>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Дата ТО</th>
                            <th>Пробег</th>
                            <th>Стоимость</th>
                            <th>Дата след. ТО</th>
                            <th>Пробег след. ТО</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($service_history as $history): ?>
                        <tr>
                            <td>#<?php echo $history['id']; ?></td>
                            <td><?php echo formatDate($history['last_service_date']); ?></td>
                            <td><?php echo !empty($history['last_service_odometer']) ? number_format($history['last_service_odometer'], 0, '', ' ') . ' км' : '-'; ?></td>
                            <td><?php echo !empty($history['cost']) ? number_format($history['cost'], 2, ',', ' ') . ' ₽' : '0,00 ₽'; ?></td>
                            <td><?php echo formatDate($history['next_service_date']); ?></td>
                            <td><?php echo !empty($history['next_service_mileage']) ? number_format($history['next_service_mileage'], 0, '', ' ') . ' км' : '-'; ?></td>
                            <td>
                                <span class="status-badge <?php echo getStatusColor($history['status']); ?>">
                                    <?php echo getStatusText($history['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="services_view.php?id=<?php echo $history['id']; ?>" class="action-btn view" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="services_edit.php?id=<?php echo $history['id']; ?>" class="action-btn edit" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Последние поездки -->
        <?php if (!empty($recent_trips)): ?>
        <div class="history-table fade-in">
            <div class="table-header">
                <h2 class="table-title">Последние поездки на этом мотоцикле</h2>
                <a href="trips.php?motorcycle_id=<?php echo $service['motorcycle_id']; ?>" class="table-link">
                    Все поездки <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Дата</th>
                            <th>Начало</th>
                            <th>Конец</th>
                            <th>Расстояние</th>
                            <th>Описание</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_trips as $trip): ?>
                        <tr>
                            <td>#<?php echo $trip['id']; ?></td>
                            <td><?php echo formatDate($trip['trip_date']); ?></td>
                            <td><?php echo !empty($trip['start_odometer']) ? number_format($trip['start_odometer'], 0, '', ' ') . ' км' : '-'; ?></td>
                            <td><?php echo !empty($trip['end_odometer']) ? number_format($trip['end_odometer'], 0, '', ' ') . ' км' : '-'; ?></td>
                            <td><?php echo !empty($trip['distance']) ? number_format($trip['distance'], 0, '', ' ') . ' км' : '-'; ?></td>
                            <td><?php echo !empty($trip['description']) ? htmlspecialchars(substr($trip['description'], 0, 50)) . '...' : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Анимации при загрузке
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>