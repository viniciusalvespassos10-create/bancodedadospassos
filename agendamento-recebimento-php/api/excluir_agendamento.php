<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);
$in = body_json();
$id = (int)($in['id'] ?? 0);
if (!$id) json_out(['erro' => 'id inválido'], 400);

$pdo = db();
// Remove os PDFs de DANFE do disco antes de apagar o registro.
$notas = $pdo->prepare('SELECT danfe_path FROM agendamento_notas WHERE agendamento_id = ? AND danfe_path IS NOT NULL');
$notas->execute([$id]);
foreach ($notas->fetchAll() as $n) {
    $full = UPLOADS_DANFE_DIR . '/' . basename($n['danfe_path']);
    if (is_file($full)) @unlink($full);
}

$stmt = $pdo->prepare('DELETE FROM agendamentos WHERE id = ?');
$stmt->execute([$id]);
json_out(['ok' => true, 'excluido' => $stmt->rowCount() > 0]);
