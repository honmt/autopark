<?php
// user/settings.php - Настройки пользователя

session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    header('Location: /autopark_moto/auth/login.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

$user_id = $_SESSION['user']['id'];

// Получаем актуальные данные пользователя из базы
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $full_name = $_POST['full_name'] ?? '';
                $phone = $_POST['phone'] ?? '';
                
                // Валидация
                $errors = [];
                
                if (empty($username)) {
                    $errors[] = 'Имя пользователя обязательно';
                }
                
                if (empty($email)) {
                    $errors[] = 'Email обязателен';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Некорректный формат email';
                }
                
                // Проверка уникальности username (кроме текущего пользователя)
                if (!empty($username) && $username != $current_user['username']) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $user_id]);
                    if ($stmt->rowCount() > 0) {
                        $errors[] = 'Это имя пользователя уже занято';
                    }
                }
                
                // Проверка уникальности email (кроме текущего пользователя)
                if (!empty($email) && $email != $current_user['email']) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->rowCount() > 0) {
                        $errors[] = 'Этот email уже используется';
                    }
                }
                
                if (empty($errors)) {
                    try {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET username = ?, email = ?, full_name = ?, phone = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([$username, $email, $full_name, $phone, $user_id]);
                        
                        // Обновляем данные в сессии
                        $_SESSION['user']['username'] = $username;
                        $_SESSION['user']['email'] = $email;
                        $_SESSION['user']['full_name'] = $full_name;
                        $_SESSION['user']['phone'] = $phone;
                        
                        $_SESSION['success'] = 'Профиль успешно обновлен!';
                        header('Location: settings.php');
                        exit();
                    } catch (PDOException $e) {
                        $_SESSION['error'] = 'Ошибка при обновлении профиля: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['error'] = implode('<br>', $errors);
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                $errors = [];
                
                if (empty($current_password)) {
                    $errors[] = 'Текущий пароль обязателен';
                } else {
                    // Проверяем текущий пароль
                    if (!password_verify($current_password, $current_user['password'])) {
                        $errors[] = 'Текущий пароль неверен';
                    }
                }
                
                if (empty($new_password)) {
                    $errors[] = 'Новый пароль обязателен';
                } elseif (strlen($new_password) < 6) {
                    $errors[] = 'Новый пароль должен быть не менее 6 символов';
                }
                
                if ($new_password !== $confirm_password) {
                    $errors[] = 'Новый пароль и подтверждение не совпадают';
                }
                
                if (empty($errors)) {
                    try {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET password = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([$hashed_password, $user_id]);
                        
                        $_SESSION['success'] = 'Пароль успешно изменен!';
                        header('Location: settings.php');
                        exit();
                    } catch (PDOException $e) {
                        $_SESSION['error'] = 'Ошибка при изменении пароля: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['error'] = implode('<br>', $errors);
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        /* Контейнер настроек */
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }

        /* Секции настроек */
        .settings-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #d60000;
        }

        /* Формы */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
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

        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: block;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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

        /* Информация о профиле */
        .profile-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .profile-icon {
            width: 70px;
            height: 70px;
            background: #d60000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            color: white;
        }

        .profile-text h3 {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .profile-text p {
            color: #666;
            font-size: 14px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
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
            
            .settings-section {
                padding: 20px;
            }
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
            <div class="sidebar-subtitle">Настройки профиля</div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">
                <?php 
                $username = $current_user['username'];
                echo strtoupper(substr($username, 0, 1)); 
                ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($current_user['full_name'] ?: $current_user['username']); ?></div>
                <div class="user-role"><?php echo $current_user['role'] == 'admin' ? 'Администратор' : 'Пользователь'; ?></div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Навигация</div>
                <ul class="menu-items">
                    <li class="menu-item">
                        <a href="index.php" class="menu-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Дашборд</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="settings.php" class="menu-link active">
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
                <h1 class="page-title">Настройки профиля</h1>
                <p class="page-subtitle">Управляйте вашими личными данными</p>
            </div>
            <div class="topbar-actions">
                <button class="btn-user btn-secondary" onclick="location.href='index.php'">
                    <i class="fas fa-arrow-left"></i> Назад к дашборду
                </button>
            </div>
        </div>

        <!-- Сообщения -->
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

        <div class="settings-container">
            <!-- Информация о профиле -->
            <div class="profile-info fade-in" style="animation-delay: 0.1s;">
                <div class="profile-header">
                    <div class="profile-icon">
                        <?php 
                        $username = $current_user['username'];
                        echo strtoupper(substr($username, 0, 1)); 
                        ?>
                    </div>
                    <div class="profile-text">
                        <h3><?php echo htmlspecialchars($current_user['full_name'] ?: $current_user['username']); ?></h3>
                        <p><?php echo htmlspecialchars($current_user['email']); ?></p>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-label">ID пользователя</span>
                        <span class="stat-value">#<?php echo $current_user['id']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Роль</span>
                        <span class="stat-value"><?php echo $current_user['role'] == 'admin' ? 'Администратор' : 'Пользователь'; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Статус</span>
                        <span class="stat-value"><?php echo $current_user['is_active'] ? 'Активен' : 'Заблокирован'; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Дата регистрации</span>
                        <span class="stat-value"><?php echo date('d.m.Y', strtotime($current_user['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Основная информация -->
            <div class="settings-section fade-in" style="animation-delay: 0.2s;">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-user-edit"></i> Основная информация
                    </h2>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="username">Имя пользователя <span class="required">*</span></label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_user['username']); ?>" required>
                            <span class="form-help">Используется для входа в систему</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email адрес <span class="required">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                            <span class="form-help">На этот адрес будут приходить уведомления</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="full_name">Полное имя</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?>">
                            <span class="form-help">Ваше имя и фамилия</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="phone">Телефон</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"
                                   oninput="formatPhone(this)">
                            <span class="form-help">Формат: +7 (XXX) XXX-XX-XX</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn-user btn-secondary">
                            <i class="fas fa-times"></i> Отмена
                        </button>
                        <button type="submit" class="btn-user btn-primary">
                            <i class="fas fa-save"></i> Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>

            <!-- Смена пароля -->
            <div class="settings-section fade-in" style="animation-delay: 0.3s;">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-key"></i> Безопасность
                    </h2>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="current_password">Текущий пароль <span class="required">*</span></label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            <span class="form-help">Введите ваш текущий пароль</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="new_password">Новый пароль <span class="required">*</span></label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <span class="form-help">Минимум 6 символов</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Подтверждение пароля <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <span class="form-help">Повторите новый пароль</span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn-user btn-secondary">
                            <i class="fas fa-times"></i> Отмена
                        </button>
                        <button type="submit" class="btn-user btn-primary">
                            <i class="fas fa-key"></i> Сменить пароль
                        </button>
                    </div>
                </form>
            </div>

            <!-- Информация об аккаунте -->
            <div class="settings-section fade-in" style="animation-delay: 0.4s;">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i> Дополнительная информация
                    </h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Дата регистрации</label>
                        <input type="text" class="form-control" 
                               value="<?php echo date('d.m.Y H:i', strtotime($current_user['created_at'])); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Последний вход</label>
                        <input type="text" class="form-control" 
                               value="<?php echo !empty($current_user['last_login']) ? date('d.m.Y H:i', strtotime($current_user['last_login'])) : 'Никогда'; ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Последнее обновление</label>
                        <input type="text" class="form-control" 
                               value="<?php echo !empty($current_user['updated_at']) ? date('d.m.Y H:i', strtotime($current_user['updated_at'])) : 'Нет данных'; ?>" disabled>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Автоматическое скрытие сообщений
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
        
        // Форматирование телефона
        function formatPhone(input) {
            let value = input.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                if (value[0] === '7' || value[0] === '8') {
                    value = '7' + value.substring(1);
                } else if (value[0] === '9') {
                    value = '7' + value;
                } else if (value[0] !== '7') {
                    value = '7' + value;
                }
                
                // Ограничиваем длину
                value = value.substring(0, 11);
                
                // Форматируем
                let formatted = '+7';
                if (value.length > 1) {
                    formatted += ' (' + value.substring(1, 4);
                }
                if (value.length > 4) {
                    formatted += ') ' + value.substring(4, 7);
                }
                if (value.length > 7) {
                    formatted += '-' + value.substring(7, 9);
                }
                if (value.length > 9) {
                    formatted += '-' + value.substring(9, 11);
                }
                
                input.value = formatted;
            }
        }
    </script>
</body>
</html>