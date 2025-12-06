<?php
// Путь: /autopark_moto/includes/admin_sidebar.php
?>

<div class="admin-sidebar">

    <div class="sidebar-header">
        <div class="sidebar-logo">AUTO<span>PARK</span></div>
        <div class="sidebar-subtitle">ADMIN PANEL</div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?= strtoupper($_SESSION['user']['username'][0]) ?>
        </div>

        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></div>
            <div class="user-role">Администратор</div>
        </div>
    </div>

    <div class="sidebar-menu">

        <div class="menu-section">
            <div class="menu-title">Основное</div>
            <ul class="menu-items">
                <li class="menu-item">
                    <a href="/autopark_moto/admin/index.php" class="menu-link">
                        <i class="fa fa-gauge"></i> Панель
                    </a>
                </li>
            </ul>
        </div>

        <div class="menu-section">
            <div class="menu-title">Управление</div>
            <ul class="menu-items">

                <li class="menu-item">
                    <a href="/autopark_moto/admin/users.php" class="menu-link">
                        <i class="fa fa-users"></i> Пользователи
                    </a>
                </li>

                <li class="menu-item">
                    <a href="/autopark_moto/admin/motorcycles.php" class="menu-link">
                        <i class="fa fa-motorcycle"></i> Мотоциклы
                    </a>
                </li>

                <li class="menu-item">
                    <a href="/autopark_moto/admin/services.php" class="menu-link">
                        <i class="fa fa-wrench"></i> ТО / Обслуживание
                    </a>
                </li>

                <li class="menu-item">
                    <a href="/autopark_moto/admin/trips.php" class="menu-link">
                        <i class="fa fa-road"></i> Поездки
                    </a>
                </li>

                <li class="menu-item">
                    <a href="/autopark_moto/admin/notifications.php" class="menu-link">
                        <i class="fa fa-bell"></i> Уведомления
                    </a>
                </li>

                <li class="menu-item">
                    <a href="/autopark_moto/admin/templates.php" class="menu-link">
                        <i class="fa fa-file"></i> Шаблоны ТО
                    </a>
                </li>

            </ul>
        </div>

        <div class="menu-section">
            <div class="menu-title">Аккаунт</div>
            <ul class="menu-items">
                <li class="menu-item">
                    <a href="/autopark_moto/auth/logout.php" class="menu-link">
                        <i class="fa fa-right-from-bracket"></i> Выход
                    </a>
                </li>
            </ul>
        </div>

    </div>

</div>
