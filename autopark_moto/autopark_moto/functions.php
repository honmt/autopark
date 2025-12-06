<?php
session_start();
require_once __DIR__ . 'includes/db.php';


function isLogged() { return !empty($_SESSION['user']); }
function isRole($role) { return isLogged() && $_SESSION['user']['role'] === $role; }
function requireLogin() { if (!isLogged()) { header('Location: /autopark_moto/auth/login.php'); exit; } }
function requireRole($roles = []) { if (!isLogged() || !in_array($_SESSION['user']['role'], (array)$roles)) { http_response_code(403); echo 'Доступ запрещён'; exit; } }


// Пересчёт следующего ТО для одной motorcycle_service записи
function recalcServiceNext($pdo, $msid) {
$stmt = $pdo->prepare("SELECT ms.*, t.interval_km, t.interval_days, m.odometer
FROM motorcycle_services ms
JOIN service_templates t ON t.id = ms.template_id
JOIN motorcycles m ON m.id = ms.motorcycle_id
WHERE ms.id = ?");
$stmt->execute([$msid]);
$r = $stmt->fetch();
if (!$r) return false;
$lastOdo = $r['last_service_odometer'] ? $r['last_service_odometer'] : $r['odometer'];
$lastDate = $r['last_service_date'] ? $r['last_service_date'] : date('Y-m-d');
$nextMileage = null; $nextDate = null;
if ($r['interval_km']) $nextMileage = $lastOdo + $r['interval_km'];
if ($r['interval_days']) $nextDate = date('Y-m-d', strtotime($lastDate . " + {$r['interval_days']} days"));
$status = 'upcoming';
if ($nextMileage !== null && $r['odometer'] >= $nextMileage) $status = 'overdue';
if ($nextDate !== null && strtotime($nextDate) < time()) $status = 'overdue';
$upd = $pdo->prepare("UPDATE motorcycle_services SET next_service_mileage = ?, next_service_date = ?, status = ? WHERE id = ?");
$upd->execute([$nextMileage, $nextDate, $status, $msid]);
return true;
}


// Пересчёт для всех
function recalcAllServices($pdo) {
$stmt = $pdo->query('SELECT id FROM motorcycle_services');
while ($r = $stmt->fetch()) recalcServiceNext($pdo, $r['id']);
}