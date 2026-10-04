<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('../index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: abogados.php');
    exit();
}

$userId = trim($_POST['id'] ?? '');
$name = trim($_POST['nombre'] ?? '');
$password = (string)($_POST['contrasena'] ?? '');

try {
    if ($userId === '' || strlen($userId) > 50 || $name === '' || strlen($name) > 100) {
        throw new InvalidArgumentException('El ID y el nombre son obligatorios.');
    }
    if (strlen($password) < 10) {
        throw new InvalidArgumentException('La contraseña debe tener al menos 10 caracteres.');
    }
    $database = app_user_database();
    $exists = $database->prepare('SELECT COUNT(*) FROM abogados WHERE id = ?');
    $exists->execute([$userId]);
    if ((int)$exists->fetchColumn() > 0) {
        throw new InvalidArgumentException('Ese ID de usuario ya está registrado.');
    }

    $insert = $database->prepare('INSERT INTO abogados (id, nombre, contrasena, rol, activo) VALUES (?, ?, ?, \'abogado\', 1)');
    $insert->execute([$userId, $name, password_hash($password, PASSWORD_DEFAULT)]);
    header('Location: abogados.php?status=created');
} catch (Throwable $error) {
    error_log($error->getMessage());
    header('Location: abogados.php?status=error');
}
exit();
