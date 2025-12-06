<?php
// maintenance.php - ГРАФИК ТО

// Подключаем базу данных
require_once __DIR__ . '/includes/db.php';

// Функция для получения данных о мотоциклах и ТО
function getMaintenanceData() {
    $db = getDBConnection();
    
    try {
        // Проверяем существование таблиц
        $tables = $db->query("SHOW TABLES LIKE 'motorcycles'")->rowCount();
        if ($tables == 0) {
            // Если таблиц нет, возвращаем тестовые данные
            return getTestData();
        }
        
        // Получаем данные из базы
        $query = "
            SELECT 
                m.id,
                m.brand,
                m.model,
                m.year,
                m.vin,
                m.owner,
                m.phone,
                m.mileage,
                MAX(s.service_date) as last_service_date,
                MAX(s.next_service_date) as next_service_date,
                MAX(s.next_service_mileage) as next_service_mileage
            FROM motorcycles m
            LEFT JOIN motorcycle_services s ON m.id = s.motorcycle_id
            WHERE m.status = 'active'
            GROUP BY m.id
            ORDER BY m.brand, m.model
        ";
        
        $stmt = $db->query($query);
        $motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $maintenanceData = [];
        
        foreach ($motorcycles as $moto) {
            // Определяем статус
            $status = 'normal';
            
            if ($moto['next_service_date']) {
                $nextDate = new DateTime($moto['next_service_date']);
                $today = new DateTime();
                $interval = $today->diff($nextDate);
                $daysUntil = $interval->days;
                
                if ($nextDate < $today) {
                    $status = 'danger';
                } elseif ($daysUntil <= 7) {
                    $status = 'danger';
                } elseif ($daysUntil <= 30) {
                    $status = 'warning';
                }
                
                // Проверка по пробегу
                if ($moto['next_service_mileage'] && $moto['mileage'] >= $moto['next_service_mileage']) {
                    $status = 'danger';
                }
            }
            
            // Получаем историю ТО
            $historyQuery = "SELECT * FROM motorcycle_services 
                            WHERE motorcycle_id = :id 
                            ORDER BY service_date DESC";
            $historyStmt = $db->prepare($historyQuery);
            $historyStmt->bindParam(':id', $moto['id']);
            $historyStmt->execute();
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formattedHistory = [];
            foreach ($history as $item) {
                $formattedHistory[] = [
                    'date' => date('d.m.Y', strtotime($item['service_date'])),
                    'mileage' => number_format($item['mileage'], 0, '', ' ') . ' км',
                    'type' => $item['service_type'],
                    'cost' => number_format($item['cost'], 0, '', ' ') . ' ₽'
                ];
            }
            
            $maintenanceData[] = [
                'id' => $moto['id'],
                'technique' => $moto['brand'] . ' ' . $moto['model'],
                'mileage' => number_format($moto['mileage'], 0, '', ' ') . ' км',
                'last_maintenance' => $moto['last_service_date'] ? date('d.m.Y', strtotime($moto['last_service_date'])) : 'Нет данных',
                'next_maintenance' => $moto['next_service_date'] ? date('d.m.Y', strtotime($moto['next_service_date'])) : 'Не определено',
                'status' => $status,
                'details' => [
                    'model' => $moto['model'],
                    'year' => $moto['year'],
                    'vin' => $moto['vin'],
                    'owner' => $moto['owner'],
                    'phone' => $moto['phone']
                ],
                'history' => $formattedHistory
            ];
        }
        
        return $maintenanceData;
        
    } catch (PDOException $e) {
        // Если есть ошибка, возвращаем тестовые данные
        return getTestData();
    }
}

// Функция с тестовыми данными (если нет БД)
function getTestData() {
    return [
        [
            'id' => 1,
            'technique' => 'Honda CB500X',
            'mileage' => '12 300 км',
            'last_maintenance' => '10.01.2025',
            'next_maintenance' => '10.04.2025',
            'status' => 'normal',
            'details' => [
                'model' => 'CB500X',
                'year' => 2020,
                'vin' => 'JH2SC5500MK123456',
                'owner' => 'Иванов И.И.',
                'phone' => '+7 (999) 123-45-67'
            ],
            'history' => [
                ['date' => '10.01.2025', 'mileage' => '12 000 км', 'type' => 'Плановое ТО', 'cost' => '8 500 ₽'],
                ['date' => '15.10.2024', 'mileage' => '8 500 км', 'type' => 'Замена масла', 'cost' => '3 200 ₽']
            ]
        ],
        [
            'id' => 2,
            'technique' => 'Yamaha MT-07',
            'mileage' => '23 800 км',
            'last_maintenance' => '05.11.2024',
            'next_maintenance' => '05.02.2025',
            'status' => 'warning',
            'details' => [
                'model' => 'MT-07',
                'year' => 2021,
                'vin' => 'JYARN53E0MA123789',
                'owner' => 'Петров П.П.',
                'phone' => '+7 (999) 987-65-43'
            ],
            'history' => [
                ['date' => '05.11.2024', 'mileage' => '23 500 км', 'type' => 'Замена цепи', 'cost' => '12 300 ₽'],
                ['date' => '01.09.2024', 'mileage' => '20 000 км', 'type' => 'Плановое ТО', 'cost' => '9 800 ₽']
            ]
        ],
        [
            'id' => 3,
            'technique' => 'BMW GS 850',
            'mileage' => '31 100 км',
            'last_maintenance' => '20.12.2024',
            'next_maintenance' => '20.03.2025',
            'status' => 'normal',
            'details' => [
                'model' => 'GS 850',
                'year' => 2022,
                'vin' => 'WB10C5100PC123456',
                'owner' => 'Сидоров С.С.',
                'phone' => '+7 (999) 456-78-90'
            ],
            'history' => [
                ['date' => '20.12.2024', 'mileage' => '31 000 км', 'type' => 'Зимняя подготовка', 'cost' => '15 500 ₽'],
                ['date' => '10.10.2024', 'mileage' => '28 000 км', 'type' => 'Замена шин', 'cost' => '22 000 ₽']
            ]
        ],
        [
            'id' => 4,
            'technique' => 'Kawasaki Ninja 400',
            'mileage' => '8 500 км',
            'last_maintenance' => '15.12.2024',
            'next_maintenance' => '15.01.2025',
            'status' => 'danger',
            'details' => [
                'model' => 'Ninja 400',
                'year' => 2023,
                'vin' => 'JKAZXCJ13ADA12345',
                'owner' => 'Кузнецов К.К.',
                'phone' => '+7 (999) 111-22-33'
            ],
            'history' => [
                ['date' => '15.12.2024', 'mileage' => '8 200 км', 'type' => 'Первое ТО', 'cost' => '5 500 ₽']
            ]
        ],
        [
            'id' => 5,
            'technique' => 'Suzuki V-Strom 650',
            'mileage' => '42 300 км',
            'last_maintenance' => '03.01.2025',
            'next_maintenance' => '03.07.2025',
            'status' => 'normal',
            'details' => [
                'model' => 'V-Strom 650',
                'year' => 2019,
                'vin' => 'JS2CX53A0K4100001',
                'owner' => 'Васильев В.В.',
                'phone' => '+7 (999) 555-44-33'
            ],
            'history' => [
                ['date' => '03.01.2025', 'mileage' => '42 000 км', 'type' => 'Крупное ТО', 'cost' => '28 000 ₽'],
                ['date' => '15.08.2024', 'mileage' => '38 000 км', 'type' => 'Замена тормозов', 'cost' => '18 500 ₽']
            ]
        ]
    ];
}

// Получаем данные
$maintenanceData = getMaintenanceData();

// Получаем статистику
function getStatistics($data) {
    $total = count($data);
    $dangerCount = 0;
    $warningCount = 0;
    $totalMileage = 0;
    
    foreach ($data as $vehicle) {
        if ($vehicle['status'] == 'danger') $dangerCount++;
        if ($vehicle['status'] == 'warning') $warningCount++;
        
        // Извлекаем числовое значение пробега
        $mileage = preg_replace('/[^0-9]/', '', $vehicle['mileage']);
        $totalMileage += intval($mileage);
    }
    
    return [
        'total_motorcycles' => $total,
        'danger' => $dangerCount,
        'warning' => $warningCount,
        'total_mileage' => $totalMileage
    ];
}

$stats = getStatistics($maintenanceData);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>AUTOPARK MOTO - График ТО</title>
    <link rel="stylesheet" href="/autopark_moto/assets/css/style.css">
    <link rel="stylesheet" href="/autopark_moto/assets/css/maintenance.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php
// Подключаем хедер
if (!isset($_SESSION)) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

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

<!-- КОНТЕНТ СТРАНИЦЫ -->
<div class="maintenance-page">
    <div class="maintenance-overlay"></div>

    <div class="maintenance-container">
        <div class="maintenance-header">
            <h1 class="maintenance-title">График ТО</h1>
            <p class="maintenance-subtitle">Мониторинг технического обслуживания мотоциклов</p>
        </div>

        <table class="maintenance-table">
            <thead>
                <tr>
                    <th>Техника</th>
                    <th>Пробег</th>
                    <th>Предыдущее ТО</th>
                    <th>Следующее ТО</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maintenanceData as $vehicle): 
                    // Определяем класс статуса
                    $statusClass = '';
                    $badgeClass = '';
                    $statusText = '';
                    
                    switch ($vehicle['status']) {
                        case 'normal':
                            $statusClass = 'status-normal';
                            $badgeClass = 'badge-normal';
                            $statusText = 'Норма';
                            break;
                        case 'warning':
                            $statusClass = 'status-warning';
                            $badgeClass = 'badge-warning';
                            $statusText = 'Скоро ТО';
                            break;
                        case 'danger':
                            $statusClass = 'status-danger';
                            $badgeClass = 'badge-danger';
                            $statusText = 'Срочно!';
                            break;
                    }
                ?>
                <tr data-id="<?php echo $vehicle['id']; ?>">
                    <td><strong><?php echo htmlspecialchars($vehicle['technique']); ?></strong></td>
                    <td><?php echo htmlspecialchars($vehicle['mileage']); ?></td>
                    <td><?php echo htmlspecialchars($vehicle['last_maintenance']); ?></td>
                    <td class="<?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($vehicle['next_maintenance']); ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $badgeClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="table-footer">
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total_motorcycles']; ?></div>
                    <div class="stat-label">Всего единиц</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($stats['total_mileage'], 0, '', ' '); ?> км</div>
                    <div class="stat-label">Общий пробег</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['danger']; ?></div>
                    <div class="stat-label">Срочные ТО</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['warning']; ?></div>
                    <div class="stat-label">Предстоящие ТО</div>
                </div>
            </div>
            
            <div class="update-time">
                Обновлено: <?php echo date('d.m.Y H:i'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для деталей -->
<div class="modal-overlay" id="vehicleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalVehicleName">Загрузка...</h2>
            <button class="modal-close" id="closeModal">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="vehicle-info" id="vehicleDetails">
                <!-- Детали будут загружены через JavaScript -->
            </div>
            
            <div class="history-section">
                <h3 class="section-title">История ТО</h3>
                <div id="historyTable">
                    <!-- Таблица истории будет загружена через JavaScript -->
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="printVehicleInfo()">
                    <i class="fas fa-print"></i> Печать
                </button>
                <button class="btn btn-primary" onclick="editVehicle()">
                    <i class="fas fa-edit"></i> Редактировать
                </button>
                <button class="btn btn-primary" onclick="addMaintenance()">
                    <i class="fas fa-plus"></i> Добавить ТО
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Футер -->
<footer class="maintenance-footer">
    <div class="footer-container">
        <p>© <?php echo date('Y'); ?> AUTOPARK MOTO. Система учета технического обслуживания мотоциклов.</p>
        <p class="footer-text">Телефон: +7 (999) 000-00-00 | Email: info@autopark-moto.ru</p>
    </div>
</footer>

<script>
// Передаем данные из PHP в JavaScript
const maintenanceData = <?php echo json_encode($maintenanceData, JSON_UNESCAPED_UNICODE); ?>;
let currentVehicleId = null;

// Открытие модального окна при клике на строку
document.querySelectorAll('.maintenance-table tbody tr').forEach(row => {
    row.addEventListener('click', function() {
        const vehicleId = parseInt(this.getAttribute('data-id'));
        currentVehicleId = vehicleId;
        openVehicleModal(vehicleId);
    });
});

// Функция открытия модального окна
function openVehicleModal(vehicleId) {
    const vehicle = maintenanceData.find(v => v.id === vehicleId);
    if (!vehicle) return;
    
    // Обновляем заголовок
    document.getElementById('modalVehicleName').textContent = vehicle.technique;
    
    // Обновляем детали техники
    const detailsHtml = `
        <div class="info-group">
            <div class="info-label">Модель</div>
            <div class="info-value">${vehicle.details.model}</div>
        </div>
        <div class="info-group">
            <div class="info-label">Год выпуска</div>
            <div class="info-value">${vehicle.details.year}</div>
        </div>
        <div class="info-group">
            <div class="info-label">VIN номер</div>
            <div class="info-value">${vehicle.details.vin}</div>
        </div>
        <div class="info-group">
            <div class="info-label">Пробег</div>
            <div class="info-value">${vehicle.mileage}</div>
        </div>
        <div class="info-group">
            <div class="info-label">Владелец</div>
            <div class="info-value">${vehicle.details.owner}</div>
        </div>
        <div class="info-group">
            <div class="info-label">Телефон</div>
            <div class="info-value">${vehicle.details.phone}</div>
        </div>
        <div class="info-group">
            <div class="info-label">Предыдущее ТО</div>
            <div class="info-value">${vehicle.last_maintenance}</div>
        </div>
        <div class="info-group">
            <div class="info-label">Следующее ТО</div>
            <div class="info-value">${vehicle.next_maintenance}</div>
        </div>
    `;
    
    document.getElementById('vehicleDetails').innerHTML = detailsHtml;
    
    // Обновляем историю ТО
    let historyHtml;
    if (vehicle.history && vehicle.history.length > 0) {
        historyHtml = `
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Пробег</th>
                        <th>Вид работ</th>
                        <th>Стоимость</th>
                    </tr>
                </thead>
                <tbody>
                    ${vehicle.history.map(item => `
                        <tr>
                            <td>${item.date}</td>
                            <td>${item.mileage}</td>
                            <td>${item.type}</td>
                            <td>${item.cost}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        historyHtml = '<div class="no-history">История технического обслуживания отсутствует</div>';
    }
    
    document.getElementById('historyTable').innerHTML = historyHtml;
    
    // Показываем модальное окно
    const modal = document.getElementById('vehicleModal');
    modal.style.display = 'block';
    setTimeout(() => {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }, 10);
}

// Закрытие модального окна
document.getElementById('closeModal').addEventListener('click', closeModal);
document.getElementById('vehicleModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Закрытие по клавише Esc
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

function closeModal() {
    const modal = document.getElementById('vehicleModal');
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }, 300);
}

// Вспомогательные функции
function printVehicleInfo() {
    window.print();
}

function editVehicle() {
    if (currentVehicleId) {
        alert('Редактирование техники с ID: ' + currentVehicleId);
        // window.location.href = 'edit.php?id=' + currentVehicleId;
    }
}

function addMaintenance() {
    if (currentVehicleId) {
        alert('Добавление ТО для техники с ID: ' + currentVehicleId);
        // window.location.href = 'add_maintenance.php?vehicle_id=' + currentVehicleId;
    }
}

// Подсвечиваем строки со срочным ТО
document.addEventListener('DOMContentLoaded', function() {
    const dangerRows = document.querySelectorAll('.status-danger');
    dangerRows.forEach(cell => {
        const row = cell.closest('tr');
        row.style.boxShadow = '0 0 15px rgba(255, 0, 0, 0.3)';
    });
    
    // Автообновление времени
    function updateTime() {
        const timeElement = document.querySelector('.update-time');
        if (timeElement) {
            const now = new Date();
            const timeString = now.toLocaleDateString('ru-RU') + ' ' + now.toLocaleTimeString('ru-RU');
            timeElement.innerHTML = 'Обновлено: ' + timeString;
        }
    }
    
    setInterval(updateTime, 60000);
});
</script>

</body>
</html>