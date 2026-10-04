<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_role(['admin', 'abogado'], '../index.php');
require_once __DIR__ . '/../includes/data_store.php';

$allNotifications = array_values(array_filter(app_data_read('notificaciones'), static fn($notification) => (string)($notification['recipient_id'] ?? '') === (string)$_SESSION['user_id']));
$notificationFilter = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
if (!in_array($notificationFilter, ['', 'comentario'], true)) $notificationFilter = '';
$notifications = array_values(array_filter($allNotifications, static fn($notification) => $notificationFilter === '' || ($notification['tipo'] ?? '') === $notificationFilter));
usort($notifications, static fn($first, $second) => strcmp($second['created_at'] ?? '', $first['created_at'] ?? ''));
$unreadCount = count(array_filter($notifications, static fn($notification) => empty($notification['leida'])));
$statusMessage = [
    'read' => 'Notificación marcada como leída.',
    'read_all' => 'Todas las notificaciones quedaron leídas.',
][is_string($_GET['status'] ?? null) ? $_GET['status'] : ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones</title>
    <style>
        :root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#191919; --muted:#707070; --line:#dedede; --page:#eef0f2; --green:#438b5c; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
        .main { min-height:100vh; margin-left:260px; padding:18px 20px 24px; }
        .head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:14px; }
        .eyebrow { color:var(--wine-800); font-size:.7rem; font-weight:700; text-transform:uppercase; }
        h1 { margin:4px 0 0; font-size:1.65rem; }
        .head-actions { display:flex; align-items:center; gap:8px; }
        .button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:35px; padding:8px 11px; border:1px solid transparent; border-radius:7px; background:linear-gradient(110deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.74rem; font-weight:700; text-decoration:none; cursor:pointer; }
        .button.secondary { border-color:#d1d1d1; background:#fff; color:var(--ink); }
        .notice { margin:0 0 12px; padding:10px 12px; border:1px solid #e4c1c7; border-radius:7px; background:#fff8f9; color:var(--wine-900); font-size:.79rem; }
        .summary { display:flex; align-items:center; gap:9px; margin-bottom:11px; color:var(--muted); font-size:.77rem; }
        .filters { display:flex; gap:7px; margin-bottom:11px; }
        .filter-link { padding:7px 10px; border:1px solid var(--line); border-radius:6px; background:#fff; color:#444; font-size:.72rem; text-decoration:none; }
        .filter-link.active { border-color:var(--wine-700); color:var(--wine-800); font-weight:700; }
        .count { display:grid; place-items:center; min-width:24px; height:24px; padding:0 7px; border-radius:99px; background:var(--wine-700); color:#fff; font-size:.68rem; font-weight:800; }
        .list { display:grid; gap:8px; }
        .notification { display:grid; grid-template-columns:8px minmax(0,1fr) auto; align-items:center; gap:12px; padding:13px; border:1px solid var(--line); border-radius:8px; background:#fff; }
        .notification.unread { border-left:3px solid var(--wine-700); background:#fffdfd; }
        .dot { width:8px; height:8px; border-radius:50%; background:#bbb; }
        .unread .dot { background:var(--wine-700); }
        .title { margin:0; font-size:.85rem; font-weight:800; }
        .message { margin:4px 0 0; color:#555; font-size:.76rem; }
        .meta { display:flex; flex-wrap:wrap; gap:10px; margin-top:5px; color:var(--muted); font-size:.66rem; }
        .actions { display:flex; align-items:center; gap:8px; }
        .open-link { color:var(--wine-800); font-size:.72rem; font-weight:700; text-decoration:none; white-space:nowrap; }
        .mark-form { margin:0; }
        .mark-button { padding:6px 8px; border:1px solid var(--line); border-radius:6px; background:#fff; color:#444; font:inherit; font-size:.67rem; cursor:pointer; white-space:nowrap; }
        .empty { padding:42px 15px; border:1px solid var(--line); border-radius:8px; background:#fff; color:var(--muted); text-align:center; }
        @media(max-width:700px) { .main { margin-left:0; padding:12px; } }
        @media(max-width:500px) { .notification { grid-template-columns:8px minmax(0,1fr); } .actions { grid-column:2; justify-content:flex-start; } .head-actions { width:100%; } }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main">
    <header class="head">
        <div><span class="eyebrow">Actividad de tus casos</span><h1>Notificaciones</h1></div>
        <div class="head-actions">
            <?php if ($unreadCount > 0): ?>
                <form action="guardar_accion.php" method="post">
                    <input type="hidden" name="action" value="marcar_todas">
                    <input type="hidden" name="return_to" value="notificaciones.php">
                    <button class="button secondary" type="submit"><i class="fa-solid fa-check-double"></i> Marcar todas leídas</button>
                </form>
            <?php endif; ?>
            <a class="button secondary" href="<?php echo $_SESSION['user_role'] === 'abogado' ? 'mis_casos.php' : '../dashboard.php'; ?>"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        </div>
    </header>
    <?php if ($statusMessage !== ''): ?><p class="notice" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <nav class="filters" aria-label="Filtrar notificaciones">
        <a class="filter-link <?php echo $notificationFilter === '' ? 'active' : ''; ?>" href="notificaciones.php">Todas</a>
        <a class="filter-link <?php echo $notificationFilter === 'comentario' ? 'active' : ''; ?>" href="notificaciones.php?tipo=comentario">Comentarios</a>
    </nav>
    <div class="summary"><span class="count"><?php echo $unreadCount; ?></span><span>sin leer · <?php echo count($notifications); ?> en total</span></div>
    <?php if (!$notifications): ?>
        <div class="empty"><i class="fa-regular fa-bell-slash"></i><p>No tienes notificaciones todavía.</p></div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $isUnread = empty($notification['leida']);
                $caseNumber = is_string($notification['caso'] ?? null) ? $notification['caso'] : '';
                $caseRoute = $_SESSION['user_role'] === 'abogado' ? 'mis_casos.php' : 'casos.php';
                $caseUrl = $caseNumber !== '' ? $caseRoute . '?' . http_build_query(['caso' => $caseNumber]) : '';
                $createdAt = strtotime($notification['created_at'] ?? '');
                ?>
                <article class="notification <?php echo $isUnread ? 'unread' : ''; ?>">
                    <span class="dot" aria-hidden="true"></span>
                    <div>
                        <h2 class="title"><?php echo htmlspecialchars($notification['titulo'] ?? 'Notificación', ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="message"><?php echo htmlspecialchars($notification['mensaje'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="meta"><span><?php echo $createdAt ? date('d/m/Y H:i', $createdAt) : ''; ?></span><?php if ($caseNumber !== ''): ?><span>Caso <?php echo htmlspecialchars($caseNumber, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></div>
                    </div>
                    <div class="actions">
                        <?php if ($caseUrl !== ''): ?><a class="open-link" href="<?php echo htmlspecialchars($caseUrl, ENT_QUOTES, 'UTF-8'); ?>">Abrir caso</a><?php endif; ?>
                        <?php if ($isUnread): ?>
                            <form class="mark-form" action="guardar_accion.php" method="post">
                                <input type="hidden" name="action" value="marcar_notificacion">
                                <input type="hidden" name="return_to" value="notificaciones.php">
                                <input type="hidden" name="notificacion_id" value="<?php echo htmlspecialchars($notification['notificacion_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="mark-button" type="submit">Marcar leída</button>
                            </form>
                        <?php else: ?>
                            <span class="meta">Leída</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
