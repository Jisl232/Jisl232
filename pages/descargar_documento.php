<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_role(['admin', 'abogado'], '../index.php');
require_once __DIR__ . '/../includes/data_store.php';

$filename = $_GET['archivo'] ?? '';
if (!preg_match('/^[a-f0-9]{24}\.(pdf|doc|docx|png)$/i', $filename)) {
    http_response_code(400);
    exit('Nombre de archivo no válido.');
}

$path = dirname(__DIR__) . '/storage/uploads/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('El documento no existe.');
}

if ($_SESSION['user_role'] === 'abogado') {
    $canAccessDocument = false;
    foreach (app_data_read('casos') as $case) {
        $assignedById = (string)($case['abogado_id'] ?? '') === (string)$_SESSION['user_id'];
        $assignedByLegacyName = empty($case['abogado_id']) && strcasecmp(trim($case['abogado'] ?? ''), $_SESSION['user_name'] ?? '') === 0;
        if (!$assignedById && !$assignedByLegacyName) continue;
        foreach ($case['documentos'] ?? [] as $document) {
            if (($document['archivo'] ?? '') === $filename) {
                $canAccessDocument = true;
                break 2;
            }
        }
    }
    if (!$canAccessDocument) {
        http_response_code(403);
        exit('No tienes permiso para abrir este documento.');
    }
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'png' => 'image/png',
];
header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Disposition: ' . ($extension === 'png' ? 'inline' : 'attachment') . '; filename="documento.' . $extension . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
