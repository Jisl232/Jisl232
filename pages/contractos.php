<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';
$contracts = array_reverse(app_data_read('contratos'));
$settingsRecords = app_data_read('configuracion');
$contractTemplate = $settingsRecords ? (end($settingsRecords)['plantilla_contrato'] ?? 'arrendamiento') : 'arrendamiento';
$prefilledClient = is_string($_GET['cliente'] ?? null) ? trim($_GET['cliente']) : '';
$today = new DateTimeImmutable('today');
$expirationLimit = $today->modify('+30 days');
$pendingSignatures = 0;
$upcomingExpirations = 0;
$expiredContracts = 0;
foreach ($contracts as $contract) {
    if (strcasecmp($contract['estado'] ?? '', 'Firmado') !== 0) {
        $pendingSignatures++;
    }
    $expirationDate = DateTimeImmutable::createFromFormat('!Y-m-d', $contract['fecha_fin'] ?? '');
    if ($expirationDate instanceof DateTimeImmutable) {
        if ($expirationDate < $today) {
            $expiredContracts++;
        } elseif ($expirationDate <= $expirationLimit) {
            $upcomingExpirations++;
        }
    }
}
$statusMessage = [
    'success' => 'Contrato guardado y registrado correctamente.',
    'success_uploaded' => 'Contrato y documento adjunto guardados correctamente.',
    'draft' => 'Borrador guardado correctamente.',
    'draft_uploaded' => 'Borrador y documento adjunto guardados correctamente.',
    'error' => 'No se pudo guardar el contrato. Revisa los campos obligatorios.',
    'upload_limit' => 'No se cargó el documento: el tamaño máximo permitido es 10 MB.',
    'upload_type' => 'Formato no permitido. Selecciona un archivo PDF, DOC o DOCX.',
    'upload_failed' => 'El servidor no pudo guardar el archivo. Revisa el directorio temporal y los permisos de storage/uploads.',
    'signature_error' => 'No se pudo guardar la firma dibujada. Inténtalo de nuevo.',
][$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Gestión de Contratos - Ley de Audacia</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --wine-900: #5f0d1a;
            --wine-800: #731627;
            --wine-700: #9a1d32;
            --wine-600: #b72a42;
            --black-900: #111111;
            --gray-100: #f5f5f5;
            --gray-200: #ececec;
            --gray-300: #dddddd;
            --gray-500: #666666;
            --white: #ffffff;
            --red-soft: #f7dfe4;
            --green-soft: #dff5e5;
            --yellow-soft: #f6ebc9;
            --border-soft: rgba(17,17,17,0.09);
            --shadow-soft: rgba(0,0,0,0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            background: #f1f1f1;
            color: var(--black-900);
            min-height: 100vh;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px 28px 28px;
            background: #f4f4f4;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f8f8;
            border-radius: 12px 12px 0 0;
            padding: 16px 18px;
            border-bottom: 1px solid var(--border-soft);
            margin-bottom: 18px;
        }

        .topbar-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--black-900);
        }

        .topbar-icons {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .icon-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: transparent;
            border: 1px solid var(--border-soft);
            color: var(--black-900);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .icon-btn:hover {
            background: var(--gray-200);
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d8d8d8, #bbbbbb);
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }

        .page-wrap {
            background: #f8f8f8;
            border-radius: 0 0 12px 12px;
            padding: 18px 18px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .page-title {
            font-size: 2.4rem;
            font-weight: 800;
            margin: 8px 0 20px;
            color: #1e1e1e;
        }

        .section-label {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 14px;
            color: #2b2b2b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f5f5f5;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 2px 5px var(--shadow-soft);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .stat-icon.red { background: var(--red-soft); color: var(--wine-700); }
        .stat-icon.blue { background: #e8f0fb; color: #2a5a84; }
        .stat-icon.yellow { background: var(--yellow-soft); color: #7b6010; }
        .stat-icon.green { background: var(--green-soft); color: #1a7b3a; }

        .stat-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1f1f1f;
        }

        .stat-label {
            display: block;
            font-size: 0.8rem;
            color: #5d5d5d;
            margin-top: 2px;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1.6fr 1.1fr;
            gap: 18px;
        }

        .panel {
            background: #f7f7f7;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            padding: 16px 18px;
            box-shadow: 0 2px 5px var(--shadow-soft);
        }

        .panel h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: #1d1d1d;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 0.76rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: #4d4d4d;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.95rem;
            color: #1d1d1d;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--wine-700);
            box-shadow: 0 0 0 2px rgba(154,29,50,0.08);
        }

        .date-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .toggle {
            position: relative;
            width: 42px;
            height: 22px;
            border-radius: 999px;
            background: #d7d7d7;
            border: 1px solid var(--gray-300);
        }

        .toggle::after {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50%;
            top: 2px;
            left: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }

        .toggle.on {
            background: var(--wine-700);
        }

        .toggle.on::after {
            left: 21px;
        }

        .switch-control {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 30px;
            cursor: pointer;
        }

        .switch-control input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .switch-control input:checked + .toggle {
            background: var(--wine-700);
        }

        .switch-control input:checked + .toggle::after {
            left: 21px;
        }

        .switch-control input:focus-visible + .toggle {
            outline: 3px solid rgba(154,29,50,0.28);
            outline-offset: 2px;
        }

        .signature-pad {
            overflow: hidden;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            background: #fff;
        }

        .signature-pad canvas {
            display: block;
            width: 100%;
            height: 120px;
            touch-action: none;
            cursor: crosshair;
        }

        .signature-pad-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            border-top: 1px solid var(--gray-200);
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .signature-clear {
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            background: #fff;
            color: var(--black-900);
            padding: 6px 9px;
            cursor: pointer;
        }

        .alert-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .alert-options > .form-group {
            margin-bottom: 0;
        }

        .alert-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
            color: #333;
            font-size: 0.85rem;
        }

        .alert-checkbox input {
            width: 18px;
            height: 18px;
            accent-color: var(--wine-700);
        }

        .document-box {
            border: 1px solid var(--gray-300);
            background: #fff;
            border-radius: 10px;
            padding: 18px 14px;
            text-align: center;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #2d2d2d;
        }

        .upload-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f3f3f3, #e2e2e2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--wine-700);
            border: 1px solid var(--gray-300);
        }

        .upload-link {
            color: var(--wine-700);
            font-weight: 700;
            cursor: pointer;
        }

        .switch-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--gray-300);
            background: #fff;
            font-size: 0.8rem;
            color: #333;
        }

        .chip.active {
            background: var(--wine-700);
            color: #fff;
            border-color: var(--wine-700);
        }

        .table-panel {
            padding: 16px 18px;
        }

        .table-list {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-list th,
        .table-list td {
            text-align: left;
            padding: 8px 6px;
            border-bottom: 1px solid var(--border-soft);
        }

        .table-list th {
            color: #4f4f4f;
            font-weight: 700;
        }

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #17a34a;
            box-shadow: 0 0 0 2px rgba(23,163,74,0.2);
        }

        .status-dot.red { background: #d9384a; box-shadow: 0 0 0 2px rgba(217,56,74,0.18); }
        .status-dot.yellow { background: #f0b51e; box-shadow: 0 0 0 2px rgba(240,181,30,0.18); }

        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.secondary {
            background: #f0f0f0;
            color: #1b1b1b;
            border: 1px solid var(--gray-300);
        }

        .btn.primary {
            background: linear-gradient(135deg, var(--wine-800), var(--wine-700));
            color: white;
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(180px, 1fr)); }
            .main-grid { grid-template-columns: 1fr; }
            .content-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include_once '../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-title">Legal System - Contracts Management</div>
            <div class="topbar-icons">
                <button class="icon-btn" type="button" aria-label="Notificaciones" onclick="alert('No hay notificaciones nuevas.');"><i class="fa-regular fa-bell"></i></button>
                <button class="icon-btn" type="button" aria-label="Mensajes" onclick="alert('Sin mensajes nuevos.');"><i class="fa-regular fa-comment"></i></button>
                <a href="../dashboard.php" aria-label="Volver al dashboard" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none;"><div class="avatar"></div></a>
            </div>
        </header>

        <div class="page-wrap">
            <h1 class="page-title">Gestión y Creación de Contratos</h1>
            <?php if ($statusMessage !== ''): ?>
                <p role="status" style="margin-bottom:16px;padding:12px;border-radius:8px;background:#f3e5e8;color:#5f0d1a;"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div class="section-label">Key Indicators</div>
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fa-solid fa-file-contract"></i></div>
                    <div>
                        <div class="stat-value"><?php echo count($contracts); ?></div>
                        <span class="stat-label">Contratos Totales</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fa-regular fa-calendar"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $pendingSignatures; ?></div>
                        <span class="stat-label">Por Firmar</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $upcomingExpirations; ?></div>
                        <span class="stat-label">Vencimientos Próximos</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fa-solid fa-check"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $expiredContracts; ?></div>
                        <span class="stat-label">Vencidos</span>
                    </div>
                </div>
            </section>

            <div class="main-grid">
                <section class="panel">
                    <h3>Creación de Nuevo Contrato</h3>
                    <form action="guardar_contrato.php" method="POST" enctype="multipart/form-data">
                        <div class="content-grid">
                            <div>
                                <div class="form-group">
                                    <label for="tipo_contrato">Tipo de Contrato</label>
                                    <select name="tipo_contrato" id="tipo_contrato" required>
                                        <option value="">Seleccione una opción</option>
                                        <option value="Plantillas" <?php echo $contractTemplate === 'servicios' ? 'selected' : ''; ?>>Plantillas / Servicios</option>
                                        <option value="Contrato de Arrendamiento" <?php echo $contractTemplate === 'arrendamiento' ? 'selected' : ''; ?>>Contrato de Arrendamiento</option>
                                        <option value="Contrato de Honorarios" <?php echo $contractTemplate === 'honorarios' ? 'selected' : ''; ?>>Contrato de Honorarios</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="cliente">Cliente</label>
                                    <input type="text" name="cliente" id="cliente" value="<?php echo htmlspecialchars($prefilledClient, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="abogado">Abogado</label>
                                    <select name="abogado" id="abogado">
                                        <option value="Dr. García">Dr. García</option>
                                        <option value="Dra. Martínez">Dra. Martínez</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <div class="form-group">
                                    <label for="documento">Carga de Documentos</label>
                                    <div class="document-box" id="document-drop-zone" style="cursor:pointer;">
                                        <div class="upload-icon"><i class="fa-regular fa-file-lines"></i></div>
                                        <label for="documento" class="upload-link" style="cursor:pointer;">Seleccionar o arrastrar PDF/Word</label>
                                        <input type="file" id="documento" name="documento" accept=".pdf,.doc,.docx" style="display:none;">
                                        <span id="documento-nombre" style="font-size:0.8rem;color:#666;">Ningún documento seleccionado. Máximo 10 MB.</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Firma Digital (Opcional)</label>
                                    <label class="switch-control">
                                        <span>¿Desea solicitar firma digital?</span>
                                        <span><input type="checkbox" name="firma_digital" value="1" checked><span class="toggle"></span></span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label for="signature-pad">Firma manuscrita (opcional)</label>
                                    <div class="signature-pad">
                                        <canvas id="signature-pad" width="640" height="180" aria-label="Área para dibujar la firma"></canvas>
                                        <div class="signature-pad-footer">
                                            <span id="signature-status">Dibuja con el mouse o el dedo</span>
                                            <button class="signature-clear" id="clear-signature" type="button">Limpiar</button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="firma_data" id="firma-data">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Fechas</label>
                            <div class="date-row">
                                <div>
                                    <input type="date" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div>
                                    <input type="date" name="fecha_fin">
                                </div>
                                <div>
                                    <label class="switch-control">
                                        <span>Renovación Automática</span>
                                        <span><input type="checkbox" name="renovacion_automatica" value="1" checked><span class="toggle"></span></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Alerta</label>
                            <div class="alert-options">
                                <label class="alert-checkbox"><input type="checkbox" name="alerta_activa" value="1" checked> Activar alertas</label>
                                <div class="form-group">
                                    <label for="alerta_frecuencia">Frecuencia</label>
                                    <select name="alerta_frecuencia" id="alerta_frecuencia">
                                        <option value="una_vez">Una vez</option>
                                        <option value="diaria">Diaria</option>
                                        <option value="semanal">Semanal</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="alerta_anticipacion">Avisar antes</label>
                                    <select name="alerta_anticipacion" id="alerta_anticipacion">
                                        <option value="1 hora">1 hora</option>
                                        <option value="1 día">1 día</option>
                                        <option value="1 semana">1 semana</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estado_actual">Estado Actual</label>
                            <select name="estado_actual" id="estado_actual">
                                <option value="Borrador">Borrador</option>
                                <option value="En revisión">En revisión</option>
                                <option value="Firmado">Firmado</option>
                            </select>
                        </div>

                        <input type="hidden" name="save_mode" id="save_mode" value="finalizar">

                        <div class="btn-row">
                            <button type="submit" class="btn secondary" onclick="document.getElementById('save_mode').value='borrador';">Guardar Borrador</button>
                            <button type="submit" class="btn primary" onclick="document.getElementById('save_mode').value='finalizar';">Finalizar y Guardar Contrato</button>
                        </div>
                    </form>
                </section>

                <aside class="panel table-panel">
                    <h3>Mis Contratos Recientes</h3>
                    <div class="btn-row" style="margin:0 0 12px;">
                        <a class="btn primary" href="exportar.php?tipo=contratos&amp;formato=pdf" style="text-decoration:none;">Descargar PDF</a>
                        <a class="btn secondary" href="exportar.php?tipo=contratos&amp;formato=csv" style="text-decoration:none;">Descargar CSV</a>
                    </div>
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Documento</th>
                                <th>Firma</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$contracts): ?>
                                <tr><td colspan="7">Todavía no hay contratos guardados.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($contracts, 0, 5) as $index => $contract): ?>
                                    <tr>
                                        <td><?php echo str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($contract['cliente'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($contract['tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($contract['fecha_inicio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($contract['estado'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if (!empty($contract['archivo'])): ?>
                                                <a href="descargar_documento.php?archivo=<?php echo rawurlencode($contract['archivo']); ?>">Descargar</a>
                                            <?php else: ?>
                                                Sin adjunto
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($contract['firma_archivo'])): ?>
                                                <a href="descargar_documento.php?archivo=<?php echo rawurlencode($contract['firma_archivo']); ?>">Ver firma</a>
                                            <?php else: ?>
                                                Sin firma
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </aside>
            </div>
        </div>
    </main>
    <script>
        const documentInput = document.getElementById('documento');
        const dropZone = document.getElementById('document-drop-zone');
        const fileName = document.getElementById('documento-nombre');
        const contractForm = documentInput.form;
        const signatureCanvas = document.getElementById('signature-pad');
        const signatureContext = signatureCanvas.getContext('2d');
        const signatureData = document.getElementById('firma-data');
        const signatureStatus = document.getElementById('signature-status');
        let isDrawing = false;
        let signatureHasInk = false;

        signatureContext.strokeStyle = '#171717';
        signatureContext.lineWidth = 2.5;
        signatureContext.lineCap = 'round';
        signatureContext.lineJoin = 'round';

        const signaturePoint = (event) => {
            const bounds = signatureCanvas.getBoundingClientRect();
            return {
                x: (event.clientX - bounds.left) * signatureCanvas.width / bounds.width,
                y: (event.clientY - bounds.top) * signatureCanvas.height / bounds.height
            };
        };

        signatureCanvas.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            signatureCanvas.setPointerCapture(event.pointerId);
            const point = signaturePoint(event);
            signatureContext.beginPath();
            signatureContext.moveTo(point.x, point.y);
            isDrawing = true;
            signatureHasInk = true;
            signatureStatus.textContent = 'Firma capturada';
        });
        signatureCanvas.addEventListener('pointermove', (event) => {
            if (!isDrawing) return;
            const point = signaturePoint(event);
            signatureContext.lineTo(point.x, point.y);
            signatureContext.stroke();
        });
        ['pointerup', 'pointercancel'].forEach((eventName) => {
            signatureCanvas.addEventListener(eventName, () => { isDrawing = false; });
        });
        document.getElementById('clear-signature').addEventListener('click', () => {
            signatureContext.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            signatureHasInk = false;
            signatureData.value = '';
            signatureStatus.textContent = 'Dibuja con el mouse o el dedo';
        });

        const showSelectedFile = () => {
            const file = documentInput.files[0];
            fileName.style.color = '#666';
            fileName.textContent = file
                ? `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`
                : 'Ningún documento seleccionado. Máximo 10 MB.';
        };

        documentInput.addEventListener('change', showSelectedFile);
        dropZone.addEventListener('click', (event) => {
            if (event.target !== documentInput && !event.target.closest('label')) documentInput.click();
        });
        ['dragenter', 'dragover'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropZone.style.borderColor = '#9a1d32';
            });
        });
        ['dragleave', 'drop'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropZone.style.borderColor = '';
            });
        });
        dropZone.addEventListener('drop', (event) => {
            if (event.dataTransfer.files.length) {
                documentInput.files = event.dataTransfer.files;
                showSelectedFile();
            }
        });
        contractForm.addEventListener('submit', (event) => {
            const file = documentInput.files[0];
            const extension = file ? file.name.split('.').pop().toLowerCase() : '';
            if (file && !['pdf', 'doc', 'docx'].includes(extension)) {
                event.preventDefault();
                fileName.textContent = 'Formato no permitido. Selecciona PDF, DOC o DOCX.';
                fileName.style.color = '#a42032';
            } else if (file && file.size > 10 * 1024 * 1024) {
                event.preventDefault();
                fileName.textContent = 'El archivo supera el máximo de 10 MB.';
                fileName.style.color = '#a42032';
            } else if (signatureHasInk) {
                signatureData.value = signatureCanvas.toDataURL('image/png');
            }
        });
    </script>
</body>
</html>