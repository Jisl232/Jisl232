<?php
session_start();
require_once __DIR__ . '/../includes/data_store.php';
require_once __DIR__ . '/../includes/user_access.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit();
}

$action = $_POST['action'] ?? '';
if (in_array($action, ['resolver_caso', 'comentario', 'marcar_notificacion', 'marcar_todas'], true)) {
    app_require_role(['admin', 'abogado'], '../index.php');
} else {
    app_require_admin('mis_casos.php');
}
$returnTo = $_POST['return_to'] ?? 'dashboard.php';
$allowedDestinations = [
    'dashboard.php' => '../dashboard.php',
    'clientes.php' => 'clientes.php',
    'agenda.php' => 'agenda.php',
    'casos.php' => 'casos.php',
    'mis_casos.php' => 'mis_casos.php',
    'notificaciones.php' => 'notificaciones.php',
    'facturacion.php' => 'facturacion.php',
    'configuracion.php' => 'configuracion.php',
];
$redirect = $allowedDestinations[$returnTo] ?? '../dashboard.php';
$record = ['created_at' => date(DATE_ATOM), 'created_by' => $_SESSION['user_id']];

try {
    switch ($action) {
        case 'cliente':
            $clientName = trim($_POST['nombre'] ?? '');
            $clientType = trim($_POST['tipo'] ?? 'Persona Física');
            $clientEmail = trim($_POST['correo'] ?? '');
            $clientIdentification = trim($_POST['identificacion'] ?? '');
            $record += [
                'nombre' => $clientName,
                'tipo' => $clientType,
                'identificacion' => $clientIdentification,
                'correo' => $clientEmail,
                'telefono' => trim($_POST['telefono'] ?? ''),
                'direccion' => trim($_POST['direccion'] ?? ''),
                'notas' => trim($_POST['notas'] ?? ''),
                'estado' => trim($_POST['estado'] ?? 'Activo'),
            ];
            if ($clientName === '') {
                throw new InvalidArgumentException('El nombre del cliente es obligatorio.');
            }
            if (!in_array($clientType, ['Persona Física', 'Persona Jurídica'], true) || !in_array($record['estado'], ['Activo', 'Inactivo'], true)) {
                throw new InvalidArgumentException('El tipo o estado del cliente no es válido.');
            }
            if ($clientEmail !== '' && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El correo del cliente no es válido.');
            }
            if ($clientIdentification !== '') {
                foreach (app_data_read('clientes') as $existingClient) {
                    if (strcasecmp(trim($existingClient['identificacion'] ?? ''), $clientIdentification) === 0) {
                        throw new InvalidArgumentException('Ya existe un cliente con esa identificación.');
                    }
                }
            }
            app_data_append('clientes', $record);
            break;

        case 'cita':
            $record += [
                'titulo' => trim($_POST['titulo'] ?? ''),
                'cliente' => trim($_POST['cliente'] ?? ''),
                'fecha' => trim($_POST['fecha'] ?? ''),
                'hora' => trim($_POST['hora'] ?? ''),
                'tipo' => trim($_POST['tipo'] ?? 'Consulta'),
                'caso' => trim($_POST['caso'] ?? ''),
                'ubicacion' => trim($_POST['ubicacion'] ?? ''),
                'notas' => trim($_POST['notas'] ?? ''),
                'estado' => 'Agendada',
            ];
            if ($record['titulo'] === '' || $record['fecha'] === '') {
                throw new InvalidArgumentException('El asunto y la fecha de la cita son obligatorios.');
            }
            $appointmentDate = DateTimeImmutable::createFromFormat('!Y-m-d', $record['fecha']);
            if (!$appointmentDate || $appointmentDate->format('Y-m-d') !== $record['fecha']) {
                throw new InvalidArgumentException('La fecha de la cita no es válida.');
            }
            if (!in_array($record['tipo'], ['Consulta', 'Audiencia', 'Reunión', 'Firma', 'Plazo'], true)) {
                throw new InvalidArgumentException('Selecciona un tipo de cita válido.');
            }
            if ($record['hora'] !== '') {
                $appointmentTime = DateTimeImmutable::createFromFormat('!H:i', $record['hora']);
                $timeErrors = DateTimeImmutable::getLastErrors();
                if (!$appointmentTime || ($timeErrors && ($timeErrors['warning_count'] > 0 || $timeErrors['error_count'] > 0)) || $appointmentTime->format('H:i') !== $record['hora']) {
                    throw new InvalidArgumentException('La hora de la cita no es válida.');
                }
            }
            app_data_append('citas', $record);
            break;

        case 'caso':
            $record += [
                'numero' => trim($_POST['numero'] ?? ''),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'tipo' => trim($_POST['tipo'] ?? ''),
                'area' => trim($_POST['area'] ?? ''),
                'estado' => trim($_POST['estado'] ?? 'Abierto'),
                'cliente' => trim($_POST['cliente'] ?? ''),
                'abogado' => trim($_POST['abogado'] ?? ''),
                'abogado_id' => trim($_POST['abogado_id'] ?? ''),
                'contrato' => trim($_POST['contrato'] ?? ''),
                'responsable' => trim($_POST['responsable'] ?? ''),
            ];
            if ($record['numero'] === '' || $record['tipo'] === '') {
                throw new InvalidArgumentException('El número y tipo de caso son obligatorios.');
            }
            if ($record['nombre'] === '') {
                $record['nombre'] = $record['tipo'] . ' - ' . $record['numero'];
            }
            if ($record['area'] === '') {
                $record['area'] = 'General';
            }
            if ($record['abogado_id'] !== '') {
                $lawyerQuery = app_user_database()->prepare("SELECT id, nombre FROM abogados WHERE id = ? AND rol = 'abogado' AND activo = 1 LIMIT 1");
                $lawyerQuery->execute([$record['abogado_id']]);
                $assignedLawyer = $lawyerQuery->fetch(PDO::FETCH_ASSOC);
                if (!$assignedLawyer) {
                    throw new InvalidArgumentException('Selecciona una cuenta de abogado activa.');
                }
                $record['abogado_id'] = $assignedLawyer['id'];
                $record['abogado'] = $assignedLawyer['nombre'];
            } else {
                $record['abogado'] = '';
            }
            if (!in_array($record['estado'], ['Abierto', 'En Proceso', 'Cerrado'], true)) {
                $record['estado'] = 'Abierto';
            }

            $documents = [];
            $files = $_FILES['documentos'] ?? null;
            if ($files && is_array($files['name'] ?? null)) {
                foreach ($files['name'] as $index => $originalName) {
                    $uploadError = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                    if ($uploadError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('No se pudo recibir uno de los documentos.');
                    }

                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    if (!in_array($extension, ['pdf', 'doc', 'docx'], true) || (int)$files['size'][$index] > 10 * 1024 * 1024) {
                        throw new InvalidArgumentException('Documento inválido o mayor de 10 MB.');
                    }

                    $uploadDirectory = dirname(__DIR__) . '/storage/uploads';
                    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                        throw new RuntimeException('No se pudo crear el directorio de documentos.');
                    }
                    $storedName = bin2hex(random_bytes(12)) . '.' . $extension;
                    if (!is_uploaded_file($files['tmp_name'][$index]) || !move_uploaded_file($files['tmp_name'][$index], $uploadDirectory . '/' . $storedName)) {
                        throw new RuntimeException('No se pudo guardar uno de los documentos.');
                    }
                    $documents[] = ['archivo' => $storedName, 'nombre' => basename($originalName)];
                }
            }
            $record['documentos'] = $documents;
            app_data_append('casos', $record);
            app_data_append('actuaciones', [
                'numero' => $record['numero'],
                'fecha' => date('d/m/Y'),
                'actor' => $_SESSION['user_name'] ?? 'Usuario',
                'actuacion' => 'Caso registrado',
                'estado' => $record['estado'],
                'created_at' => date(DATE_ATOM),
                'created_by' => $_SESSION['user_id'],
            ]);
                if ($record['abogado_id'] !== '') {
                    app_data_append('notificaciones', [
                        'notificacion_id' => bin2hex(random_bytes(16)),
                        'recipient_id' => (string)$record['abogado_id'],
                        'tipo' => 'caso_asignado',
                        'titulo' => 'Nuevo caso asignado',
                        'mensaje' => $record['numero'] . ' · ' . $record['nombre'],
                        'caso' => $record['numero'],
                        'leida' => false,
                        'created_at' => date(DATE_ATOM),
                        'created_by' => $_SESSION['user_id'],
                    ]);
                }
            break;

        case 'comentario':
            $caseNumber = trim($_POST['numero'] ?? '');
            $commentText = trim($_POST['comentario'] ?? '');
            $caseRecord = null;
            foreach (app_data_read('casos') as $candidateCase) {
                if (($candidateCase['numero'] ?? '') === $caseNumber) {
                    $caseRecord = $candidateCase;
                    break;
                }
            }
            if (!$caseRecord || $commentText === '' || strlen($commentText) > 5000) {
                throw new InvalidArgumentException('Selecciona un caso y escribe un comentario de hasta 5000 caracteres.');
            }
            if ($_SESSION['user_role'] === 'abogado') {
                $assignedById = (string)($caseRecord['abogado_id'] ?? '') === (string)$_SESSION['user_id'];
                $assignedByLegacyName = empty($caseRecord['abogado_id']) && strcasecmp(trim($caseRecord['abogado'] ?? ''), $_SESSION['user_name'] ?? '') === 0;
                if (!$assignedById && !$assignedByLegacyName) {
                    throw new InvalidArgumentException('Este caso no está asignado a tu cuenta.');
                }
            }

            $commentId = bin2hex(random_bytes(16));
            app_data_append('comentarios', [
                'comentario_id' => $commentId,
                'numero' => $caseNumber,
                'autor_id' => $_SESSION['user_id'],
                'autor' => $_SESSION['user_name'],
                'rol_autor' => $_SESSION['user_role'],
                'comentario' => $commentText,
                'created_at' => date(DATE_ATOM),
            ]);

            $recipientId = $_SESSION['user_role'] === 'admin'
                ? (string)($caseRecord['abogado_id'] ?? '')
                : (string)($databaseAdminId = app_user_database()->query("SELECT id FROM abogados WHERE rol = 'admin' AND activo = 1 ORDER BY fecha_creacion ASC, id ASC LIMIT 1")->fetchColumn());
            if ($recipientId !== '' && $recipientId !== (string)$_SESSION['user_id']) {
                app_data_append('notificaciones', [
                    'notificacion_id' => bin2hex(random_bytes(16)),
                    'recipient_id' => $recipientId,
                    'tipo' => 'comentario',
                    'titulo' => 'Nuevo comentario en un caso',
                    'mensaje' => $caseNumber . ' · ' . mb_substr($commentText, 0, 140),
                    'url' => ($_SESSION['user_role'] === 'admin' ? 'casos.php' : 'mis_casos.php') . '?caso=' . rawurlencode($caseNumber),
                    'caso' => $caseNumber,
                    'leida' => false,
                    'created_at' => date(DATE_ATOM),
                    'created_by' => $_SESSION['user_id'],
                ]);
            }
            break;

        case 'marcar_notificacion':
            $notificationId = trim($_POST['notificacion_id'] ?? '');
            $ownedNotification = null;
            foreach (app_data_read('notificaciones') as $notification) {
                if (($notification['notificacion_id'] ?? '') === $notificationId && (string)($notification['recipient_id'] ?? '') === (string)$_SESSION['user_id']) {
                    $ownedNotification = $notification;
                    break;
                }
            }
            if (!$ownedNotification) {
                throw new InvalidArgumentException('No se encontró la notificación de esta cuenta.');
            }
            app_update_record('notificaciones', 'notificacion_id', $notificationId, ['leida' => true, 'leida_at' => date(DATE_ATOM)]);
            break;

        case 'marcar_todas':
            foreach (app_data_read('notificaciones') as $notification) {
                if ((string)($notification['recipient_id'] ?? '') === (string)$_SESSION['user_id'] && empty($notification['leida'])) {
                    app_update_record('notificaciones', 'notificacion_id', $notification['notificacion_id'], ['leida' => true, 'leida_at' => date(DATE_ATOM)]);
                }
            }
            break;

        case 'resolver_caso':
            $caseNumber = trim($_POST['numero'] ?? '');
            $assignedCase = null;
            foreach (app_data_read('casos') as $candidateCase) {
                $assignedById = (string)($candidateCase['abogado_id'] ?? '') === (string)$_SESSION['user_id'];
                $assignedByLegacyName = empty($candidateCase['abogado_id']) && strcasecmp(trim($candidateCase['abogado'] ?? ''), $_SESSION['user_name'] ?? '') === 0;
                if (($candidateCase['numero'] ?? '') === $caseNumber && ($assignedById || $assignedByLegacyName)) {
                    $assignedCase = $candidateCase;
                    break;
                }
            }
            if (!$assignedCase) {
                throw new InvalidArgumentException('Este caso no está asignado a tu cuenta.');
            }
            if (strcasecmp($assignedCase['estado'] ?? '', 'Cerrado') === 0) {
                throw new InvalidArgumentException('El caso ya aparece como resuelto.');
            }
            if (!app_update_record('casos', 'numero', $caseNumber, [
                'estado' => 'Cerrado',
                'resuelto_por' => $_SESSION['user_name'],
                'fecha_resolucion' => date('Y-m-d'),
            ])) {
                throw new RuntimeException('No se pudo actualizar el estado del caso.');
            }
            app_data_append('actuaciones', [
                'numero' => $caseNumber,
                'fecha' => date('d/m/Y'),
                'actor' => $_SESSION['user_name'],
                'actuacion' => 'Caso marcado como resuelto',
                'estado' => 'Cerrado',
                'created_at' => date(DATE_ATOM),
                'created_by' => $_SESSION['user_id'],
            ]);
            $adminId = app_user_database()->query("SELECT id FROM abogados WHERE rol = 'admin' AND activo = 1 ORDER BY fecha_creacion ASC, id ASC LIMIT 1")->fetchColumn();
            if ($adminId && (string)$adminId !== (string)$_SESSION['user_id']) {
                app_data_append('notificaciones', [
                    'notificacion_id' => bin2hex(random_bytes(16)),
                    'recipient_id' => (string)$adminId,
                    'tipo' => 'caso_resuelto',
                    'titulo' => 'Caso marcado como resuelto',
                    'mensaje' => $caseNumber . ' · ' . ($assignedCase['nombre'] ?? ''),
                    'caso' => $caseNumber,
                    'leida' => false,
                    'created_at' => date(DATE_ATOM),
                    'created_by' => $_SESSION['user_id'],
                ]);
            }
            break;

        case 'actuacion':
            $record += [
                'numero' => trim($_POST['numero'] ?? ''),
                'fecha' => date('d/m/Y'),
                'actor' => $_SESSION['user_name'] ?? 'Usuario',
                'actuacion' => trim($_POST['actuacion'] ?? ''),
                'estado' => trim($_POST['estado'] ?? 'Abierto'),
            ];
            if ($record['numero'] === '' || $record['actuacion'] === '') {
                throw new InvalidArgumentException('Selecciona un caso y describe la actuación.');
            }
            if (!in_array($record['estado'], ['Abierto', 'En Proceso', 'Cerrado', 'Archivado'], true)) {
                $record['estado'] = 'Abierto';
            }
            app_data_append('actuaciones', $record);
            break;

        case 'factura':
            $cliente = trim($_POST['cliente'] ?? '');
            $base = $_POST['base'] ?? '';
            $settingsRecords = app_data_read('configuracion');
            $billingSettings = $settingsRecords ? end($settingsRecords) : [];
            $ivaPercent = $_POST['iva_porcentaje'] ?? $billingSettings['iva_porcentaje'] ?? '16';
            $discount = $_POST['descuento'] ?? '0';
            if ($cliente === '' || !is_numeric($base) || !is_numeric($ivaPercent) || !is_numeric($discount)) {
                throw new InvalidArgumentException('Completa cliente, honorarios, IVA y descuento con valores válidos.');
            }

            $base = round((float)$base, 2);
            $ivaPercent = round((float)$ivaPercent, 2);
            $discount = round((float)$discount, 2);
            if ($base <= 0 || $ivaPercent < 0 || $ivaPercent > 100 || $discount < 0) {
                throw new InvalidArgumentException('Revisa los importes de la factura.');
            }
            $tax = round($base * $ivaPercent / 100, 2);
            $total = round($base + $tax - $discount, 2);
            if ($discount > $base + $tax || $total <= 0) {
                throw new InvalidArgumentException('El descuento no puede superar el subtotal.');
            }
            $invoiceStatus = trim($_POST['estado'] ?? 'Pendiente');
            if (!in_array($invoiceStatus, ['Pendiente', 'Anulada'], true)) {
                $invoiceStatus = 'Pendiente';
            }

            $existingInvoices = app_data_read('facturas');
            $invoicePrefix = $billingSettings['prefijo_factura'] ?? 'F';
            $paymentTermDays = (int)($billingSettings['plazo_pago_dias'] ?? 30);
            do {
                $invoiceNumber = $invoicePrefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $numberExists = false;
                foreach ($existingInvoices as $existingInvoice) {
                    if (($existingInvoice['numero'] ?? '') === $invoiceNumber) {
                        $numberExists = true;
                        break;
                    }
                }
            } while ($numberExists);

            $record += [
                'numero' => $invoiceNumber,
                'cliente' => $cliente,
                'caso_contrato' => trim($_POST['caso_contrato'] ?? ''),
                'base' => $base,
                'iva_porcentaje' => $ivaPercent,
                'iva_monto' => $tax,
                'descuento' => $discount,
                'total' => $total,
                'importe' => $total,
                'fecha' => date('Y-m-d'),
                'fecha_vencimiento' => date('Y-m-d', strtotime('+' . $paymentTermDays . ' days')),
                'estado' => ($_POST['save_mode'] ?? '') === 'draft' ? 'Borrador' : $invoiceStatus,
            ];
            app_data_append('facturas', $record);
            break;

        case 'pago':
            $invoiceNumber = trim($_POST['numero_factura'] ?? '');
            $amount = $_POST['importe'] ?? '';
            $paymentDate = trim($_POST['fecha'] ?? '');
            $method = trim($_POST['metodo'] ?? '');
            if ($invoiceNumber === '' || !is_numeric($amount) || (float)$amount <= 0 || $paymentDate === '') {
                throw new InvalidArgumentException('Selecciona una factura e introduce importe y fecha válidos.');
            }
            $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
            if (!$dateValue || $dateValue->format('Y-m-d') !== $paymentDate) {
                throw new InvalidArgumentException('La fecha del pago no es válida.');
            }
            if (!in_array($method, ['Efectivo', 'Transferencia', 'Tarjeta'], true)) {
                throw new InvalidArgumentException('Selecciona una forma de pago válida.');
            }

            $invoice = null;
            foreach (app_data_read('facturas') as $index => $candidate) {
                $candidateNumber = $candidate['numero'] ?? 'LEG-' . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);
                if ($candidateNumber === $invoiceNumber) {
                    $invoice = $candidate;
                    break;
                }
            }
            if (!$invoice || in_array($invoice['estado'] ?? '', ['Borrador', 'Anulada'], true)) {
                throw new InvalidArgumentException('La factura seleccionada no está disponible para recibir pagos.');
            }

            $invoiceTotal = (float)($invoice['total'] ?? $invoice['importe'] ?? 0);
            $alreadyPaid = 0.0;
            foreach (app_data_read('pagos') as $payment) {
                if (($payment['numero_factura'] ?? '') === $invoiceNumber) {
                    $alreadyPaid += (float)($payment['importe'] ?? 0);
                }
            }
            $amount = round((float)$amount, 2);
            if ($amount > round($invoiceTotal - $alreadyPaid, 2) + 0.001) {
                throw new InvalidArgumentException('El pago supera el saldo pendiente de la factura.');
            }

            $record += [
                'numero_factura' => $invoiceNumber,
                'cliente' => $invoice['cliente'] ?? '',
                'caso_contrato' => $invoice['caso_contrato'] ?? '',
                'importe' => $amount,
                'fecha' => $paymentDate,
                'metodo' => $method,
            ];
            app_data_append('pagos', $record);
            break;

        case 'configuracion':
            $savedSettings = app_data_read('configuracion');
            $currentSettings = $savedSettings ? end($savedSettings) : [];
            $officeName = trim($_POST['despacho'] ?? $currentSettings['despacho'] ?? '');
            $email = trim($_POST['correo'] ?? $currentSettings['correo'] ?? '');
            $taxRate = $_POST['iva_porcentaje'] ?? $currentSettings['iva_porcentaje'] ?? 16;
            $paymentTerm = $_POST['plazo_pago_dias'] ?? $currentSettings['plazo_pago_dias'] ?? 30;
            $invoicePrefix = strtoupper(trim($_POST['prefijo_factura'] ?? $currentSettings['prefijo_factura'] ?? 'F'));
            $contractTemplate = trim($_POST['plantilla_contrato'] ?? $currentSettings['plantilla_contrato'] ?? 'arrendamiento');
            if ($officeName === '' || !is_numeric($taxRate) || (float)$taxRate < 0 || (float)$taxRate > 100) {
                throw new InvalidArgumentException('Completa el nombre del despacho y una tasa IVA entre 0 y 100.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El correo institucional no es válido.');
            }
            if (!filter_var($paymentTerm, FILTER_VALIDATE_INT) || (int)$paymentTerm < 1 || (int)$paymentTerm > 365) {
                throw new InvalidArgumentException('El plazo de pago debe estar entre 1 y 365 días.');
            }
            if (!preg_match('/^[A-Z0-9-]{1,8}$/', $invoicePrefix)) {
                throw new InvalidArgumentException('El prefijo de factura admite hasta 8 letras, números o guiones.');
            }
            if (!in_array($contractTemplate, ['arrendamiento', 'honorarios', 'servicios'], true)) {
                throw new InvalidArgumentException('Selecciona una plantilla de contrato válida.');
            }

            $record += [
                'despacho' => $officeName,
                'identificacion_fiscal' => trim($_POST['identificacion_fiscal'] ?? $currentSettings['identificacion_fiscal'] ?? ''),
                'telefono' => trim($_POST['telefono'] ?? $currentSettings['telefono'] ?? ''),
                'direccion' => trim($_POST['direccion'] ?? $currentSettings['direccion'] ?? ''),
                'correo' => $email,
                'iva_porcentaje' => round((float)$taxRate, 2),
                'plazo_pago_dias' => (int)$paymentTerm,
                'prefijo_factura' => $invoicePrefix,
                'plantilla_contrato' => $contractTemplate,
                'recordatorios' => array_key_exists('recordatorios', $_POST)
                    ? in_array($_POST['recordatorios'], ['1', 'on', 1, true], true)
                    : (bool)($currentSettings['recordatorios'] ?? false),
                'backup' => array_key_exists('backup', $_POST)
                    ? in_array($_POST['backup'], ['1', 'on', 1, true], true)
                    : (bool)($currentSettings['backup'] ?? false),
            ];
            app_data_append('configuracion', $record);
            break;

        default:
            throw new InvalidArgumentException('Acción no reconocida.');
    }

    $query = ['status' => 'success'];
    if ($action === 'factura') {
        $query['status'] = ($_POST['save_mode'] ?? '') === 'draft' ? 'draft_saved' : 'invoice_saved';
    } elseif ($action === 'pago') {
        $query['status'] = 'payment_saved';
    } elseif ($action === 'resolver_caso') {
        $query['status'] = 'resolved';
        $query['numero'] = trim($_POST['numero'] ?? '');
    } elseif ($action === 'comentario') {
        $query['status'] = 'commented';
        $query['caso'] = trim($_POST['numero'] ?? '');
    } elseif ($action === 'marcar_notificacion') {
        $query['status'] = 'read';
    } elseif ($action === 'marcar_todas') {
        $query['status'] = 'read_all';
    }
    if ($action === 'cita' && $returnTo === 'agenda.php' && preg_match('/^\d{4}-\d{2}$/', $_POST['mes'] ?? '')) {
        $query['mes'] = $_POST['mes'];
    }
    if ($action === 'actuacion' && $returnTo === 'casos.php' && !empty($_POST['return_case'])) {
        $query['caso'] = trim($_POST['return_case']);
    }
    header('Location: ' . $redirect . '?' . http_build_query($query));
} catch (Throwable $error) {
    error_log($error->getMessage());
    $query = ['status' => 'error'];
    if ($action === 'actuacion' && $returnTo === 'casos.php' && !empty($_POST['return_case'])) {
        $query['caso'] = trim($_POST['return_case']);
    }
    header('Location: ' . $redirect . '?' . http_build_query($query));
}
exit();
