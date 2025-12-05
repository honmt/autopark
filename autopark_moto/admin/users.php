<?php
// admin/users.php - Управление пользователями

session_start();

// Проверяем, авторизован ли пользователь и является ли он администратором
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /autopark_moto/auth/login.php');
    exit();
}

require_once __DIR__ . '/../includes/db.php';
$db = getDBConnection();

// Обработка действий
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;

if ($action === 'delete' && $user_id > 0) {
    // Нельзя удалить самого себя
    if ($user_id != $_SESSION['user']['id']) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        
        header('Location: users.php?message=Пользователь удален');
        exit();
    } else {
        header('Location: users.php?error=Нельзя удалить самого себя');
        exit();
    }
}

// Получаем список пользователей
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($role_filter) {
    $query .= " AND role = :role";
    $params[':role'] = $role_filter;
}

if ($status_filter !== '') {
    $query .= " AND is_active = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подсчитываем пользователей по ролям
$stats = [
    'total' => count($users),
    'admins' => 0,
    'managers' => 0,
    'users' => 0,
    'active' => 0,
    'inactive' => 0
];

foreach ($users as $user) {
    switch ($user['role']) {
        case 'admin': $stats['admins']++; break;
        case 'manager': $stats['managers']++; break;
        case 'user': $stats['users']++; break;
    }
    if ($user['is_active']) $stats['active']++;
    else $stats['inactive']++;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - AUTOPARK MOTO</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Стили из index.php, но адаптированные для страницы пользователей */
        /* ... вставьте стили из admin/index.php, начиная от <style> до </style> ... */
        /* Или вынесите их в отдельный файл css */

        /* Дополнительные стили для этой страницы */
        .filters-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .filter-input, .filter-select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #d60000;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-filter {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-apply {
            background: #d60000;
            color: white;
        }

        .btn-apply:hover {
            background: #a00000;
            transform: translateY(-2px);
        }

        .btn-reset {
            background: #6c757d;
            color: white;
        }

        .btn-reset:hover {
            background: #545b62;
            transform: translateY(-2px);
        }

        /* Статистика пользователей */
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .user-stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .user-stat-value {
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 5px;
        }

        .user-stat-label {
            font-size: 14px;
            color: #666;
        }

        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Боковая панель -->
    <aside class="admin-sidebar">
        <!-- ... та же боковая панель, что и в index.php ... -->
        <!-- Для экономии места, вставьте боковую панель из index.php -->
        <!-- Или вынесите ее в отдельный файл -->
    </aside>

    <!-- Основной контент -->
    <div class="admin-content">
        <!-- Верхняя панель -->
        <div class="admin-topbar fade-in">
            <div>
                <h1 class="page-title">Управление пользователями</h1>
                <p class="page-subtitle">Всего пользователей: <?php echo $stats['total']; ?></p>
            </div>
            <div class="topbar-actions">
                <button class="btn-admin btn-primary" onclick="openAddUserModal()">
                    <i class="fas fa-user-plus"></i> Добавить пользователя
                </button>
            </div>
        </div>

        <!-- Статистика -->
        <div class="user-stats fade-in">
            <div class="user-stat-card">
                <div class="user-stat-value" style="color: #007bff;"><?php echo $stats['admins']; ?></div>
                <div class="user-stat-label">Администраторы</div>
            </div>
            <div class="user-stat-card">
                <div class="user-stat-value" style="color: #28a745;"><?php echo $stats['managers']; ?></div>
                <div class="user-stat-label">Менеджеры</div>
            </div>
            <div class="user-stat-card">
                <div class="user-stat-value" style="color: #6c757d;"><?php echo $stats['users']; ?></div>
                <div class="user-stat-label">Пользователи</div>
            </div>
            <div class="user-stat-card">
                <div class="user-stat-value" style="color: #28a745;"><?php echo $stats['active']; ?></div>
                <div class="user-stat-label">Активных</div>
            </div>
            <div class="user-stat-card">
                <div class="user-stat-value" style="color: #dc3545;"><?php echo $stats['inactive']; ?></div>
                <div class="user-stat-label">Неактивных</div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="filters-panel fade-in">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label for="search">Поиск</label>
                    <input type="text" id="search" name="search" class="filter-input" 
                           placeholder="Имя, email, телефон..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label for="role">Роль</label>
                    <select id="role" name="role" class="filter-select">
                        <option value="">Все роли</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                        <option value="manager" <?php echo $role_filter == 'manager' ? 'selected' : ''; ?>>Менеджер</option>
                        <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>Пользователь</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status" class="filter-select">
                        <option value="">Все статусы</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Активные</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Неактивные</option>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter btn-apply">
                        <i class="fas fa-filter"></i> Применить
                    </button>
                    <button type="button" class="btn-filter btn-reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Сбросить
                    </button>
                </div>
            </form>
        </div>

        <!-- Таблица пользователей -->
        <div class="admin-table fade-in">
            <div class="table-header">
                <h2 class="table-title">Список пользователей</h2>
                <div>
                    <button class="btn-admin btn-secondary" onclick="exportUsers()">
                        <i class="fas fa-download"></i> Экспорт
                    </button>
                </div>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Аватар</th>
                            <th>Имя пользователя</th>
                            <th>Email</th>
                            <th>Полное имя</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-users" style="font-size: 48px; margin-bottom: 20px; display: block; opacity: 0.5;"></i>
                                Пользователи не найдены
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td>
                                <div class="user-avatar" style="width: 40px; height: 40px; font-size: 16px;">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                            </td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?: 'Не указано'); ?></td>
                            <td>
                                <span class="status-badge <?php 
                                    echo $user['role'] == 'admin' ? 'status-active' : 
                                    ($user['role'] == 'manager' ? 'status-pending' : 'status-inactive'); 
                                ?>">
                                    <?php echo $user['role']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view" onclick="viewUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" 
                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            <?php echo $user['id'] == $_SESSION['user']['id'] ? 'disabled title="Нельзя удалить себя"' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Пагинация -->
        <div style="margin-top: 30px; text-align: center;">
            <div style="display: inline-flex; gap: 10px; align-items: center;">
                <button class="btn-admin btn-secondary">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span style="color: #666; font-size: 14px;">Страница 1 из 1</span>
                <button class="btn-admin btn-secondary">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Модальное окно добавления пользователя -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Добавить пользователя</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="userForm" style="margin-top: 20px;">
                <div style="display: grid; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Имя пользователя *</label>
                        <input type="text" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" 
                               required minlength="3">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email *</label>
                        <input type="email" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" 
                               required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Пароль *</label>
                        <input type="password" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" 
                               required minlength="6">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Полное имя</label>
                        <input type="text" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Роль</label>
                        <select style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="user">Пользователь</option>
                            <option value="manager">Менеджер</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" style="background: #d60000; color: white; border: none; padding: 12px 30px; 
                                border-radius: 5px; cursor: pointer; font-weight: 600; width: 100%;">
                            <i class="fas fa-save"></i> Сохранить пользователя
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Функции для работы с модальными окнами
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        function viewUser(id) {
            alert('Просмотр пользователя #' + id + '\nВ реальной системе здесь будет страница просмотра.');
        }

        function editUser(id) {
            alert('Редактирование пользователя #' + id + '\nВ реальной системе здесь будет форма редактирования.');
        }

        function deleteUser(id, username) {
            if (confirm('Вы уверены, что хотите удалить пользователя "' + username + '" (ID: #' + id + ')?')) {
                window.location.href = 'users.php?action=delete&id=' + id;
            }
        }

        function resetFilters() {
            window.location.href = 'users.php';
        }

        function exportUsers() {
            alert('Экспорт пользователей в формате CSV\nВ реальной системе здесь будет экспорт данных.');
        }

        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('addUserModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Обработка формы добавления пользователя
        document.getElementById('userForm').onsubmit = function(e) {
            e.preventDefault();
            alert('Пользователь добавлен!\nВ реальной системе здесь будет отправка данных на сервер.');
            closeModal();
        };

        // Сообщения об успехе/ошибке из URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('message')) {
            alert(urlParams.get('message'));
        }
        if (urlParams.has('error')) {
            alert('Ошибка: ' + urlParams.get('error'));
        }
    </script>
</body>
</html>