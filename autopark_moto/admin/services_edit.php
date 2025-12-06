<?php
// admin/services_edit.php - Редактирование записи ТО

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

// Получаем данные о ТО для редактирования
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

// Получаем список мотоциклов для выпадающего списка
$motorcycles_stmt = $db->query("SELECT id, CONCAT(make, ' ', model) as name FROM motorcycles ORDER BY make, model");
$motorcycles = $motorcycles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем список шаблонов ТО (если есть таблица service_templates)
$templates = [];
try {
    $templates_stmt = $db->query("SELECT id, name, description FROM service_templates ORDER BY name");
    $templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Таблица может отсутствовать
    $templates = [];
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $motorcycle_id = intval($_POST['motorcycle_id']);
    $template_id = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
    $last_service_date = !empty($_POST['last_service_date']) ? $_POST['last_service_date'] : null;
    $last_service_odometer = !empty($_POST['last_service_odometer']) ? intval(str_replace(' ', '', $_POST['last_service_odometer'])) : null;
    $next_service_date = !empty($_POST['next_service_date']) ? $_POST['next_service_date'] : null;
    $next_service_mileage = !empty($_POST['next_service_mileage']) ? intval(str_replace(' ', '', $_POST['next_service_mileage'])) : null;
    $status = $_POST['status'];
    $cost = !empty($_POST['cost']) ? floatval(str_replace(',', '.', str_replace(' ', '', $_POST['cost']))) : 0.00;
    $notes = $_POST['notes'] ?? '';
    
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
    
    // Если нет ошибок, обновляем запись
    if (empty($errors)) {
        try {
            $update_stmt = $db->prepare("
                UPDATE motorcycle_services 
                SET motorcycle_id = :motorcycle_id,
                    template_id = :template_id,
                    last_service_date = :last_service_date,
                    last_service_odometer = :last_service_odometer,
                    next_service_date = :next_service_date,
                    next_service_mileage = :next_service_mileage,
                    status = :status,
                    cost = :cost,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $update_stmt->execute([
                ':motorcycle_id' => $motorcycle_id,
                ':template_id' => $template_id,
                ':last_service_date' => $last_service_date,
                ':last_service_odometer' => $last_service_odometer,
                ':next_service_date' => $next_service_date,
                ':next_service_mileage' => $next_service_mileage,
                ':status' => $status,
                ':cost' => $cost,
                ':notes' => $notes,
                ':id' => $service_id
            ]);
            
            $_SESSION['success'] = 'Запись ТО успешно обновлена';
            header("Location: services_view.php?id=$service_id");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = 'Ошибка при обновлении записи: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование ТО #<?php echo $service_id; ?> - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для страницы редактирования */
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

        /* Форма редактирования */
        .edit-form-container {
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

        /* Сообщения об ошибках и успехе */
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
        .edit-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        @media (max-width: 1024px) {
            .edit-form {
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
            border-color: #d60000;
            box-shadow: 0 0 0 3px rgba(214, 0, 0, 0.1);
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

        /* Кнопки формы */
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .btn-save {
            background: #28a745;
            color: white;
        }

        .btn-save:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
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
        }

        @media (max-width: 480px) {
            .admin-sidebar {
                width: 60px;
            }
            
            .admin-content {
                margin-left: 60px;
                padding: 15px;
            }
            
            .edit-form-container {
                padding: 20px;
            }
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
                <h1 class="page-title">Редактирование ТО</h1>
                <p class="page-subtitle">Редактирование записи ТО #<?php echo $service_id; ?></p>
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

        <!-- Форма редактирования -->
        <div class="edit-form-container fade-in">
            <div class="form-header">
                <div>
                    <h2 class="form-title">Редактирование записи ТО</h2>
                    <div class="form-subtitle">Заполните все необходимые поля</div>
                </div>
                <div>
                    <?php
                    $status_classes = [
                        'upcoming' => 'status-upcoming',
                        'overdue' => 'status-overdue', 
                        'done' => 'status-done'
                    ];
                    $status_text = [
                        'upcoming' => 'Предстоящее',
                        'overdue' => 'Просрочено',
                        'done' => 'Выполнено'
                    ];
                    ?>
                    <span class="status-badge <?php echo $status_classes[$service['status']] ?? 'status-upcoming'; ?>">
                        <?php echo $status_text[$service['status']] ?? $service['status']; ?>
                    </span>
                </div>
            </div>

            <form method="POST" class="edit-form">
                <!-- Мотоцикл -->
                <div class="form-group">
                    <label class="form-label required" for="motorcycle_id">Мотоцикл</label>
                    <select class="form-control" id="motorcycle_id" name="motorcycle_id" required>
                        <option value="">Выберите мотоцикл</option>
                        <?php foreach ($motorcycles as $moto): ?>
                        <option value="<?php echo $moto['id']; ?>" 
                            <?php echo ($service['motorcycle_id'] == $moto['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($moto['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ID шаблона (если есть) -->
                <div class="form-group">
                    <label class="form-label" for="template_id">ID шаблона ТО</label>
                    <input type="number" class="form-control" id="template_id" name="template_id" 
                           value="<?php echo htmlspecialchars($service['template_id'] ?? ''); ?>"
                           min="1" step="1">
                    <div class="form-help">Оставьте пустым, если не используется</div>
                </div>

                <!-- Дата последнего ТО -->
                <div class="form-group">
                    <label class="form-label required" for="last_service_date">Дата последнего ТО</label>
                    <input type="date" class="form-control" id="last_service_date" name="last_service_date" 
                           value="<?php echo htmlspecialchars($service['last_service_date'] ?? ''); ?>" required>
                </div>

                <!-- Пробег на момент ТО -->
                <div class="form-group">
                    <label class="form-label" for="last_service_odometer">Пробег на момент ТО</label>
                    <input type="number" class="form-control" id="last_service_odometer" name="last_service_odometer" 
                           value="<?php echo htmlspecialchars($service['last_service_odometer'] ?? ''); ?>"
                           min="0" step="1">
                    <div class="form-help">Укажите пробег в километрах</div>
                </div>

                <!-- Дата следующего ТО -->
                <div class="form-group">
                    <label class="form-label required" for="next_service_date">Дата следующего ТО</label>
                    <input type="date" class="form-control" id="next_service_date" name="next_service_date" 
                           value="<?php echo htmlspecialchars($service['next_service_date'] ?? ''); ?>" required>
                </div>

                <!-- Пробег для следующего ТО -->
                <div class="form-group">
                    <label class="form-label" for="next_service_mileage">Пробег для следующего ТО</label>
                    <input type="number" class="form-control" id="next_service_mileage" name="next_service_mileage" 
                           value="<?php echo htmlspecialchars($service['next_service_mileage'] ?? ''); ?>"
                           min="0" step="1">
                    <div class="form-help">Укажите пробег в километрах</div>
                </div>

                <!-- Статус -->
                <div class="form-group">
                    <label class="form-label required" for="status">Статус ТО</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="upcoming" <?php echo ($service['status'] == 'upcoming') ? 'selected' : ''; ?>>Предстоящее</option>
                        <option value="overdue" <?php echo ($service['status'] == 'overdue') ? 'selected' : ''; ?>>Просрочено</option>
                        <option value="done" <?php echo ($service['status'] == 'done') ? 'selected' : ''; ?>>Выполнено</option>
                    </select>
                </div>

                <!-- Стоимость -->
                <div class="form-group">
                    <label class="form-label" for="cost">Стоимость ТО</label>
                    <input type="number" class="form-control" id="cost" name="cost" 
                           value="<?php echo htmlspecialchars($service['cost'] ?? '0.00'); ?>"
                           min="0" step="0.01">
                    <div class="form-help">Укажите стоимость в рублях</div>
                </div>

                <!-- Примечания -->
                <div class="form-group full-width">
                    <label class="form-label" for="notes">Примечания</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($service['notes'] ?? ''); ?></textarea>
                    <div class="form-help">Дополнительная информация о ТО</div>
                </div>

                <!-- Кнопки формы -->
                <div class="form-actions">
                    <button type="submit" class="btn-admin btn-save">
                        <i class="fas fa-save"></i> Сохранить изменения
                    </button>
                    <a href="services_view.php?id=<?php echo $service_id; ?>" class="btn-admin btn-cancel">
                        <i class="fas fa-times"></i> Отмена
                    </a>
                </div>
            </form>
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

            // Расчет даты следующего ТО на основе пробега
            const lastServiceOdometer = document.getElementById('last_service_odometer');
            const nextServiceMileage = document.getElementById('next_service_mileage');
            const currentOdometer = <?php echo $service['current_odometer'] ?? 0; ?>;

            if (lastServiceOdometer && nextServiceMileage) {
                function calculateRemaining() {
                    const last = parseInt(lastServiceOdometer.value) || 0;
                    const next = parseInt(nextServiceMileage.value) || 0;
                    
                    if (last && next) {
                        const remaining = next - currentOdometer;
                        const remainingElement = document.getElementById('remaining-km');
                        
                        if (!remainingElement) {
                            const helpDiv = nextServiceMileage.parentElement.querySelector('.form-help');
                            const span = document.createElement('span');
                            span.id = 'remaining-km';
                            span.style.display = 'block';
                            span.style.marginTop = '5px';
                            span.style.fontWeight = '600';
                            helpDiv.appendChild(span);
                        }
                        
                        const span = document.getElementById('remaining-km');
                        if (remaining <= 0) {
                            span.style.color = '#dc3545';
                            span.textContent = `Просрочено на ${Math.abs(remaining).toLocaleString('ru-RU')} км`;
                        } else if (remaining <= 500) {
                            span.style.color = '#e0a800';
                            span.textContent = `Осталось ${remaining.toLocaleString('ru-RU')} км`;
                        } else {
                            span.style.color = '#28a745';
                            span.textContent = `Осталось ${remaining.toLocaleString('ru-RU')} км`;
                        }
                    }
                }

                lastServiceOdometer.addEventListener('input', calculateRemaining);
                nextServiceMileage.addEventListener('input', calculateRemaining);
                
                // Инициализация при загрузке
                calculateRemaining();
            }

            // Подсказка при выборе статуса
            const statusSelect = document.getElementById('status');
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    console.log('Статус изменен на:', selectedOption.text);
                });
            }

            // Валидация формы
            const form = document.querySelector('.edit-form');
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
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Пожалуйста, заполните все обязательные поля');
                    }
                });
            }
        });
    </script>
</body>
</html>