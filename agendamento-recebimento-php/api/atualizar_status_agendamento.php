<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);
$in = body_json();
$id = (int)($in['id'] ?? 0);
$status = $in['status'] ?? '';
if (!$id || !isset(STATUS_LABEL[$status])) json_out(['erro' => 'Dados inválidos'], 400);

$stmt = db()->prepare('UPDATE agendamentos SET status = ? WHERE id = ?');
$stmt->execute([$status, $id]);
if ($stmt->rowCount() === 0) {
    // mesmo sem mudar linha (já estava nesse status), confirma que o registro existe
    $exists = db()->prepare('SELECT 1 FROM agendamentos WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetch()) json_out(['erro' => 'Agendamento não encontrado'], 404);
}
registrar_historico($id, 'Status alterado para "' . STATUS_LABEL[$status] . '"', 'Sistema');
json_out(['ok' => true]);
