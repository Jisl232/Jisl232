<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';

// Procesar el formulario si viene por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $tipo_contrato = isset($_POST['tipo_contrato']) ? trim($_POST['tipo_contrato']) : '';
    $cliente       = isset($_POST['cliente']) ? trim($_POST['cliente']) : '';
    $abogado       = isset($_POST['abogado']) ? trim($_POST['abogado']) : '';
    $fecha_inicio  = isset($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : '';
    $fecha_fin     = isset($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : '';
    $estado_actual = isset($_POST['estado_actual']) ? trim($_POST['estado_actual']) : 'Borrador';
    $saveMode      = isset($_POST['save_mode']) ? $_POST['save_mode'] : 'finalizar';

    if ($tipo_contrato === '' || $cliente === '' || $fecha_inicio === '') {
        header('Location: contractos.php?status=error');
        exit();
    }

    $nombreArchivo = '';
    $uploadError = $_FILES['documento']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError !== UPLOAD_ERR_NO_FILE && $uploadError !== UPLOAD_ERR_OK) {
        $status = in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'upload_limit'
            : 'upload_failed';
        header('Location: contractos.php?status=' . $status);
        exit();
    }

    if ($uploadError === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($_FILES['documento']['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'doc', 'docx'], true)) {
            header('Location: contractos.php?status=upload_type');
            exit();
        }
        if ((int)$_FILES['documento']['size'] > 10 * 1024 * 1024) {
            header('Location: contractos.php?status=upload_limit');
            exit();
        }

        $uploadDirectory = dirname(__DIR__) . '/storage/uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            header('Location: contractos.php?status=upload_failed');
            exit();
        }
        $nombreArchivo = bin2hex(random_bytes(12)) . '.' . $extension;
        if (!is_uploaded_file($_FILES['documento']['tmp_name']) || !move_uploaded_file($_FILES['documento']['tmp_name'], $uploadDirectory . '/' . $nombreArchivo)) {
            header('Location: contractos.php?status=upload_failed');
            exit();
        }
    }

    $firmaArchivo = '';
    $firmaData = $_POST['firma_data'] ?? '';
    if ($firmaData !== '') {
        $signaturePrefix = 'data:image/png;base64,';
        if (!str_starts_with($firmaData, $signaturePrefix) || strlen($firmaData) > 2 * 1024 * 1024) {
            header('Location: contractos.php?status=signature_error');
            exit();
        }
        $signatureBytes = base64_decode(substr($firmaData, strlen($signaturePrefix)), true);
        if ($signatureBytes === false || substr($signatureBytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            header('Location: contractos.php?status=signature_error');
            exit();
        }
        $uploadDirectory = dirname(__DIR__) . '/storage/uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            header('Location: contractos.php?status=upload_failed');
            exit();
        }
        $firmaArchivo = bin2hex(random_bytes(12)) . '.png';
        if (file_put_contents($uploadDirectory . '/' . $firmaArchivo, $signatureBytes, LOCK_EX) === false) {
            header('Location: contractos.php?status=upload_failed');
            exit();
        }
    }

    app_data_append('contratos', [
        'tipo' => $tipo_contrato,
        'cliente' => $cliente,
        'abogado' => $abogado,
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin,
        'estado' => $estado_actual,
        'archivo' => $nombreArchivo,
        'firma_archivo' => $firmaArchivo,
        'solicitar_firma_digital' => isset($_POST['firma_digital']),
        'renovacion_automatica' => isset($_POST['renovacion_automatica']),
        'alerta_activa' => isset($_POST['alerta_activa']),
        'alerta_frecuencia' => trim($_POST['alerta_frecuencia'] ?? 'una_vez'),
        'alerta_anticipacion' => trim($_POST['alerta_anticipacion'] ?? '1 hora'),
        'created_at' => date(DATE_ATOM),
        'created_by' => $_SESSION['user_id'],
    ]);

    $hasAttachment = $nombreArchivo !== '';
    if ($saveMode === 'borrador') {
        header('Location: contractos.php?status=' . ($hasAttachment ? 'draft_uploaded' : 'draft'));
        exit();
    }

    header('Location: contractos.php?status=' . ($hasAttachment ? 'success_uploaded' : 'success'));
    exit();

} else {
    header("Location: contractos.php");
    exit();
}