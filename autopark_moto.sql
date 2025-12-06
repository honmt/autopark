-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Дек 06 2025 г., 21:42
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `autopark_moto`
--

-- --------------------------------------------------------

--
-- Структура таблицы `motorcycles`
--

CREATE TABLE `motorcycles` (
  `id` int(11) NOT NULL,
  `plate` varchar(30) NOT NULL,
  `model` varchar(150) NOT NULL,
  `make` varchar(150) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `odometer` double DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `mileage` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `motorcycles`
--

INSERT INTO `motorcycles` (`id`, `plate`, `model`, `make`, `year`, `odometer`, `notes`, `created_at`, `mileage`) VALUES
(1, 'A123BC77', 'CB500X', 'Honda', '2019', 12300, 'В отличном состоянии, недавно заменили цепь', '2025-12-06 12:34:10', 350),
(2, 'B234CD78', 'MT-07', 'Yamaha', '2020', 23800, 'Кастомизированная выхлопная система', '2025-12-06 12:34:10', 420),
(3, 'C345DE79', 'F850GS', 'BMW', '2021', 31100, 'Полный комплект защиты, боковые кофры', '2025-12-06 12:34:10', 520),
(4, 'D456EF80', 'Ninja 650', 'Kawasaki', '2022', 8700, 'Новый, всего 3 месяца эксплуатации', '2025-12-06 12:34:10', 280),
(5, 'E567FG81', 'V-Strom 650', 'Suzuki', '2018', 45200, 'Требуется замена тормозных колодок', '2025-12-06 12:34:10', 380),
(6, 'F678GH82', 'Street Triple', 'Triumph', '2021', 15600, 'Премиум комплектация, квикшифтер', '2025-12-06 12:34:10', 310),
(7, 'G789HI83', 'Duke 390', 'KTM', '2022', 5200, 'Городской мотоцикл для начинающих', '2025-12-06 12:34:10', 190),
(8, 'H890JK84', 'Rebel 500', 'Honda', '2020', 9800, 'Классический круизер в идеальном состоянии', '2025-12-06 12:34:10', 240),
(9, 'I901KL85', 'R1250GS', 'BMW', '2021', 28700, 'Adventure пакет, навигация', '2025-12-06 12:34:10', 610),
(10, 'J012LM86', 'Z900', 'Kawasaki', '2022', 11200, 'Спортивный характер, мощный двигатель', '2025-12-06 12:34:10', 330),
(11, 'S6666S', 'R6', 'Ducati', '2025', 0, '', '2025-12-06 20:34:11', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `motorcycle_services`
--

CREATE TABLE `motorcycle_services` (
  `id` int(11) NOT NULL,
  `motorcycle_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `last_service_date` date DEFAULT NULL,
  `last_service_odometer` double DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `next_service_mileage` double DEFAULT NULL,
  `status` enum('upcoming','overdue','done') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cost` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `motorcycle_services`
--

INSERT INTO `motorcycle_services` (`id`, `motorcycle_id`, `template_id`, `last_service_date`, `last_service_odometer`, `next_service_date`, `next_service_mileage`, `status`, `created_at`, `cost`) VALUES
(1, 1, 1, '2025-01-10', 11800, '2025-07-10', 16800, '', '2025-12-06 12:34:10', 3500.00),
(2, 1, 3, '2025-03-15', 12100, '2025-04-15', 13100, 'upcoming', '2025-12-06 12:34:10', 500.00),
(3, 2, 1, '2024-11-05', 22800, '2025-05-05', 27800, 'overdue', '2025-12-06 12:34:10', 3200.00),
(4, 2, 2, '2024-08-20', 20000, '2025-08-20', 30000, 'upcoming', '2025-12-06 12:34:10', 1500.00),
(5, 3, 1, '2024-12-20', 30100, '2025-06-20', 35100, 'upcoming', '2025-12-06 12:34:10', 4200.00),
(6, 3, 4, '2024-06-10', 25000, '2026-06-10', 45000, 'upcoming', '2025-12-06 12:34:10', 2800.00),
(7, 4, 1, '2025-02-28', 8500, '2025-08-28', 13500, 'upcoming', '2025-12-06 12:34:10', 3000.00),
(8, 5, 1, '2024-10-15', 41000, '2025-04-15', 46000, 'overdue', '2025-12-06 12:34:10', 3100.00),
(9, 5, 6, '2024-10-15', 41000, '2025-04-15', 46000, 'overdue', '2025-12-06 12:34:10', 2500.00),
(10, 6, 1, '2025-01-30', 15000, '2025-07-30', 20000, '', '2025-12-06 12:34:10', 3800.00);

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `motorcycle_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `motorcycle_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 2, 'Требуется обслуживание', 'Мотоцикл Yamaha MT-07 требует замены масла. Следующее обслуживание: 05.05.2025', 1, '2025-12-06 12:34:10'),
(2, 1, 5, 'Просрочено обслуживание', 'Мотоцикл Suzuki V-Strom 650 имеет просроченное ТО. Необходимо срочно провести обслуживание.', 1, '2025-12-06 12:34:10'),
(3, 1, 1, 'Предстоящее обслуживание', 'Мотоцикл Honda CB500X скоро требует обслуживания цепи. Следующая дата: 15.04.2025', 1, '2025-12-06 12:34:10'),
(4, 1, 3, 'Напоминание о ТО', 'Мотоцикл BMW F850GS приближается к плановому ТО. Следующая дата: 20.06.2025', 1, '2025-12-06 12:34:10'),
(5, 1, NULL, 'Добро пожаловать', 'Добро пожаловать в систему управления мотоциклами AUTOPARK MOTO!', 1, '2025-12-06 12:34:10'),
(6, 1, 4, 'Поздравляем с покупкой', 'Ваш новый мотоцикл Kawasaki Ninja 650 успешно добавлен в систему!', 1, '2025-12-06 12:34:10'),
(7, 1, 2, 'Пробег достиг отметки', 'Мотоцикл Yamaha MT-07 преодолел 23 800 км!', 1, '2025-12-06 12:34:10'),
(8, 1, 1, 'Новая поездка добавлена', 'Зарегистрирована новая поездка на Honda CB500X протяженностью 50 км.', 1, '2025-12-06 12:34:10'),
(9, 1, NULL, 'Обновление системы', 'Система была обновлена до версии 2.1. Добавлены новые функции аналитики.', 1, '2025-12-06 12:34:10'),
(10, 1, 5, 'Внимание: требуется замена тормозов', 'Мотоцикл Suzuki V-Strom 650 требует замены тормозных колодок.', 1, '2025-12-06 12:34:10');

-- --------------------------------------------------------

--
-- Структура таблицы `service_templates`
--

CREATE TABLE `service_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `interval_km` int(11) DEFAULT NULL,
  `interval_days` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `service_templates`
--

INSERT INTO `service_templates` (`id`, `name`, `interval_km`, `interval_days`, `notes`) VALUES
(1, 'Замена масла и фильтра', 5000, 180, 'Стандартное ТО: замена моторного масла и масляного фильтра'),
(2, 'Замена воздушного фильтра', 10000, 365, 'Очистка или замена воздушного фильтра'),
(3, 'Регулировка цепи', 1000, 30, 'Регулировка натяжения и смазка цепи'),
(4, 'Замена тормозной жидкости', 20000, 730, 'Полная замена тормозной жидкости'),
(5, 'Замена свечей зажигания', 15000, 730, 'Замена свечей зажигания'),
(6, 'Проверка тормозных колодок', 5000, 180, 'Диагностика и замена при необходимости'),
(7, 'Замена шин', 20000, 1095, 'Замена передней и задней шин'),
(8, 'Регламентное ТО', 10000, 365, 'Полное техническое обслуживание'),
(9, 'Замена жидкости охлаждения', 30000, 1460, 'Замена антифриза'),
(10, 'Диагностика двигателя', 5000, 180, 'Компьютерная диагностика двигателя');

-- --------------------------------------------------------

--
-- Структура таблицы `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `setting_type` varchar(20) DEFAULT 'text',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'AUTOPARK MOTO', 'general', 'text', 'Название системы', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(2, 'site_description', 'Система управления мотоциклами', 'general', 'text', 'Описание системы', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(3, 'admin_email', 'admin@autopark-moto.ru', 'general', 'email', 'Email администратора', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(4, 'timezone', 'Europe/Moscow', 'general', 'select', 'Часовой пояс', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(5, 'date_format', 'd.m.Y', 'general', 'select', 'Формат даты', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(6, 'items_per_page', '20', 'general', 'number', 'Элементов на странице', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(7, 'notify_overdue', '1', 'notifications', 'checkbox', 'Уведомлять о просроченном ТО', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(8, 'notify_upcoming', '1', 'notifications', 'checkbox', 'Уведомлять о предстоящем ТО', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(9, 'notify_new_trip', '1', 'notifications', 'checkbox', 'Уведомлять о новых поездках', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(10, 'notify_new_user', '1', 'notifications', 'checkbox', 'Уведомлять о новых пользователях', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(11, 'email_notifications', '1', 'notifications', 'checkbox', 'Email уведомления', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(12, 'push_notifications', '1', 'notifications', 'checkbox', 'Push уведомления', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(13, 'login_attempts', '5', 'security', 'number', 'Максимум попыток входа', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(14, 'session_timeout', '30', 'security', 'number', 'Таймаут сессии (минуты)', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(15, 'password_min_length', '8', 'security', 'number', 'Минимальная длина пароля', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(16, 'password_complexity', '1', 'security', 'checkbox', 'Требовать сложный пароль', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(17, 'two_factor_auth', '0', 'security', 'checkbox', 'Двухфакторная аутентификация', '2025-12-06 13:03:48', '2025-12-06 13:03:48'),
(18, 'ip_whitelist', '', 'security', 'textarea', 'Белый список IP (по одному на строку)', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(19, 'default_service_interval', '5000', 'maintenance', 'number', 'Интервал ТО по умолчанию (км)', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(20, 'default_service_days', '180', 'maintenance', 'number', 'Интервал ТО по умолчанию (дни)', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(21, 'notify_before_days', '7', 'maintenance', 'number', 'Уведомлять за дней до ТО', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(22, 'auto_generate_services', '1', 'maintenance', 'checkbox', 'Автоматически создавать ТО', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(23, 'service_cost_tracking', '1', 'maintenance', 'checkbox', 'Отслеживать стоимость ТО', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(24, 'google_maps_api', '', 'integrations', 'text', 'Google Maps API ключ', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(25, 'smtp_enabled', '0', 'integrations', 'checkbox', 'Включить SMTP', '2025-12-06 13:03:48', '2025-12-06 13:03:48'),
(26, 'smtp_host', '', 'integrations', 'text', 'SMTP хост', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(27, 'smtp_port', '587', 'integrations', 'number', 'SMTP порт', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(28, 'smtp_username', '', 'integrations', 'text', 'SMTP пользователь', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(29, 'smtp_password', '', 'integrations', 'password', 'SMTP пароль', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(30, 'backup_enabled', '1', 'backup', 'checkbox', 'Включить резервное копирование', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(31, 'backup_interval', 'daily', 'backup', 'select', 'Интервал резервного копирования', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(32, 'backup_keep_days', '30', 'backup', 'number', 'Хранить резервные копии (дней)', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(33, 'backup_email', '', 'backup', 'email', 'Email для уведомлений о бэкапах', '2025-12-06 13:03:48', '2025-12-06 19:14:01'),
(34, 'backup_compress', '1', 'backup', 'checkbox', 'Сжимать резервные копии', '2025-12-06 13:03:48', '2025-12-06 19:14:01');

-- --------------------------------------------------------

--
-- Структура таблицы `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `level` enum('info','warning','error','debug','security') DEFAULT 'info',
  `module` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `motorcycle_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `start_odometer` double NOT NULL,
  `end_odometer` double NOT NULL,
  `distance` double GENERATED ALWAYS AS (`end_odometer` - `start_odometer`) STORED,
  `trip_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `trips`
--

INSERT INTO `trips` (`id`, `motorcycle_id`, `user_id`, `start_odometer`, `end_odometer`, `trip_date`, `description`, `created_at`) VALUES
(1, 1, 1, 12100, 12250, '2025-12-01', 'Поездка по городу', '2025-12-06 12:34:10'),
(2, 1, 1, 12250, 12300, '2025-12-02', 'Тестовая поездка после обслуживания', '2025-12-06 12:34:10'),
(3, 2, 1, 23700, 23800, '2025-11-30', 'Загородная поездка', '2025-12-06 12:34:10'),
(4, 3, 1, 31000, 31100, '2025-11-28', 'Поездка в горы', '2025-12-06 12:34:10'),
(5, 4, 1, 8600, 8700, '2025-12-03', 'Первая поездка на новом мотоцикле', '2025-12-06 12:34:10'),
(6, 5, 1, 45100, 45200, '2025-11-25', 'Поездка на работу', '2025-12-06 12:34:10'),
(7, 6, 1, 15500, 15600, '2025-11-20', 'Спортивная поездка', '2025-12-06 12:34:10'),
(8, 1, 1, 12300, 12350, '2025-12-04', 'Встреча с друзьями', '2025-12-06 12:34:10'),
(9, 2, 1, 23800, 23880, '2025-12-05', 'Тест после замены масла', '2025-12-06 12:34:10'),
(10, 3, 1, 31100, 31160, '2025-12-06', 'Утренняя прогулка', '2025-12-06 12:34:10');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `avatar`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'imtrdn@gmail.com', '$2y$10$UB3qJfNpv0lJIvlP/z8WWe5OgdYF.TwGYLr1mnE1AbftzyzddmVyC', 'Салтысек Мария', '+79528364073', 'admin', NULL, 1, '2025-12-06 23:39:20', '2025-12-06 00:06:45', '2025-12-06 23:39:20'),
(2, 'user', 'honeymmilktea@gmail.com', '$2y$10$RYxxhloZjenbAIoQN55ffeVmLQ3wGZWxR3BRhEOe7ClnPOiJrnMbm', 'Иванова Анна Сергеевна', '+79790669955', 'user', NULL, 1, '2025-12-06 23:38:18', '2025-12-06 17:23:53', '2025-12-06 23:38:18');

-- --------------------------------------------------------

--
-- Структура таблицы `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mileage` int(11) NOT NULL,
  `last_maintenance` date NOT NULL,
  `interval_km` int(11) NOT NULL DEFAULT 5000,
  `interval_days` int(11) NOT NULL DEFAULT 180
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `vehicles`
--

INSERT INTO `vehicles` (`id`, `name`, `mileage`, `last_maintenance`, `interval_km`, `interval_days`) VALUES
(1, 'Honda CB500X', 12300, '2025-01-10', 5000, 180),
(2, 'Yamaha MT-07', 23800, '2024-11-05', 7000, 180),
(3, 'BMW F850GS', 31100, '2024-12-20', 6000, 365),
(4, 'Honda CB500X', 12300, '2025-01-10', 5000, 180),
(5, 'Yamaha MT-07', 23800, '2024-11-05', 7000, 180),
(6, 'BMW F850GS', 31100, '2024-12-20', 6000, 365),
(7, 'Kawasaki Ninja 650', 8700, '2025-02-28', 5000, 180),
(8, 'Suzuki V-Strom 650', 45200, '2024-10-15', 5000, 180),
(9, 'Triumph Street Triple', 15600, '2025-01-30', 5000, 180),
(10, 'KTM Duke 390', 5200, '2025-03-01', 3000, 90),
(11, 'Honda Rebel 500', 9800, '2025-01-15', 5000, 180),
(12, 'BMW R1250GS', 28700, '2024-12-10', 7000, 365),
(13, 'Kawasaki Z900', 11200, '2025-02-20', 5000, 180);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `motorcycles`
--
ALTER TABLE `motorcycles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate` (`plate`);

--
-- Индексы таблицы `motorcycle_services`
--
ALTER TABLE `motorcycle_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `motorcycle_id` (`motorcycle_id`),
  ADD KEY `template_id` (`template_id`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `motorcycle_id` (`motorcycle_id`);

--
-- Индексы таблицы `service_templates`
--
ALTER TABLE `service_templates`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Индексы таблицы `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Индексы таблицы `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `motorcycle_id` (`motorcycle_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `motorcycles`
--
ALTER TABLE `motorcycles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `motorcycle_services`
--
ALTER TABLE `motorcycle_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `service_templates`
--
ALTER TABLE `service_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT для таблицы `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `motorcycle_services`
--
ALTER TABLE `motorcycle_services`
  ADD CONSTRAINT `motorcycle_services_ibfk_1` FOREIGN KEY (`motorcycle_id`) REFERENCES `motorcycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `motorcycle_services_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `service_templates` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`motorcycle_id`) REFERENCES `motorcycles` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`motorcycle_id`) REFERENCES `motorcycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
