<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_lawyer('../index.php');
require_once __DIR__ . '/../includes/data_store.php';

$userId = (string)$_SESSION['user_id'];
$userName = (string)$_SESSION['user_name'];
$allCases = array_reverse(app_data_read('casos'));
$assignedCases = array_values(array_filter($allCases, static function ($case) use ($userId, $userName) {
    $assignedById = (string)($case['abogado_id'] ?? '') === $userId;
    $assignedByLegacyName = empty($case['abogado_id']) && strcasecmp(trim($case['abogado'] ?? ''), $userName) === 0;
    return $assignedById || $assignedByLegacyName;
}));
$assignedCaseNumbers = array_values(array_filter(array_map(static fn($case) => $case['numero'] ?? '', $assignedCases)));
$caseComments = array_values(array_filter(app_data_read('comentarios'), static fn($comment) => in_array($comment['numero'] ?? '', $assignedCaseNumbers, true)));
$commentsByCase = [];
foreach ($caseComments as $comment) {
    $commentsByCase[$comment['numero']][] = $comment;
}
$caseNumberFilter = is_string($_GET['caso'] ?? null) ? trim($_GET['caso']) : '';
$statusFilter = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : '';
if (!in_array($statusFilter, ['', 'Abierto', 'En Proceso', 'Cerrado'], true)) $statusFilter = '';
$visibleCases = array_values(array_filter($assignedCases, static function ($case) use ($caseNumberFilter, $statusFilter) {
    return ($caseNumberFilter === '' || ($case['numero'] ?? '') === $caseNumberFilter)
        && ($statusFilter === '' || ($case['estado'] ?? 'Abierto') === $statusFilter);
}));
$openCount = count(array_filter($assignedCases, static fn($case) => ($case['estado'] ?? 'Abierto') === 'Abierto'));
$progressCount = count(array_filter($assignedCases, static fn($case) => ($case['estado'] ?? '') === 'En Proceso'));
$closedCount = count(array_filter($assignedCases, static fn($case) => ($case['estado'] ?? '') === 'Cerrado'));
$statusMessage = [
    'resolved' => 'El caso se marcó como resuelto y quedó registrado en el historial.',
    'commented' => 'Comentario publicado y enviado al administrador.',
    'error' => 'No se pudo actualizar el caso. Confirma que siga asignado a tu usuario.',
][$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis casos</title>
    <style>
        :root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#191919; --muted:#707070; --line:#dedede; --page:#eef0f2; --green:#438b5c; --blue:#426f9e; --gold:#b88734; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
        .main { min-height:100vh; margin-left:260px; padding:18px 20px 24px; }
        .head { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:14px; }
        .eyebrow { color:var(--wine-800); font-size:.7rem; font-weight:700; text-transform:uppercase; }
        h1 { margin:4px 0 0; font-size:1.7rem; }
        h2 { margin:0; font-size:1rem; }
        .metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:13px; }
        .metric { display:flex; align-items:center; gap:10px; min-height:68px; padding:11px 13px; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,.06); }
        .metric i { display:grid; place-items:center; width:35px; height:35px; border-radius:8px; background:#e7eef8; color:var(--blue); }
        .metric:nth-child(2) i { background:#f5eddc; color:var(--gold); }
        .metric:nth-child(3) i { background:#e4f1e7; color:var(--green); }
        .metric small { display:block; color:var(--muted); font-size:.68rem; }
        .metric strong { display:block; margin-top:2px; font-size:1.1rem; }
        .notice { margin:0 0 12px; padding:10px 12px; border:1px solid #e4c1c7; border-radius:7px; background:#fff8f9; color:var(--wine-900); font-size:.8rem; }
        .panel { padding:14px; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,.06); }
        .panel-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:11px; }
        .filter select { min-height:34px; padding:6px 8px; border:1px solid var(--line); border-radius:6px; background:#fff; font:inherit; font-size:.74rem; }
        .button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:35px; padding:8px 11px; border:1px solid transparent; border-radius:7px; background:linear-gradient(110deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.74rem; font-weight:700; text-decoration:none; cursor:pointer; }
        .button.secondary { border-color:#d1d1d1; background:#fff; color:var(--ink); }
        .button:disabled { opacity:.5; cursor:not-allowed; }
        .case-list { display:grid; gap:10px; }
        .case-card { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:14px; padding:13px; border:1px solid #e5e5e5; border-radius:7px; background:#fff; }
        .case-reference { color:var(--wine-800); font-size:.7rem; font-weight:800; }
        .case-title { margin:4px 0 7px; font-size:.94rem; font-weight:800; }
        .case-meta { display:flex; flex-wrap:wrap; gap:7px 15px; color:#666; font-size:.7rem; }
        .case-docs { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; font-size:.7rem; }
        .case-docs a { color:var(--wine-800); }
        .comments { display:grid; gap:6px; margin-top:11px; }
        .comment { padding:8px 9px; border:1px solid #ececec; border-radius:6px; background:#fafafa; }
        .comment-meta { display:flex; justify-content:space-between; gap:8px; color:var(--muted); font-size:.62rem; }
        .comment-text { margin:4px 0 0; font-size:.72rem; white-space:pre-wrap; overflow-wrap:anywhere; }
        .comment-form { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:7px; margin-top:9px; }
        .comment-form textarea { min-height:36px; padding:7px 8px; border:1px solid #d1d1d1; border-radius:6px; font:inherit; font-size:.72rem; resize:vertical; }
        .comment-form textarea:focus { outline:2px solid rgba(151,27,49,.17); border-color:var(--wine-700); }
        .case-state { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:99px; background:#e7eef8; color:#315779; font-size:.66rem; font-weight:700; }
        .case-state.progress { background:#f5eddc; color:#76591c; }
        .case-state.closed { background:#e4f1e7; color:#27683d; }
        .card-action { align-self:center; min-width:145px; }
        .empty { padding:28px 10px; color:var(--muted); text-align:center; }
        @media(max-width:700px) { .main { margin-left:0; padding:12px; } }
        @media(max-width:500px) { .head { align-items:flex-start; flex-direction:column; } .metrics { grid-template-columns:1fr; } .case-card { grid-template-columns:1fr; } .card-action { justify-self:start; } }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main">
    <header class="head"><div><span class="eyebrow">Espacio profesional</span><h1>Mis casos</h1></div><strong><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></strong></header>
    <?php if ($statusMessage !== ''): ?><p class="notice" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <section class="metrics" aria-label="Resumen de casos asignados">
        <div class="metric"><i class="fa-solid fa-folder-open"></i><span><small>Abiertos</small><strong><?php echo $openCount; ?></strong></span></div>
        <div class="metric"><i class="fa-solid fa-hourglass-half"></i><span><small>En proceso</small><strong><?php echo $progressCount; ?></strong></span></div>
        <div class="metric"><i class="fa-solid fa-circle-check"></i><span><small>Resueltos</small><strong><?php echo $closedCount; ?></strong></span></div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h2>Casos asignados</h2></div>
            <form class="filter" action="mis_casos.php" method="get">
                <select name="estado" aria-label="Filtrar casos por estado" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <?php foreach (['Abierto', 'En Proceso', 'Cerrado'] as $caseState): ?><option value="<?php echo htmlspecialchars($caseState, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $caseState ? 'selected' : ''; ?>><?php echo $caseState === 'Cerrado' ? 'Resuelto' : htmlspecialchars($caseState, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (!$visibleCases): ?>
            <p class="empty"><?php echo $assignedCases ? 'No hay casos con este estado.' : 'Todavía no tienes casos asignados. Contacta a administración para que te asigne uno.'; ?></p>
        <?php else: ?>
            <div class="case-list">
                <?php foreach ($visibleCases as $case): ?>
                    <?php
                    $caseState = $case['estado'] ?? 'Abierto';
                    $stateClass = $caseState === 'Cerrado' ? 'closed' : ($caseState === 'En Proceso' ? 'progress' : '');
                    ?>
                    <article class="case-card">
                        <div>
                            <span class="case-reference"><?php echo htmlspecialchars($case['numero'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <h3 class="case-title"><?php echo htmlspecialchars($case['nombre'] ?? (($case['tipo'] ?? 'Caso') . ' · ' . ($case['numero'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="case-meta">
                                <span><?php echo htmlspecialchars($case['tipo'] ?? 'Caso', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span>Área: <?php echo htmlspecialchars($case['area'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span>Cliente: <?php echo htmlspecialchars($case['cliente'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if (!empty($case['contrato'])): ?><span><?php echo htmlspecialchars($case['contrato'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            </div>
                            <?php if (!empty($case['documentos'])): ?><div class="case-docs"><?php foreach ($case['documentos'] as $document): ?><a href="descargar_documento.php?archivo=<?php echo rawurlencode($document['archivo']); ?>"><i class="fa-regular fa-file-lines"></i> <?php echo htmlspecialchars($document['nombre'], ENT_QUOTES, 'UTF-8'); ?></a><?php endforeach; ?></div><?php endif; ?>
                            <div class="comments">
                                <?php foreach ($commentsByCase[$case['numero']] ?? [] as $comment): ?>
                                    <?php $commentTime = strtotime($comment['created_at'] ?? ''); ?>
                                    <article class="comment"><div class="comment-meta"><strong><?php echo htmlspecialchars(($comment['autor'] ?? 'Usuario') . ' · ' . (($comment['rol_autor'] ?? '') === 'admin' ? 'Administración' : 'Abogado'), ENT_QUOTES, 'UTF-8'); ?></strong><time><?php echo $commentTime ? date('d/m/Y H:i', $commentTime) : ''; ?></time></div><p class="comment-text"><?php echo htmlspecialchars($comment['comentario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                                <?php endforeach; ?>
                            </div>
                            <form class="comment-form" action="guardar_accion.php" method="post">
                                <input type="hidden" name="action" value="comentario">
                                <input type="hidden" name="return_to" value="mis_casos.php">
                                <input type="hidden" name="numero" value="<?php echo htmlspecialchars($case['numero'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <textarea name="comentario" maxlength="5000" placeholder="Escribe un comentario al administrador" aria-label="Comentario del caso" required></textarea>
                                <button class="button" type="submit">Enviar</button>
                            </form>
                            <?php if ($caseState === 'Cerrado' && !empty($case['fecha_resolucion'])): ?><div class="case-meta" style="margin-top:8px;">Resuelto el <?php echo htmlspecialchars(date('d/m/Y', strtotime($case['fecha_resolucion'])), ENT_QUOTES, 'UTF-8'); ?> por <?php echo htmlspecialchars($case['resuelto_por'] ?? $userName, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                        </div>
                        <div class="card-action">
                            <span class="case-state <?php echo $stateClass; ?>"><?php echo $caseState === 'Cerrado' ? 'Resuelto' : htmlspecialchars($caseState, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($caseState !== 'Cerrado'): ?>
                                <form action="guardar_accion.php" method="post" style="margin-top:9px;">
                                    <input type="hidden" name="action" value="resolver_caso">
                                    <input type="hidden" name="return_to" value="mis_casos.php">
                                    <input type="hidden" name="numero" value="<?php echo htmlspecialchars($case['numero'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="button" type="submit"><i class="fa-solid fa-check"></i> Marcar resuelto</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
