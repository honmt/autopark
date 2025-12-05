<?php if (!isset($_SESSION)) session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="main-header">
    <div class="header-top">
        <div class="header-logo">
            <img src="/assets/img/logo.png" class="logo" alt="Logo">
        </div>
        <div class="header-auth">
            <?php if (!empty($_SESSION['user'])): ?>
                <a href="/auth/logout.php" class="btn-logout">Выход</a>
            <?php else: ?>
                <a href="/auth/login.php" class="btn-login">Войти</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="header-center">
        <div class="header-title">
            AUTOPARK <span>MOTO</span>
        </div>
        <nav class="header-nav">
            <a href="/index.php">Главная</a>
            <a href="/park.php">Автопарк</a>
            <a href="/maintenance.php">График ТО</a>
            <a href="/about.php">О системе</a>
        </nav>
    </div>
</header>