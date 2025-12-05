<?php
// data.php - Массив с данными для таблицы ТО
$maintenanceData = [
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
            ['date' => '15.10.2024', 'mileage' => '8 500 км', 'type' => 'Замена масла', 'cost' => '3 200 ₽'],
            ['date' => '20.07.2024', 'mileage' => '5 000 км', 'type' => 'Диагностика', 'cost' => '2 100 ₽']
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
?>