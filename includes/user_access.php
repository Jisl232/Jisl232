<?php
function app_user_database(): PDO
{
    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        require dirname(__DIR__) . '/config/database.php';
        $GLOBALS['pdo'] = $pdo;
    }

    return $GLOBALS['pdo'];
}

function app_ensure_user_schema(PDO $database): void
{
    $columns = $database->query("SHOW COLUMNS FROM abogados")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('rol', $columns, true)) {
        $database->exec("ALTER TABLE abogados ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'admin'");
    }
    if (!in_array('activo', $columns, true)) {
        $database->exec('ALTER TABLE abogados ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1');
    }
    $database->exec("UPDATE abogados SET rol = 'admin' WHERE rol IS NULL OR rol = ''");

    $admins = $database->query("SELECT id FROM abogados WHERE rol = 'admin' ORDER BY fecha_creacion ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
    if (count($admins) > 1) {
        $demote = $database->prepare("UPDATE abogados SET rol = 'abogado' WHERE rol = 'admin' AND id <> ?");
        $demote->execute([$admins[0]]);
    }
}

function app_require_role(array $allowedRoles, string $deniedLocation, string $loginLocation = '../index.php'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . $loginLocation);
        exit();
    }

    $database = app_user_database();
    app_ensure_user_schema($database);
    $query = $database->prepare('SELECT id, nombre, rol, activo FROM abogados WHERE id = ? LIMIT 1');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    if (!$user || !(bool)$user['activo']) {
        session_unset();
        session_destroy();
        header('Location: ' . $loginLocation . '?status=inactive');
        exit();
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['nombre'];
    $_SESSION['user_role'] = $user['rol'];
    if (!in_array($user['rol'], $allowedRoles, true)) {
        header('Location: ' . $deniedLocation);
        exit();
    }
}

function app_require_admin(string $deniedLocation = 'mis_casos.php', string $loginLocation = '../index.php'): void
{
    app_require_role(['admin'], $deniedLocation, $loginLocation);
}

function app_require_lawyer(string $deniedLocation = '../dashboard.php', string $loginLocation = '../index.php'): void
{
    app_require_role(['abogado'], $deniedLocation, $loginLocation);
}

function app_update_record(string $type, string $key, string $value, array $changes): bool
{
    app_data_path($type);
    app_data_migrate_legacy($type);
    $database = app_data_connection();
    $query = $database->prepare('SELECT id, payload FROM app_records WHERE record_type = ? ORDER BY id ASC');
    $query->execute([$type]);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $record = json_decode($row['payload'], true);
        if (!is_array($record) || (string)($record[$key] ?? '') !== $value) {
            continue;
        }

        $payload = json_encode(array_merge($record, $changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('No se pudo serializar el registro actualizado.');
        }
        $update = $database->prepare('UPDATE app_records SET payload = ? WHERE id = ?');
        $update->execute([$payload, $row['id']]);
        return $update->rowCount() > 0;
    }

    return false;
}