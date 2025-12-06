<?php
// admin/services_add.php - Добавление новой записи ТО

session_start();

// Проверяем, авторизован ли пользователь и является ли он администратором
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /autopark_moto/auth/login.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Получаем список мотоциклов для выпадающего списка
$motorcycles_stmt = $db->query("SELECT id, CONCAT(make, ' ', model) as name, odometer FROM motorcycles ORDER BY make, model");
$motorcycles = $motorcycles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем список шаблонов ТО (если есть таблица service_templates)
$templates = [];
try {
    $templates_stmt = $db->query("SELECT id, name, description, interval_months, interval_km FROM service_templates ORDER BY name");
    $templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Таблица может отсутствовать
    $templates = [];
}

// Обработка формы добавления
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $motorcycle_id = intval($_POST['motorcycle_id']);
    $template_id = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
    $last_service_date = !empty($_POST['last_service_date']) ? $_POST['last_service_date'] : null;
    $last_service_odometer = !empty($_POST['last_service_odometer']) ? intval(str_replace(' ', '', $_POST['last_service_odometer'])) : null;
    $next_service_date = !empty($_POST['next_service_date']) ? $_POST['next_service_date'] : null;
    $next_service_mileage = !empty($_POST['next_service_mileage']) ? intval(str_replace(' ', '', $_POST['next_service_mileage'])) : null;
    $status = $_POST['status'] ?? 'upcoming';
    $cost = !empty($_POST['cost']) ? floatval(str_replace(',', '.', str_replace(' ', '', $_POST['cost']))) : 0.00;
    $notes = $_POST['notes'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $service_center = $_POST['service_center'] ?? '';
    
    // Валидация
    $errors = [];
    
    if (empty($motorcycle_id)) {
        $errors[] = 'Выберите мотоцикл';
    }
    
    if (empty($last_service_date)) {
        $errors[] = 'Укажите дату последнего ТО';
    }
    
    if (empty($next_service_date)) {
        $errors[] = 'Укажите дату следующего ТО';
    }
    
    // Проверка дат
    if (!empty($last_service_date) && !empty($next_service_date)) {
        if (strtotime($next_service_date) < strtotime($last_service_date)) {
            $errors[] = 'Дата следующего ТО не может быть раньше даты последнего ТО';
        }
    }
    
    // Проверка пробега
    if (!empty($last_service_odometer) && !empty($next_service_mileage)) {
        if ($next_service_mileage < $last_service_odometer) {
            $errors[] = 'Пробег следующего ТО не может быть меньше пробега последнего ТО';
        }
    }
    
    // Если нет ошибок, добавляем запись
    if (empty($errors)) {
        try {
            $insert_stmt = $db->prepare("
                INSERT INTO motorcycle_services (
                    motorcycle_id,
                    template_id,
                    last_service_date,
                    last_service_odometer,
                    next_service_date,
                    next_service_mileage,
                    status,
                    cost,
                    notes,
                    service_type,
                    service_center,
                    created_at,
                    updated_at
                ) VALUES (
                    :motorcycle_id,
                    :template_id,
                    :last_service_date,
                    :last_service_odometer,
                    :next_service_date,
                    :next_service_mileage,
                    :status,
                    :cost,
                    :notes,
                    :service_type,
                    :service_center,
                    NOW(),
                    NOW()
                )
            ");
            
            $insert_stmt->execute([
                ':motorcycle_id' => $motorcycle_id,
                ':template_id' => $template_id,
                ':last_service_date' => $last_service_date,
                ':last_service_odometer' => $last_service_odometer,
                ':next_service_date' => $next_service_date,
                ':next_service_mileage' => $next_service_mileage,
                ':status' => $status,
                ':cost' => $cost,
                ':notes' => $notes,
                ':service_type' => $service_type,
                ':service_center' => $service_center
            ]);
            
            $new_service_id = $db->lastInsertId();
            
            $_SESSION['success'] = 'Новая запись ТО успешно добавлена!';
            header("Location: services_view.php?id=$new_service_id");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = 'Ошибка при добавлении записи: ' . $e->getMessage();
        }
    }
}

// Установим даты по умолчанию
$default_last_date = date('Y-m-d');
$default_next_date = date('Y-m-d', strtotime('+6 months'));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавление ТО - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для страницы добавления ТО */
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

        /* Форма добавления */
        .add-form-container {
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
        .add-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        @media (max-width: 1024px) {
            .add-form {
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

        .btn-add {
            background: #28a745;
            color: white;
        }

        .btn-add:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        /* Информационный блок */
        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .info-icon {
            font-size: 50px;
            color: #1976d2;
            opacity: 0.8;
        }

        .info-content {
            flex: 1;
        }

        .info-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1565c0;
        }

        .info-text {
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }

        /* Статус */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
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
            
            .info-box {
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
            
            .add-form-container {
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

        /* Автозаполнение для шаблонов */
        .template-select {
            position: relative;
        }

        .template-info {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            font-size: 13px;
            display: none;
        }

        .template-info.active {
            display: block;
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
                <h1 class="page-title">Добавление ТО</h1>
                <p class="page-subtitle">Добавление новой записи технического обслуживания</p>
            </div>
            <div class="topbar-actions">
                <a href="services.php" class="btn-admin btn-back">
                    <i class="fas fa-arrow-left"></i> Назад к списку
                </a>
            </div>
        </div>

        <!-- Информационный блок -->
        <div class="info-box fade-in">
            <div class="info-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="info-content">
                <h3 class="info-title">Информация о добавлении ТО</h3>
                <p class="info-text">
                    Заполните форму для добавления новой записи технического обслуживания. 
                    Поля, отмеченные звёздочкой (<span style="color: #dc3545">*</span>), являются обязательными для заполнения.
                    После успешного добавления вы будете перенаправлены на страницу просмотра созданной записи.
                </p>
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

        <!-- Форма добавления ТО -->
        <div class="add-form-container fade-in">
            <div class="form-header">
                <div>
                    <h2 class="form-title">Добавить новое ТО</h2>
                    <div class="form-subtitle">Заполните информацию о техническом обслуживании</div>
                </div>
                <div class="form-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
            </div>

            <form method="POST" class="add-form" id="addServiceForm">
                <!-- Мотоцикл -->
                <div class="form-group">
                    <label class="form-label required" for="motorcycle_id">Мотоцикл</label>
                    <select class="form-control" id="motorcycle_id" name="motorcycle_id" required>
                        <option value="">Выберите мотоцикл</option>
                        <?php foreach ($motorcycles as $moto): ?>
                        <option value="<?php echo $moto['id']; ?>" 
                                data-odometer="<?php echo $moto['odometer']; ?>">
                            <?php echo htmlspecialchars($moto['name']); ?> 
                            (<?php echo number_format($moto['odometer'], 0, '', ' ') . ' км'; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">Текущий пробег будет подставлен автоматически</div>
                </div>

                <!-- ID шаблона (если есть) -->
                <?php if (!empty($templates)): ?>
                <div class="form-group template-select">
                    <label class="form-label" for="template_id">Шаблон ТО</label>
                    <select class="form-control" id="template_id" name="template_id">
                        <option value="">Без шаблона</option>
                        <?php foreach ($templates as $template): ?>
                        <option value="<?php echo $template['id']; ?>"
                                data-months="<?php echo $template['interval_months']; ?>"
                                data-km="<?php echo $template['interval_km']; ?>"
                                data-description="<?php echo htmlspecialchars($template['description']); ?>">
                            <?php echo htmlspecialchars($template['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="template-info" id="templateInfo"></div>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label" for="template_id">ID шаблона ТО</label>
                    <input type="number" class="form-control" id="template_id" name="template_id" 
                           min="1" step="1" placeholder="Оставьте пустым, если не используется">
                </div>
                <?php endif; ?>

                <!-- Дата последнего ТО -->
                <div class="form-group">
                    <label class="form-label required" for="last_service_date">Дата последнего ТО</label>
                    <input type="date" class="form-control" id="last_service_date" name="last_service_date" 
                           value="<?php echo $default_last_date; ?>" required>
                    <div class="form-help">Дата, когда было выполнено последнее ТО</div>
                </div>

                <!-- Пробег на момент ТО -->
                <div class="form-group">
                    <label class="form-label" for="last_service_odometer">Пробег на момент ТО</label>
                    <input type="number" class="form-control" id="last_service_odometer" name="last_service_odometer" 
                           min="0" step="1" placeholder="Автоматически из выбранного мотоцикла">
                    <div class="form-help">Пробег мотоцикла на момент выполнения ТО</div>
                </div>

                <!-- Дата следующего ТО -->
                <div class="form-group">
                    <label class="form-label required" for="next_service_date">Дата следующего ТО</label>
                    <input type="date" class="form-control" id="next_service_date" name="next_service_date" 
                           value="<?php echo $default_next_date; ?>" required>
                    <div class="form-help">Планируемая дата следующего ТО</div>
                </div>

                <!-- Пробег для следующего ТО -->
                <div class="form-group">
                    <label class="form-label" for="next_service_mileage">Пробег для следующего ТО</label>
                    <input type="number" class="form-control" id="next_service_mileage" name="next_service_mileage" 
                           min="0" step="1">
                    <div class="form-help">Планируемый пробег для следующего ТО</div>
                </div>

                <!-- Статус -->
                <div class="form-group">
                    <label class="form-label required" for="status">Статус ТО</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="upcoming" selected>Предстоящее</option>
                        <option value="overdue">Просрочено</option>
                        <option value="done">Выполнено</option>
                    </select>
                </div>

                <!-- Стоимость -->
                <div class="form-group">
                    <label class="form-label" for="cost">Стоимость ТО</label>
                    <input type="number" class="form-control" id="cost" name="cost" 
                           value="0.00" min="0" step="0.01">
                    <div class="form-help">Стоимость ТО в рублях</div>
                </div>

                <!-- Тип обслуживания -->
                <div class="form-group">
                    <label class="form-label" for="service_type">Тип обслуживания</label>
                    <select class="form-control" id="service_type" name="service_type">
                        <option value="">Не указан</option>
                        <option value="ТО-1">ТО-1 (Техническое обслуживание 1)</option>
                        <option value="ТО-2">ТО-2 (Техническое обслуживание 2)</option>
                        <option value="ТО-3">ТО-3 (Техническое обслуживание 3)</option>
                        <option value="Сезонное">Сезонное обслуживание</option>
                        <option value="Замена масла">Замена масла</option>
                        <option value="Замена цепи">Замена цепи</option>
                        <option value="Замена тормозов">Замена тормозов</option>
                        <option value="Ремонт">Ремонт</option>
                        <option value="Диагностика">Диагностика</option>
                        <option value="Другое">Другое</option>
                    </select>
                </div>

                <!-- Сервисный центр -->
                <div class="form-group">
                    <label class="form-label" for="service_center">Сервисный центр</label>
                    <input type="text" class="form-control" id="service_center" name="service_center" 
                           maxlength="255" placeholder="Название сервисного центра">
                </div>

                <!-- Примечания -->
                <div class="form-group full-width">
                    <label class="form-label" for="notes">Примечания</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                              placeholder="Дополнительная информация о ТО..."></textarea>
                </div>

                <!-- Кнопки формы -->
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn-admin btn-add">
                            <i class="fas fa-plus-circle"></i> Добавить ТО
                        </button>
                    </div>
                    <div>
                        <button type="reset" class="btn-admin btn-back" id="resetBtn">
                            <i class="fas fa-redo"></i> Сбросить форму
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Подсказки -->
        <div class="alert alert-info fade-in">
            <div>
                <i class="fas fa-lightbulb"></i>
                <strong>Подсказки:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>Выберите мотоцикл - пробег подставится автоматически</li>
                    <li>При выборе шаблона ТО даты и пробеги рассчитаются автоматически</li>
                    <li>Статус "Предстоящее" используется для запланированных ТО</li>
                    <li>Статус "Просрочено" - если ТО должно было быть выполнено ранее</li>
                    <li>Статус "Выполнено" - если ТО уже выполнено</li>
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

            // Элементы формы
            const motorcycleSelect = document.getElementById('motorcycle_id');
            const templateSelect = document.getElementById('template_id');
            const lastDateInput = document.getElementById('last_service_date');
            const lastOdometerInput = document.getElementById('last_service_odometer');
            const nextDateInput = document.getElementById('next_service_date');
            const nextMileageInput = document.getElementById('next_service_mileage');
            const templateInfo = document.getElementById('templateInfo');
            const statusSelect = document.getElementById('status');
            const costInput = document.getElementById('cost');

            // Подсветка обязательных полей
            const requiredFields = document.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                field.classList.add('highlight');
            });

            // Автозаполнение пробега при выборе мотоцикла
            if (motorcycleSelect && lastOdometerInput) {
                motorcycleSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const odometer = selectedOption.getAttribute('data-odometer');
                        lastOdometerInput.value = odometer || '';
                        
                        // Если поле следующего пробега пустое, рассчитываем +5000 км
                        if (nextMileageInput && !nextMileageInput.value && odometer) {
                            nextMileageInput.value = parseInt(odometer) + 5000;
                        }
                    }
                });
            }

            // Обработка выбора шаблона ТО
            if (templateSelect && templateInfo) {
                templateSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const months = selectedOption.getAttribute('data-months');
                        const km = selectedOption.getAttribute('data-km');
                        const description = selectedOption.getAttribute('data-description');
                        
                        // Показываем информацию о шаблоне
                        templateInfo.innerHTML = `
                            <strong>Интервал:</strong> ${months} месяцев / ${km} км<br>
                            <strong>Описание:</strong> ${description}
                        `;
                        templateInfo.classList.add('active');
                        
                        // Автоматический расчет даты и пробега
                        if (lastDateInput.value && months) {
                            const lastDate = new Date(lastDateInput.value);
                            lastDate.setMonth(lastDate.getMonth() + parseInt(months));
                            nextDateInput.value = lastDate.toISOString().split('T')[0];
                        }
                        
                        if (lastOdometerInput.value && km) {
                            nextMileageInput.value = parseInt(lastOdometerInput.value) + parseInt(km);
                        }
                    } else {
                        templateInfo.classList.remove('active');
                    }
                });
            }

            // Автоматический расчет следующей даты при изменении последней даты
            if (lastDateInput && nextDateInput) {
                lastDateInput.addEventListener('change', function() {
                    if (this.value && !nextDateInput.value) {
                        const lastDate = new Date(this.value);
                        lastDate.setMonth(lastDate.getMonth() + 6); // +6 месяцев по умолчанию
                        nextDateInput.value = lastDate.toISOString().split('T')[0];
                    }
                });
            }

            // Автоматический расчет следующего пробега при изменении последнего пробега
            if (lastOdometerInput && nextMileageInput) {
                lastOdometerInput.addEventListener('input', function() {
                    if (this.value && !nextMileageInput.value) {
                        nextMileageInput.value = parseInt(this.value) + 5000; // +5000 км по умолчанию
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
            const numberFields = document.querySelectorAll('#last_service_odometer, #next_service_mileage, #cost');
            numberFields.forEach(formatNumber);

            // Кнопка сброса формы
            const resetBtn = document.getElementById('resetBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm('Вы уверены, что хотите сбросить все поля формы?')) {
                        document.getElementById('addServiceForm').reset();
                        
                        // Сброс дополнительных элементов
                        if (templateInfo) {
                            templateInfo.classList.remove('active');
                            templateInfo.innerHTML = '';
                        }
                        
                        // Установка дат по умолчанию
                        const today = new Date().toISOString().split('T')[0];
                        const nextDate = new Date();
                        nextDate.setMonth(nextDate.getMonth() + 6);
                        const nextDateStr = nextDate.toISOString().split('T')[0];
                        
                        if (lastDateInput) lastDateInput.value = today;
                        if (nextDateInput) nextDateInput.value = nextDateStr;
                        if (statusSelect) statusSelect.value = 'upcoming';
                        if (costInput) costInput.value = '0.00';
                    }
                });
            }

            // Валидация формы
            const form = document.getElementById('addServiceForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    const errorMessages = [];
                    
                    // Проверка обязательных полей
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
                    
                    // Проверка дат
                    if (lastDateInput.value && nextDateInput.value) {
                        const lastDate = new Date(lastDateInput.value);
                        const nextDate = new Date(nextDateInput.value);
                        
                        if (nextDate < lastDate) {
                            isValid = false;
                            errorMessages.push('Дата следующего ТО не может быть раньше даты последнего ТО');
                            nextDateInput.style.borderColor = '#dc3545';
                        }
                    }
                    
                    // Проверка пробега
                    if (lastOdometerInput.value && nextMileageInput.value) {
                        const lastOdometer = parseInt(lastOdometerInput.value.replace(/\s/g, ''));
                        const nextMileage = parseInt(nextMileageInput.value.replace(/\s/g, ''));
                        
                        if (nextMileage < lastOdometer) {
                            isValid = false;
                            errorMessages.push('Пробег следующего ТО не может быть меньше пробега последнего ТО');
                            nextMileageInput.style.borderColor = '#dc3545';
                        }
                    }
                    
                    // Проверка стоимости
                    if (costInput.value) {
                        const cost = parseFloat(costInput.value.replace(/\s/g, '').replace(',', '.'));
                        if (cost < 0) {
                            isValid = false;
                            errorMessages.push('Стоимость не может быть отрицательной');
                            costInput.style.borderColor = '#dc3545';
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        let alertMessage = 'Пожалуйста, исправьте следующие ошибки:\n\n';
                        if (errorMessages.length > 0) {
                            alertMessage += errorMessages.join('\n');
                        } else {
                            alertMessage += 'Заполните все обязательные поля';
                        }
                        alert(alertMessage);
                    } else {
                        // Подтверждение добавления
                        if (!confirm('Вы уверены, что хотите добавить новое ТО?')) {
                            e.preventDefault();
                        }
                    }
                });
            }

            // Показать/скрыть дополнительные опции при выборе статуса
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    const status = this.value;
                    console.log('Статус изменен на:', status);
                });
            }

            // Подсказка при наведении на поля
            const fieldsWithHelp = document.querySelectorAll('.form-control');
            fieldsWithHelp.forEach(field => {
                field.addEventListener('mouseover', function() {
                    const helpText = this.parentElement.querySelector('.form-help');
                    if (helpText) {
                        helpText.style.color = '#1976d2';
                        helpText.style.fontWeight = '600';
                    }
                });
                
                field.addEventListener('mouseout', function() {
                    const helpText = this.parentElement.querySelector('.form-help');
                    if (helpText) {
                        helpText.style.color = '#666';
                        helpText.style.fontWeight = 'normal';
                    }
                });
            });
        });
    </script>
</body>
</html>