<?php
if (!isset($_SESSION)) session_start();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/assets/css/header_footer.css">
</head>

<body>

<header class="main-header">
    <div class="header-container">

        <a href="/index.php" class="logo-text">
            AUTOPARK <span>MOTO</span>
        </a>

        <nav class="nav-menu">
            <a href="/index.php">Главная</a>
            <a href="/park.php">Автопарк</a>
            <a href="/maintenance.php">График ТО</a>
            <a href="/about.php">О системе</a>

            <?php if (!empty($_SESSION['user'])): ?>
                <a href="/auth/logout.php" class="btn-logout">Выход</a>
            <?php else: ?>
                <a href="/auth/login.php" class="btn-login">Войти</a>
            <?php endif; ?>
        </nav>

    </div>
</header>
