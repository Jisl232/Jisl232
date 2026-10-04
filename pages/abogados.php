<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('../index.php');

$database = app_user_database();
$users = $database->query('SELECT id, nombre, rol, activo, fecha_creacion FROM abogados ORDER BY fecha_creacion DESC')->fetchAll(PDO::FETCH_ASSOC);
$lawyerCount = count(array_filter($users, static fn($user) => $user['rol'] === 'abogado' && (bool)$user['activo']));
$adminCount = count(array_filter($users, static fn($user) => $user['rol'] === 'admin' && (bool)$user['activo']));
$statusMessage = [
	'created' => 'La cuenta se creó correctamente y ya puede iniciar sesión.',
	'error' => 'No se pudo crear la cuenta. Verifica el ID, nombre y contraseña.',
][$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Usuarios y Abogados</title>
	<style>
		:root { --wine-950:#40070e; --wine-900:#570b16; --wine-800:#751321; --wine-700:#971b31; --ink:#191919; --muted:#707070; --line:#dedede; --page:#eef0f2; --green:#438b5c; --blue:#426f9e; }
		* { box-sizing:border-box; }
		body { margin:0; background:var(--page); color:var(--ink); font-family:'Montserrat',sans-serif; }
		.main { min-height:100vh; margin-left:260px; padding:18px 20px 24px; }
		.page-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
		.eyebrow { color:var(--wine-800); font-size:.7rem; font-weight:700; text-transform:uppercase; }
		h1 { margin:4px 0 0; font-size:1.65rem; }
		h2 { margin:0 0 10px; font-size:1rem; }
		.button { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:36px; padding:8px 12px; border:1px solid transparent; border-radius:7px; background:linear-gradient(110deg,var(--wine-950),var(--wine-800)); color:#fff; font:inherit; font-size:.76rem; font-weight:700; text-decoration:none; cursor:pointer; }
		.button.secondary { border-color:#d1d1d1; background:#fff; color:var(--ink); }
		.notice { margin:0 0 12px; padding:10px 12px; border:1px solid #e4c1c7; border-radius:7px; background:#fff8f9; color:var(--wine-900); font-size:.8rem; }
		.metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:13px; }
		.metric { display:flex; align-items:center; gap:10px; min-height:66px; padding:11px 13px; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,.06); }
		.metric-icon { display:grid; place-items:center; width:35px; height:35px; border-radius:8px; background:#e7eef8; color:var(--blue); }
		.metric:nth-child(2) .metric-icon { background:#e4f1e7; color:var(--green); }
		.metric:nth-child(3) .metric-icon { background:#f5e5e7; color:var(--wine-700); }
		.metric small { display:block; color:var(--muted); font-size:.68rem; }
		.metric strong { display:block; margin-top:2px; font-size:1.1rem; }
		.layout { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(290px,.75fr); gap:12px; align-items:start; }
		.panel { min-width:0; padding:14px; border:1px solid var(--line); border-radius:8px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,.06); }
		.panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
		.panel-head p { margin:3px 0 0; color:var(--muted); font-size:.7rem; }
		.table-wrap { overflow-x:auto; }
		table { width:100%; border-collapse:collapse; font-size:.74rem; }
		th,td { padding:9px 7px; border-bottom:1px solid #e8e8e8; text-align:left; }
		th { color:#555; font-size:.68rem; font-weight:800; white-space:nowrap; }
		.role { display:inline-block; padding:4px 8px; border-radius:99px; background:#e7eef8; color:#315779; font-size:.66rem; font-weight:700; }
		.role.admin { background:#f5e5e7; color:var(--wine-900); }
		.active { color:var(--green); font-weight:700; }
		.field { margin-bottom:10px; }
		.field label { display:block; margin-bottom:5px; font-size:.72rem; font-weight:700; }
		.field input,.field select { width:100%; min-height:37px; padding:8px 9px; border:1px solid #d1d1d1; border-radius:6px; font:inherit; font-size:.8rem; }
		.field input:focus,.field select:focus { outline:2px solid rgba(151,27,49,.17); border-color:var(--wine-700); }
		.help { margin:5px 0 12px; color:var(--muted); font-size:.68rem; line-height:1.45; }
		.full { width:100%; }
		@media(max-width:900px) { .layout { grid-template-columns:1fr; } }
		@media(max-width:700px) { .main { margin-left:0; padding:12px; } }
		@media(max-width:500px) { .metrics { grid-template-columns:1fr; } .page-head { align-items:flex-start; flex-direction:column; } }
	</style>
</head>
<body>
<?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main">
	<header class="page-head">
		<div><span class="eyebrow">Accesos del despacho</span><h1>Usuarios y Abogados</h1></div>
		<a class="button secondary" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Escritorio</a>
	</header>
	<?php if ($statusMessage !== ''): ?><p class="notice" role="status"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

	<section class="metrics" aria-label="Resumen de usuarios">
		<div class="metric"><span class="metric-icon"><i class="fa-solid fa-shield-halved"></i></span><span><small>Administrador único</small><strong><?php echo $adminCount; ?></strong></span></div>
		<div class="metric"><span class="metric-icon"><i class="fa-solid fa-user-tie"></i></span><span><small>Abogados</small><strong><?php echo $lawyerCount; ?></strong></span></div>
		<div class="metric"><span class="metric-icon"><i class="fa-solid fa-users"></i></span><span><small>Cuentas activas</small><strong><?php echo $adminCount + $lawyerCount; ?></strong></span></div>
	</section>

	<div class="layout">
		<section class="panel">
			<div class="panel-head"><div><h2>Cuentas del sistema</h2><p>Las contraseñas nunca se muestran.</p></div></div>
			<div class="table-wrap">
				<table>
					<thead><tr><th>ID</th><th>Nombre</th><th>Perfil</th><th>Estado</th><th>Alta</th></tr></thead>
					<tbody>
						<?php foreach ($users as $user): ?>
							<tr>
								<td><?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><span class="role <?php echo $user['rol'] === 'admin' ? 'admin' : ''; ?>"><?php echo $user['rol'] === 'admin' ? 'Administración' : 'Abogado'; ?></span></td>
								<td class="active"><?php echo (bool)$user['activo'] ? 'Activo' : 'Inactivo'; ?></td>
								<td><?php echo htmlspecialchars(substr((string)$user['fecha_creacion'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<aside class="panel">
			<h2>Crear abogado</h2>
			<p class="help">El abogado podrá iniciar sesión con su ID y contraseña y accederá únicamente a sus casos asignados.</p>
			<form action="crear_abogado.php" method="post" autocomplete="off">
				<div class="field"><label for="user-id">ID de usuario</label><input id="user-id" name="id" maxlength="50" required></div>
				<div class="field"><label for="user-name">Nombre completo</label><input id="user-name" name="nombre" maxlength="100" required></div>
				<div class="field"><label for="user-password">Contraseña inicial</label><input id="user-password" name="contrasena" type="password" minlength="10" autocomplete="new-password" required></div>
				<button class="button full" type="submit"><i class="fa-solid fa-user-plus"></i> Crear abogado</button>
			</form>
		</aside>
	</div>
</main>
</body>
</html>
