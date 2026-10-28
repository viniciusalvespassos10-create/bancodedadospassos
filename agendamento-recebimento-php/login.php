<?php
require_once __DIR__ . '/auth.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if ($usuario !== '' && $senha !== '' && fazer_login($usuario, $senha)) {
        header('Location: index.php');
        exit;
    }
    $erro = 'Usuário ou senha incorretos.';
}
if (usuario_logado()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Agendamento de Recebimento</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);}
  .login-card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:32px;width:100%;max-width:360px;}
  .login-card h1{font-size:20px;margin:0 0 4px;}
  .login-card p{color:var(--text-dim);font-size:13px;margin:0 0 20px;}
  .login-card label{display:block;font-size:12px;color:var(--text-dim);margin:14px 0 6px;}
  .login-card input{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:var(--panel-2);color:var(--text);font-size:14px;}
  .login-card button{margin-top:22px;width:100%;padding:11px;border-radius:8px;border:none;background:var(--accent);color:#06121e;font-weight:700;cursor:pointer;font-size:14px;}
  .login-err{margin-top:14px;color:var(--danger);font-size:13px;}
</style>
</head>
<body>
  <div class="login-wrap">
    <form class="login-card" method="post">
      <h1>Agendamento de Recebimento</h1>
      <p>Mercado Urso — Grupo Vila Porto</p>
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" autofocus required>
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="current-password" required>
      <button type="submit">Entrar</button>
      <?php if ($erro): ?><div class="login-err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    </form>
  </div>
</body>
</html>
