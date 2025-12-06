<?php
// user/index.php - Пользовательская панель

session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    header('Location: /autopark_moto/auth/login.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

$user_id = $_SESSION['user']['id'];

// Обработка добавления мотоцикла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_motorcycle') {
    $make = $_POST['make'] ?? '';
    $model = $_POST['model'] ?? '';
    $year = $_POST['year'] ?? '';
    $odometer = $_POST['odometer'] ?? 0;
    $plate = $_POST['plate'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $mileage = $_POST['mileage'] ?? 0;  // Пробег после последнего ТО
    
    // Валидация
    if (!empty($make) && !empty($model) && !empty($year)) {
        try {
            // Проверяем, есть ли уже такой мотоцикл с таким же номером
            $stmt = $db->prepare("SELECT id FROM motorcycles WHERE plate = ?");
            $stmt->execute([$plate]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = 'Мотоцикл с таким номером уже существует';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO motorcycles 
                    (make, model, year, odometer, plate, notes, mileage, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $make, 
                    $model, 
                    $year, 
                    $odometer, 
                    $plate, 
                    $notes, 
                    $mileage
                ]);
                
                $_SESSION['success'] = 'Мотоцикл успешно добавлен!';
                header('Location: index.php');
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Ошибка при добавлении мотоцикла: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Пожалуйста, заполните обязательные поля (марка, модель, год)';
    }
}

// Получаем статистику для пользователя
function getUserStats($db) {
    $stats = [];
    
    // Количество мотоциклов
    $stmt = $db->query("SELECT COUNT(*) as count FROM motorcycles");
    $stats['total_motorcycles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Общий пробег всех мотоциклов
    $stmt = $db->query("SELECT SUM(odometer) as total FROM motorcycles");
    $stats['total_mileage'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Средний пробег на мотоцикл
    $stats['avg_mileage'] = $stats['total_motorcycles'] > 0 
        ? round($stats['total_mileage'] / $stats['total_motorcycles'], 0) 
        : 0;
    
    // Количество мотоциклов по маркам (топ-5)
    $stmt = $db->query("SELECT make, COUNT(*) as count FROM motorcycles GROUP BY make ORDER BY count DESC LIMIT 5");
    $stats['top_brands'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Общий пробег после последнего ТО
    $stmt = $db->query("SELECT SUM(mileage) as total FROM motorcycles");
    $stats['total_mileage_after_service'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    return $stats;
}

$stats = getUserStats($db);

// Получаем все мотоциклы
$stmt = $db->query("SELECT * FROM motorcycles ORDER BY created_at DESC");
$motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем самые свежие мотоциклы
$stmt = $db->query("SELECT * FROM motorcycles ORDER BY created_at DESC LIMIT 5");
$recent_motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем мотоциклы с наибольшим пробегом
$stmt = $db->query("SELECT * FROM motorcycles ORDER BY odometer DESC LIMIT 5");
$top_mileage = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой гараж - AUTOPARK MOTO</title>
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
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
            display: flex;
        }

        /* Боковая панель */
        .user-sidebar {
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
        .user-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            background: #f5f5f5;
            min-height: 100vh;
        }

        /* Верхняя панель */
        .user-topbar {
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

        /* Карточки статистики */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .card-motorcycles::before { background: linear-gradient(135deg, #007bff, #0056b3); }
        .card-mileage::before { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .card-avg-mileage::before { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .card-service-mileage::before { background: linear-gradient(135deg, #17a2b8, #138496); }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.motorcycles .stat-icon { color: #007bff; }
        .stat-card.mileage .stat-icon { color: #28a745; }
        .stat-card.avg-mileage .stat-icon { color: #ffc107; }
        .stat-card.service-mileage .stat-icon { color: #17a2b8; }

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

        /* Форма добавления мотоцикла */
        .add-motorcycle-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }

        .section-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-label .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .form-control:focus {
            border-color: #d60000;
            outline: none;
            box-shadow: 0 0 0 3px rgba(214, 0, 0, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* Таблицы */
        .user-table {
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
            padding: 0 30px 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        /* Мотоциклы карточки */
        .motorcycles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .motorcycle-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #d60000;
        }

        .motorcycle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .motorcycle-header {
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #eee;
        }

        .motorcycle-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .motorcycle-subtitle {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .motorcycle-badge {
            background: #d60000;
            color: white;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 600;
        }

        .motorcycle-body {
            padding: 20px;
        }

        .motorcycle-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .motorcycle-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .motorcycle-date {
            font-size: 12px;
            color: #666;
        }

        /* Бренды */
        .brands-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .brand-badge {
            background: rgba(214, 0, 0, 0.1);
            color: #d60000;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(214, 0, 0, 0.2);
        }

        /* Сообщения */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .close-alert {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
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
            
            .motorcycles-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
            
            .motorcycles-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .form-actions {
                flex-direction: column;
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
            
            .motorcycle-details {
                grid-template-columns: 1fr;
            }
        }

        /* Футер */
        .user-footer {
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
    <aside class="user-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">AUTOPARK <span>MOTO</span></div>
            <div class="sidebar-subtitle">Мой гараж</div>
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
                <div class="user-role">Пользователь</div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Меню</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="index.php" class="menu-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Дашборд</span>
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
                            <i class="fas fa-home"></i>
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
                <h1 class="page-title">Мой гараж</h1>
                <p class="page-subtitle">Управляйте вашими мотоциклами и отслеживайте статистику</p>
            </div>
            <div class="topbar-actions">
                <button class="btn-user btn-secondary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Обновить
                </button>
            </div>
        </div>

        <!-- Сообщения об успехе/ошибке -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success fade-in">
            <span><?php echo $_SESSION['success']; ?></span>
            <button class="close-alert" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error fade-in">
            <span><?php echo $_SESSION['error']; ?></span>
            <button class="close-alert" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Карточки статистики -->
        <div class="stats-grid fade-in" style="animation-delay: 0.1s;">
            <div class="stat-card motorcycles card-motorcycles">
                <div class="stat-icon">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_motorcycles']; ?></div>
                <div class="stat-label">Всего мотоциклов</div>
                <div class="brands-list">
                    <?php foreach ($stats['top_brands'] as $brand): ?>
                    <span class="brand-badge"><?php echo htmlspecialchars($brand['make']); ?>: <?php echo $brand['count']; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stat-card mileage card-mileage">
                <div class="stat-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_mileage'], 0, '', ' '); ?></div>
                <div class="stat-label">Общий пробег (км)</div>
            </div>

            <div class="stat-card avg-mileage card-avg-mileage">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['avg_mileage'], 0, '', ' '); ?></div>
                <div class="stat-label">Средний пробег на мотоцикл</div>
            </div>

            <div class="stat-card service-mileage card-service-mileage">
                <div class="stat-icon">
                    <i class="fas fa-wrench"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_mileage_after_service'], 0, '', ' '); ?></div>
                <div class="stat-label">Пробег после ТО (км)</div>
            </div>
        </div>

        <!-- Форма добавления мотоцикла -->
        <div class="add-motorcycle-section fade-in" style="animation-delay: 0.2s;">
            <div class="section-header">
                <h2 class="section-title">Добавить мотоцикл</h2>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_motorcycle">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="make">Марка <span class="required">*</span></label>
                        <input type="text" id="make" name="make" class="form-control" required placeholder="Например: Honda">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="model">Модель <span class="required">*</span></label>
                        <input type="text" id="model" name="model" class="form-control" required placeholder="Например: CB500X">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="year">Год выпуска <span class="required">*</span></label>
                        <input type="number" id="year" name="year" class="form-control" required min="1900" max="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="odometer">Текущий пробег (км)</label>
                        <input type="number" id="odometer" name="odometer" class="form-control" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="mileage">Пробег после ТО (км)</label>
                        <input type="number" id="mileage" name="mileage" class="form-control" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="plate">Гос. номер <span class="required">*</span></label>
                        <input type="text" id="plate" name="plate" class="form-control" required placeholder="Например: A123BC77">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Примечания</label>
                    <textarea id="notes" name="notes" class="form-control" placeholder="Описание состояния, особенности мотоцикла..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn-user btn-secondary">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                    <button type="submit" class="btn-user btn-primary">
                        <i class="fas fa-plus"></i> Добавить мотоцикл
                    </button>
                </div>
            </form>
        </div>

        <!-- Все мотоциклы -->
        <div class="user-table fade-in" style="animation-delay: 0.3s;">
            <div class="table-header">
                <h2 class="table-title">Все мотоциклы</h2>
                <span class="table-link">Всего: <?php echo $stats['total_motorcycles']; ?></span>
            </div>
            <div class="table-content">
                <?php if (empty($motorcycles)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-motorcycle" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
                    <p style="color: #666; font-size: 16px;">Нет добавленных мотоциклов</p>
                    <p style="color: #999; font-size: 14px;">Добавьте ваш первый мотоцикл, используя форму выше</p>
                </div>
                <?php else: ?>
                <div class="motorcycles-grid">
                    <?php foreach ($motorcycles as $motorcycle): ?>
                    <div class="motorcycle-card">
                        <div class="motorcycle-header">
                            <h3 class="motorcycle-title"><?php echo htmlspecialchars($motorcycle['make'] . ' ' . $motorcycle['model']); ?></h3>
                            <div class="motorcycle-subtitle">
                                <span><?php echo htmlspecialchars($motorcycle['year']); ?> год</span>
                                <span class="motorcycle-badge"><?php echo htmlspecialchars($motorcycle['plate']); ?></span>
                            </div>
                        </div>
                        
                        <div class="motorcycle-body">
                            <div class="motorcycle-details">
                                <div class="detail-item">
                                    <span class="detail-label">Текущий пробег</span>
                                    <span class="detail-value"><?php echo number_format($motorcycle['odometer'], 0, '', ' ') . ' км'; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Пробег после ТО</span>
                                    <span class="detail-value"><?php echo number_format($motorcycle['mileage'], 0, '', ' ') . ' км'; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Добавлен</span>
                                    <span class="detail-value"><?php echo date('d.m.Y', strtotime($motorcycle['created_at'])); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Статус</span>
                                    <span class="detail-value">
                                        <?php 
                                        if ($motorcycle['mileage'] >= 1000) {
                                            echo '<span style="color: #dc3545;">Требуется ТО</span>';
                                        } elseif ($motorcycle['mileage'] >= 800) {
                                            echo '<span style="color: #ffc107;">Скоро ТО</span>';
                                        } else {
                                            echo '<span style="color: #28a745;">В норме</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($motorcycle['notes'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Примечания</span>
                                <span class="detail-value" style="font-weight: normal;"><?php echo htmlspecialchars($motorcycle['notes']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="motorcycle-footer">
                            <span class="motorcycle-date">ID: #<?php echo $motorcycle['id']; ?></span>
                            <button class="btn-user btn-primary" style="padding: 5px 15px; font-size: 12px;" 
                                    onclick="editMotorcycle(<?php echo $motorcycle['id']; ?>)">
                                <i class="fas fa-edit"></i> Редактировать
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Последние добавленные -->
        <?php if (!empty($recent_motorcycles)): ?>
        <div class="user-table fade-in" style="animation-delay: 0.4s;">
            <div class="table-header">
                <h2 class="table-title">Последние добавленные мотоциклы</h2>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Марка</th>
                            <th>Модель</th>
                            <th>Год</th>
                            <th>Гос. номер</th>
                            <th>Пробег</th>
                            <th>Дата добавления</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_motorcycles as $motorcycle): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($motorcycle['make']); ?></strong></td>
                            <td><?php echo htmlspecialchars($motorcycle['model']); ?></td>
                            <td><?php echo htmlspecialchars($motorcycle['year']); ?></td>
                            <td><span style="background: #f8f9fa; padding: 3px 10px; border-radius: 4px; font-weight: 600;"><?php echo htmlspecialchars($motorcycle['plate']); ?></span></td>
                            <td><?php echo number_format($motorcycle['odometer'], 0, '', ' ') . ' км'; ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($motorcycle['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Мотоциклы с наибольшим пробегом -->
        <?php if (!empty($top_mileage)): ?>
        <div class="user-table fade-in" style="animation-delay: 0.5s;">
            <div class="table-header">
                <h2 class="table-title">Мотоциклы с наибольшим пробегом</h2>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Марка</th>
                            <th>Модель</th>
                            <th>Год</th>
                            <th>Гос. номер</th>
                            <th>Пробег</th>
                            <th>Пробег после ТО</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_mileage as $motorcycle): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($motorcycle['make']); ?></strong></td>
                            <td><?php echo htmlspecialchars($motorcycle['model']); ?></td>
                            <td><?php echo htmlspecialchars($motorcycle['year']); ?></td>
                            <td><?php echo htmlspecialchars($motorcycle['plate']); ?></td>
                            <td><strong><?php echo number_format($motorcycle['odometer'], 0, '', ' ') . ' км'; ?></strong></td>
                            <td><?php echo number_format($motorcycle['mileage'], 0, '', ' ') . ' км'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Футер -->
        <div class="user-footer fade-in" style="animation-delay: 0.6s;">
            <p>© <?php echo date('Y'); ?> AUTOPARK MOTO - Мой гараж</p>
            <p>Всего мотоциклов: <?php echo $stats['total_motorcycles']; ?> | Общий пробег: <?php echo number_format($stats['total_mileage'], 0, '', ' '); ?> км</p>
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
                    location.reload();
                }, 1000);
            });
            
            // Автоматическое скрытие сообщений через 5 секунд
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
        
        function editMotorcycle(id) {
            if (confirm('Вы хотите редактировать этот мотоцикл?')) {
                // Здесь можно добавить функционал редактирования
                // Например, открыть модальное окно или перейти на страницу редактирования
                alert('Редактирование мотоцикла #' + id + ' будет доступно в следующей версии');
            }
        }
    </script>
</body>
</html>