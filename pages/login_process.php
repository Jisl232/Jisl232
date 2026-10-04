<?php
session_start();
require_once __DIR__ . '/../includes/user_access.php';

$database = app_user_database();
app_ensure_user_schema($database);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$name = trim($_POST['nombre'] ?? '');
$userId = trim($_POST['id'] ?? '');
$password = (string)($_POST['contrasena'] ?? $_POST['password'] ?? '');
if ($name === '' || $userId === '' || $password === '') {
    header('Location: ../index.php?status=missing');
    exit();
}

$query = $database->prepare('SELECT id, nombre, contrasena, rol, activo FROM abogados WHERE id = ? LIMIT 1');
$query->execute([$userId]);
$user = $query->fetch(PDO::FETCH_ASSOC);
if (!$user || !(bool)$user['activo'] || strcasecmp(trim($user['nombre']), $name) !== 0) {
    header('Location: ../index.php?status=invalid');
    exit();
}

$storedPassword = (string)$user['contrasena'];
$passwordMatches = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);
if (!$passwordMatches) {
    header('Location: ../index.php?status=invalid');
    exit();
}

if (hash_equals($storedPassword, $password)) {
    $passwordUpdate = $database->prepare('UPDATE abogados SET contrasena = ? WHERE id = ?');
    $passwordUpdate->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['nombre'];
$_SESSION['user_role'] = $user['rol'];

$destination = $user['rol'] === 'abogado' ? 'mis_casos.php' : '../dashboard.php';
header('Location: ' . $destination);
exit();
