<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';
$appointments = app_data_read('citas');
$clients = app_data_read('clientes');
$cases = app_data_read('casos');
$requestedMonth = is_string($_GET['mes'] ?? null) ? $_GET['mes'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $requestedMonth)) {
  $requestedMonth = date('Y-m');
}
$monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedMonth . '-01');
if (!$monthStart || $monthStart->format('Y-m') !== $requestedMonth) {
  $monthStart = new DateTimeImmutable('first day of this month');
  $requestedMonth = $monthStart->format('Y-m');
}
$selectedDate = is_string($_GET['dia'] ?? null) ? $_GET['dia'] : '';
$selectedDateObject = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)
  ? DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate)
  : false;
if (!$selectedDateObject || $selectedDateObject->format('Y-m-d') !== $selectedDate) {
  $selectedDate = '';
} elseif ($selectedDateObject->format('Y-m') !== $requestedMonth) {
  $monthStart = $selectedDateObject->modify('first day of this month');
  $requestedMonth = $monthStart->format('Y-m');
}

$appointments = array_values(array_filter($appointments, static fn($appointment) => !empty($appointment['fecha'])));
usort($appointments, static fn($first, $second) => (($first['fecha'] ?? '') . ' ' . ($first['hora'] ?? '')) <=> (($second['fecha'] ?? '') . ' ' . ($second['hora'] ?? '')));
$monthAppointments = array_values(array_filter($appointments, static fn($appointment) => substr($appointment['fecha'], 0, 7) === $requestedMonth));
$visibleAppointments = $selectedDate !== ''
  ? array_values(array_filter($monthAppointments, static fn($appointment) => $appointment['fecha'] === $selectedDate))
  : $monthAppointments;
$appointmentsByDate = [];
foreach ($monthAppointments as $appointment) {
  $appointmentsByDate[$appointment['fecha']][] = $appointment;
}
$today = date('Y-m-d');
$weekLimit = date('Y-m-d', strtotime('+7 days'));
$todayCount = count(array_filter($appointments, static fn($appointment) => $appointment['fecha'] === $today));
$monthCount = count($monthAppointments);
$weekCount = count(array_filter($appointments, static fn($appointment) => $appointment['fecha'] >= $today && $appointment['fecha'] <= $weekLimit));
$calendarStart = $monthStart->modify('monday this week');
$calendarDays = [];
for ($offset = 0; $offset < 42; $offset++) {
  $calendarDays[] = $calendarStart->modify('+' . $offset . ' days');
}
$monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$monthTitle = ucfirst($monthNames[(int)$monthStart->format('n') - 1]) . ' ' . $monthStart->format('Y');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$selectedTitle = $selectedDate !== '' ? $selectedDateObject->format('d/m/Y') : $monthTitle;
$statusMessage = [
  'success' => 'Cita guardada correctamente.',
  'error' => 'No se pudo guardar la cita. Revisa el asunto, fecha y hora.',
][$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agenda y Citas</title>
    <style>
    :root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#191919; --muted:#707070; --line:#dedede; --page:#eef0f2; --surface:#fff; --blue:#4775a8; --green:#438b5c; --gold:#bb8a36; }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
    .main { min-height:100vh; margin-left:260px; padding:16px 18px 24px; }
    .page-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .eyebrow { color:var(--wine-800); font-size:.7rem; font-weight:700; text-transform:uppercase; }
    h1 { margin:4px 0 0; font-size:1.65rem; }
    h2 { margin:0; font-size:1rem; }
    .button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:35px; padding:8px 11px; border:1px solid transparent; border-radius:7px; background:linear-gradient(110deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.74rem; font-weight:700; text-decoration:none; cursor:pointer; }
    .button.secondary { border-color:#d0d0d0; background:#fff; color:var(--ink); }
    .status-message { margin:0 0 12px; padding:9px 12px; border:1px solid #e4c1c7; border-radius:7px; background:#fff8f9; color:var(--wine-900); font-size:.78rem; }
    .metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
    .metric { display:flex; align-items:center; gap:10px; min-height:68px; padding:11px 13px; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,.06); }
    .metric-icon { display:grid; place-items:center; width:36px; height:36px; border-radius:8px; background:#f5e5e6; color:var(--wine-700); }
    .metric:nth-child(2) .metric-icon { background:#e7eef8; color:var(--blue); }
    .metric:nth-child(3) .metric-icon { background:#f6edd8; color:var(--gold); }
    .metric-label { display:block; color:var(--muted); font-size:.68rem; }
    .metric-value { display:block; margin-top:2px; font-size:1.1rem; font-weight:800; }
    .layout { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(280px,.85fr); gap:12px; align-items:start; }
    .column { display:grid; gap:12px; min-width:0; }
    .panel { min-width:0; padding:13px; border:1px solid var(--line); border-radius:8px; background:var(--surface); box-shadow:0 2px 6px rgba(0,0,0,.06); }
    .panel-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:9px; margin-bottom:10px; }
    .month-nav { display:flex; align-items:center; gap:8px; }
    .icon-link { display:grid; place-items:center; width:30px; height:30px; border:1px solid var(--line); border-radius:6px; background:#fff; color:#444; text-decoration:none; }
    .month-title { min-width:130px; text-align:center; font-size:.85rem; font-weight:800; text-transform:capitalize; }
    .calendar { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); border-top:1px solid var(--line); border-left:1px solid var(--line); }
    .weekday { padding:7px 3px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); background:#f6f6f6; color:#555; text-align:center; font-size:.65rem; font-weight:800; }
    .day { min-width:0; min-height:82px; padding:5px; overflow:hidden; border-right:1px solid var(--line); border-bottom:1px solid var(--line); background:#fff; color:var(--ink); text-decoration:none; }
    .day:hover { background:#faf5f6; }
    .day.outside { background:#f7f7f7; color:#aaa; }
    .day.selected { box-shadow:inset 0 0 0 2px var(--wine-700); }
    .day-number { display:inline-grid; place-items:center; width:23px; height:23px; border-radius:50%; font-size:.7rem; }
    .day.today .day-number { background:var(--wine-800); color:#fff; font-weight:800; }
    .event-chip { display:block; margin-top:4px; padding:3px 5px; overflow:hidden; border-radius:4px; background:#f4e3e5; color:var(--wine-900); font-size:.61rem; line-height:1.25; text-overflow:ellipsis; white-space:nowrap; }
    .event-chip.audiencia { background:#e5edf7; color:#315779; }
    .event-chip.reunion { background:#e4f1e7; color:#316744; }
    .event-chip.plazo { background:#f7edd8; color:#76591c; }
    .event-chip.firma { background:#f3e8f3; color:#67456b; }
    .more-events { display:block; margin-top:3px; color:var(--muted); font-size:.58rem; }
    .appointments { display:grid; gap:8px; }
    .appointment { display:grid; grid-template-columns:62px minmax(0,1fr); gap:9px; padding:9px 0; border-bottom:1px solid #ededed; }
    .appointment:last-child { border-bottom:0; }
    .appointment time { color:var(--wine-800); font-size:.72rem; font-weight:800; }
    .appointment-title { font-size:.77rem; font-weight:700; }
    .appointment-meta { margin-top:3px; color:var(--muted); font-size:.67rem; }
    .empty { padding:16px 6px; color:var(--muted); font-size:.75rem; text-align:center; }
    .field { margin-bottom:9px; }
    .field label { display:block; margin-bottom:4px; color:#505050; font-size:.7rem; font-weight:700; }
    .field input,.field select,.field textarea { width:100%; min-height:34px; padding:7px 9px; border:1px solid #d1d1d1; border-radius:6px; background:#fff; color:var(--ink); font:inherit; font-size:.76rem; }
    .field textarea { min-height:62px; resize:vertical; }
    .field input:focus,.field select:focus,.field textarea:focus { outline:2px solid rgba(151,27,49,.18); border-color:var(--wine-700); }
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:9px; }
    .form-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:12px; }
    .panel-note { margin:0 0 10px; color:var(--muted); font-size:.68rem; }
    .list-actions { display:flex; flex-wrap:wrap; gap:7px; }
    @media (max-width:1000px) { .layout { grid-template-columns:1fr; } .side-column { grid-template-columns:1fr 1fr; align-items:start; } }
    @media (max-width:700px) { .main { margin-left:0; padding:12px; } .metrics { grid-template-columns:1fr 1fr; } .side-column { grid-template-columns:1fr; } .day { min-height:66px; padding:3px; } .event-chip { font-size:.56rem; } }
    @media (max-width:430px) { .metrics { grid-template-columns:1fr; } .page-head { align-items:flex-start; flex-direction:column; } .weekday { font-size:.58rem; } .day { min-height:56px; } .event-chip { padding:2px; font-size:.52rem; } }
    </style>
</head>
<body>
<?php include_once '../includes/sidebar.php'; ?>
<div class="main">
  <header class="page-head">
    <div><span class="eyebrow">Calendario del despacho</span><h1>Agenda y Citas</h1></div>
    <div class="list-actions">
      <a class="button secondary" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Escritorio</a>
      <a class="button" href="#nueva-cita"><i class="fa-solid fa-plus"></i> Nueva cita</a>
    </div>
  </header>
  <?php if ($statusMessage !== ''): ?><p class="status-message" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="metrics" aria-label="Resumen de agenda">
    <div class="metric"><span class="metric-icon"><i class="fa-regular fa-calendar-check"></i></span><span><span class="metric-label">Citas hoy</span><strong class="metric-value"><?php echo $todayCount; ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-regular fa-calendar"></i></span><span><span class="metric-label">Este mes</span><strong class="metric-value"><?php echo $monthCount; ?></strong></span></div>
    <div class="metric"><span class="metric-icon"><i class="fa-solid fa-hourglass-half"></i></span><span><span class="metric-label">Próximos 7 días</span><strong class="metric-value"><?php echo $weekCount; ?></strong></span></div>
  </section>

  <div class="layout">
    <div class="column">
      <section class="panel">
        <div class="panel-head">
          <h2>Calendario</h2>
          <div class="month-nav">
            <a class="icon-link" href="?mes=<?php echo htmlspecialchars($previousMonth, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mes anterior" title="Mes anterior"><i class="fa-solid fa-chevron-left"></i></a>
            <span class="month-title"><?php echo htmlspecialchars($monthTitle, ENT_QUOTES, 'UTF-8'); ?></span>
            <a class="icon-link" href="?mes=<?php echo htmlspecialchars($nextMonth, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mes siguiente" title="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></a>
            <a class="button secondary" href="?mes=<?php echo date('Y-m'); ?>&amp;dia=<?php echo date('Y-m-d'); ?>">Hoy</a>
          </div>
        </div>
        <div class="calendar">
          <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday): ?><div class="weekday"><?php echo $weekday; ?></div><?php endforeach; ?>
          <?php foreach ($calendarDays as $calendarDay): ?>
            <?php
            $dateKey = $calendarDay->format('Y-m-d');
            $classes = ['day'];
            if ($calendarDay->format('Y-m') !== $requestedMonth) $classes[] = 'outside';
            if ($dateKey === $today) $classes[] = 'today';
            if ($dateKey === $selectedDate) $classes[] = 'selected';
            $dayUrl = '?mes=' . rawurlencode($requestedMonth) . '&amp;dia=' . rawurlencode($dateKey);
            ?>
            <a class="<?php echo implode(' ', $classes); ?>" href="<?php echo $dayUrl; ?>" aria-label="Ver citas del <?php echo htmlspecialchars($calendarDay->format('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?>">
              <span class="day-number"><?php echo $calendarDay->format('j'); ?></span>
              <?php if (!empty($appointmentsByDate[$dateKey])): ?>
                <?php foreach (array_slice($appointmentsByDate[$dateKey], 0, 2) as $appointment): ?>
                  <?php $eventType = strtolower($appointment['tipo'] ?? ''); $eventClass = $eventType === 'reunión' ? 'reunion' : (in_array($eventType, ['audiencia', 'plazo', 'firma'], true) ? $eventType : ''); ?>
                  <span class="event-chip <?php echo htmlspecialchars($eventClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(($appointment['hora'] ?? '') . ' ' . ($appointment['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
                <?php if (count($appointmentsByDate[$dateKey]) > 2): ?><span class="more-events">+<?php echo count($appointmentsByDate[$dateKey]) - 2; ?> más</span><?php endif; ?>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <div><h2><?php echo $selectedDate !== '' ? 'Citas del ' . htmlspecialchars($selectedTitle, ENT_QUOTES, 'UTF-8') : 'Citas de ' . htmlspecialchars($monthTitle, ENT_QUOTES, 'UTF-8'); ?></h2><p class="panel-note"><?php echo count($visibleAppointments); ?> citas</p></div>
          <div class="list-actions">
            <?php if ($selectedDate !== ''): ?><a class="button secondary" href="?mes=<?php echo htmlspecialchars($requestedMonth, ENT_QUOTES, 'UTF-8'); ?>">Ver mes completo</a><?php endif; ?>
            <a class="button secondary" href="exportar.php?tipo=citas&amp;desde=<?php echo $selectedDate !== '' ? rawurlencode($selectedDate) : $monthStart->format('Y-m-01'); ?>&amp;hasta=<?php echo $selectedDate !== '' ? rawurlencode($selectedDate) : $monthStart->format('Y-m-t'); ?>&amp;formato=csv" aria-label="Exportar citas a CSV" title="Exportar citas a CSV"><i class="fa-solid fa-file-csv"></i></a>
          </div>
        </div>
        <?php if (!$visibleAppointments): ?>
          <p class="empty">No hay citas para esta fecha.</p>
        <?php else: ?>
          <div class="appointments">
            <?php foreach ($visibleAppointments as $appointment): ?>
              <article class="appointment">
                <time><?php echo htmlspecialchars(($appointment['hora'] ?? '—') . ' · ' . date('d/m', strtotime($appointment['fecha'])), ENT_QUOTES, 'UTF-8'); ?></time>
                <div><div class="appointment-title"><?php echo htmlspecialchars($appointment['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div><div class="appointment-meta"><?php echo htmlspecialchars(trim(($appointment['tipo'] ?? 'Cita') . ' · ' . ($appointment['cliente'] ?? 'Sin cliente') . (!empty($appointment['caso']) ? ' · ' . $appointment['caso'] : '')), ENT_QUOTES, 'UTF-8'); ?></div><?php if (!empty($appointment['ubicacion'])): ?><div class="appointment-meta"><?php echo htmlspecialchars($appointment['ubicacion'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?></div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <aside class="column side-column">
      <section class="panel" id="nueva-cita">
        <h2>Nueva cita</h2>
        <form action="guardar_accion.php" method="post">
          <input type="hidden" name="action" value="cita">
          <input type="hidden" name="return_to" value="agenda.php">
          <input type="hidden" name="mes" value="<?php echo htmlspecialchars($requestedMonth, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="field"><label for="appointment-title">Asunto</label><input id="appointment-title" name="titulo" placeholder="Audiencia, reunión..." required></div>
          <div class="field"><label for="appointment-type">Tipo</label><select id="appointment-type" name="tipo"><option>Consulta</option><option>Audiencia</option><option>Reunión</option><option>Firma</option><option>Plazo</option></select></div>
          <div class="field"><label for="appointment-client">Cliente</label><input id="appointment-client" name="cliente" list="agenda-clients" placeholder="Nombre del cliente"><datalist id="agenda-clients"><?php foreach ($clients as $client): ?><option value="<?php echo htmlspecialchars($client['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php endforeach; ?></datalist></div>
          <div class="field"><label for="appointment-case">Caso</label><select id="appointment-case" name="caso"><option value="">Sin caso asociado</option><?php foreach ($cases as $case): ?><option value="<?php echo htmlspecialchars(($case['numero'] ?? '') . ' · ' . ($case['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(($case['numero'] ?? '') . ' · ' . ($case['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
          <div class="two-col">
            <div class="field"><label for="appointment-date">Fecha</label><input id="appointment-date" name="fecha" type="date" value="<?php echo htmlspecialchars($selectedDate !== '' ? $selectedDate : $today, ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div class="field"><label for="appointment-time">Hora</label><input id="appointment-time" name="hora" type="time"></div>
          </div>
          <div class="field"><label for="appointment-location">Ubicación</label><input id="appointment-location" name="ubicacion" placeholder="Sala o dirección"></div>
          <div class="field"><label for="appointment-notes">Notas</label><textarea id="appointment-notes" name="notas" placeholder="Detalles adicionales"></textarea></div>
          <div class="form-actions"><button class="button" type="submit"><i class="fa-regular fa-floppy-disk"></i> Guardar cita</button></div>
        </form>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Próximas citas</h2><a href="?mes=<?php echo date('Y-m'); ?>&amp;dia=<?php echo date('Y-m-d'); ?>" class="button secondary">Hoy</a></div>
        <?php $upcoming = array_slice(array_values(array_filter($appointments, static fn($appointment) => $appointment['fecha'] >= $today)), 0, 5); ?>
        <?php if (!$upcoming): ?><p class="empty">No hay próximas citas.</p><?php else: ?><div class="appointments">
          <?php foreach ($upcoming as $appointment): ?><article class="appointment"><time><?php echo htmlspecialchars(date('d/m', strtotime($appointment['fecha'])) . ' · ' . ($appointment['hora'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></time><div><div class="appointment-title"><?php echo htmlspecialchars($appointment['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div><div class="appointment-meta"><?php echo htmlspecialchars(($appointment['cliente'] ?? 'Sin cliente') . ' · ' . ($appointment['tipo'] ?? 'Cita'), ENT_QUOTES, 'UTF-8'); ?></div></div></article><?php endforeach; ?>
        </div><?php endif; ?>
      </section>
    </aside>
  </div>
</div>
</body>
</html>
