<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);
$in = body_json();
$id = (int)($in['id'] ?? 0);
$status = $in['status'] ?? '';
if (!$id || !isset(STATUS_LABEL[$status])) json_out(['erro' => 'Dados inválidos'], 400);

$stmt = db()->prepare('UPDATE tratativas SET status = ? WHERE id = ?');
$stmt->execute([$status, $id]);
json_out(['ok' => true]);
