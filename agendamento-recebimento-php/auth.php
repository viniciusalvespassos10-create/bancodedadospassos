<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function usuario_logado(): ?array {
    return $_SESSION['usuario'] ?? null;
}

function exigir_login(): array {
    $u = usuario_logado();
    if (!$u) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/') || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['erro' => 'Sessão expirada. Faça login novamente.']);
            exit;
        }
        header('Location: login.php');
        exit;
    }
    return $u;
}

function fazer_login(string $usuario, string $senha): bool {
    $stmt = db()->prepare('SELECT id, nome, usuario, senha_hash FROM usuarios WHERE usuario = ? AND ativo = 1');
    $stmt->execute([$usuario]);
    $row = $stmt->fetch();
    if ($row && password_verify($senha, $row['senha_hash'])) {
        $_SESSION['usuario'] = ['id' => $row['id'], 'nome' => $row['nome'], 'usuario' => $row['usuario']];
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function fazer_logout(): void {
    $_SESSION = [];
    session_destroy();
}
