<?php
// admin/services_complete.php - Отметка ТО как выполненного

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

// Проверяем, что ТО еще не выполнено
if ($service['status'] === 'done') {
    $_SESSION['warning'] = 'Это ТО уже отмечено как выполненное';
    header("Location: services_view.php?id=$service_id");
    exit();
}

// Обработка формы отметки ТО как выполненного
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $actual_date = !empty($_POST['actual_date']) ? $_POST['actual_date'] : date('Y-m-d');
    $actual_odometer = !empty($_POST['actual_odometer']) ? intval(str_replace(' ', '', $_POST['actual_odometer'])) : null;
    $actual_cost = !empty($_POST['actual_cost']) ? floatval(str_replace(',', '.', str_replace(' ', '', $_POST['actual_cost']))) : 0.00;
    $next_date = !empty($_POST['next_date']) ? $_POST['next_date'] : null;
    $next_mileage = !empty($_POST['next_mileage']) ? intval(str_replace(' ', '', $_POST['next_mileage'])) : null;
    $notes = $_POST['notes'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $service_center = $_POST['service_center'] ?? '';
    
    // Валидация
    $errors = [];
    
    if (empty($actual_date)) {
        $errors[] = 'Укажите фактическую дату выполнения ТО';
    }
    
    if (empty($actual_odometer)) {
        $errors[] = 'Укажите фактический пробег при выполнении ТО';
    }
    
    // Если нет ошибок, обновляем запись
    if (empty($errors)) {
        try {
            // Начинаем транзакцию
            $db->beginTransaction();
            
            // Обновляем запись ТО
            $update_stmt = $db->prepare("
                UPDATE motorcycle_services 
                SET last_service_date = :actual_date,
                    last_service_odometer = :actual_odometer,
                    next_service_date = :next_date,
                    next_service_mileage = :next_mileage,
                    cost = :actual_cost,
                    status = 'done',
                    notes = :notes,
                    service_type = :service_type,
                    service_center = :service_center,
                    updated_at = NOW(),
                    completed_at = NOW()
                WHERE id = :id
            ");
            
            $update_stmt->execute([
                ':actual_date' => $actual_date,
                ':actual_odometer' => $actual_odometer,
                ':next_date' => $next_date,
                ':next_mileage' => $next_mileage,
                ':actual_cost' => $actual_cost,
                ':notes' => $notes,
                ':service_type' => $service_type,
                ':service_center' => $service_center,
                ':id' => $service_id
            ]);
            
            // Обновляем текущий пробег мотоцикла
            $update_moto_stmt = $db->prepare("
                UPDATE motorcycles 
                SET odometer = :odometer,
                    updated_at = NOW()
                WHERE id = :motorcycle_id
            ");
            
            $update_moto_stmt->execute([
                ':odometer' => $actual_odometer,
                ':motorcycle_id' => $service['motorcycle_id']
            ]);
            
            // Фиксируем транзакцию
            $db->commit();
            
            $_SESSION['success'] = 'ТО успешно отмечено как выполненное!';
            header("Location: services_view.php?id=$service_id");
            exit();
            
        } catch (PDOException $e) {
            // Откатываем транзакцию при ошибке
            $db->rollBack();
            $errors[] = 'Ошибка при обновлении записи: ' . $e->getMessage();
        }
    }
}

// Рассчитываем рекомендуемые даты для следующего ТО
$recommended_next_date = '';
$recommended_next_mileage = '';

if (!empty($service['last_service_date'])) {
    // Если есть предыдущая дата ТО, предлагаем +6 месяцев
    $last_date = new DateTime($service['last_service_date']);
    $last_date->modify('+6 months');
    $recommended_next_date = $last_date->format('Y-m-d');
}

if (!empty($service['last_service_odometer'])) {
    // Если есть предыдущий пробег, предлагаем +5000 км
    $recommended_next_mileage = $service['last_service_odometer'] + 5000;
} elseif (!empty($service['current_odometer'])) {
    // Иначе от текущего пробега
    $recommended_next_mileage = $service['current_odometer'] + 5000;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отметить ТО как выполненное #<?php echo $service_id; ?> - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для страницы завершения ТО */
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
            background: #28a745;
            color: white;
        }

        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
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

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }

        /* Форма завершения ТО */
        .complete-form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-title {
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }

        .form-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .form-icon {
            font-size: 50px;
            color: #28a745;
            opacity: 0.8;
        }

        /* Сообщения об ошибках */
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

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #e0a800;
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

        .error-list {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .error-list ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .error-list li {
            color: #dc3545;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-list li:before {
            content: '⚠️';
        }

        /* Форма */
        .complete-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        @media (max-width: 1024px) {
            .complete-form {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-label.required:after {
            content: ' *';
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
            padding-right: 40px;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .form-help.success {
            color: #28a745;
        }

        .form-help.warning {
            color: #e0a800;
        }

        /* Кнопки формы */
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .btn-complete {
            background: #28a745;
            color: white;
        }

        .btn-complete:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        /* Информация о ТО */
        .service-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .service-info-icon {
            font-size: 50px;
            color: #d60000;
            opacity: 0.8;
        }

        .service-details {
            flex: 1;
        }

        .service-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .service-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .service-meta {
                grid-template-columns: 1fr;
            }
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .meta-value.planned {
            color: #e0a800;
        }

        .meta-value.overdue {
            color: #dc3545;
        }

        /* Статус */
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-upcoming {
            background: rgba(255, 193, 7, 0.1);
            color: #e0a800;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-overdue {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .status-done {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .service-info {
                flex-direction: column;
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
            
            .complete-form-container {
                padding: 20px;
            }
        }

        /* Подсветка полей */
        .highlight {
            animation: highlight 2s ease;
        }

        @keyframes highlight {
            0% { background-color: #fff3cd; }
            100% { background-color: white; }
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
                <h1 class="page-title">Завершение ТО</h1>
                <p class="page-subtitle">Отметка технического обслуживания как выполненного</p>
            </div>
            <div class="topbar-actions">
                <a href="services.php" class="btn-admin btn-back">
                    <i class="fas fa-arrow-left"></i> Назад к списку
                </a>
                <a href="services_view.php?id=<?php echo $service_id; ?>" class="btn-admin btn-secondary">
                    <i class="fas fa-eye"></i> Просмотр
                </a>
            </div>
        </div>

        <!-- Информация о текущем ТО -->
        <div class="service-info fade-in">
            <div class="service-info-icon">
                <i class="fas fa-wrench"></i>
            </div>
            <div class="service-details">
                <div class="service-title">
                    <?php echo htmlspecialchars($service['motorcycle_full'] ?? 'Неизвестный мотоцикл'); ?>
                    <span class="status-badge <?php echo $service['status'] == 'overdue' ? 'status-overdue' : 'status-upcoming'; ?>">
                        <?php echo $service['status'] == 'overdue' ? 'Просрочено' : 'Предстоящее'; ?>
                    </span>
                </div>
                <div class="service-meta">
                    <div class="meta-item">
                        <div class="meta-label">Запланированная дата</div>
                        <div class="meta-value planned">
                            <?php echo !empty($service['last_service_date']) ? date('d.m.Y', strtotime($service['last_service_date'])) : 'Не указана'; ?>
                        </div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-label">Запланированный пробег</div>
                        <div class="meta-value planned">
                            <?php echo !empty($service['last_service_odometer']) ? number_format($service['last_service_odometer'], 0, '', ' ') . ' км' : 'Не указан'; ?>
                        </div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-label">Текущий пробег</div>
                        <div class="meta-value">
                            <?php echo number_format($service['current_odometer'] ?? 0, 0, '', ' ') . ' км'; ?>
                        </div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-label">Планируемая стоимость</div>
                        <div class="meta-value">
                            <?php echo !empty($service['cost']) ? number_format($service['cost'], 2, ',', ' ') . ' ₽' : 'Не указана'; ?>
                        </div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-label">Дата след. ТО</div>
                        <div class="meta-value planned">
                            <?php echo !empty($service['next_service_date']) ? date('d.m.Y', strtotime($service['next_service_date'])) : 'Не указана'; ?>
                        </div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="meta-label">Пробег след. ТО</div>
                        <div class="meta-value planned">
                            <?php echo !empty($service['next_service_mileage']) ? number_format($service['next_service_mileage'], 0, '', ' ') . ' км' : 'Не указан'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Сообщения об ошибках -->
        <?php if (!empty($errors)): ?>
        <div class="error-list fade-in">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Форма завершения ТО -->
        <div class="complete-form-container fade-in">
            <div class="form-header">
                <div>
                    <h2 class="form-title">Фактические данные выполнения ТО</h2>
                    <div class="form-subtitle">Заполните фактические данные о выполненном техническом обслуживании</div>
                </div>
                <div class="form-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <form method="POST" class="complete-form">
                <!-- Фактическая дата выполнения -->
                <div class="form-group">
                    <label class="form-label required" for="actual_date">Фактическая дата выполнения</label>
                    <input type="date" class="form-control" id="actual_date" name="actual_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                    <div class="form-help">Дата, когда было фактически выполнено ТО</div>
                </div>

                <!-- Фактический пробег -->
                <div class="form-group">
                    <label class="form-label required" for="actual_odometer">Фактический пробег</label>
                    <input type="number" class="form-control" id="actual_odometer" name="actual_odometer" 
                           value="<?php echo $service['current_odometer'] ?? ''; ?>"
                           min="0" step="1" required>
                    <div class="form-help">Пробег мотоцикла на момент выполнения ТО</div>
                </div>

                <!-- Фактическая стоимость -->
                <div class="form-group">
                    <label class="form-label required" for="actual_cost">Фактическая стоимость</label>
                    <input type="number" class="form-control" id="actual_cost" name="actual_cost" 
                           value="<?php echo htmlspecialchars($service['cost'] ?? '0.00'); ?>"
                           min="0" step="0.01" required>
                    <div class="form-help">Фактическая стоимость выполненного ТО в рублях</div>
                </div>

                <!-- Тип обслуживания -->
                <div class="form-group">
                    <label class="form-label" for="service_type">Тип обслуживания</label>
                    <select class="form-control" id="service_type" name="service_type">
                        <option value="">Выберите тип обслуживания</option>
                        <option value="ТО-1" <?php echo ($service['service_type'] ?? '') == 'ТО-1' ? 'selected' : ''; ?>>ТО-1 (Техническое обслуживание 1)</option>
                        <option value="ТО-2" <?php echo ($service['service_type'] ?? '') == 'ТО-2' ? 'selected' : ''; ?>>ТО-2 (Техническое обслуживание 2)</option>
                        <option value="ТО-3" <?php echo ($service['service_type'] ?? '') == 'ТО-3' ? 'selected' : ''; ?>>ТО-3 (Техническое обслуживание 3)</option>
                        <option value="Сезонное" <?php echo ($service['service_type'] ?? '') == 'Сезонное' ? 'selected' : ''; ?>>Сезонное обслуживание</option>
                        <option value="Замена масла" <?php echo ($service['service_type'] ?? '') == 'Замена масла' ? 'selected' : ''; ?>>Замена масла</option>
                        <option value="Замена цепи" <?php echo ($service['service_type'] ?? '') == 'Замена цепи' ? 'selected' : ''; ?>>Замена цепи</option>
                        <option value="Замена тормозов" <?php echo ($service['service_type'] ?? '') == 'Замена тормозов' ? 'selected' : ''; ?>>Замена тормозов</option>
                        <option value="Ремонт" <?php echo ($service['service_type'] ?? '') == 'Ремонт' ? 'selected' : ''; ?>>Ремонт</option>
                        <option value="Диагностика" <?php echo ($service['service_type'] ?? '') == 'Диагностика' ? 'selected' : ''; ?>>Диагностика</option>
                        <option value="Другое" <?php echo ($service['service_type'] ?? '') == 'Другое' ? 'selected' : ''; ?>>Другое</option>
                    </select>
                    <div class="form-help">Тип выполненного технического обслуживания</div>
                </div>

                <!-- Сервисный центр -->
                <div class="form-group">
                    <label class="form-label" for="service_center">Сервисный центр</label>
                    <input type="text" class="form-control" id="service_center" name="service_center" 
                           value="<?php echo htmlspecialchars($service['service_center'] ?? ''); ?>"
                           maxlength="255">
                    <div class="form-help">Название сервисного центра, где выполнено ТО</div>
                </div>

                <!-- Дата следующего ТО -->
                <div class="form-group">
                    <label class="form-label" for="next_date">Дата следующего ТО</label>
                    <input type="date" class="form-control" id="next_date" name="next_date" 
                           value="<?php echo htmlspecialchars($recommended_next_date); ?>">
                    <div class="form-help">Дата следующего планового ТО (рекомендуется: +6 месяцев)</div>
                </div>

                <!-- Пробег следующего ТО -->
                <div class="form-group">
                    <label class="form-label" for="next_mileage">Пробег следующего ТО</label>
                    <input type="number" class="form-control" id="next_mileage" name="next_mileage" 
                           value="<?php echo htmlspecialchars($recommended_next_mileage); ?>"
                           min="0" step="1">
                    <div class="form-help">Пробег для следующего планового ТО (рекомендуется: +5000 км)</div>
                </div>

                <!-- Примечания -->
                <div class="form-group full-width">
                    <label class="form-label" for="notes">Примечания</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Опишите выполненные работы, замененные детали, рекомендации..."><?php echo htmlspecialchars($service['notes'] ?? ''); ?></textarea>
                    <div class="form-help">Подробная информация о выполненном ТО</div>
                </div>

                <!-- Кнопки формы -->
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn-admin btn-complete">
                            <i class="fas fa-check-circle"></i> Отметить как выполненное
                        </button>
                    </div>
                    <div>
                        <a href="services_view.php?id=<?php echo $service_id; ?>" class="btn-admin btn-back">
                            <i class="fas fa-times"></i> Отмена
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Информационное сообщение -->
        <div class="alert alert-warning fade-in">
            <div>
                <i class="fas fa-info-circle"></i>
                <strong>Внимание!</strong> После отметки ТО как выполненного:
                <ul style="margin: 10px 0 0 20px;">
                    <li>Статус ТО изменится на "Выполнено"</li>
                    <li>Будет обновлен текущий пробег мотоцикла</li>
                    <li>Будут установлены дата и пробег для следующего ТО</li>
                    <li>Эта операция необратима</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Анимации при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            // Автоматическое скрытие сообщений об ошибках через 5 секунд
            setTimeout(() => {
                const errorList = document.querySelector('.error-list');
                if (errorList) {
                    errorList.style.transition = 'opacity 0.5s';
                    errorList.style.opacity = '0';
                    setTimeout(() => errorList.remove(), 500);
                }
            }, 5000);

            // Подсветка важных полей
            const importantFields = ['actual_date', 'actual_odometer', 'actual_cost'];
            importantFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.classList.add('highlight');
                }
            });

            // Автоматический расчет даты следующего ТО
            const actualDateField = document.getElementById('actual_date');
            const nextDateField = document.getElementById('next_date');
            
            if (actualDateField && nextDateField) {
                actualDateField.addEventListener('change', function() {
                    if (this.value && !nextDateField.value) {
                        const actualDate = new Date(this.value);
                        actualDate.setMonth(actualDate.getMonth() + 6);
                        const nextDate = actualDate.toISOString().split('T')[0];
                        nextDateField.value = nextDate;
                    }
                });
            }

            // Автоматический расчет пробега следующего ТО
            const actualOdometerField = document.getElementById('actual_odometer');
            const nextMileageField = document.getElementById('next_mileage');
            
            if (actualOdometerField && nextMileageField) {
                actualOdometerField.addEventListener('input', function() {
                    if (this.value && !nextMileageField.value) {
                        const nextMileage = parseInt(this.value) + 5000;
                        nextMileageField.value = nextMileage;
                    }
                });
            }

            // Форматирование чисел при вводе
            function formatNumber(input) {
                if (!input) return;
                
                input.addEventListener('blur', function() {
                    let value = this.value.replace(/\s/g, '');
                    if (value && !isNaN(value)) {
                        this.value = Number(value).toLocaleString('ru-RU');
                    }
                });
                
                input.addEventListener('focus', function() {
                    this.value = this.value.replace(/\s/g, '');
                });
            }

            // Применяем форматирование к полям с пробегом и стоимостью
            const numberFields = document.querySelectorAll('#actual_odometer, #actual_cost, #next_mileage');
            numberFields.forEach(formatNumber);

            // Валидация формы
            const form = document.querySelector('.complete-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    const requiredFields = form.querySelectorAll('[required]');
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = '#dc3545';
                            field.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                        } else {
                            field.style.borderColor = '#ddd';
                            field.style.boxShadow = 'none';
                        }
                    });
                    
                    // Проверка даты
                    const actualDate = document.getElementById('actual_date').value;
                    if (actualDate) {
                        const today = new Date().toISOString().split('T')[0];
                        const selectedDate = new Date(actualDate);
                        const maxDate = new Date();
                        maxDate.setDate(maxDate.getDate() + 1); // Завтра
                        
                        if (selectedDate > maxDate) {
                            isValid = false;
                            alert('Дата выполнения ТО не может быть в будущем');
                        }
                    }
                    
                    // Проверка пробега
                    const actualOdometer = parseInt(document.getElementById('actual_odometer').value.replace(/\s/g, '') || 0);
                    const currentOdometer = <?php echo $service['current_odometer'] ?? 0; ?>;
                    
                    if (actualOdometer < currentOdometer - 100) { // Допускаем небольшую погрешность
                        if (!confirm('Введенный пробег меньше текущего пробега мотоцикла. Вы уверены?')) {
                            isValid = false;
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Пожалуйста, заполните все обязательные поля корректно');
                    } else {
                        // Подтверждение завершения ТО
                        if (!confirm('Вы уверены, что хотите отметить это ТО как выполненное?\n\nПосле этого:\n- Статус изменится на "Выполнено"\n- Пробег мотоцикла будет обновлен\n- Будут установлены параметры следующего ТО')) {
                            e.preventDefault();
                        }
                    }
                });
            }

            // Подсказка при наведении на кнопку
            const completeBtn = document.querySelector('.btn-complete');
            if (completeBtn) {
                completeBtn.addEventListener('mouseover', function() {
                    this.title = 'Отметить ТО как выполненное и обновить все данные';
                });
            }
        });
    </script>
</body>
</html>