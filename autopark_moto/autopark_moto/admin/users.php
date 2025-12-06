<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: /autopark_moto/auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Получение всех пользователей
$users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Пользователи — Admin Panel</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .admin-content {
            margin-left: 250px;
            padding: 30px;
        }
        .admin-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
        }
        .table-wrapper {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f3f3f3;
            text-align: left;
        }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: #fff;
            font-size: 14px;
        }
        .btn-edit { background: #ffaa00; }
        .btn-delete { background: #d60000; }
        .btn-add {
            background: #009944;
            margin-bottom: 15px;
            display: inline-block;
        }
    </style>
</head>
<body>

<?php include __DIR__ . "/admin_sidebar.php"; ?>

<div class="admin-content">
    <div class="admin-title">Пользователи</div>

    <a href="users_add.php" class="btn btn-add"><i class="fa fa-plus"></i> Добавить пользователя</a>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>Email</th>
                    <th>Имя</th>
                    <th>Роль</th>
                    <th>Телефон</th>
                    <th>Активен</th>
                    <th>Последний вход</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><b><?= $u['role'] ?></b></td>
                    <td><?= $u['phone'] ?></td>
                    <td><?= $u['is_active'] ? "Да" : "Нет" ?></td>
                    <td><?= $u['last_login'] ?></td>
                    <td>
                        <a href="users_edit.php?id=<?= $u['id'] ?>" class="btn btn-edit">Изменить</a>
                        <a href="users_delete.php?id=<?= $u['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить пользователя?')">Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
