<?php
function app_data_types(): array
{
    return ['clientes', 'casos', 'citas', 'contratos', 'facturas', 'pagos', 'configuracion', 'actuaciones', 'comentarios', 'notificaciones'];
}

function app_data_path(string $type): string
{
    if (!in_array($type, app_data_types(), true)) {
        throw new InvalidArgumentException('Tipo de datos no válido.');
    }

    return dirname(__DIR__) . '/storage/' . $type . '.json';
}

function app_data_connection(): PDO
{
    static $connection;
    static $schemaReady = false;

    if (!$connection instanceof PDO) {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $connection = $GLOBALS['pdo'];
        } else {
            require dirname(__DIR__) . '/config/database.php';
            if (!isset($pdo) || !$pdo instanceof PDO) {
                throw new RuntimeException('No se pudo inicializar la conexión MySQL.');
            }
            $connection = $pdo;
        }
    }

    if (!$schemaReady) {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS app_records (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                record_type VARCHAR(40) NOT NULL,
                payload LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_app_records_type_id (record_type, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS app_migrations (
                migration_key VARCHAR(100) NOT NULL PRIMARY KEY,
                migrated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $schemaReady = true;
    }

    return $connection;
}

function app_data_migrate_legacy(string $type): void
{
    $database = app_data_connection();
    $migrationKey = 'json:' . $type;
    $database->beginTransaction();

    try {
        $marker = $database->prepare('INSERT IGNORE INTO app_migrations (migration_key) VALUES (?)');
        $marker->execute([$migrationKey]);
        if ($marker->rowCount() !== 1) {
            $database->commit();
            return;
        }

        $path = app_data_path($type);
        if (is_file($path)) {
            $legacyRecords = json_decode(file_get_contents($path) ?: '[]', true);
            if (is_array($legacyRecords)) {
                $insert = $database->prepare('INSERT INTO app_records (record_type, payload) VALUES (?, ?)');
                foreach ($legacyRecords as $legacyRecord) {
                    if (is_array($legacyRecord)) {
                        $payload = json_encode($legacyRecord, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if ($payload === false) {
                            throw new RuntimeException('No se pudo convertir un registro JSON para migrarlo.');
                        }
                        $insert->execute([$type, $payload]);
                    }
                }
            }
        }

        $database->commit();
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $error;
    }
}

function app_data_read(string $type): array
{
    app_data_path($type);
    app_data_migrate_legacy($type);

    $query = app_data_connection()->prepare('SELECT payload FROM app_records WHERE record_type = ? ORDER BY id ASC');
    $query->execute([$type]);
    $records = [];
    foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $payload) {
        $record = json_decode($payload, true);
        if (is_array($record)) {
            $records[] = $record;
        }
    }

    return $records;
}

function app_data_count(string $type): int
{
    app_data_path($type);
    app_data_migrate_legacy($type);

    $query = app_data_connection()->prepare('SELECT COUNT(*) FROM app_records WHERE record_type = ?');
    $query->execute([$type]);
    return (int)$query->fetchColumn();
}

function app_data_append(string $type, array $record): void
{
    app_data_path($type);
    app_data_migrate_legacy($type);

    $payload = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudieron serializar los datos para guardarlos.');
    }

    $insert = app_data_connection()->prepare('INSERT INTO app_records (record_type, payload) VALUES (?, ?)');
    $insert->execute([$type, $payload]);
}