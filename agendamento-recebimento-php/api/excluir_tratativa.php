<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);
$in = body_json();
$id = (int)($in['id'] ?? 0);
if (!$id) json_out(['erro' => 'id inválido'], 400);

$stmt = db()->prepare('DELETE FROM tratativas WHERE id = ?');
$stmt->execute([$id]);
json_out(['ok' => true, 'excluido' => $stmt->rowCount() > 0]);
