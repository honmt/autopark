<?php
if (!isset($_SESSION)) {
    session_start();
}

// Определяем текущую страницу для подсветки активной ссылки
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="main-header">
    <div class="header-center">
        <!-- НАЗВАНИЕ -->
        <div class="header-title">
            AUTOPARK <span>MOTO</span>
        </div>

        <!-- МЕНЮ + КНОПКА ВОЙТИ -->
        <nav class="header-nav">
            <a href="index.php" class="<?php echo ($current_page == 'index') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Главная
            </a>
            <a href="park.php" class="<?php echo ($current_page == 'park') ? 'active' : ''; ?>">
                <i class="fas fa-motorcycle"></i> Автопарк
            </a>
            <a href="maintenance.php" class="<?php echo ($current_page == 'maintenance') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> График ТО
            </a>
            <a href="about.php" class="<?php echo ($current_page == 'about') ? 'active' : ''; ?>">
                <i class="fas fa-info-circle"></i> О системе
            </a>

            <?php if (!empty($_SESSION['user'])): ?>
                <a href="auth/logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Выход (<?php echo htmlspecialchars($_SESSION['user']['username'] ?? ''); ?>)
                </a>
            <?php else: ?>
                <a href="auth/login.php" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Войти
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>