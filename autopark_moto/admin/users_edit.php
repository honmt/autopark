<?php
// admin/users_edit.php - Редактирование пользователя

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
    $_SESSION['error'] = 'Не указан ID пользователя';
    header('Location: users.php');
    exit();
}

$user_id = intval($_GET['id']);

// Получаем данные пользователя для редактирования
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = 'Пользователь не найден';
    header('Location: users.php');
    exit();
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из формы
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Валидация
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Имя пользователя обязательно';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Имя пользователя должно содержать минимум 3 символа';
    }
    
    if (empty($email)) {
        $errors[] = 'Email обязателен';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный формат email';
    }
    
    // Проверяем уникальность username (кроме текущего пользователя)
    $check_stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_stmt->execute([$username, $user_id]);
    if ($check_stmt->fetch()) {
        $errors[] = 'Пользователь с таким именем уже существует';
    }
    
    // Проверяем уникальность email (кроме текущего пользователя)
    $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->execute([$email, $user_id]);
    if ($check_stmt->fetch()) {
        $errors[] = 'Пользователь с таким email уже существует';
    }
    
    // Если меняется пароль
    $password_changed = false;
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        
        if (strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать минимум 6 символов';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Пароли не совпадают';
        } else {
            $password_changed = true;
        }
    }
    
    // Если нет ошибок, обновляем запись
    if (empty($errors)) {
        try {
            if ($password_changed) {
                // Обновляем с паролем
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $db->prepare("
                    UPDATE users 
                    SET username = :username,
                        email = :email,
                        full_name = :full_name,
                        phone = :phone,
                        role = :role,
                        is_active = :is_active,
                        password = :password,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                
                $update_stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':full_name' => $full_name,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':is_active' => $is_active,
                    ':password' => $hashed_password,
                    ':id' => $user_id
                ]);
            } else {
                // Обновляем без изменения пароля
                $update_stmt = $db->prepare("
                    UPDATE users 
                    SET username = :username,
                        email = :email,
                        full_name = :full_name,
                        phone = :phone,
                        role = :role,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                
                $update_stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':full_name' => $full_name,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':is_active' => $is_active,
                    ':id' => $user_id
                ]);
            }
            
            $_SESSION['success'] = 'Данные пользователя успешно обновлены';
            header("Location: users_view.php?id=$user_id");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = 'Ошибка при обновлении данных: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование пользователя #<?php echo $user_id; ?> - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для страницы редактирования пользователя */
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .checkbox-group label {
            font-size: 14px;
            color: #333;
            cursor: pointer;
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
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-inactive {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        /* Информация о пользователе */
        .user-info-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .user-avatar-large {
            width: 80px;
            height: 80px;
            background: #d60000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 32px;
            color: white;
        }

        .user-details {
            flex: 1;
        }

        .user-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .user-meta {
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
            
            .user-info-card {
                flex-direction: column;
                text-align: center;
            }
            
            .user-meta {
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
            
            .edit-form-container {
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
                        <a href="users.php" class="menu-link active">
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
                <h1 class="page-title">Редактирование пользователя</h1>
                <p class="page-subtitle">Изменение данных пользователя #<?php echo $user_id; ?></p>
            </div>
            <div class="topbar-actions">
                <a href="users.php" class="btn-admin btn-back">
                    <i class="fas fa-arrow-left"></i> Назад к списку
                </a>
                <a href="users_view.php?id=<?php echo $user_id; ?>" class="btn-admin btn-secondary">
                    <i class="fas fa-eye"></i> Просмотр
                </a>
            </div>
        </div>

        <!-- Информация о пользователе -->
        <div class="user-info-card fade-in">
            <div class="user-avatar-large">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-title">
                    <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $user['is_active'] ? 'Активен' : 'Неактивен'; ?>
                    </span>
                </div>
                <div class="user-meta">
                    <div class="meta-item">
                        <span class="meta-label">Роль</span>
                        <span class="meta-value"><?php echo htmlspecialchars($user['role']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Email</span>
                        <span class="meta-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Дата регистрации</span>
                        <span class="meta-value"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></span>
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
                <h2 class="form-title">Основная информация</h2>
                <div class="form-subtitle">Изменение данных пользователя</div>
            </div>

            <form method="POST" class="edit-form" id="editUserForm">
                <!-- Имя пользователя -->
                <div class="form-group">
                    <label class="form-label required" for="username">Имя пользователя</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                           required minlength="3" maxlength="50">
                    <div class="form-help">Минимум 3 символа</div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label required" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                           required maxlength="255">
                </div>

                <!-- Полное имя -->
                <div class="form-group">
                    <label class="form-label" for="full_name">Полное имя</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                           maxlength="100">
                </div>

                <!-- Телефон -->
                <div class="form-group">
                    <label class="form-label" for="phone">Телефон</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                           maxlength="20">
                </div>

                <!-- Роль -->
                <div class="form-group">
                    <label class="form-label required" for="role">Роль</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>Пользователь</option>
                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                    </select>
                </div>

                <!-- Статус активности -->
                <div class="form-group">
                    <label class="form-label">Статус</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" 
                               value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active">Активен</label>
                    </div>
                    <div class="form-help">Если отключено, пользователь не сможет войти в систему</div>
                </div>

                <!-- Пароль (опционально) -->
                <div class="form-group full-width">
                    <label class="form-label" for="password">Новый пароль</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           minlength="6" placeholder="Оставьте пустым, если не меняете">
                    <div class="form-help">Минимум 6 символов</div>
                </div>

                <!-- Подтверждение пароля -->
                <div class="form-group full-width">
                    <label class="form-label" for="password_confirm">Подтверждение пароля</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                           minlength="6" placeholder="Повторите пароль">
                </div>

                <!-- Кнопки формы -->
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn-admin btn-save">
                            <i class="fas fa-save"></i> Сохранить изменения
                        </button>
                    </div>
                    <div>
                        <a href="users_view.php?id=<?php echo $user_id; ?>" class="btn-admin btn-cancel">
                            <i class="fas fa-times"></i> Отмена
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Предупреждение -->
        <div class="alert alert-warning fade-in">
            <div>
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Внимание!</strong> Изменение данных пользователя может повлиять на его доступ к системе.
                При смене пароля пользователю потребуется войти заново.
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

            // Валидация пароля
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirm');
            
            function validatePassword() {
                if (passwordInput.value && passwordConfirmInput.value) {
                    if (passwordInput.value !== passwordConfirmInput.value) {
                        passwordConfirmInput.style.borderColor = '#dc3545';
                        passwordConfirmInput.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                        return false;
                    } else {
                        passwordConfirmInput.style.borderColor = '#28a745';
                        passwordConfirmInput.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
                        return true;
                    }
                }
                return true;
            }
            
            if (passwordInput && passwordConfirmInput) {
                passwordInput.addEventListener('input', validatePassword);
                passwordConfirmInput.addEventListener('input', validatePassword);
            }

            // Проверка уникальности имени пользователя (AJAX)
            const usernameInput = document.getElementById('username');
            const originalUsername = '<?php echo $user["username"]; ?>';
            
            if (usernameInput) {
                let usernameTimeout;
                
                usernameInput.addEventListener('input', function() {
                    clearTimeout(usernameTimeout);
                    usernameTimeout = setTimeout(() => {
                        const username = this.value.trim();
                        
                        if (username.length >= 3 && username !== originalUsername) {
                            // AJAX запрос для проверки
                            fetch(`/autopark_moto/admin/api/check_username.php?username=${encodeURIComponent(username)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.exists) {
                                        usernameInput.style.borderColor = '#dc3545';
                                        usernameInput.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                                        showError('Пользователь с таким именем уже существует');
                                    } else {
                                        usernameInput.style.borderColor = '#28a745';
                                        usernameInput.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
                                    }
                                })
                                .catch(error => {
                                    console.error('Ошибка проверки имени:', error);
                                });
                        }
                    }, 500);
                });
            }

            // Проверка уникальности email (AJAX)
            const emailInput = document.getElementById('email');
            const originalEmail = '<?php echo $user["email"]; ?>';
            
            if (emailInput) {
                let emailTimeout;
                
                emailInput.addEventListener('input', function() {
                    clearTimeout(emailTimeout);
                    emailTimeout = setTimeout(() => {
                        const email = this.value.trim();
                        
                        if (email.length >= 3 && email !== originalEmail) {
                            // AJAX запрос для проверки
                            fetch(`/autopark_moto/admin/api/check_email.php?email=${encodeURIComponent(email)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.exists) {
                                        emailInput.style.borderColor = '#dc3545';
                                        emailInput.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                                        showError('Пользователь с таким email уже существует');
                                    } else {
                                        emailInput.style.borderColor = '#28a745';
                                        emailInput.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
                                    }
                                })
                                .catch(error => {
                                    console.error('Ошибка проверки email:', error);
                                });
                        }
                    }, 500);
                });
            }

            // Валидация формы
            const form = document.getElementById('editUserForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    const errorMessages = [];
                    
                    // Проверка обязательных полей
                    const requiredFields = form.querySelectorAll('[required]');
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = '#dc3545';
                            field.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                        }
                    });
                    
                    // Проверка имени пользователя
                    if (usernameInput.value.length < 3) {
                        isValid = false;
                        errorMessages.push('Имя пользователя должно содержать минимум 3 символа');
                    }
                    
                    // Проверка email
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailInput.value)) {
                        isValid = false;
                        errorMessages.push('Некорректный формат email');
                    }
                    
                    // Проверка пароля
                    if (passwordInput.value) {
                        if (passwordInput.value.length < 6) {
                            isValid = false;
                            errorMessages.push('Пароль должен содержать минимум 6 символов');
                        }
                        
                        if (passwordInput.value !== passwordConfirmInput.value) {
                            isValid = false;
                            errorMessages.push('Пароли не совпадают');
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        let alertMessage = 'Пожалуйста, исправьте следующие ошибки:\n\n';
                        alertMessage += errorMessages.length > 0 
                            ? errorMessages.join('\n') 
                            : 'Заполните все обязательные поля';
                        alert(alertMessage);
                    } else {
                        // Подтверждение сохранения
                        if (!confirm('Вы уверены, что хотите сохранить изменения?')) {
                            e.preventDefault();
                        }
                    }
                });
            }

            // Вспомогательная функция для показа ошибок
            function showError(message) {
                // Создаем временное уведомление об ошибке
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-list fade-in';
                errorDiv.innerHTML = `
                    <ul>
                        <li>${message}</li>
                    </ul>
                `;
                
                // Вставляем перед формой
                const formContainer = document.querySelector('.edit-form-container');
                if (formContainer) {
                    formContainer.parentNode.insertBefore(errorDiv, formContainer);
                    
                    // Автоматическое удаление через 5 секунд
                    setTimeout(() => {
                        errorDiv.style.transition = 'opacity 0.5s';
                        errorDiv.style.opacity = '0';
                        setTimeout(() => errorDiv.remove(), 500);
                    }, 5000);
                }
            }

            // Подсказка при наведении на поля
            const fieldsWithHelp = document.querySelectorAll('.form-control');
            fieldsWithHelp.forEach(field => {
                field.addEventListener('mouseover', function() {
                    const helpText = this.parentElement.querySelector('.form-help');
                    if (helpText) {
                        helpText.style.color = '#d60000';
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