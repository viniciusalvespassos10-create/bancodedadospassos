<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);
$in = body_json();
$notaId = (int)($in['nota_id'] ?? 0);
if (!$notaId) json_out(['erro' => 'id inválido'], 400);

$nota = db()->prepare('SELECT danfe_path FROM agendamento_notas WHERE id = ?');
$nota->execute([$notaId]);
$nota = $nota->fetch();
if ($nota && $nota['danfe_path']) {
    $full = UPLOADS_DANFE_DIR . '/' . basename($nota['danfe_path']);
    if (is_file($full)) @unlink($full);
}
db()->prepare('UPDATE agendamento_notas SET danfe_path = NULL, danfe_filename = NULL WHERE id = ?')->execute([$notaId]);
json_out(['ok' => true]);
