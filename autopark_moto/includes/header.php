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
    <title>AUTOPARK MOTO - График ТО</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Подключение jQuery для AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        /* Дополнительные стили для этой страницы */
        .maintenance-page {
            min-height: 100vh;
            padding: 40px 20px;
            background: url("/assets/img/main.jpg") center/cover no-repeat fixed;
            position: relative;
        }

        .maintenance-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0,0,0,0.8), rgba(20,0,0,0.9));
        }

        .maintenance-container {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            color: white;
        }

        .maintenance-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .maintenance-title {
            font-size: 42px;
            font-weight: 900;
            color: #d60000;
            margin-bottom: 10px;
            text-transform: uppercase;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .maintenance-subtitle {
            font-size: 18px;
            color: #ccc;
            margin-bottom: 30px;
        }

        .maintenance-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(20, 20, 20, 0.95);
            border: 1px solid #444;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .maintenance-table thead {
            background: linear-gradient(135deg, #d60000, #a00000);
        }

        .maintenance-table th {
            color: white;
            font-size: 18px;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #d60000;
        }

        .maintenance-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #333;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .maintenance-table tbody tr {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .maintenance-table tbody tr:hover {
            background: rgba(214, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .status-normal {
            color: #00cc66;
            font-weight: bold;
        }

        .status-warning {
            color: #ff9900;
            font-weight: bold;
        }

        .status-danger {
            color: #ff3333;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .badge-normal {
            background: rgba(0, 204, 102, 0.2);
            border: 1px solid #00cc66;
        }

        .badge-warning {
            background: rgba(255, 153, 0, 0.2);
            border: 1px solid #ff9900;
        }

        .badge-danger {
            background: rgba(255, 51, 51, 0.2);
            border: 1px solid #ff3333;
        }

        .table-footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: rgba(214, 0, 0, 0.2);
            padding: 10px 20px;
            border-radius: 8px;
            border-left: 4px solid #d60000;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #d60000;
        }

        .stat-label {
            font-size: 14px;
            color: #aaa;
        }

        /* Стили для модального окна */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 2000;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background: #111;
            max-width: 900px;
            margin: 50px auto;
            border-radius: 15px;
            border: 2px solid #d60000;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            transform: translateY(-30px);
            opacity: 0;
            transition: all 0.4s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            background: linear-gradient(135deg, #d60000, #a00000);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 28px;
            font-weight: bold;
            color: white;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            transition: transform 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
            color: #ddd;
        }

        .vehicle-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }

        .info-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 14px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-value {
            font-size: 18px;
            color: white;
            font-weight: 600;
        }

        .history-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 22px;
            color: #d60000;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .history-table th {
            background: #222;
            color: #d60000;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #333;
        }

        .history-table td {
            padding: 12px 15px;
            border: 1px solid #333;
            color: #ddd;
        }

        .history-table tr:nth-child(even) {
            background: rgba(255,255,255,0.03);
        }

        .history-table tr:hover {
            background: rgba(214, 0, 0, 0.1);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
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

        .no-history {
            text-align: center;
            padding: 30px;
            color: #777;
            font-style: italic;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin-top: 20px;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .maintenance-title {
                font-size: 32px;
            }
            
            .maintenance-table th,
            .maintenance-table td {
                padding: 12px 8px;
                font-size: 14px;
            }
            
            .modal-content {
                margin: 20px auto;
            }
            
            .vehicle-info {
                grid-template-columns: 1fr;
            }
            
            .modal-header {
                padding: 15px 20px;
            }
            
            .modal-title {
                font-size: 22px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 480px) {
            .maintenance-page {
                padding: 20px 10px;
            }
            
            .maintenance-title {
                font-size: 28px;
            }
            
            .maintenance-table th,
            .maintenance-table td {
                padding: 10px 6px;
                font-size: 13px;
            }
        }
    </style>
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

<main>