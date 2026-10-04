<?php
require_once __DIR__ . '/data_store.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$baseUrl = preg_replace('~/pages$~', '', $scriptDirectory);
$isLawyer = ($_SESSION['user_role'] ?? 'admin') === 'abogado';
$userNotifications = array_values(array_filter(app_data_read('notificaciones'), static fn($notification) => (string)($notification['recipient_id'] ?? '') === (string)($_SESSION['user_id'] ?? '')));
$unreadNotificationCount = count(array_filter($userNotifications, static fn($notification) => empty($notification['leida'])));
$menu = $isLawyer ? [
	['label' => 'Mis casos', 'path' => '/pages/mis_casos.php', 'icon' => 'fa-briefcase'],
	['label' => 'Notificaciones', 'path' => '/pages/notificaciones.php', 'icon' => 'fa-bell'],
	['label' => 'Cerrar sesión', 'path' => '/includes/logout.php', 'icon' => 'fa-right-from-bracket'],
] : [
	['label' => 'Dashboard', 'path' => '/dashboard.php', 'icon' => 'fa-gauge-high'],
	['label' => 'Abogados', 'path' => '/pages/abogados.php', 'icon' => 'fa-user-tie'],
	['label' => 'Contratos', 'path' => '/pages/contractos.php', 'icon' => 'fa-file-contract'],
	['label' => 'Clientes', 'path' => '/pages/clientes.php', 'icon' => 'fa-users'],
	['label' => 'Casos', 'path' => '/pages/casos.php', 'icon' => 'fa-briefcase'],
	['label' => 'Notificaciones', 'path' => '/pages/notificaciones.php', 'icon' => 'fa-bell'],
	['label' => 'Agenda', 'path' => '/pages/agenda.php', 'icon' => 'fa-calendar'],
	['label' => 'Facturación', 'path' => '/pages/facturacion.php', 'icon' => 'fa-dollar-sign'],
	['label' => 'Reportes', 'path' => '/pages/reportes.php', 'icon' => 'fa-chart-column'],
	['label' => 'Configuración', 'path' => '/pages/configuracion.php', 'icon' => 'fa-gear'],
	['label' => 'Cerrar sesión', 'path' => '/includes/logout.php', 'icon' => 'fa-right-from-bracket'],
];
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
	.sidebar {
		position: fixed;
		inset: 0 auto 0 0;
		z-index: 1000;
		display: flex;
		flex-direction: column;
		width: 260px;
		min-height: 100vh;
		overflow-y: auto;
		padding: 28px 16px;
		border-right: 1px solid rgba(255,255,255,0.08);
		background: linear-gradient(180deg, #2b0d12 0%, #1b1b1b 100%);
		color: #fff;
	}

	.sidebar-brand {
		margin: 0 0 26px;
		padding: 8px 4px 20px;
		border-bottom: 1px solid rgba(255,255,255,0.12);
		color: #fff;
		font-family: 'Cinzel', serif;
		font-size: 1.35rem;
		text-align: center;
	}

	.sidebar-nav {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	.sidebar-link {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px 14px;
		border-left: 3px solid transparent;
		border-radius: 8px;
		color: #f5f5f5;
		font-size: 0.92rem;
		font-weight: 500;
		text-decoration: none;
		transition: background 0.18s ease, border-color 0.18s ease;
	}

	.sidebar-link:hover,
	.sidebar-link.active {
		border-left-color: #b72a42;
		background: rgba(183,42,66,0.2);
		color: #fff;
	}

	.sidebar-link i {
		width: 20px;
		color: #fff;
		text-align: center;
	}

	.notification-count {
		margin-left: auto;
		min-width: 20px;
		padding: 2px 6px;
		border-radius: 99px;
		background: #b72a42;
		color: #fff;
		font-size: .68rem;
		font-weight: 700;
		text-align: center;
	}

	.sidebar-link.logout {
		margin-top: auto;
	}

	@media (max-width: 700px) {
		.sidebar {
			position: relative;
			width: 100%;
			min-height: auto;
			padding: 12px;
		}

		.sidebar-brand {
			margin-bottom: 10px;
			padding-bottom: 10px;
		}

		.sidebar-nav {
			flex-direction: row;
			flex-wrap: wrap;
		}

		.sidebar-link {
			padding: 9px 10px;
		}

		.main-content,
		.main {
			margin-left: 0 !important;
		}
	}
</style>

<aside class="sidebar">
	<div class="sidebar-brand">Ley de Audasez</div>
	<nav class="sidebar-nav" aria-label="Navegación principal">
		<?php foreach ($menu as $item): ?>
			<?php
			$isLogout = $item['label'] === 'Cerrar sesión';
			$url = $baseUrl . $item['path'];
			?>
			<a class="sidebar-link <?php echo $currentPage === basename($item['path']) ? 'active' : ''; ?><?php echo $isLogout ? ' logout' : ''; ?>"
			   href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
			   <?php echo $isLogout ? 'onclick="return confirm(\'¿Deseas cerrar sesión?\');"' : ''; ?>>
				<i class="fa-solid <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
				<span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
				<?php if ($item['label'] === 'Notificaciones' && $unreadNotificationCount > 0): ?><span class="notification-count"><?php echo $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount; ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>
</aside>