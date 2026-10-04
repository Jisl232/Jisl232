<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';
$reportTypes = [
  'resumen' => 'Resumen general',
  'clientes' => 'Clientes',
  'casos' => 'Casos',
  'citas' => 'Agenda',
  'contratos' => 'Contratos',
  'facturas' => 'Facturas',
  'pagos' => 'Pagos',
  'facturacion' => 'Facturación e ingresos',
  'actuaciones' => 'Actuaciones',
];
$reportType = is_string($_GET['tipo'] ?? null) && isset($reportTypes[$_GET['tipo']]) ? $_GET['tipo'] : 'resumen';
$normalizeDate = static function ($value): string {
  if (!is_string($value)) return '';
  $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
  return $date && $date->format('Y-m-d') === $value ? $value : '';
};
$startDate = $normalizeDate($_GET['desde'] ?? '') ?: date('Y-m-01');
$endDate = $normalizeDate($_GET['hasta'] ?? '') ?: date('Y-m-d');
if ($startDate > $endDate) [$startDate, $endDate] = [$endDate, $startDate];
$recordDate = static function (array $record): string {
  foreach (['fecha', 'fecha_inicio', 'created_at'] as $key) {
    if (empty($record[$key])) continue;
    $value = substr((string)$record[$key], 0, 19);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($value, 0, 10))) return substr($value, 0, 10);
    $legacyDate = DateTimeImmutable::createFromFormat('!d/m/Y', $value);
    if ($legacyDate) return $legacyDate->format('Y-m-d');
  }
  return '';
};
$dateInRange = static function (array $record) use ($startDate, $endDate, $recordDate): bool {
  $date = $recordDate($record);
  return $date !== '' && $date >= $startDate && $date <= $endDate;
};

$allData = [];
foreach (['clientes', 'casos', 'citas', 'contratos', 'facturas', 'pagos', 'actuaciones'] as $dataType) {
  $allData[$dataType] = app_data_read($dataType);
}
$periodData = [];
foreach ($allData as $dataType => $records) {
  $periodData[$dataType] = array_values(array_filter($records, $dateInRange));
}

$monthlyRevenue = [];
$monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$monthStart = new DateTimeImmutable('first day of this month');
for ($offset = 5; $offset >= 0; $offset--) {
  $month = $monthStart->modify('-' . $offset . ' months');
  $monthlyRevenue[$month->format('Y-m')] = ['label' => ucfirst($monthNames[(int)$month->format('n') - 1]), 'total' => 0.0];
}
foreach ($allData['pagos'] as $payment) {
  $monthKey = substr($payment['fecha'] ?? '', 0, 7);
  if (isset($monthlyRevenue[$monthKey])) $monthlyRevenue[$monthKey]['total'] += (float)($payment['importe'] ?? 0);
}
$chartMaximum = max(1, ...array_column($monthlyRevenue, 'total'));
$periodRevenue = array_sum(array_map(static fn($payment) => (float)($payment['importe'] ?? 0), $periodData['pagos']));
$openCases = count(array_filter($allData['casos'], static fn($case) => strcasecmp($case['estado'] ?? '', 'Abierto') === 0));
$periodRecordCount = array_sum(array_map('count', $periodData));

$makeReportRow = static function (string $type, array $record, int $index) use ($recordDate): array {
  $reference = $record['numero'] ?? $record['numero_factura'] ?? strtoupper(substr($type, 0, 3)) . '-' . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);
  $detail = $record['cliente'] ?? $record['nombre'] ?? $record['titulo'] ?? $record['actuacion'] ?? $record['despacho'] ?? '';
  if ($type === 'contratos') $detail = trim(($record['cliente'] ?? '') . ' · ' . ($record['tipo'] ?? 'Contrato'));
  if ($type === 'pagos') $detail = trim(($record['cliente'] ?? '') . ' · ' . ($record['metodo'] ?? 'Pago'));
  $amount = $record['total'] ?? $record['importe'] ?? '';
  return [
    'Referencia' => $reference,
    'Detalle' => $detail,
    'Fecha' => $recordDate($record),
    'Estado' => $record['estado'] ?? ($type === 'citas' ? 'Programada' : 'Registrado'),
    'Importe' => $amount === '' ? '' : number_format((float)$amount, 2),
  ];
};

$reportRows = [];
if ($reportType === 'resumen') {
  foreach ($periodData as $dataType => $records) {
    $sum = 0.0;
    foreach ($records as $record) $sum += (float)($record['total'] ?? $record['importe'] ?? 0);
    $reportRows[] = [
      'Referencia' => $reportTypes[$dataType] ?? ucfirst($dataType),
      'Detalle' => count($records) . ' registros en el período',
      'Fecha' => '',
      'Estado' => 'Resumen',
      'Importe' => $sum > 0 ? number_format($sum, 2) : '',
    ];
  }
} elseif ($reportType === 'facturacion') {
  foreach ($periodData['facturas'] as $index => $invoice) $reportRows[] = $makeReportRow('facturas', $invoice, $index);
  foreach ($periodData['pagos'] as $index => $payment) $reportRows[] = $makeReportRow('pagos', $payment, $index);
} else {
  foreach ($periodData[$reportType] as $index => $record) $reportRows[] = $makeReportRow($reportType, $record, $index);
}
$reportAmount = 0.0;
foreach ($reportRows as $row) $reportAmount += (float)str_replace(',', '', $row['Importe'] ?? '0');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Centro de Reportes</title>
    <style>
    :root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#191919; --muted:#707070; --line:#dedede; --surface:#fff; --page:#eef0f2; --gold:#bc9442; --green:#438b5c; --blue:#426f9e; }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
    .main { min-height:100vh; margin-left:260px; padding:18px 20px 24px; }
    .page-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
    .eyebrow { color:var(--wine-800); font-size:.72rem; font-weight:700; text-transform:uppercase; }
    h1 { margin:4px 0 0; font-size:1.7rem; }
    h2 { margin:0; font-size:1rem; }
    .button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:36px; padding:8px 12px; border:1px solid transparent; border-radius:7px; background:linear-gradient(110deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.76rem; font-weight:700; text-decoration:none; cursor:pointer; }
    .button.secondary { border-color:#d1d1d1; background:#fff; color:var(--ink); }
    .filters { display:grid; grid-template-columns:minmax(190px,1.4fr) repeat(2,minmax(145px,.8fr)) auto; align-items:end; gap:10px; margin-bottom:14px; padding:12px; border:1px solid var(--line); border-radius:9px; background:#fff; }
    .field label { display:block; margin-bottom:5px; color:#505050; font-size:.7rem; font-weight:700; }
    .field select,.field input { width:100%; min-height:35px; padding:7px 9px; border:1px solid #d3d3d3; border-radius:6px; background:#fff; color:var(--ink); font:inherit; font-size:.78rem; }
    .metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
    .metric { display:flex; align-items:center; gap:11px; min-height:76px; padding:12px; border:1px solid var(--line); border-radius:9px; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,.06); }
    .metric-icon { display:grid; place-items:center; width:38px; height:38px; border-radius:8px; background:#f4e9c8; color:#9b7627; }
    .metric:nth-child(2) .metric-icon { background:#e8eef8; color:var(--blue); }
    .metric:nth-child(3) .metric-icon { background:#e4f2e8; color:var(--green); }
    .metric:nth-child(4) .metric-icon { background:#f5e2e4; color:var(--wine-700); }
    .metric-label { display:block; color:var(--muted); font-size:.68rem; }
    .metric-value { display:block; margin-top:3px; font-size:1.1rem; font-weight:800; }
    .workspace { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(270px,.8fr); gap:14px; align-items:start; }
    .panel { min-width:0; padding:14px; border:1px solid var(--line); border-radius:9px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,.06); }
    .panel-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:10px; }
    .panel-head p { margin:3px 0 0; color:var(--muted); font-size:.7rem; }
    .export-actions { display:flex; gap:7px; }
    .table-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:.74rem; }
    th,td { padding:9px 7px; border-bottom:1px solid #e8e8e8; text-align:left; }
    th { color:#555; font-weight:800; white-space:nowrap; }
    td:last-child,th:last-child { text-align:right; }
    .empty { padding:24px 8px; color:var(--muted); text-align:center; }
    .chart { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); align-items:end; gap:8px; height:156px; padding:10px 4px 0; border-bottom:1px solid var(--line); background:repeating-linear-gradient(to bottom,transparent 0,transparent 37px,#ededed 38px,#ededed 39px); }
    .chart-item { display:flex; flex-direction:column; align-items:center; justify-content:end; height:100%; gap:5px; color:var(--muted); font-size:.65rem; }
    .chart-bar { width:min(28px,75%); min-height:3px; border-radius:4px 4px 0 0; background:linear-gradient(180deg,#c9a44d,#94702b); }
    .insight-list { display:grid; gap:10px; margin-top:14px; }
    .insight { display:flex; align-items:center; justify-content:space-between; gap:12px; padding-bottom:9px; border-bottom:1px solid #ededed; font-size:.76rem; }
    .insight span { color:var(--muted); }
    .quick-links { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:14px; }
    .quick-links a { justify-content:flex-start; }
    @media (max-width:1000px) { .workspace { grid-template-columns:1fr; } }
    @media (max-width:760px) { .main { margin-left:0; padding:12px; } .filters { grid-template-columns:1fr 1fr; } .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:460px) { .page-head { align-items:flex-start; flex-direction:column; } .filters { grid-template-columns:1fr; } .metrics { grid-template-columns:1fr 1fr; } .export-actions { width:100%; } .export-actions .button { flex:1; } }
    </style>
</head>
<body>
<?php include_once '../includes/sidebar.php'; ?>
<div class="main">
  <header class="page-head">
    <div><span class="eyebrow">Análisis operativo</span><h1>Centro de Reportes</h1></div>
    <a class="button secondary" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Escritorio</a>
  </header>

  <form class="filters" method="get" action="reportes.php">
    <div class="field"><label for="report-type">Reporte</label><select id="report-type" name="tipo">
      <?php foreach ($reportTypes as $type => $label): ?><option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $reportType === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label for="date-from">Desde</label><input id="date-from" name="desde" type="date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <div class="field"><label for="date-to">Hasta</label><input id="date-to" name="hasta" type="date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <button class="button" type="submit"><i class="fa-solid fa-filter"></i> Aplicar</button>
  </form>

  <section class="metrics" aria-label="Indicadores del período">
    <div class="metric"><span class="metric-icon"><i class="fa-solid fa-database"></i></span><span><span class="metric-label">Registros consultados</span><strong class="metric-value"><?php echo $periodRecordCount; ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-solid fa-briefcase"></i></span><span><span class="metric-label">Casos abiertos</span><strong class="metric-value"><?php echo $openCases; ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-regular fa-calendar-check"></i></span><span><span class="metric-label">Citas del período</span><strong class="metric-value"><?php echo count($periodData['citas']); ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-solid fa-money-bill-wave"></i></span><span><span class="metric-label">Ingresos recibidos</span><strong class="metric-value">$<?php echo number_format($periodRevenue, 2); ?></strong></span></div>
  </section>

  <div class="workspace">
    <section class="panel">
      <div class="panel-head">
        <div><h2><?php echo htmlspecialchars($reportTypes[$reportType], ENT_QUOTES, 'UTF-8'); ?></h2><p><?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?> a <?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?> · <?php echo count($reportRows); ?> filas</p></div>
        <?php $exportQuery = http_build_query(['tipo' => $reportType, 'desde' => $startDate, 'hasta' => $endDate]); ?>
        <div class="export-actions">
          <a class="button" href="exportar.php?<?php echo htmlspecialchars($exportQuery . '&formato=pdf', ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-file-pdf"></i> Descargar PDF</a>
          <a class="button secondary" href="exportar.php?<?php echo htmlspecialchars($exportQuery . '&formato=csv', ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-file-csv"></i> Descargar CSV</a>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Referencia</th><th>Detalle</th><th>Fecha</th><th>Estado</th><th>Importe</th></tr></thead>
          <tbody>
            <?php if (!$reportRows): ?><tr><td class="empty" colspan="5">No hay registros dentro del período elegido.</td></tr>
            <?php else: foreach (array_slice(array_reverse($reportRows), 0, 50) as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars((string)($row['Referencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['Detalle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['Fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['Estado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo ($row['Importe'] ?? '') !== '' ? '$' . htmlspecialchars((string)$row['Importe'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <aside class="panel">
      <div class="panel-head"><div><h2>Ingresos de los últimos 6 meses</h2><p>Pagos registrados en MySQL</p></div></div>
      <div class="chart" aria-label="Ingresos mensuales">
        <?php foreach ($monthlyRevenue as $month): ?>
          <div class="chart-item" title="$<?php echo number_format($month['total'], 2); ?>"><div class="chart-bar" style="height:<?php echo max(3, (int)round($month['total'] / $chartMaximum * 88)); ?>%;"></div><span><?php echo htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endforeach; ?>
      </div>
      <div class="insight-list">
        <div class="insight"><span>Facturas en el período</span><strong><?php echo count($periodData['facturas']); ?></strong></div>
        <div class="insight"><span>Pagos en el período</span><strong><?php echo count($periodData['pagos']); ?></strong></div>
        <div class="insight"><span>Importe facturado</span><strong>$<?php echo number_format(array_sum(array_map(static fn($invoice) => (float)($invoice['total'] ?? $invoice['importe'] ?? 0), $periodData['facturas'])), 2); ?></strong></div>
      </div>
      <div class="quick-links">
        <a class="button secondary" href="exportar.php?tipo=facturacion&amp;formato=pdf"><i class="fa-solid fa-file-invoice-dollar"></i> Facturación PDF</a>
        <a class="button secondary" href="exportar.php?tipo=casos&amp;formato=csv"><i class="fa-solid fa-briefcase"></i> Casos CSV</a>
      </div>
    </aside>
  </div>
</div>
</body>
</html>
