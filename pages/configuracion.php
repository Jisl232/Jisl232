<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';
$savedConfigurations = app_data_read('configuracion');
$configuration = $savedConfigurations ? end($savedConfigurations) : [];
$database = app_data_connection();
$databaseName = $database->query('SELECT DATABASE()')->fetchColumn();
$storedRecords = (int)$database->query('SELECT COUNT(*) FROM app_records')->fetchColumn();
$statusMessage = [
  'success' => 'Configuración guardada correctamente y aplicada a los módulos.',
  'error' => 'No se guardó la configuración. Revisa correo, IVA, prefijo y plazo de pago.',
][$_GET['status'] ?? ''] ?? '';
$templateLabels = [
  'arrendamiento' => 'Arrendamiento',
  'honorarios' => 'Honorarios profesionales',
  'servicios' => 'Servicios generales',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuración del despacho</title>
    <style>
    :root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#191919; --muted:#707070; --line:#dedede; --page:#eef0f2; --surface:#fff; --green:#2f8151; }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
    .main { min-height:100vh; margin-left:260px; padding:20px; }
    .page-head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:15px; }
    .eyebrow { color:var(--wine-800); font-size:.72rem; font-weight:700; text-transform:uppercase; }
    h1 { margin:4px 0 0; font-size:1.7rem; }
    h2 { margin:0 0 12px; font-size:1rem; }
    .layout { display:grid; grid-template-columns:minmax(0,1.6fr) minmax(260px,.8fr); gap:14px; align-items:start; }
    .panel { min-width:0; padding:16px; border:1px solid var(--line); border-radius:9px; background:var(--surface); box-shadow:0 2px 6px rgba(0,0,0,.06); }
    .section { padding:0 0 14px; margin-bottom:14px; border-bottom:1px solid #e8e8e8; }
    .section:last-of-type { margin-bottom:0; border-bottom:0; }
    .section-heading { display:flex; align-items:center; gap:9px; margin-bottom:11px; }
    .section-heading i { color:var(--wine-700); }
    .section-heading h2 { margin:0; }
    .fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px 13px; }
    .field.full { grid-column:1/-1; }
    .field label { display:block; margin-bottom:5px; color:#505050; font-size:.72rem; font-weight:700; }
    .field input,.field select,.field textarea { width:100%; min-height:37px; padding:8px 10px; border:1px solid #d1d1d1; border-radius:6px; background:#fff; color:var(--ink); font:inherit; font-size:.8rem; }
    .field textarea { min-height:72px; resize:vertical; }
    .field input:focus,.field select:focus,.field textarea:focus { outline:2px solid rgba(151,27,49,.18); border-color:var(--wine-700); }
    .help { margin:5px 0 0; color:var(--muted); font-size:.68rem; }
    .button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:36px; padding:8px 12px; border:1px solid transparent; border-radius:7px; background:linear-gradient(110deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.76rem; font-weight:700; text-decoration:none; cursor:pointer; }
    .button.secondary { border-color:#d1d1d1; background:#fff; color:var(--ink); }
    .form-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:15px; }
    .status-message { margin:0 0 13px; padding:10px 12px; border:1px solid #e5c1c7; border-radius:7px; background:#fff8f9; color:var(--wine-900); font-size:.8rem; }
    .connection { display:flex; align-items:center; gap:8px; margin-bottom:14px; padding:10px; border-radius:7px; background:#eaf5ee; color:#245c3a; font-size:.77rem; }
    .connection i { color:var(--green); }
    .connection span { margin-left:auto; font-variant-numeric:tabular-nums; }
    .backup-copy { margin:0 0 12px; color:var(--muted); font-size:.76rem; line-height:1.55; }
    .quick-link { display:flex; align-items:center; gap:9px; padding:10px 0; border-bottom:1px solid #ededed; color:var(--ink); font-size:.78rem; text-decoration:none; }
    .quick-link:last-child { border-bottom:0; }
    .quick-link i { width:18px; color:var(--wine-700); text-align:center; }
    @media (max-width:900px) { .layout { grid-template-columns:1fr; } }
    @media (max-width:700px) { .main { margin-left:0; padding:12px; } }
    @media (max-width:480px) { .fields { grid-template-columns:1fr; } .field.full { grid-column:auto; } .page-head { align-items:flex-start; flex-direction:column; } .form-actions { flex-direction:column; } }
    </style>
</head>
<body>
<?php include_once '../includes/sidebar.php'; ?>
<div class="main">
  <header class="page-head">
    <div><span class="eyebrow">Preferencias del sistema</span><h1>Configuración del despacho</h1></div>
    <a class="button secondary" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Escritorio</a>
  </header>
  <?php if ($statusMessage !== ''): ?><p class="status-message" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <div class="layout">
    <form class="panel" action="guardar_accion.php" method="post">
      <input type="hidden" name="action" value="configuracion">
      <input type="hidden" name="return_to" value="configuracion.php">
      <input type="hidden" name="settings_scope" value="full">

      <section class="section">
        <div class="section-heading"><i class="fa-solid fa-building"></i><h2>Datos del despacho</h2></div>
        <div class="fields">
          <div class="field"><label for="office-name">Nombre del despacho</label><input id="office-name" name="despacho" value="<?php echo htmlspecialchars($configuration['despacho'] ?? 'Bufete de Abogados', ENT_QUOTES, 'UTF-8'); ?>" required></div>
          <div class="field"><label for="tax-id">Identificación fiscal</label><input id="tax-id" name="identificacion_fiscal" value="<?php echo htmlspecialchars($configuration['identificacion_fiscal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div class="field"><label for="office-email">Correo institucional</label><input id="office-email" name="correo" type="email" value="<?php echo htmlspecialchars($configuration['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div class="field"><label for="office-phone">Teléfono</label><input id="office-phone" name="telefono" type="tel" value="<?php echo htmlspecialchars($configuration['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div class="field full"><label for="office-address">Dirección</label><input id="office-address" name="direccion" value="<?php echo htmlspecialchars($configuration['direccion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
      </section>

      <section class="section">
        <div class="section-heading"><i class="fa-solid fa-file-invoice-dollar"></i><h2>Facturación</h2></div>
        <div class="fields">
          <div class="field"><label for="default-tax">IVA predeterminado (%)</label><input id="default-tax" name="iva_porcentaje" type="number" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars((string)($configuration['iva_porcentaje'] ?? 16), ENT_QUOTES, 'UTF-8'); ?>" required><p class="help">Se aplicará al crear la próxima factura.</p></div>
          <div class="field"><label for="payment-term">Plazo de pago (días)</label><input id="payment-term" name="plazo_pago_dias" type="number" min="1" max="365" step="1" value="<?php echo htmlspecialchars((string)($configuration['plazo_pago_dias'] ?? 30), ENT_QUOTES, 'UTF-8'); ?>" required><p class="help">Define el vencimiento de nuevas facturas.</p></div>
          <div class="field"><label for="invoice-prefix">Prefijo de factura</label><input id="invoice-prefix" name="prefijo_factura" maxlength="8" pattern="[A-Za-z0-9-]{1,8}" value="<?php echo htmlspecialchars($configuration['prefijo_factura'] ?? 'F', ENT_QUOTES, 'UTF-8'); ?>" required><p class="help">Ejemplo: F, BUF o LEGAL.</p></div>
        </div>
      </section>

      <section class="section">
        <div class="section-heading"><i class="fa-solid fa-file-signature"></i><h2>Plantilla predeterminada</h2></div>
        <div class="field">
          <label for="contract-template">Tipo de contrato al abrir el formulario</label>
          <select id="contract-template" name="plantilla_contrato">
            <?php foreach ($templateLabels as $templateKey => $templateLabel): ?>
              <option value="<?php echo htmlspecialchars($templateKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($configuration['plantilla_contrato'] ?? 'arrendamiento') === $templateKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($templateLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <p class="help">Esta selección se aplicará al crear un contrato nuevo.</p>
        </div>
      </section>

      <div class="form-actions">
        <button class="button" type="submit"><i class="fa-regular fa-floppy-disk"></i> Guardar configuración</button>
      </div>
    </form>

    <aside>
      <section class="panel">
        <h2>Conexión de datos</h2>
        <div class="connection"><i class="fa-solid fa-circle-check"></i><strong>MySQL conectado</strong><span><?php echo htmlspecialchars((string)$databaseName, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <p class="backup-copy">Hay <?php echo number_format($storedRecords); ?> registros almacenados en la base. El respaldo JSON incluye datos de los módulos; excluye credenciales y archivos adjuntos guardados fuera de MySQL.</p>
        <a class="button" href="exportar.php?tipo=respaldo&amp;formato=json"><i class="fa-solid fa-download"></i> Descargar respaldo JSON</a>
      </section>
      <section class="panel" style="margin-top:12px;">
        <h2>Accesos rápidos</h2>
        <a class="quick-link" href="facturacion.php"><i class="fa-solid fa-file-invoice-dollar"></i> Ajustes aplicados a facturación</a>
        <a class="quick-link" href="contractos.php"><i class="fa-solid fa-file-contract"></i> Abrir contratos</a>
        <a class="quick-link" href="reportes.php"><i class="fa-solid fa-chart-column"></i> Centro de reportes</a>
      </section>
    </aside>
  </div>
</div>
</body>
</html>
