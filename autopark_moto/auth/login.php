<?php
// auth/login.php

session_start();
require_once __DIR__ . '/../includes/db.php';

// Если пользователь уже авторизован, перенаправляем на главную
if (isset($_SESSION['user'])) {
    header('Location: /autopark_moto/index.php');
    exit();
}

// Инициализируем переменные
$error_message = '';
$success_message = '';
$active_tab = 'login'; // По умолчанию активна вкладка входа

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = 'Пожалуйста, заполните все поля';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_active']) {
                    // Обновляем время последнего входа
                    $update_stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                    $update_stmt->bindParam(':id', $user['id']);
                    $update_stmt->execute();
                    
                    // Сохраняем пользователя в сессии
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role'],
                        'avatar' => $user['avatar']
                    ];
                    
                    // Перенаправляем на главную
                    header('Location: /autopark_moto/index.php');
                    exit();
                } else {
                    $error_message = 'Ваш аккаунт заблокирован. Обратитесь к администратору.';
                }
            } else {
                $error_message = 'Неверное имя пользователя или пароль';
            }
        } catch (PDOException $e) {
            $error_message = 'Ошибка подключения к базе данных';
        }
    }
}

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $active_tab = 'register'; // Переключаем на вкладку регистрации
    
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    $full_name = trim($_POST['reg_full_name']);
    $phone = trim($_POST['reg_phone']);
    
    // Валидация
    $errors = [];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = 'Все обязательные поля должны быть заполнены';
    }
    
    if (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Имя пользователя должно быть от 3 до 50 символов';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email адрес';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Пароли не совпадают';
    }
    
    if (empty($errors)) {
        try {
            $db = getDBConnection();
            
            // Проверяем, не занят ли username
            $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Это имя пользователя уже занято';
            }
            
            // Проверяем, не занят ли email
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Этот email уже зарегистрирован';
            }
            
            // Если ошибок нет, создаем пользователя
            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, phone, role) 
                                     VALUES (:username, :email, :password, :full_name, :phone, 'user')");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':phone', $phone);
                
                if ($stmt->execute()) {
                    $success_message = 'Регистрация успешна! Теперь вы можете войти в систему.';
                    $active_tab = 'login'; // Переключаем на вкладку входа после успешной регистрации
                } else {
                    $errors[] = 'Ошибка при регистрации. Попробуйте позже.';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Ошибка подключения к базе данных';
        }
    }
    
    // Если есть ошибки, показываем их
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AUTOPARK MOTO - Вход и регистрация</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили для страницы авторизации */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow-x: hidden;
            padding-top: 80px; /* Отступ для фиксированного хедера */
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('/autopark_moto/assets/img/main.jpg') center/cover no-repeat fixed;
            opacity: 0.1;
            z-index: -1;
        }

        /* Фиксированный хедер */
        .main-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 3px solid #d60000;
        }

        .header-center {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .header-title {
            font-size: 28px;
            font-weight: 900;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header-title span {
            color: #d60000;
        }

        .header-nav {
            display: flex;
            gap: 25px;
            align-items: center;
            flex-wrap: wrap;
        }

        .header-nav a {
            color: #ddd;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            padding: 10px 0;
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-nav a:hover {
            color: #d60000;
        }

        .header-nav a.active {
            color: #d60000;
        }

        .header-nav a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: #d60000;
            border-radius: 3px;
        }

        .header-nav i {
            font-size: 18px;
        }

        .btn-login, .btn-logout {
            background: #d60000;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-login:hover, .btn-logout:hover {
            background: #ff3333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(214, 0, 0, 0.4);
        }

        /* Основной контент с формами */
        .auth-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 80px);
            padding: 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 500px;
            background: rgba(30, 30, 30, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            border: 1px solid #444;
        }

        /* Хедер формы */
        .auth-header {
            background: linear-gradient(135deg, #d60000, #a00000);
            padding: 40px 30px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .auth-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: #d60000;
            border-radius: 50%;
            border: 5px solid #1a1a1a;
        }

        .auth-logo {
            font-size: 36px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .auth-logo span {
            color: #ffcccc;
        }

        .auth-subtitle {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 300;
        }

        /* Вкладки */
        .auth-tabs {
            display: flex;
            background: #222;
            border-bottom: 2px solid #d60000;
        }

        .auth-tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            color: #aaa;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            background: none;
            position: relative;
            overflow: hidden;
        }

        .auth-tab:hover {
            color: white;
            background: rgba(214, 0, 0, 0.1);
        }

        .auth-tab.active {
            color: white;
            background: rgba(214, 0, 0, 0.2);
        }

        .auth-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #d60000;
        }

        .auth-tab i {
            margin-right: 10px;
            font-size: 20px;
        }

        /* Контент форм */
        .auth-content {
            padding: 40px;
        }

        .auth-form {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .auth-form.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Сообщения */
        .auth-message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-message {
            background: rgba(255, 51, 51, 0.1);
            border: 1px solid rgba(255, 51, 51, 0.3);
            color: #ff6b6b;
        }

        .success-message {
            background: rgba(0, 204, 102, 0.1);
            border: 1px solid rgba(0, 204, 102, 0.3);
            color: #00cc66;
        }

        /* Поля формы */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ccc;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group .required::after {
            content: ' *';
            color: #d60000;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid #444;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #d60000;
            box-shadow: 0 0 0 3px rgba(214, 0, 0, 0.1);
        }

        .form-control:focus + i {
            color: #d60000;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #d60000;
        }

        /* Кнопка отправки */
        .auth-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #d60000, #a00000);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .auth-submit:hover {
            background: linear-gradient(135deg, #ff3333, #d60000);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(214, 0, 0, 0.3);
        }

        .auth-submit:active {
            transform: translateY(0);
        }

        /* Дополнительные ссылки */
        .auth-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #444;
        }

        .auth-link {
            color: #aaa;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
            display: inline-block;
            margin: 0 10px;
        }

        .auth-link:hover {
            color: #d60000;
        }

        .auth-link i {
            margin-right: 5px;
        }

        /* Социальные кнопки */
        .social-auth {
            margin-top: 30px;
        }

        .social-title {
            text-align: center;
            color: #aaa;
            margin-bottom: 20px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }

        .social-title::before,
        .social-title::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: #444;
        }

        .social-title::before {
            left: 0;
        }

        .social-title::after {
            right: 0;
        }

        .social-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .social-btn {
            padding: 15px;
            border: 2px solid #444;
            border-radius: 10px;
            background: transparent;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            border-color: #d60000;
        }

        .btn-google { color: #DB4437; }
        .btn-facebook { color: #4267B2; }
        .btn-github { color: #333; }

        /* Информация о регистрации */
        .registration-info {
            margin-top: 25px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .info-text {
            color: #aaa;
            font-size: 13px;
            line-height: 1.5;
        }

        .info-link {
            color: #d60000;
            text-decoration: none;
        }

        .info-link:hover {
            text-decoration: underline;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .header-center {
                flex-direction: column;
                gap: 15px;
            }
            
            .header-nav {
                gap: 15px;
            }
            
            .auth-container {
                max-width: 100%;
                margin: 20px;
            }
            
            .auth-content {
                padding: 30px 20px;
            }
            
            .auth-header {
                padding: 30px 20px 20px;
            }
            
            .auth-logo {
                font-size: 32px;
            }
            
            .social-buttons {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 60px 10px 10px;
            }
            
            .auth-tabs {
                flex-direction: column;
            }
            
            .auth-tab {
                padding: 15px;
            }
            
            .auth-header {
                padding: 25px 15px 15px;
            }
            
            .auth-logo {
                font-size: 28px;
            }
        }

        /* Дополнительные стили для уведомлений */
        .password-requirements {
            margin-top: 8px;
            font-size: 12px;
            color: #777;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
        }

        .requirement i {
            font-size: 12px;
        }

        .requirement.valid {
            color: #00cc66;
        }

        .requirement.invalid {
            color: #ff3333;
        }

        .password-match {
            margin-top: 8px;
            font-size: 12px;
        }

        /* Анимация загрузки */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Фиксированный хедер -->
    <header class="main-header">
        <div class="header-center">
            <div class="header-title">
                AUTOPARK <span>MOTO</span>
            </div>

            <nav class="header-nav">
                <a href="/autopark_moto/index.php">
                    <i class="fas fa-home"></i> Главная
                </a>
                <a href="/autopark_moto/park.php">
                    <i class="fas fa-motorcycle"></i> Автопарк
                </a>
                <a href="/autopark_moto/maintenance.php">
                    <i class="fas fa-calendar-alt"></i> График ТО
                </a>
                <a href="/autopark_moto/about.php">
                    <i class="fas fa-info-circle"></i> О системе
                </a>
                
                <!-- На странице входа кнопка входа не нужна, показываем только ссылки -->
            </nav>
        </div>
    </header>

    <!-- Основной контент с формами -->
    <div class="auth-wrapper">
        <div class="auth-container">
            <!-- Хедер формы -->
            <div class="auth-header">
                <div class="auth-logo">AUTOPARK <span>MOTO</span></div>
                <div class="auth-subtitle">Система учета технического обслуживания</div>
            </div>

            <!-- Вкладки -->
            <div class="auth-tabs">
                <button class="auth-tab <?php echo $active_tab == 'login' ? 'active' : ''; ?>" data-tab="login">
                    <i class="fas fa-sign-in-alt"></i> Вход
                </button>
                <button class="auth-tab <?php echo $active_tab == 'register' ? 'active' : ''; ?>" data-tab="register">
                    <i class="fas fa-user-plus"></i> Регистрация
                </button>
            </div>

            <!-- Сообщения об ошибках/успехе -->
            <?php if ($error_message): ?>
                <div class="auth-message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="auth-message success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Контент форм -->
            <div class="auth-content">
                <!-- Форма входа -->
                <form method="POST" action="" class="auth-form <?php echo $active_tab == 'login' ? 'active' : ''; ?>" id="loginForm">
                    <input type="hidden" name="login" value="1">
                    
                    <div class="form-group">
                        <label for="username" class="required">Логин или Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-control" 
                                   placeholder="Введите ваш логин или email"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="required">Пароль</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   placeholder="Введите ваш пароль"
                                   required>
                            <button type="button" class="password-toggle" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit">
                        <i class="fas fa-sign-in-alt"></i> Войти в систему
                    </button>

                    <div class="auth-links">
                        <a href="#" class="auth-link" id="forgotPassword">
                            <i class="fas fa-key"></i> Забыли пароль?
                        </a>
                        <a href="#" class="auth-link" onclick="switchTab('register')">
                            <i class="fas fa-user-plus"></i> Создать аккаунт
                        </a>
                    </div>

                    <!-- Социальные кнопки (опционально) -->
                    <div class="social-auth">
                        <div class="social-title">Или войдите через</div>
                        <div class="social-buttons">
                            <button type="button" class="social-btn btn-google">
                                <i class="fab fa-google"></i> Google
                            </button>
                            <button type="button" class="social-btn btn-facebook">
                                <i class="fab fa-facebook-f"></i> Facebook
                            </button>
                            <button type="button" class="social-btn btn-github">
                                <i class="fab fa-github"></i> GitHub
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Форма регистрации -->
                <form method="POST" action="" class="auth-form <?php echo $active_tab == 'register' ? 'active' : ''; ?>" id="registerForm">
                    <input type="hidden" name="register" value="1">
                    
                    <div class="form-group">
                        <label for="reg_username" class="required">Имя пользователя</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user-tag"></i>
                            <input type="text" 
                                   id="reg_username" 
                                   name="reg_username" 
                                   class="form-control" 
                                   placeholder="Придумайте логин (мин. 3 символа)"
                                   value="<?php echo isset($_POST['reg_username']) ? htmlspecialchars($_POST['reg_username']) : ''; ?>"
                                   required
                                   minlength="3"
                                   maxlength="50">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg_email" class="required">Email адрес</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   id="reg_email" 
                                   name="reg_email" 
                                   class="form-control" 
                                   placeholder="Введите ваш email"
                                   value="<?php echo isset($_POST['reg_email']) ? htmlspecialchars($_POST['reg_email']) : ''; ?>"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg_full_name">Полное имя</label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card"></i>
                            <input type="text" 
                                   id="reg_full_name" 
                                   name="reg_full_name" 
                                   class="form-control" 
                                   placeholder="Иванов Иван Иванович"
                                   value="<?php echo isset($_POST['reg_full_name']) ? htmlspecialchars($_POST['reg_full_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg_phone">Телефон</label>
                        <div class="input-with-icon">
                            <i class="fas fa-phone"></i>
                            <input type="tel" 
                                   id="reg_phone" 
                                   name="reg_phone" 
                                   class="form-control" 
                                   placeholder="+7 (999) 123-45-67"
                                   value="<?php echo isset($_POST['reg_phone']) ? htmlspecialchars($_POST['reg_phone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg_password" class="required">Пароль</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" 
                                   id="reg_password" 
                                   name="reg_password" 
                                   class="form-control" 
                                   placeholder="Придумайте пароль (мин. 6 символов)"
                                   required
                                   minlength="6">
                            <button type="button" class="password-toggle" data-target="reg_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements" id="passwordRequirements">
                            <div class="requirement invalid" id="reqLength">
                                <i class="fas fa-times-circle"></i> Минимум 6 символов
                            </div>
                            <div class="requirement invalid" id="reqUppercase">
                                <i class="fas fa-times-circle"></i> Заглавная буква
                            </div>
                            <div class="requirement invalid" id="reqNumber">
                                <i class="fas fa-times-circle"></i> Хотя бы одна цифра
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg_confirm_password" class="required">Подтверждение пароля</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" 
                                   id="reg_confirm_password" 
                                   name="reg_confirm_password" 
                                   class="form-control" 
                                   placeholder="Повторите пароль"
                                   required>
                            <button type="button" class="password-toggle" data-target="reg_confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-match" id="passwordMatch">
                            <span id="matchText" style="color: #777; font-size: 12px;"></span>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit" id="registerSubmit">
                        <i class="fas fa-user-plus"></i> Зарегистрироваться
                    </button>

                    <div class="auth-links">
                        <a href="#" class="auth-link" onclick="switchTab('login')">
                            <i class="fas fa-arrow-left"></i> Вернуться к входу
                        </a>
                        <a href="#" class="auth-link" id="helpLink">
                            <i class="fas fa-question-circle"></i> Помощь
                        </a>
                    </div>

                    <!-- Информация о регистрации -->
                    <div class="registration-info">
                        <p class="info-text">
                            <i class="fas fa-info-circle" style="color: #d60000;"></i>
                            Регистрируясь, вы соглашаетесь с 
                            <a href="/autopark_moto/about.php?page=terms" class="info-link">Условиями использования</a> 
                            и 
                            <a href="/autopark_moto/about.php?page=privacy" class="info-link">Политикой конфиденциальности</a>.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Переключение вкладок
        function switchTab(tabName) {
            // Переключаем активную вкладку
            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.tab === tabName);
            });
            
            // Переключаем активную форму
            document.querySelectorAll('.auth-form').forEach(form => {
                form.classList.toggle('active', form.id === tabName + 'Form');
            });
            
            // Сбрасываем ошибки
            const errorMessage = document.querySelector('.error-message');
            const successMessage = document.querySelector('.success-message');
            if (errorMessage) errorMessage.remove();
            if (successMessage) successMessage.remove();
            
            // Автофокус на первом поле активной формы
            setTimeout(() => {
                const activeForm = document.querySelector('.auth-form.active');
                if (activeForm) {
                    const firstInput = activeForm.querySelector('input:not([type="hidden"])');
                    if (firstInput) firstInput.focus();
                }
            }, 300);
        }

        // Инициализация переключения вкладок
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', () => switchTab(tab.dataset.tab));
        });

        // Показ/скрытие пароля
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Валидация пароля при регистрации
        const passwordInput = document.getElementById('reg_password');
        const confirmInput = document.getElementById('reg_confirm_password');
        const submitButton = document.getElementById('registerSubmit');

        function validatePassword() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            // Проверка требований к паролю
            const hasMinLength = password.length >= 6;
            const hasUppercase = /[A-ZА-Я]/.test(password);
            const hasNumber = /\d/.test(password);
            
            // Обновляем иконки требований
            document.getElementById('reqLength').classList.toggle('valid', hasMinLength);
            document.getElementById('reqLength').classList.toggle('invalid', !hasMinLength);
            document.getElementById('reqLength').querySelector('i').className = hasMinLength ? 
                'fas fa-check-circle' : 'fas fa-times-circle';
            
            document.getElementById('reqUppercase').classList.toggle('valid', hasUppercase);
            document.getElementById('reqUppercase').classList.toggle('invalid', !hasUppercase);
            document.getElementById('reqUppercase').querySelector('i').className = hasUppercase ? 
                'fas fa-check-circle' : 'fas fa-times-circle';
            
            document.getElementById('reqNumber').classList.toggle('valid', hasNumber);
            document.getElementById('reqNumber').classList.toggle('invalid', !hasNumber);
            document.getElementById('reqNumber').querySelector('i').className = hasNumber ? 
                'fas fa-check-circle' : 'fas fa-times-circle';
            
            // Проверка совпадения паролей
            const matchText = document.getElementById('matchText');
            if (confirm) {
                if (password === confirm) {
                    matchText.textContent = '✓ Пароли совпадают';
                    matchText.style.color = '#00cc66';
                } else {
                    matchText.textContent = '✗ Пароли не совпадают';
                    matchText.style.color = '#ff3333';
                }
            } else {
                matchText.textContent = '';
            }
            
            // Активация/деактивация кнопки отправки
            const isPasswordValid = hasMinLength && hasUppercase && hasNumber;
            const isPasswordMatch = password === confirm;
            
            submitButton.disabled = !(isPasswordValid && isPasswordMatch);
        }

        // Слушатели событий для валидации
        if (passwordInput && confirmInput) {
            passwordInput.addEventListener('input', validatePassword);
            confirmInput.addEventListener('input', validatePassword);
            
            // Инициализируем валидацию при загрузке
            validatePassword();
        }

        // Валидация формы входа
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    showMessage('Пожалуйста, заполните все поля', 'error');
                }
            });
        }

        // Функция показа сообщений
        function showMessage(text, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `auth-message ${type}-message`;
            messageDiv.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ${text}`;
            
            const container = document.querySelector('.auth-content');
            container.insertBefore(messageDiv, container.firstChild);
            
            // Автоматическое скрытие через 5 секунд
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(-10px)';
                setTimeout(() => messageDiv.remove(), 300);
            }, 5000);
        }

        // Социальные кнопки (заглушки)
        document.querySelectorAll('.social-btn').forEach(button => {
            button.addEventListener('click', function() {
                const provider = this.classList.contains('btn-google') ? 'Google' :
                               this.classList.contains('btn-facebook') ? 'Facebook' : 'GitHub';
                showMessage(`Вход через ${provider} в разработке`, 'info');
            });
        });

        // Забыли пароль
        document.getElementById('forgotPassword').addEventListener('click', function(e) {
            e.preventDefault();
            showMessage('Функция восстановления пароля в разработке', 'info');
        });

        // Помощь
        document.getElementById('helpLink').addEventListener('click', function(e) {
            e.preventDefault();
            showMessage('Раздел помощи в разработке', 'info');
        });

        // Анимация при загрузке
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('.auth-submit');
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // Через 3 секунды снимаем блокировку (на случай если форма не отправилась)
                setTimeout(() => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                }, 3000);
            });
        });

        // Автофокус на первом поле формы при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            const activeForm = document.querySelector('.auth-form.active');
            if (activeForm) {
                const firstInput = activeForm.querySelector('input:not([type="hidden"])');
                if (firstInput) firstInput.focus();
            }
        });

        // Добавляем эффект наведения на ссылки хедера
        document.querySelectorAll('.header-nav a').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            link.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Анимация появления контейнера авторизации
        document.addEventListener('DOMContentLoaded', function() {
            const authContainer = document.querySelector('.auth-container');
            authContainer.style.opacity = '0';
            authContainer.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                authContainer.style.transition = 'all 0.5s ease';
                authContainer.style.opacity = '1';
                authContainer.style.transform = 'translateY(0)';
            }, 300);
        });
    </script>
</body>
</html>