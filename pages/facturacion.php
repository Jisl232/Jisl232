<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';
$invoiceRecords = app_data_read('facturas');
$payments = app_data_read('pagos');
$clients = app_data_read('clientes');
$cases = app_data_read('casos');
$contracts = app_data_read('contratos');
$settingsRecords = app_data_read('configuracion');
$billingSettings = $settingsRecords ? end($settingsRecords) : [];
$defaultTaxRate = (float)($billingSettings['iva_porcentaje'] ?? 16);
$paidByInvoice = [];
foreach ($payments as $payment) {
  $invoiceNumber = $payment['numero_factura'] ?? '';
  $paidByInvoice[$invoiceNumber] = ($paidByInvoice[$invoiceNumber] ?? 0) + (float)($payment['importe'] ?? 0);
}

$invoices = [];
$today = new DateTimeImmutable('today');
foreach ($invoiceRecords as $index => $invoice) {
  $invoice['numero'] = $invoice['numero'] ?? 'LEG-' . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);
  $invoiceTotal = (float)($invoice['total'] ?? $invoice['importe'] ?? 0);
  $amountPaid = (float)($paidByInvoice[$invoice['numero']] ?? 0);
  $invoice['total'] = $invoiceTotal;
  $invoice['importe'] = $invoiceTotal;
  $invoice['pagado'] = $amountPaid;
  $invoice['saldo'] = max(0, round($invoiceTotal - $amountPaid, 2));

  $storedStatus = $invoice['estado'] ?? 'Pendiente';
  if ($storedStatus === 'Borrador' || $storedStatus === 'Anulada') {
    $invoice['estado_calculado'] = $storedStatus;
  } elseif ($invoiceTotal > 0 && $invoice['saldo'] <= 0.001) {
    $invoice['estado_calculado'] = 'Pagada';
  } elseif ($storedStatus === 'Pagada') {
    $invoice['estado_calculado'] = 'Pagada';
  } elseif (!empty($invoice['fecha_vencimiento']) && $invoice['fecha_vencimiento'] < $today->format('Y-m-d')) {
    $invoice['estado_calculado'] = 'Vencida';
  } else {
    $invoice['estado_calculado'] = 'Pendiente';
  }
  $invoices[] = $invoice;
}

$issuedInvoices = array_values(array_filter($invoices, static fn($invoice) => ($invoice['estado_calculado'] ?? '') !== 'Borrador'));
$payableInvoices = array_values(array_filter($invoices, static fn($invoice) => !in_array($invoice['estado_calculado'] ?? '', ['Borrador', 'Anulada'], true) && ($invoice['saldo'] ?? 0) > 0));
$invoiceCount = count($issuedInvoices);
$totalCollected = array_sum(array_map(static fn($payment) => (float)($payment['importe'] ?? 0), $payments));
$totalOutstanding = array_sum(array_map(static fn($invoice) => in_array($invoice['estado_calculado'] ?? '', ['Borrador', 'Anulada'], true) ? 0 : (float)($invoice['saldo'] ?? 0), $invoices));
$collectionBase = $totalCollected + $totalOutstanding;
$collectionRate = $collectionBase > 0 ? min(100, (int)round($totalCollected / $collectionBase * 100)) : 0;
$clientFilter = trim($_GET['cliente'] ?? '');
$filteredInvoices = array_values(array_filter($issuedInvoices, static fn($invoice) => $clientFilter === '' || ($invoice['cliente'] ?? '') === $clientFilter));

$currentMonth = new DateTimeImmutable('first day of this month');
$monthShortNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$monthlyRevenue = [];
for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
  $month = $currentMonth->modify('-' . $monthOffset . ' months');
  $monthlyRevenue[$month->format('Y-m')] = ['label' => ucfirst($monthShortNames[(int)$month->format('n') - 1]), 'total' => 0.0];
}
$previousSixMonths = 0.0;
foreach ($payments as $payment) {
  $paymentMonth = substr($payment['fecha'] ?? '', 0, 7);
  if (isset($monthlyRevenue[$paymentMonth])) {
    $monthlyRevenue[$paymentMonth]['total'] += (float)($payment['importe'] ?? 0);
  }
  $previousStart = $currentMonth->modify('-11 months')->format('Y-m');
  $previousEnd = $currentMonth->modify('-6 months')->format('Y-m');
  if ($paymentMonth >= $previousStart && $paymentMonth <= $previousEnd) {
    $previousSixMonths += (float)($payment['importe'] ?? 0);
  }
}
$currentSixMonths = array_sum(array_column($monthlyRevenue, 'total'));
$semiannualGrowth = $previousSixMonths > 0 ? (int)round(($currentSixMonths - $previousSixMonths) / $previousSixMonths * 100) : 0;
$monthlyMaximum = max(1, ...array_column($monthlyRevenue, 'total'));
$currentMonthIncome = $monthlyRevenue[$currentMonth->format('Y-m')]['total'] ?? 0;
$clientNames = array_values(array_unique(array_filter(array_merge(
  array_map(static fn($client) => trim($client['nombre'] ?? ''), $clients),
  array_map(static fn($invoice) => trim($invoice['cliente'] ?? ''), $invoices)
))));
$statusMessage = [
  'invoice_saved' => 'Factura emitida y guardada en la base de datos.',
  'draft_saved' => 'Borrador guardado en la base de datos.',
  'payment_saved' => 'Pago registrado correctamente.',
  'error' => 'No se guardó la operación. Revisa los importes, la factura y los datos obligatorios.',
][$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facturación y Reportes</title>
    <style>
    :root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#1a1a1a; --muted:#707070; --line:#dedede; --surface:#fff; --page:#eef0f2; --gold:#c49a44; --green:#56a778; --red:#d96468; --gray:#888; }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
    .main { min-height:100vh; margin-left:260px; padding:10px 14px 18px; }
    .topbar { display:flex; align-items:center; justify-content:space-between; min-height:34px; margin-bottom:8px; }
    .topbar-title { font-size:.82rem; font-weight:600; }
    .topbar-actions { display:flex; align-items:center; gap:12px; }
    .topbar-actions a { color:var(--ink); text-decoration:none; }
    .page-title { margin:0 0 10px; font-size:1.45rem; font-weight:800; }
    .status-message { margin:0 0 10px; padding:9px 12px; border:1px solid #e5c1c7; border-radius:7px; background:#fff8f9; color:var(--wine-900); font-size:.82rem; }
    .metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:9px; margin-bottom:10px; }
    .metric { display:flex; align-items:center; gap:9px; min-height:60px; padding:9px 11px; border:1px solid var(--line); border-radius:9px; background:var(--surface); box-shadow:0 2px 5px rgba(0,0,0,.08); }
    .metric-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:8px; background:#f4e9c8; color:#9b7627; }
    .metric:nth-child(3) .metric-icon,.metric:nth-child(4) .metric-icon { background:#f6e2e3; color:var(--wine-700); }
    .metric-label { display:block; color:#555; font-size:.68rem; }
    .metric-value { display:block; margin-top:2px; font-size:1.15rem; font-weight:800; }
    .layout { display:grid; grid-template-columns:minmax(210px,.78fr) minmax(220px,.78fr) minmax(390px,1.5fr); gap:10px; align-items:start; }
    .column { display:grid; gap:10px; min-width:0; }
    .panel { min-width:0; padding:11px; border:1px solid var(--line); border-radius:9px; background:var(--surface); box-shadow:0 2px 6px rgba(0,0,0,.09); }
    .panel h2 { margin:0 0 9px; font-size:.9rem; font-weight:800; }
    .panel-title-row { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px; }
    .panel-title-row h2 { margin:0; }
    .field { min-width:0; margin-bottom:8px; }
    .field label { display:block; margin-bottom:4px; font-size:.69rem; font-weight:600; }
    .field input,.field select { width:100%; min-height:32px; padding:6px 8px; border:1px solid #d2d2d2; border-radius:6px; background:#fff; color:var(--ink); font:inherit; font-size:.75rem; }
    .field input:focus,.field select:focus { outline:2px solid rgba(151,27,49,.18); border-color:var(--wine-700); }
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .summary-row { display:flex; justify-content:space-between; gap:12px; padding:6px 2px; border-bottom:1px solid #ededed; font-size:.75rem; }
    .summary-row.total { margin-top:3px; border-bottom:0; font-weight:800; font-size:.9rem; }
    .summary-row span:last-child { font-variant-numeric:tabular-nums; }
    .table-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:.68rem; }
    th,td { padding:7px 5px; border-bottom:1px solid #e7e7e7; text-align:left; white-space:nowrap; }
    th { color:#555; font-weight:800; }
    .status { display:inline-flex; align-items:center; gap:5px; padding:4px 7px; border-radius:99px; background:#f7edcf; color:#6e581a; font-size:.62rem; font-weight:700; }
    .status::before { width:7px; height:7px; border-radius:50%; background:var(--gold); content:''; }
    .status.pagada { background:#e5f3e9; color:#27683d; }
    .status.pagada::before { background:var(--green); }
    .status.vencida,.status.anulada { background:#f9e5e5; color:#8a2d35; }
    .status.vencida::before,.status.anulada::before { background:var(--red); }
    .status.borrador { background:#ededed; color:#555; }
    .status.borrador::before { background:var(--gray); }
    .filter select { max-width:170px; min-height:30px; padding:4px 7px; border:1px solid var(--line); border-radius:6px; background:#fff; font:inherit; font-size:.68rem; }
    .chart { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); align-items:end; gap:7px; height:118px; padding:9px 4px 0; border-bottom:1px solid var(--line); background:repeating-linear-gradient(to bottom,transparent 0,transparent 28px,#ededed 29px,#ededed 30px); }
    .chart-item { display:flex; flex-direction:column; align-items:center; justify-content:end; height:100%; gap:4px; color:#666; font-size:.6rem; }
    .chart-bar { width:min(25px,75%); min-height:3px; border-radius:4px 4px 0 0; background:linear-gradient(180deg,#cfaa55,#9b7627); }
    .chart-stats { display:flex; justify-content:space-between; gap:12px; margin-top:9px; }
    .chart-stats span { display:block; color:var(--muted); font-size:.66rem; }
    .chart-stats strong { display:block; margin-top:3px; font-size:.95rem; }
    .actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
    .button { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:33px; padding:7px 10px; border:1px solid transparent; border-radius:6px; background:linear-gradient(100deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.7rem; font-weight:700; text-decoration:none; cursor:pointer; }
    .button.secondary { border-color:#cfcfcf; background:#fff; color:var(--ink); }
    .button:hover { filter:brightness(1.08); }
    .button:disabled { cursor:not-allowed; opacity:.48; }
    .notice { margin:3px 0 0; color:var(--muted); font-size:.68rem; }
    .empty { padding:16px 8px; color:var(--muted); text-align:center; font-size:.75rem; }
    @media (max-width:1180px) { .layout { grid-template-columns:repeat(2,minmax(0,1fr)); } .history-column { grid-column:1/-1; } }
    @media (max-width:700px) { .main { margin-left:0; padding:10px; } .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .layout { grid-template-columns:1fr; } .history-column { grid-column:auto; } }
    </style>
</head>
<body>
<?php include_once '../includes/sidebar.php'; ?>
<div class="main">
  <header class="topbar">
    <span class="topbar-title">Sistema Legal - Facturación y Reportes de Ingresos</span>
    <div class="topbar-actions"><a href="../dashboard.php" aria-label="Volver al escritorio" title="Volver al escritorio"><i class="fa-solid fa-house"></i></a></div>
  </header>
  <h1 class="page-title">Gestión de Facturación y Reportes de Ingresos</h1>
  <?php if ($statusMessage !== ''): ?><p class="status-message" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="metrics" aria-label="Indicadores de facturación">
    <div class="metric"><span class="metric-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span><span><span class="metric-label">Facturas Totales</span><strong class="metric-value"><?php echo $invoiceCount; ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-solid fa-money-bill-wave"></i></span><span><span class="metric-label">Total Recaudado</span><strong class="metric-value">$<?php echo number_format($totalCollected, 2); ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-regular fa-clock"></i></span><span><span class="metric-label">Pendiente de Cobro</span><strong class="metric-value">$<?php echo number_format($totalOutstanding, 2); ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-solid fa-chart-line"></i></span><span><span class="metric-label">Tasa de Cobranza</span><strong class="metric-value"><?php echo $collectionRate; ?>%</strong></span></div>
  </section>

  <div class="layout">
    <section class="panel">
      <h2>Generación de Factura</h2>
      <form id="invoice-form" action="guardar_accion.php" method="post">
        <input type="hidden" name="action" value="factura">
        <input type="hidden" name="return_to" value="facturacion.php">
        <div class="field">
          <label for="caso-contrato">1. Asociar a Caso / Contrato</label>
          <select id="caso-contrato" name="caso_contrato">
            <option value="">Sin asociación</option>
            <?php foreach ($cases as $case): ?>
              <?php $caseLabel = 'Caso ' . ($case['numero'] ?? '') . ' · ' . ($case['nombre'] ?? ''); ?>
              <option value="<?php echo htmlspecialchars($caseLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($caseLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
            <?php foreach ($contracts as $contract): ?>
              <?php $contractLabel = 'Contrato · ' . ($contract['cliente'] ?? '') . ' · ' . ($contract['tipo'] ?? ''); ?>
              <option value="<?php echo htmlspecialchars($contractLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contractLabel, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="invoice-client">Cliente</label>
          <input id="invoice-client" name="cliente" list="client-options" placeholder="Nombre del cliente" required>
          <datalist id="client-options">
            <?php foreach ($clientNames as $clientName): ?><option value="<?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="field">
          <label for="fee-base">2. Cálculo de Honorarios</label>
          <label for="fee-base">Honorarios Base</label>
          <input id="fee-base" name="base" type="number" min="0.01" step="0.01" value="0.00" required>
        </div>
        <div class="two-col">
                    <div class="field"><label for="tax-rate">IVA (%)</label><input id="tax-rate" name="iva_porcentaje" type="number" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars(number_format($defaultTaxRate, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required></div>
          <div class="field"><label for="discount">Descuento</label><input id="discount" name="descuento" type="number" min="0" step="0.01" value="0" required></div>
        </div>
        <div class="field"><label for="invoice-state">3. Estado Actual</label><select id="invoice-state" name="estado"><option value="Pendiente">Pendiente</option><option value="Anulada">Anulada</option></select></div>
        <button class="button" type="submit" name="save_mode" value="emitir"><i class="fa-solid fa-file-circle-plus"></i> Generar Factura</button>
      </form>
    </section>

    <div class="column">
      <section class="panel">
        <h2>Resumen de Factura Actual</h2>
        <div class="summary-row"><span>Honorarios Base</span><strong id="summary-base">$0.00</strong></div>
        <div class="summary-row"><span>IVA (<span id="summary-tax-rate">16</span>%)</span><strong id="summary-tax">$0.00</strong></div>
        <div class="summary-row"><span>Descuento</span><strong id="summary-discount">-$0.00</strong></div>
        <div class="summary-row total"><span>Total</span><strong id="summary-total">$0.00</strong></div>
      </section>

      <section class="panel">
        <h2>Registro de Pagos</h2>
        <?php if ($payableInvoices): ?>
          <form action="guardar_accion.php" method="post" id="payment-form">
            <input type="hidden" name="action" value="pago">
            <input type="hidden" name="return_to" value="facturacion.php">
            <div class="field"><label for="payment-invoice">Factura pendiente</label><select id="payment-invoice" name="numero_factura" required>
              <?php foreach ($payableInvoices as $invoice): ?><option value="<?php echo htmlspecialchars($invoice['numero'], ENT_QUOTES, 'UTF-8'); ?>" data-balance="<?php echo htmlspecialchars((string)$invoice['saldo'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($invoice['numero'] . ' · ' . $invoice['cliente'] . ' · saldo $' . number_format($invoice['saldo'], 2), ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
            </select></div>
            <div class="two-col">
              <div class="field"><label for="payment-amount">Monto</label><input id="payment-amount" name="importe" type="number" min="0.01" step="0.01" required></div>
              <div class="field"><label for="payment-date">Fecha</label><input id="payment-date" name="fecha" type="date" value="<?php echo date('Y-m-d'); ?>" required></div>
            </div>
            <div class="field"><label for="payment-method">Forma de Pago</label><select id="payment-method" name="metodo" required><option>Efectivo</option><option>Transferencia</option><option>Tarjeta</option></select></div>
            <button class="button" type="submit"><i class="fa-solid fa-circle-check"></i> Registrar Pago</button>
          </form>
        <?php else: ?>
          <p class="notice">Emite una factura pendiente para habilitar el registro de pagos.</p>
        <?php endif; ?>
      </section>
    </div>

    <div class="column history-column">
      <section class="panel">
        <div class="panel-title-row">
          <h2>Historial de Facturas y Reportes</h2>
          <form class="filter" action="facturacion.php" method="get">
            <label for="client-filter">Filtrar:</label>
            <select id="client-filter" name="cliente" onchange="this.form.submit()">
              <option value="">Todos los clientes</option>
              <?php foreach ($clientNames as $clientName): ?><option value="<?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $clientFilter === $clientName ? 'selected' : ''; ?>><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
            </select>
          </form>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>ID</th><th>Cliente</th><th>Caso/Contrato</th><th>Fecha</th><th>Total</th><th>Estado</th></tr></thead>
            <tbody>
              <?php if (!$filteredInvoices): ?><tr><td class="empty" colspan="6">No hay facturas para este filtro.</td></tr>
              <?php else: foreach (array_slice(array_reverse($filteredInvoices), 0, 30) as $invoice): ?>
                <?php $statusClass = strtolower(str_replace(' ', '', $invoice['estado_calculado'])); ?>
                <tr>
                  <td><?php echo htmlspecialchars($invoice['numero'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($invoice['cliente'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($invoice['caso_contrato'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($invoice['fecha'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>$<?php echo number_format((float)$invoice['total'], 2); ?></td>
                  <td><span class="status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($invoice['estado_calculado'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="panel">
        <div class="panel-title-row"><h2>Reportes de Ingresos</h2><a class="button secondary" href="exportar.php?tipo=facturacion&amp;formato=pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a></div>
        <div class="chart" aria-label="Ingresos de los últimos seis meses">
          <?php foreach ($monthlyRevenue as $month): ?>
            <div class="chart-item" title="$<?php echo number_format($month['total'], 2); ?>"><div class="chart-bar" style="height:<?php echo max(3, (int)round($month['total'] / $monthlyMaximum * 88)); ?>%;"></div><span><?php echo htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8'); ?></span></div>
          <?php endforeach; ?>
        </div>
        <div class="chart-stats">
          <div><span>Ingresos este mes</span><strong>$<?php echo number_format($currentMonthIncome, 2); ?></strong></div>
          <div><span>Crecimiento semestral</span><strong><?php echo $semiannualGrowth > 0 ? '+' : ''; ?><?php echo $semiannualGrowth; ?>%</strong></div>
        </div>
      </section>
    </div>
  </div>

  <section class="panel actions" aria-label="Botones de acción">
    <button class="button secondary" type="submit" form="invoice-form" name="save_mode" value="draft"><i class="fa-regular fa-floppy-disk"></i> Guardar Datos</button>
    <a class="button" href="exportar.php?tipo=facturacion&amp;formato=pdf"><i class="fa-solid fa-download"></i> Descargar Reporte Completo</a>
    <a class="button secondary" href="exportar.php?tipo=facturacion&amp;formato=csv" aria-label="Descargar reporte CSV" title="Descargar CSV"><i class="fa-solid fa-file-csv"></i></a>
  </section>
</div>
<script>
  const baseInput = document.getElementById('fee-base');
  const taxInput = document.getElementById('tax-rate');
  const discountInput = document.getElementById('discount');
  const currency = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'USD' });
  const updateInvoiceSummary = () => {
    const base = Math.max(0, Number(baseInput.value) || 0);
    const taxRate = Math.max(0, Math.min(100, Number(taxInput.value) || 0));
    const discount = Math.max(0, Number(discountInput.value) || 0);
    const tax = Math.round(base * taxRate) / 100;
    const total = Math.max(0, base + tax - discount);
    document.getElementById('summary-base').textContent = currency.format(base);
    document.getElementById('summary-tax-rate').textContent = taxRate.toFixed(2).replace(/\.00$/, '');
    document.getElementById('summary-tax').textContent = currency.format(tax);
    document.getElementById('summary-discount').textContent = '-' + currency.format(discount);
    document.getElementById('summary-total').textContent = currency.format(total);
    discountInput.max = (base + tax).toFixed(2);
  };
  [baseInput, taxInput, discountInput].forEach((input) => input.addEventListener('input', updateInvoiceSummary));
  updateInvoiceSummary();

  const paymentInvoice = document.getElementById('payment-invoice');
  const paymentAmount = document.getElementById('payment-amount');
  if (paymentInvoice && paymentAmount) {
    const updatePaymentBalance = () => {
      const balance = Number(paymentInvoice.selectedOptions[0]?.dataset.balance || 0);
      paymentAmount.max = balance.toFixed(2);
      paymentAmount.placeholder = `Máximo ${currency.format(balance)}`;
    };
    paymentInvoice.addEventListener('change', updatePaymentBalance);
    updatePaymentBalance();
  }
</script>
</body>
</html>
