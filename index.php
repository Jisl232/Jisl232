<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . (($_SESSION['user_role'] ?? 'admin') === 'abogado' ? 'pages/mis_casos.php' : 'dashboard.php'));
    exit();
}
$messages = [
    'missing' => 'Completa los tres campos para ingresar.',
    'invalid' => 'El nombre, ID o contraseña no coinciden.',
    'inactive' => 'Esta cuenta está desactivada. Contacta a administración.',
];
$error = $messages[$_GET['status'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bufete de Abogados | Acceso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --wine:#64101d; --wine-dark:#3f0810; --ink:#1b1b1b; --muted:#707070; --line:#dedede; }
        * { box-sizing:border-box; }
        body { display:grid; min-height:100vh; margin:0; place-items:center; padding:22px; background:linear-gradient(135deg,#eee 0%,#fff 55%,#f4e9eb 100%); color:var(--ink); font-family:'Montserrat',sans-serif; }
        .login { display:grid; grid-template-columns:minmax(230px,.8fr) minmax(320px,1fr); width:min(780px,100%); min-height:470px; overflow:hidden; border:1px solid rgba(0,0,0,.08); border-radius:12px; background:#fff; box-shadow:0 18px 50px rgba(50,10,16,.14); }
        .brand { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:15px; padding:30px; background:linear-gradient(160deg,var(--wine-dark),var(--wine)); color:#fff; text-align:center; }
        .brand-mark { display:grid; place-items:center; width:132px; height:132px; overflow:hidden; border:2px solid #d8c08a; border-radius:50%; background:#fff; box-shadow:0 0 0 6px rgba(216,192,138,.12); }
        .brand-mark img { display:block; width:100%; height:100%; object-fit:cover; }
        .brand h1 { margin:0; font-family:'Cinzel',serif; font-size:1.35rem; }
        .brand p { margin:0; color:rgba(255,255,255,.77); font-size:.78rem; }
        .form-panel { align-self:center; padding:42px clamp(24px,6vw,52px); }
        .form-panel h2 { margin:0 0 7px; font-size:1.5rem; }
        .form-panel > p { margin:0 0 24px; color:var(--muted); font-size:.82rem; }
        .error { margin-bottom:15px; padding:10px 12px; border:1px solid #e7c4ca; border-radius:7px; background:#fff5f6; color:#7e1b2b; font-size:.78rem; }
        .field { margin-bottom:15px; }
        label { display:block; margin-bottom:6px; font-size:.76rem; font-weight:700; }
        input { width:100%; min-height:42px; padding:10px 12px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--ink); font:inherit; font-size:.86rem; }
        input:focus { outline:2px solid rgba(100,16,29,.17); border-color:var(--wine); }
        button { width:100%; min-height:43px; margin-top:7px; border:0; border-radius:7px; background:linear-gradient(105deg,var(--wine-dark),var(--wine)); color:#fff; font:inherit; font-size:.85rem; font-weight:700; cursor:pointer; }
        button:hover { filter:brightness(1.08); }
        @media (max-width:620px) { .login { grid-template-columns:1fr; } .brand { min-height:160px; padding:20px; } .brand-mark { width:84px; height:84px; } .form-panel { padding:28px 24px; } }
    </style>
</head>
<body>
    <main class="login">
        <aside class="brand">
            <div class="brand-mark"><img src="assets/img/image.png" alt="Emblema del despacho"></div>
            <h1>Ley de Audasez</h1>
            <p>Sistema de gestión jurídica</p>
        </aside>
        <section class="form-panel">
            <h2>Iniciar sesión</h2>
            <p>Ingresa con tu cuenta del despacho.</p>
            <?php if ($error !== ''): ?><div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <form method="post" action="pages/login_process.php" autocomplete="on">
                <div class="field"><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" autocomplete="username" required></div>
                <div class="field"><label for="id">ID de usuario</label><input id="id" name="id" autocomplete="off" required></div>
                <div class="field"><label for="contrasena">Contraseña</label><input id="contrasena" name="contrasena" type="password" autocomplete="current-password" required></div>
                <button type="submit">Ingresar</button>
            </form>
        </section>
    </main>
</body>
</html>
