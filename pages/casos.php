<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';
$lawyerQuery = app_user_database()->query("SELECT id, nombre FROM abogados WHERE rol = 'abogado' AND activo = 1 ORDER BY nombre");
$lawyers = $lawyerQuery->fetchAll(PDO::FETCH_ASSOC);
$cases = array_reverse(app_data_read('casos'));
$actions = array_reverse(app_data_read('actuaciones'));
$appointments = app_data_read('citas');
$clients = app_data_read('clientes');
$contracts = app_data_read('contratos');

$selectedCaseNumber = trim($_GET['caso'] ?? ($cases[0]['numero'] ?? ''));
$selectedCase = null;
foreach ($cases as $case) {
  if (($case['numero'] ?? '') === $selectedCaseNumber) {
    $selectedCase = $case;
    break;
  }
}
if ($selectedCase === null && $cases) {
  $selectedCase = $cases[0];
  $selectedCaseNumber = $selectedCase['numero'] ?? '';
}
$comments = array_values(array_filter(app_data_read('comentarios'), static fn($comment) => ($comment['numero'] ?? '') === $selectedCaseNumber));
usort($comments, static fn($first, $second) => strcmp($first['created_at'] ?? '', $second['created_at'] ?? ''));

$statusFilter = $_GET['historial'] ?? 'Todos';
$validHistoryFilters = ['Todos', 'Abierto', 'En Proceso', 'Cerrado', 'Archivado'];
if (!in_array($statusFilter, $validHistoryFilters, true)) {
  $statusFilter = 'Todos';
}
$visibleActions = array_values(array_filter($actions, static function ($action) use ($selectedCaseNumber, $statusFilter) {
  $sameCase = ($action['numero'] ?? '') === $selectedCaseNumber;
  $sameStatus = $statusFilter === 'Todos' || strcasecmp($action['estado'] ?? '', $statusFilter) === 0;
  return $sameCase && $sameStatus;
}));

$openCases = count(array_filter($cases, static fn($case) => strcasecmp($case['estado'] ?? '', 'Abierto') === 0));
$inProgressCases = count(array_filter($cases, static fn($case) => strcasecmp($case['estado'] ?? '', 'En Proceso') === 0));
$closedCases = count(array_filter($cases, static fn($case) => strcasecmp($case['estado'] ?? '', 'Cerrado') === 0));
$upcomingHearings = count(array_filter($appointments, static fn($appointment) => !empty($appointment['fecha']) && $appointment['fecha'] >= date('Y-m-d')));

$requestedMonth = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $requestedMonth) || !($monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedMonth . '-01'))) {
  $monthStart = new DateTimeImmutable('first day of this month');
  $requestedMonth = $monthStart->format('Y-m');
}
$monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$monthTitle = ucfirst($monthNames[(int)$monthStart->format('n') - 1]) . ' ' . $monthStart->format('Y');
$calendarStart = $monthStart->modify('monday this week');
$calendarCellCount = (int)ceil(((int)$monthStart->format('N') - 1 + (int)$monthStart->format('t')) / 7) * 7;
$calendarDays = [];
for ($dayOffset = 0; $dayOffset < $calendarCellCount; $dayOffset++) {
  $calendarDays[] = $calendarStart->modify('+' . $dayOffset . ' days');
}
$appointmentsByDate = [];
foreach ($appointments as $appointment) {
  if (!empty($appointment['fecha'])) {
    $appointmentsByDate[$appointment['fecha']][] = $appointment;
  }
}
$monthAppointments = array_values(array_filter($appointments, static fn($appointment) => !empty($appointment['fecha']) && substr($appointment['fecha'], 0, 7) === $requestedMonth));
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$nextCaseNumber = 'C-' . date('Y') . '-' . str_pad((string)(1001 + count($cases)), 4, '0', STR_PAD_LEFT);
$statusMessage = [
  'success' => 'Cambios guardados correctamente.',
  'commented' => 'Comentario publicado y notificado.',
  'error' => 'No se pudo guardar. Verifica los campos obligatorios y el formato de los documentos.',
][$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro y Gestión de Casos</title>
    <style>
    :root {
      --wine-950: #40070e;
      --wine-900: #570b16;
      --wine-800: #751321;
      --wine-700: #971b31;
      --ink: #191919;
      --muted: #777;
      --line: #dedede;
      --surface: #fff;
      --page: #eeeeee;
      --blue: #3478c8;
      --green: #438953;
      --gold: #c58c32;
      --gray: #8c8c8c;
    }

    * { box-sizing: border-box; }
    body { margin: 0; background: var(--page); color: var(--ink); font-family: 'Montserrat', sans-serif; }
    .main { min-height: 100vh; margin-left: 260px; padding: 12px 16px 20px; }
    .topbar { min-height: 48px; display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 10px; }
    .topbar h1 { margin: 0; font-size: 1.35rem; font-weight: 800; }
    .topbar-actions { display: flex; align-items: center; gap: 12px; }
    .topbar-actions a { display: grid; place-items: center; width: 34px; height: 34px; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--ink); text-decoration: none; }
    .topbar-actions button { width: 34px; height: 34px; border: 1px solid var(--line); border-radius: 8px; background: #fff; cursor: pointer; }
    .status-message { margin: 0 0 10px; padding: 10px 13px; border: 1px solid #e8c5cc; border-radius: 8px; background: #fff6f7; color: var(--wine-900); }
    .metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 12px; }
    .metric { min-height: 66px; padding: 10px 14px; border: 1px solid #ddd; border-radius: 11px; background: #fff; box-shadow: 0 3px 6px rgba(0,0,0,.12); }
    .metric span { display: block; margin-bottom: 3px; font-size: .82rem; }
    .metric strong { font-size: 1.45rem; line-height: 1; }
    .workspace { display: grid; grid-template-columns: minmax(440px, 1.05fr) minmax(430px, 1fr); gap: 14px; align-items: start; }
    .panel { min-width: 0; padding: 13px; border: 1px solid #d8d8d8; border-radius: 11px; background: var(--surface); box-shadow: 0 3px 7px rgba(0,0,0,.12); }
    .panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
    .panel-heading h2 { margin: 0; font-size: .98rem; font-weight: 800; }
    .case-layout { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 9px 12px; }
    .field { min-width: 0; }
    .field.full { grid-column: 1 / -1; }
    .field label, .field-label { display: block; margin: 0 0 5px; font-size: .76rem; font-weight: 600; }
    .field input:not([type=radio]):not([type=file]), .field select, .field textarea { width: 100%; min-height: 36px; padding: 7px 9px; border: 1px solid #c9c9c9; border-radius: 7px; background: #fff; color: var(--ink); font: inherit; font-size: .82rem; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: 2px solid rgba(151,27,49,.2); border-color: var(--wine-700); }
    .field textarea { min-height: 62px; resize: vertical; }
    .status-options { display: flex; flex-wrap: wrap; gap: 6px; }
    .status-choice input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .status-choice span { display: inline-flex; align-items: center; min-height: 26px; padding: 4px 9px; border: 1px solid transparent; border-radius: 6px; color: #fff; font-size: .72rem; cursor: pointer; opacity: .68; }
    .status-choice input:checked + span, .status-choice input:focus-visible + span { opacity: 1; outline: 2px solid rgba(0,0,0,.2); }
    .status-open { background: var(--blue); }
    .status-progress { background: var(--gold); }
    .status-closed { background: var(--green); }
    .section-divider { grid-column: 1 / -1; height: 1px; margin: 1px 0; background: var(--line); }
    .document-drop { min-height: 74px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; padding: 9px; border: 1px dashed #c8c8c8; border-radius: 8px; background: #fafafa; color: #555; text-align: center; cursor: pointer; }
    .document-drop i { color: var(--wine-700); font-size: 1.05rem; }
    .document-drop strong { font-size: .78rem; font-weight: 600; }
    .document-drop small { max-width: 100%; overflow-wrap: anywhere; color: var(--muted); font-size: .68rem; }
    .document-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .primary-btn, .secondary-btn { display: inline-flex; justify-content: center; align-items: center; gap: 7px; min-height: 37px; padding: 8px 13px; border: 0; border-radius: 7px; color: #fff; background: linear-gradient(100deg, var(--wine-950), var(--wine-800)); font: inherit; font-size: .8rem; font-weight: 700; text-decoration: none; cursor: pointer; }
    .secondary-btn { border: 1px solid #d1d1d1; color: var(--ink); background: #f4f4f4; }
    .primary-btn:disabled { cursor: not-allowed; opacity: .5; }
    .form-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 11px; }
    .right-column { display: grid; gap: 12px; }
    .right-top { display: grid; grid-template-columns: minmax(220px, 1.1fr) minmax(210px, 1fr); gap: 12px; }
    .calendar-heading { display: grid; grid-template-columns: 28px 1fr 28px; align-items: center; margin: -2px 0 8px; text-align: center; }
    .calendar-heading strong { font-size: .76rem; }
    .icon-link { display: inline-grid; place-items: center; width: 27px; height: 27px; border: 0; border-radius: 6px; color: #555; background: transparent; text-decoration: none; }
    .icon-link:hover { background: #f1f1f1; }
    .weekdays, .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 3px; text-align: center; }
    .weekdays { margin-bottom: 3px; color: #555; font-size: .64rem; font-weight: 700; }
    .calendar-day { position: relative; min-height: 24px; display: grid; place-items: center; border-radius: 50%; color: #333; font-size: .68rem; text-decoration: none; }
    .calendar-day.outside { color: #aaa; }
    .calendar-day.today { color: #fff; background: var(--wine-800); font-weight: 700; }
    .calendar-day.has-event:not(.today)::after { position: absolute; bottom: 1px; width: 4px; height: 4px; border-radius: 50%; background: var(--wine-700); content: ''; }
    .calendar-day.today.has-event::after { background: #fff; }
    .hearing-list { display: grid; gap: 8px; margin-top: 10px; }
    .hearing { display: grid; grid-template-columns: 42px 12px minmax(0, 1fr); align-items: center; gap: 6px; font-size: .7rem; }
    .hearing time { color: #444; }
    .hearing .marker { width: 9px; height: 9px; border-radius: 50%; background: var(--wine-800); }
    .hearing:nth-child(2n) .marker { background: var(--blue); }
    .hearing:nth-child(3n) .marker { background: var(--gold); }
    .hearing span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .history-toolbar { display: flex; align-items: center; gap: 5px; }
    .history-controls { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; }
    .case-picker select { max-width: 190px; min-height: 29px; padding: 4px 7px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--ink); font: inherit; font-size: .68rem; }
    .filter-link { padding: 4px 7px; border-radius: 6px; color: #fff; background: var(--gray); font-size: .65rem; text-decoration: none; }
    .filter-link[data-status="Abierto"] { background: var(--blue); }
    .filter-link[data-status="En Proceso"] { background: var(--gold); }
    .filter-link[data-status="Cerrado"] { background: var(--green); }
    .filter-link.active { outline: 2px solid rgba(0,0,0,.22); outline-offset: 1px; }
    .table-scroll { overflow-x: auto; }
    .history-table { width: 100%; border-collapse: collapse; font-size: .7rem; }
    .history-table th, .history-table td { padding: 8px 6px; border-bottom: 1px solid #e4e4e4; text-align: left; vertical-align: top; }
    .history-table th { font-weight: 800; white-space: nowrap; }
    .history-table td:last-child { min-width: 150px; }
    .case-summary { display: flex; flex-wrap: wrap; gap: 8px 16px; margin-top: 10px; color: #555; font-size: .72rem; }
    .case-docs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 9px; font-size: .72rem; }
    .case-docs a { color: var(--wine-800); }
    .comment-section { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--line); }
    .comment-section h3 { margin: 0 0 9px; font-size: .82rem; }
    .comment-list { display: grid; gap: 7px; max-height: 220px; overflow-y: auto; }
    .comment-item { padding: 8px 10px; border: 1px solid #ededed; border-radius: 7px; background: #fafafa; }
    .comment-meta { display: flex; justify-content: space-between; gap: 8px; color: var(--muted); font-size: .64rem; }
    .comment-text { margin: 5px 0 0; font-size: .74rem; white-space: pre-wrap; overflow-wrap: anywhere; }
    .comment-form { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 8px; margin-top: 9px; }
    .comment-form textarea { min-height: 38px; max-height: 100px; padding: 8px; border: 1px solid #d5d5d5; border-radius: 7px; font: inherit; font-size: .73rem; resize: vertical; }
    .comment-form textarea:focus { outline: 2px solid rgba(151,27,49,.18); border-color: var(--wine-700); }
    dialog { width: min(460px, calc(100% - 24px)); padding: 18px; border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 15px 50px rgba(0,0,0,.24); }
    dialog::backdrop { background: rgba(0,0,0,.42); }
    .dialog-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .dialog-header h2 { margin: 0; font-size: 1rem; }
    .dialog-close { border: 0; background: transparent; font-size: 1.25rem; cursor: pointer; }
    .dialog-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
    .empty-state { padding: 14px 8px; color: var(--muted); font-size: .75rem; text-align: center; }

    @media (max-width: 1100px) {
      .workspace { grid-template-columns: 1fr; }
      .right-column { grid-template-columns: 1fr 1fr; align-items: start; }
    }
    @media (max-width: 700px) {
      .main { margin-left: 0; padding: 12px; }
      .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .right-column, .right-top { grid-template-columns: 1fr; }
      .case-layout { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .field.full, .section-divider { grid-column: 1 / -1; }
      .panel-heading { align-items: flex-start; flex-wrap: wrap; }
    }
    @media (max-width: 430px) {
      .case-layout { grid-template-columns: 1fr; }
      .field.full, .section-divider { grid-column: auto; }
      .form-actions { grid-template-columns: 1fr; }
      .history-toolbar { flex-wrap: wrap; }
    }
    </style>
</head>
<body>
<?php include_once '../includes/sidebar.php'; ?>
<div class="main">
  <header class="topbar">
    <h1>Registro y Gestión de Casos</h1>
    <div class="topbar-actions">
            <a href="agenda.php" aria-label="Abrir agenda" title="Abrir agenda"><i class="fa-regular fa-calendar"></i></a>
            <a href="../dashboard.php" aria-label="Volver al escritorio" title="Volver al escritorio"><i class="fa-solid fa-house"></i></a>
    </div>
  </header>

  <?php if ($statusMessage !== ''): ?>
    <p class="status-message" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <section class="metrics" aria-label="Resumen de casos">
    <div class="metric"><span>Casos Abiertos:</span><strong><?php echo $openCases; ?></strong></div>
    <div class="metric"><span>Casos En Proceso:</span><strong><?php echo $inProgressCases; ?></strong></div>
    <div class="metric"><span>Casos Cerrados:</span><strong><?php echo $closedCases; ?></strong></div>
    <div class="metric"><span>Próximas Audiencias:</span><strong><?php echo $upcomingHearings; ?></strong></div>
  </section>

  <div class="workspace">
    <section class="panel">
      <div class="panel-heading">
        <h2>Registro de Caso</h2>
        <div class="status-options" aria-label="Estado inicial del caso">
          <label class="status-choice"><input form="case-form" type="radio" name="estado" value="Abierto" checked><span class="status-open">Abierto</span></label>
          <label class="status-choice"><input form="case-form" type="radio" name="estado" value="En Proceso"><span class="status-progress">En Proceso</span></label>
          <label class="status-choice"><input form="case-form" type="radio" name="estado" value="Cerrado"><span class="status-closed">Cerrado</span></label>
        </div>
      </div>

      <form id="case-form" action="guardar_accion.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="caso">
        <input type="hidden" name="return_to" value="casos.php">
        <div class="case-layout">
          <div class="field">
            <label for="numero">Número de Caso</label>
            <input id="numero" name="numero" value="<?php echo htmlspecialchars($nextCaseNumber, ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>
          <div class="field">
            <label for="tipo">Tipo de Caso</label>
            <select id="tipo" name="tipo" required>
              <option selected>Civil</option><option>Penal</option><option>Laboral</option><option>Mercantil</option><option>Familiar</option><option>Administrativo</option>
            </select>
          </div>
          <div class="field">
            <label for="area">Área de Práctica</label>
            <input id="area" name="area" value="Contratos" placeholder="Ej. Contratos" required>
          </div>

          <div class="section-divider"></div>

          <div class="field">
            <label for="cliente">Cliente</label>
            <select id="cliente" name="cliente">
              <option value="">Sin asignar</option>
              <?php foreach (array_reverse($clients) as $client): ?>
                <option value="<?php echo htmlspecialchars($client['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($client['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="abogado">Abogado</label>
            <select id="abogado" name="abogado_id">
              <option value="">Sin asignar</option>
              <?php foreach ($lawyers as $lawyer): ?>
                <option value="<?php echo htmlspecialchars($lawyer['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lawyer['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="contrato">Contrato</label>
            <select id="contrato" name="contrato">
              <option value="">Sin contrato vinculado</option>
              <?php foreach (array_reverse($contracts) as $contract): ?>
                <?php $contractLabel = trim(($contract['cliente'] ?? '') . ' · ' . ($contract['tipo'] ?? 'Contrato')); ?>
                <option value="<?php echo htmlspecialchars($contractLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contractLabel, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field full">
            <label for="documentos">Subir Documentos</label>
            <label class="document-drop" id="case-dropzone" for="documentos">
              <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
              <strong>Haz clic o arrastra los documentos aquí</strong>
              <small id="document-list">PDF, DOC o DOCX; hasta 10 MB por archivo</small>
              <input id="documentos" type="file" name="documentos[]" accept=".pdf,.doc,.docx" multiple>
            </label>
          </div>
        </div>
        <div class="form-actions">
          <button class="primary-btn" type="submit"><i class="fa-regular fa-floppy-disk"></i> Guardar Caso</button>
          <button class="secondary-btn" type="button" onclick="document.getElementById('actuation-dialog').showModal();"><i class="fa-solid fa-plus"></i> Nueva Actuación</button>
        </div>
      </form>
    </section>

    <div class="right-column">
      <div class="right-top">
        <section class="panel">
          <div class="panel-heading"><h2>Audiencias y Plazos</h2></div>
          <div class="hearing-list">
            <?php if (!$monthAppointments): ?>
              <p class="empty-state">No hay audiencias para este mes.</p>
            <?php else: ?>
              <?php foreach (array_slice($monthAppointments, 0, 5) as $appointment): ?>
                <div class="hearing">
                  <time><?php echo htmlspecialchars($appointment['hora'] ?? '09:00', ENT_QUOTES, 'UTF-8'); ?></time>
                  <span class="marker"></span>
                  <span title="<?php echo htmlspecialchars($appointment['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(($appointment['titulo'] ?? 'Audiencia') . (!empty($appointment['cliente']) ? ' · ' . $appointment['cliente'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section class="panel">
          <div class="calendar-heading">
            <a class="icon-link" href="?mes=<?php echo htmlspecialchars($previousMonth, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mes anterior" title="Mes anterior"><i class="fa-solid fa-chevron-left"></i></a>
            <strong><?php echo htmlspecialchars($monthTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
            <a class="icon-link" href="?mes=<?php echo htmlspecialchars($nextMonth, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mes siguiente" title="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="weekdays"><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span></div>
          <div class="calendar-grid">
            <?php foreach ($calendarDays as $calendarDay): ?>
              <?php
              $dateKey = $calendarDay->format('Y-m-d');
              $dayClasses = ['calendar-day'];
              if ($calendarDay->format('m') !== $monthStart->format('m')) $dayClasses[] = 'outside';
              if ($dateKey === date('Y-m-d')) $dayClasses[] = 'today';
              if (isset($appointmentsByDate[$dateKey])) $dayClasses[] = 'has-event';
              ?>
              <span class="<?php echo implode(' ', $dayClasses); ?>" title="<?php echo isset($appointmentsByDate[$dateKey]) ? count($appointmentsByDate[$dateKey]) . ' cita(s)' : ''; ?>"><?php echo $calendarDay->format('j'); ?></span>
            <?php endforeach; ?>
          </div>
        </section>
      </div>

      <section class="panel">
        <div class="panel-heading">
          <h2>Historial de Actuaciones<?php echo $selectedCaseNumber !== '' ? ' · ' . htmlspecialchars($selectedCaseNumber, ENT_QUOTES, 'UTF-8') : ''; ?></h2>
          <div class="history-controls">
            <?php if ($cases): ?>
              <form class="case-picker" action="casos.php" method="get">
                <select name="caso" aria-label="Seleccionar caso" onchange="this.form.submit()">
                  <?php foreach ($cases as $case): ?>
                    <option value="<?php echo htmlspecialchars($case['numero'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($case['numero'] ?? '') === $selectedCaseNumber ? 'selected' : ''; ?>><?php echo htmlspecialchars(($case['numero'] ?? '') . ' · ' . ($case['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="historial" value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="mes" value="<?php echo htmlspecialchars($requestedMonth, ENT_QUOTES, 'UTF-8'); ?>">
              </form>
            <?php endif; ?>
            <nav class="history-toolbar" aria-label="Filtrar historial">
              <?php foreach ($validHistoryFilters as $filter): ?>
                <?php $filterUrl = '?' . http_build_query(['caso' => $selectedCaseNumber, 'historial' => $filter, 'mes' => $requestedMonth]); ?>
                <a class="filter-link <?php echo $statusFilter === $filter ? 'active' : ''; ?>" data-status="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($filterUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?></a>
              <?php endforeach; ?>
            </nav>
          </div>
        </div>
        <div class="table-scroll">
          <table class="history-table">
            <thead><tr><th>Fecha</th><th>Actor</th><th>Actuación</th><th>Estado</th></tr></thead>
            <tbody>
              <?php if (!$visibleActions): ?>
                <tr><td class="empty-state" colspan="4">No hay actuaciones para este filtro.</td></tr>
              <?php else: ?>
                <?php foreach (array_slice($visibleActions, 0, 12) as $action): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($action['fecha'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($action['actor'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($action['actuacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($action['estado'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($selectedCase): ?>
          <section class="comment-section" aria-label="Comentarios del caso">
            <h3>Comentarios del caso</h3>
            <?php if (!$comments): ?><p class="empty-state">Todavía no hay comentarios.</p>
            <?php else: ?><div class="comment-list">
              <?php foreach ($comments as $comment): ?>
                <?php $commentTime = strtotime($comment['created_at'] ?? ''); ?>
                <article class="comment-item">
                  <div class="comment-meta"><strong><?php echo htmlspecialchars(($comment['autor'] ?? 'Usuario') . ' · ' . (($comment['rol_autor'] ?? '') === 'admin' ? 'Administración' : 'Abogado'), ENT_QUOTES, 'UTF-8'); ?></strong><time><?php echo $commentTime ? date('d/m/Y H:i', $commentTime) : ''; ?></time></div>
                  <p class="comment-text"><?php echo htmlspecialchars($comment['comentario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
              <?php endforeach; ?>
            </div><?php endif; ?>
            <form class="comment-form" action="guardar_accion.php" method="post">
              <input type="hidden" name="action" value="comentario">
              <input type="hidden" name="return_to" value="casos.php">
              <input type="hidden" name="numero" value="<?php echo htmlspecialchars($selectedCaseNumber, ENT_QUOTES, 'UTF-8'); ?>">
              <textarea name="comentario" maxlength="5000" placeholder="Escribe una actualización para este caso" aria-label="Comentario del caso" required></textarea>
              <button class="primary-btn" type="submit"><i class="fa-regular fa-paper-plane"></i> Publicar</button>
            </form>
          </section>
        <?php endif; ?>
        <?php if ($selectedCase && !empty($selectedCase['documentos'])): ?>
          <div class="case-docs">
            <strong>Documentos:</strong>
            <?php foreach ($selectedCase['documentos'] as $document): ?>
              <a href="descargar_documento.php?archivo=<?php echo rawurlencode($document['archivo']); ?>"><?php echo htmlspecialchars($document['nombre'], ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($selectedCase): ?>
          <div class="case-summary">
            <span>Cliente: <?php echo htmlspecialchars($selectedCase['cliente'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></span>
            <span>Abogado: <?php echo htmlspecialchars($selectedCase['abogado'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></span>
            <span>Área: <?php echo htmlspecialchars($selectedCase['area'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<dialog id="actuation-dialog" aria-labelledby="actuation-title">
  <div class="dialog-header">
    <h2 id="actuation-title">Nueva Actuación</h2>
    <button class="dialog-close" type="button" onclick="document.getElementById('actuation-dialog').close();" aria-label="Cerrar">&times;</button>
  </div>
  <?php if ($cases): ?>
    <form action="guardar_accion.php" method="post">
      <input type="hidden" name="action" value="actuacion">
      <input type="hidden" name="return_to" value="casos.php">
      <input type="hidden" name="return_case" value="<?php echo htmlspecialchars($selectedCaseNumber, ENT_QUOTES, 'UTF-8'); ?>">
      <div class="field">
        <label for="actuation-case">Caso</label>
        <select id="actuation-case" name="numero" required>
          <?php foreach ($cases as $case): ?>
            <option value="<?php echo htmlspecialchars($case['numero'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($case['numero'] ?? '') === $selectedCaseNumber ? 'selected' : ''; ?>><?php echo htmlspecialchars(($case['numero'] ?? '') . ' · ' . ($case['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-top:10px;">
        <label for="actuation-text">Descripción</label>
        <textarea id="actuation-text" name="actuacion" required></textarea>
      </div>
      <div class="field" style="margin-top:10px;">
        <label for="actuation-status">Estado</label>
        <select id="actuation-status" name="estado">
          <option>Abierto</option><option>En Proceso</option><option>Cerrado</option><option>Archivado</option>
        </select>
      </div>
      <div class="dialog-actions">
        <button class="secondary-btn" type="button" onclick="document.getElementById('actuation-dialog').close();">Cancelar</button>
        <button class="primary-btn" type="submit">Guardar actuación</button>
      </div>
    </form>
  <?php else: ?>
    <p class="empty-state">Primero guarda un caso para poder asociarle una actuación.</p>
    <div class="dialog-actions">
      <button class="secondary-btn" type="button" onclick="document.getElementById('actuation-dialog').close();">Cancelar</button>
      <button class="primary-btn" type="button" onclick="document.getElementById('actuation-dialog').close(); document.getElementById('numero').focus();">Registrar caso</button>
    </div>
  <?php endif; ?>
</dialog>

<script>
  const caseFileInput = document.getElementById('documentos');
  const caseDropzone = document.getElementById('case-dropzone');
  const documentList = document.getElementById('document-list');
  const updateDocumentList = () => {
    const files = Array.from(caseFileInput.files);
    documentList.textContent = files.length
      ? files.map((file) => `${file.name} (${(file.size / 1024 / 1024).toFixed(1)} MB)`).join(', ')
      : 'PDF, DOC o DOCX; hasta 10 MB por archivo';
  };
  caseFileInput.addEventListener('change', updateDocumentList);
  ['dragenter', 'dragover'].forEach((eventName) => {
    caseDropzone.addEventListener(eventName, (event) => {
      event.preventDefault();
      caseDropzone.style.borderColor = '#971b31';
    });
  });
  ['dragleave', 'drop'].forEach((eventName) => {
    caseDropzone.addEventListener(eventName, (event) => {
      event.preventDefault();
      caseDropzone.style.borderColor = '';
    });
  });
  caseDropzone.addEventListener('drop', (event) => {
    if (event.dataTransfer.files.length) {
      caseFileInput.files = event.dataTransfer.files;
      updateDocumentList();
    }
  });
</script>
</body>
</html>
