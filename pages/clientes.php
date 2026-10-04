<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';
$clients = array_reverse(app_data_read('clientes'));
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$typeFilter = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
$statusFilter = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '';
if (!in_array($typeFilter, ['', 'Persona Física', 'Persona Jurídica'], true)) $typeFilter = '';
if (!in_array($statusFilter, ['', 'Activo', 'Inactivo'], true)) $statusFilter = '';
$filteredClients = array_values(array_filter($clients, static function ($client) use ($search, $typeFilter, $statusFilter) {
    $searchable = implode(' ', [$client['nombre'] ?? '', $client['identificacion'] ?? '', $client['correo'] ?? '', $client['telefono'] ?? '']);
    $matchesSearch = $search === '' || stripos($searchable, $search) !== false;
    $matchesType = $typeFilter === '' || ($client['tipo'] ?? 'Persona Física') === $typeFilter;
    $matchesStatus = $statusFilter === '' || ($client['estado'] ?? 'Activo') === $statusFilter;
    return $matchesSearch && $matchesType && $matchesStatus;
}));
$activeClients = count(array_filter($clients, static fn($client) => ($client['estado'] ?? 'Activo') === 'Activo'));
$individualClients = count(array_filter($clients, static fn($client) => ($client['tipo'] ?? 'Persona Física') === 'Persona Física'));
$companyClients = count(array_filter($clients, static fn($client) => ($client['tipo'] ?? '') === 'Persona Jurídica'));
$statusMessage = [
    'success' => 'Cliente guardado correctamente.',
    'error' => 'No se pudo guardar. Revisa el nombre, correo y que la identificación no esté duplicada.',
][$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes</title>
    <style>
        :root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#191919; --muted:#707070; --line:#dedede; --page:#eef0f2; --surface:#fff; --green:#438b5c; --gray:#858585; --blue:#426f9e; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
        .main { min-height:100vh; margin-left:260px; padding:18px 20px 24px; }
        .page-head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:13px; }
        .eyebrow { color:var(--wine-800); font-size:.7rem; font-weight:700; text-transform:uppercase; }
        h1 { margin:4px 0 0; font-size:1.7rem; }
        h2 { margin:0; font-size:.98rem; }
        .button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:35px; padding:8px 11px; border:1px solid transparent; border-radius:7px; background:linear-gradient(110deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.73rem; font-weight:700; text-decoration:none; cursor:pointer; }
        .button.secondary { border-color:#d1d1d1; background:#fff; color:var(--ink); }
        .status-message { margin:0 0 12px; padding:9px 12px; border:1px solid #e4c1c7; border-radius:7px; background:#fff8f9; color:var(--wine-900); font-size:.78rem; }
        .metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:13px; }
        .metric { display:flex; align-items:center; gap:10px; min-height:68px; padding:11px 12px; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,.06); }
        .metric-icon { display:grid; place-items:center; width:36px; height:36px; border-radius:8px; background:#f5e5e7; color:var(--wine-700); }
        .metric:nth-child(2) .metric-icon { background:#e4f1e7; color:var(--green); }
        .metric:nth-child(3) .metric-icon { background:#e7eef8; color:var(--blue); }
        .metric:nth-child(4) .metric-icon { background:#ededed; color:#555; }
        .metric-label { display:block; color:var(--muted); font-size:.67rem; }
        .metric-value { display:block; margin-top:2px; font-size:1.08rem; font-weight:800; }
        .layout { display:grid; grid-template-columns:minmax(0,1.6fr) minmax(280px,.8fr); gap:12px; align-items:start; }
        .panel { min-width:0; padding:13px; border:1px solid var(--line); border-radius:8px; background:var(--surface); box-shadow:0 2px 6px rgba(0,0,0,.06); }
        .panel-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:9px; margin-bottom:10px; }
        .panel-head p { margin:3px 0 0; color:var(--muted); font-size:.68rem; }
        .filters { display:grid; grid-template-columns:minmax(140px,1fr) 150px 145px auto; gap:8px; align-items:end; margin-bottom:10px; }
        .field { min-width:0; margin-bottom:9px; }
        .field label { display:block; margin-bottom:4px; color:#505050; font-size:.69rem; font-weight:700; }
        .field input,.field select,.field textarea { width:100%; min-height:34px; padding:7px 8px; border:1px solid #d1d1d1; border-radius:6px; background:#fff; color:var(--ink); font:inherit; font-size:.75rem; }
        .field textarea { min-height:68px; resize:vertical; }
        .field input:focus,.field select:focus,.field textarea:focus { outline:2px solid rgba(151,27,49,.17); border-color:var(--wine-700); }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:.71rem; }
        th,td { padding:8px 7px; border-bottom:1px solid #e8e8e8; text-align:left; vertical-align:middle; }
        th { color:#555; font-size:.67rem; font-weight:800; white-space:nowrap; }
        .client-name { font-weight:700; }
        .client-sub { display:block; margin-top:3px; color:var(--muted); font-size:.65rem; }
        .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 7px; border-radius:99px; background:#e5f3e9; color:#27683d; font-size:.63rem; font-weight:700; white-space:nowrap; }
        .badge::before { width:6px; height:6px; border-radius:50%; background:var(--green); content:''; }
        .badge.inactive { background:#ededed; color:#555; }
        .badge.inactive::before { background:var(--gray); }
        .row-action { color:var(--wine-800); font-weight:700; text-decoration:none; white-space:nowrap; }
        .empty { padding:22px 8px; color:var(--muted); text-align:center; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 10px; }
        .field.full { grid-column:1/-1; }
        .form-actions { display:flex; justify-content:flex-end; margin-top:5px; }
        .quick-links { display:grid; gap:8px; margin-top:12px; }
        .quick-links a { justify-content:flex-start; }
        @media (max-width:1100px) { .layout { grid-template-columns:1fr; } }
        @media (max-width:760px) { .main { margin-left:0; padding:12px; } .metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .filters { grid-template-columns:1fr 1fr; } }
        @media (max-width:460px) { .page-head { align-items:flex-start; flex-direction:column; } .filters,.form-grid { grid-template-columns:1fr; } .field.full { grid-column:auto; } }
    </style>
</head>
<body>
<?php include_once '../includes/sidebar.php'; ?>
<div class="main">
    <header class="page-head">
        <div><span class="eyebrow">Directorio del despacho</span><h1>Gestión de Clientes</h1></div>
        <a class="button secondary" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Escritorio</a>
    </header>
    <?php if ($statusMessage !== ''): ?><p class="status-message" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

    <section class="metrics" aria-label="Indicadores de clientes">
        <div class="metric"><span class="metric-icon"><i class="fa-solid fa-users"></i></span><span><span class="metric-label">Clientes registrados</span><strong class="metric-value"><?php echo count($clients); ?></strong></span></div>
        <div class="metric"><span class="metric-icon"><i class="fa-solid fa-user-check"></i></span><span><span class="metric-label">Activos</span><strong class="metric-value"><?php echo $activeClients; ?></strong></span></div>
        <div class="metric"><span class="metric-icon"><i class="fa-regular fa-user"></i></span><span><span class="metric-label">Personas físicas</span><strong class="metric-value"><?php echo $individualClients; ?></strong></span></div>
        <div class="metric"><span class="metric-icon"><i class="fa-solid fa-building"></i></span><span><span class="metric-label">Personas jurídicas</span><strong class="metric-value"><?php echo $companyClients; ?></strong></span></div>
    </section>

    <div class="layout">
        <section class="panel">
            <div class="panel-head"><div><h2>Directorio</h2><p><?php echo count($filteredClients); ?> de <?php echo count($clients); ?> clientes</p></div><div class="quick-links" style="display:flex;margin:0;">
                <a class="button secondary" href="exportar.php?tipo=clientes&amp;formato=csv"><i class="fa-solid fa-file-csv"></i> CSV</a>
                <a class="button secondary" href="exportar.php?tipo=clientes&amp;formato=pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
            </div></div>
            <form class="filters" method="get" action="clientes.php">
                <div class="field"><label for="client-search">Buscar</label><input id="client-search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre, RFC, correo o teléfono"></div>
                <div class="field"><label for="client-type-filter">Tipo</label><select id="client-type-filter" name="tipo"><option value="">Todos</option><option value="Persona Física" <?php echo $typeFilter === 'Persona Física' ? 'selected' : ''; ?>>Persona Física</option><option value="Persona Jurídica" <?php echo $typeFilter === 'Persona Jurídica' ? 'selected' : ''; ?>>Persona Jurídica</option></select></div>
                <div class="field"><label for="client-status-filter">Estado</label><select id="client-status-filter" name="estado"><option value="">Todos</option><option value="Activo" <?php echo $statusFilter === 'Activo' ? 'selected' : ''; ?>>Activo</option><option value="Inactivo" <?php echo $statusFilter === 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option></select></div>
                <button class="button" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Cliente</th><th>Identificación</th><th>Contacto</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php if (!$filteredClients): ?><tr><td class="empty" colspan="5">No hay clientes para estos filtros.</td></tr>
                        <?php else: foreach ($filteredClients as $index => $client): ?>
                            <?php $clientStatus = $client['estado'] ?? 'Activo'; $contractUrl = 'contractos.php?' . http_build_query(['cliente' => $client['nombre'] ?? '']); ?>
                            <tr>
                                <td><span class="client-name"><?php echo htmlspecialchars($client['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="client-sub"><?php echo htmlspecialchars($client['tipo'] ?? 'Persona Física', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars($client['identificacion'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php if (!empty($client['correo'])): ?><a class="row-action" href="mailto:<?php echo htmlspecialchars($client['correo'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($client['correo'], ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?><?php if (!empty($client['telefono'])): ?><span class="client-sub"><?php echo htmlspecialchars($client['telefono'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?><?php if (empty($client['correo']) && empty($client['telefono'])): ?>—<?php endif; ?></td>
                                <td><span class="badge <?php echo $clientStatus === 'Activo' ? '' : 'inactive'; ?>"><?php echo htmlspecialchars($clientStatus, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><a class="row-action" href="<?php echo htmlspecialchars($contractUrl, ENT_QUOTES, 'UTF-8'); ?>">Crear contrato</a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="panel">
            <div class="panel-head"><div><h2>Registrar cliente</h2><p>Se guardará en MySQL</p></div></div>
            <form action="guardar_accion.php" method="post">
                <input type="hidden" name="action" value="cliente">
                <input type="hidden" name="return_to" value="clientes.php">
                <div class="form-grid">
                    <div class="field full"><label for="client-name">Nombre / Razón social</label><input id="client-name" name="nombre" required></div>
                    <div class="field"><label for="client-kind">Tipo de cliente</label><select id="client-kind" name="tipo"><option>Persona Física</option><option>Persona Jurídica</option></select></div>
                    <div class="field"><label for="client-state">Estado</label><select id="client-state" name="estado"><option>Activo</option><option>Inactivo</option></select></div>
                    <div class="field full"><label for="client-id">RFC / NIT</label><input id="client-id" name="identificacion" autocomplete="off"></div>
                    <div class="field"><label for="client-email">Correo</label><input id="client-email" name="correo" type="email"></div>
                    <div class="field"><label for="client-phone">Teléfono</label><input id="client-phone" name="telefono" type="tel"></div>
                    <div class="field full"><label for="client-address">Dirección</label><input id="client-address" name="direccion"></div>
                    <div class="field full"><label for="client-notes">Notas</label><textarea id="client-notes" name="notas"></textarea></div>
                </div>
                <div class="form-actions"><button class="button" type="submit"><i class="fa-regular fa-floppy-disk"></i> Guardar cliente</button></div>
            </form>
            <div class="quick-links"><a class="button secondary" href="contractos.php"><i class="fa-solid fa-file-contract"></i> Abrir contratos</a></div>
        </aside>
    </div>
</div>
</body>
</html>
