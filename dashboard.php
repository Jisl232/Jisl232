<?php
require_once __DIR__ . '/includes/user_access.php';
app_require_admin('pages/mis_casos.php', 'index.php');
require_once __DIR__ . '/includes/data_store.php';

$userName = $_SESSION['user_name'] ?? 'Usuario';
$clientsTotal = app_data_count('clientes');
$contracts = app_data_read('contratos');
$cases = app_data_read('casos');
$appointments = app_data_read('citas');
$appointmentsToday = count(array_filter($appointments, static fn($appointment) => ($appointment['fecha'] ?? '') === date('Y-m-d')));
$openCases = count(array_filter($cases, static fn($case) => strcasecmp($case['estado'] ?? '', 'Abierto') === 0));
$contractsTotal = count($contracts);
$todayAppointments = array_values(array_filter($appointments, static fn($appointment) => ($appointment['fecha'] ?? '') >= date('Y-m-d')));
usort($todayAppointments, static fn($first, $second) => (($first['fecha'] ?? '') . ' ' . ($first['hora'] ?? '')) <=> (($second['fecha'] ?? '') . ' ' . ($second['hora'] ?? '')));
$reportSummary = [
    ['label' => 'Clientes', 'value' => $clientsTotal],
    ['label' => 'Casos abiertos', 'value' => $openCases],
    ['label' => 'Citas de hoy', 'value' => $appointmentsToday],
    ['label' => 'Contratos', 'value' => $contractsTotal],
];
$chartMaximum = max(1, ...array_column($reportSummary, 'value'));
$monthStart = new DateTimeImmutable('first day of this month');
$calendarStart = $monthStart->modify('monday this week');
$calendarDays = [];
$appointmentDates = [];
foreach ($appointments as $appointment) {
    if (!empty($appointment['fecha'])) {
        $appointmentDates[$appointment['fecha']] = true;
    }
}
for ($dayOffset = 0; $dayOffset < 42; $dayOffset++) {
    $calendarDays[] = $calendarStart->modify('+' . $dayOffset . ' days');
}
$calendarMonthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$configurationRecords = app_data_read('configuracion');
$dashboardConfiguration = $configurationRecords ? end($configurationRecords) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard principal - Bufete</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --wine-900: #4a0b14;
            --wine-800: #5f0d1a;
            --wine-700: #7a1225;
            --wine-600: #9a1d32;
            --black-900: #121212;
            --text: #1f1f1f;
            --muted: #666666;
            --line: rgba(17,17,17,0.08);
            --panel: #f7f7f7;
            --soft-red: #f6dfe4;
            --soft-blue: #e7effd;
            --soft-yellow: #fdf0ce;
            --soft-green: #dff3e8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            background: #eeeeee;
            color: var(--text);
        }

        .main-content {
            margin-left: 260px;
            background: #f4f4f4;
            min-height: 100vh;
            padding: 18px 22px 26px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,0.75);
            border: 1px solid var(--line);
            border-radius: 12px 12px 0 0;
            padding: 14px 18px;
        }

        .topbar-title {
            font-size: 1.08rem;
            font-weight: 700;
            color: var(--text);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.7);
            color: var(--text);
            cursor: pointer;
            transition: 0.2s ease;
        }

        .icon-btn:hover {
            background: #ececec;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d9d9d9, #bdbdbd);
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            border: 2px solid #fff;
        }

        .page-wrap {
            background: #f8f8f8;
            border: 1px solid var(--line);
            border-top: none;
            border-radius: 0 0 12px 12px;
            padding: 18px 20px 22px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }

        .welcome-title {
            font-size: 2.3rem;
            font-weight: 800;
            margin: 6px 0 20px;
            color: #1d1d1d;
        }

        .kpi-label {
            font-size: 1.08rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #2d2d2d;
        }

        .kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .kpi-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f5f5f5;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px 16px;
            min-height: 82px;
        }

        .kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .ink-red { background: var(--soft-red); color: var(--wine-700); }
        .ink-blue { background: var(--soft-blue); color: #3c6b9b; }
        .ink-yellow { background: var(--soft-yellow); color: #8e6b1a; }
        .ink-green { background: var(--soft-green); color: #177a42; }

        .kpi-value {
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .kpi-meta {
            display: block;
            font-size: 0.78rem;
            color: var(--muted);
            margin-top: 3px;
        }

        .module-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 18px;
        }

        .module {
            background: #f6f6f6;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px 18px;
        }

        .module h3 {
            font-size: 1.08rem;
            font-weight: 700;
            margin-bottom: 14px;
            color: #1f1f1f;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 12px;
        }

        .field label {
            font-size: 0.77rem;
            font-weight: 700;
            color: #4d4d4d;
        }

        .field input,
        .field select {
            width: 100%;
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            background: #fff;
            padding: 10px 12px;
            color: var(--text);
            font-size: 0.95rem;
        }

        .field input:focus,
        .field select:focus {
            outline: none;
            border-color: var(--wine-700);
            box-shadow: 0 0 0 2px rgba(122,18,37,0.08);
        }

        .mini-upload {
            border: 1px solid #d9d9d9;
            background: #fff;
            border-radius: 10px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
            color: #2d2d2d;
        }

        .mini-upload .upload-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--wine-700);
            font-size: 1.3rem;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 7px 0 10px;
            color: #333;
            font-size: 0.86rem;
        }

        .toggle {
            position: relative;
            width: 42px;
            height: 22px;
            border-radius: 999px;
            background: var(--wine-700);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle::after {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            top: 2px;
            right: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.18);
        }

        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            font-weight: 700;
            color: #2c2c2c;
        }

        .arrow-wrap {
            display: flex;
            gap: 8px;
        }

        .arrow-btn {
            width: 28px;
            height: 28px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: #333;
            cursor: pointer;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(28px, 1fr));
            gap: 6px;
            margin-top: 10px;
        }

        .day {
            position: relative;
            background: #f0f0f0;
            border: 1px solid transparent;
            border-radius: 8px;
            min-height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: #2e2e2e;
        }

        .day.active {
            background: #f3dfe4;
            border-color: rgba(122,18,37,0.18);
            color: var(--wine-700);
            font-weight: 700;
        }

        .day.has-appointment::after {
            content: "";
            position: absolute;
            bottom: 3px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--wine-700);
        }

        .day.active.has-appointment::after {
            background: var(--wine-700);
        }

        .agenda-list {
            list-style: none;
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #333;
            font-size: 0.85rem;
        }

        .agenda-list li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot.red { background: var(--wine-700); }
        .dot.green { background: #2bbb5f; }
        .dot.orange { background: #e0a637; }

        .report-box {
            height: 120px;
            background: linear-gradient(180deg, rgba(122,18,37,0.06), rgba(122,18,37,0.02));
            border: 1px solid rgba(122,18,37,0.08);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }

        .bar {
            position: absolute;
            bottom: 0;
            width: 18px;
            border-radius: 8px 8px 0 0;
            background: linear-gradient(180deg, #9a1d32, #5f0d1a);
        }

        .report-list {
            display: grid;
            gap: 8px;
            margin-top: 12px;
        }

        .report-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            font-size: 0.8rem;
            color: #333;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(120px, 1fr));
            gap: 10px 12px;
        }

        .setting-box {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 10px;
            padding: 10px;
            min-height: 110px;
            color: #2a2a2a;
        }

        .setting-box strong {
            display: block;
            margin-bottom: 8px;
        }

        .setting-box span {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 12px;
            gap: 12px;
            font-size: 0.85rem;
            color: #333;
        }

        .btn-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .btn {
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn.primary {
            background: linear-gradient(135deg, var(--wine-800), var(--wine-700));
            color: #fff;
        }

        .btn.secondary {
            background: #f0f0f0;
            border-color: #d7d7d7;
            color: #1d1d1d;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 1120px) {
            .module-grid { grid-template-columns: 1fr; }
            .kpis { grid-template-columns: repeat(2, minmax(180px, 1fr)); }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-title">Bufete de Abogados - Panel de Inicio</div>
            <div class="topbar-actions">
                <button class="icon-btn" type="button" onclick="alert('Sin notificaciones nuevas.');"><i class="fa-regular fa-bell"></i></button>
                <button class="icon-btn" type="button" onclick="alert('Sin mensajes nuevos.');"><i class="fa-regular fa-envelope"></i></button>
                <a href="dashboard.php" aria-label="Dashboard" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none;"><div class="avatar"></div></a>
            </div>
        </header>

        <div class="page-wrap">
            <h1 class="welcome-title">Gestión de Clientes, Agenda, Reportes y Configuración</h1>

            <div class="kpi-label">KPI</div>
            <section class="kpis">
                <div class="kpi-card">
                    <div class="kpi-icon ink-red"><i class="fa-solid fa-user-group"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo $clientsTotal; ?></div>
                        <span class="kpi-meta">Clientes Totales</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon ink-blue"><i class="fa-regular fa-calendar-days"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo $appointmentsToday; ?></div>
                        <span class="kpi-meta">Citas Hoy</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon ink-yellow"><i class="fa-solid fa-file-lines"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo $openCases; ?></div>
                        <span class="kpi-meta">Casos Abiertos</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon ink-green"><i class="fa-solid fa-file-contract"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo $contractsTotal; ?></div>
                        <span class="kpi-meta">Contratos Registrados</span>
                    </div>
                </div>
            </section>

            <section class="module-grid">
                <div class="module">
                    <h3>Módulo de Gestión de Clientes</h3>
                    <form action="pages/guardar_accion.php" method="post">
                        <input type="hidden" name="action" value="cliente">
                        <input type="hidden" name="return_to" value="dashboard.php">
                    <div class="field">
                        <label>Nombre / Razón Social</label>
                        <input type="text" name="nombre" placeholder="Nombre del cliente" required />
                    </div>
                    <div class="field">
                        <label>RFC / NIT</label>
                        <input type="text" name="identificacion" />
                    </div>
                    <div class="field">
                        <label>Correo</label>
                        <input type="email" name="correo" />
                    </div>
                    <div class="field">
                        <label>Dirección</label>
                        <input type="text" name="direccion" />
                    </div>
                    <div class="btn-row">
                        <button class="btn primary" type="submit">Guardar Cliente</button>
                        <button class="btn secondary" type="button" onclick="window.location.href='pages/clientes.php';">Ver clientes</button>
                    </div>
                    </form>
                </div>

                <div class="module">
                    <h3>Módulo de Agenda / Citas</h3>
                    <div class="calendar-header">
                        <span><?php echo ucfirst($calendarMonthNames[(int)$monthStart->format('n') - 1]) . ' ' . $monthStart->format('Y'); ?></span>
                        <div class="arrow-wrap">
                            <button class="arrow-btn" type="button" aria-label="Abrir agenda" onclick="window.location.href='pages/agenda.php';"><i class="fa-solid fa-chevron-left"></i></button>
                            <button class="arrow-btn" type="button" aria-label="Abrir agenda" onclick="window.location.href='pages/agenda.php';"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="calendar-grid">
                        <div class="day">L</div><div class="day">M</div><div class="day">M</div><div class="day">J</div><div class="day">V</div><div class="day">S</div><div class="day">D</div>
                        <?php foreach ($calendarDays as $calendarDay): ?>
                            <?php
                            $dayClasses = ['day'];
                            if ($calendarDay->format('m') !== $monthStart->format('m')) $dayClasses[] = 'outside';
                            if ($calendarDay->format('Y-m-d') === date('Y-m-d')) $dayClasses[] = 'active';
                            if (isset($appointmentDates[$calendarDay->format('Y-m-d')])) $dayClasses[] = 'has-appointment';
                            ?>
                            <div class="<?php echo implode(' ', $dayClasses); ?>" title="<?php echo isset($appointmentDates[$calendarDay->format('Y-m-d')]) ? 'Tiene citas registradas' : ''; ?>"><?php echo $calendarDay->format('j'); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <ul class="agenda-list">
                        <?php if (!$todayAppointments): ?>
                            <li>No hay próximas citas registradas.</li>
                        <?php else: ?>
                            <?php foreach (array_slice($todayAppointments, 0, 3) as $appointment): ?>
                                <li><span class="dot red"></span> <?php echo htmlspecialchars(($appointment['fecha'] ?? '') . ' ' . ($appointment['hora'] ?? '') . ' · ' . ($appointment['titulo'] ?? 'Cita'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <div class="btn-row">
                        <button class="btn primary" type="button" onclick="window.location.href='pages/agenda.php#nueva';">Nueva Cita</button>
                        <button class="btn secondary" type="button" onclick="window.location.href='pages/agenda.php';">Ver agenda</button>
                    </div>
                </div>

                <div class="module">
                    <h3>Módulo de Reportes</h3>
                    <div class="report-box">
                        <?php foreach ($reportSummary as $index => $item): ?>
                            <div class="bar" style="left:<?php echo 8 + $index * 23; ?>%;height:<?php echo max(8, (int)round(($item['value'] / $chartMaximum) * 90)); ?>%;" title="<?php echo htmlspecialchars($item['label'] . ': ' . $item['value'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="report-list">
                        <?php foreach ($reportSummary as $item): ?>
                            <div class="report-item"><span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo date('d/m/Y'); ?></span><strong><?php echo $item['value']; ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="btn-row">
                        <a class="btn primary" href="pages/exportar.php?tipo=resumen&amp;formato=pdf" style="text-decoration:none;">Exportar PDF</a>
                        <a class="btn secondary" href="pages/exportar.php?tipo=resumen&amp;formato=csv" style="text-decoration:none;">Exportar CSV</a>
                        <button class="btn secondary" type="button" onclick="window.location.href='pages/reportes.php';">Ver reportes</button>
                    </div>
                </div>
            </section>

            <section class="module" style="margin-top: 18px;">
                <h3>Módulo de Configuración</h3>
                <form action="pages/guardar_accion.php" method="post">
                    <input type="hidden" name="action" value="configuracion">
                    <input type="hidden" name="return_to" value="dashboard.php">
                    <input type="hidden" name="despacho" value="<?php echo htmlspecialchars($dashboardConfiguration['despacho'] ?? 'Bufete de Abogados', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="correo" value="<?php echo htmlspecialchars($dashboardConfiguration['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="settings-grid">
                    <div class="setting-box">
                        <strong>Detalles del despacho</strong>
                        <span>Dirección, contacto, logo y correo institucional.</span>
                    </div>
                    <div class="setting-box">
                        <strong>Plantillas</strong>
                        <span>Contratos, notificaciones y avisos automáticos.</span>
                    </div>
                    <div class="setting-box">
                        <strong>Impuestos</strong>
                        <span>IVA, tasas, validaciones y pagos de facturación.</span>
                    </div>
                    <div class="setting-box">
                        <strong>Seguridad</strong>
                        <div class="settings-row">
                            <span>Backup</span>
                            <input type="checkbox" name="backup" <?php echo !isset($dashboardConfiguration['backup']) || $dashboardConfiguration['backup'] ? 'checked' : ''; ?>>
                        </div>
                        <div class="settings-row">
                            <span>Recordatorios</span>
                            <input type="checkbox" name="recordatorios" <?php echo !isset($dashboardConfiguration['recordatorios']) || $dashboardConfiguration['recordatorios'] ? 'checked' : ''; ?>>
                        </div>
                    </div>
                </div>
                <div class="btn-row">
                    <button class="btn primary" type="submit">Guardar Cambios</button>
                    <button class="btn secondary" type="button" onclick="window.location.href='pages/configuracion.php';">Abrir configuración</button>
                </div>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
