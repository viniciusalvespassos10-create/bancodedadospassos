<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_out(['erro' => 'id inválido'], 400);

$pdo = db();
$t = $pdo->prepare('SELECT * FROM tratativas WHERE id = ?');
$t->execute([$id]);
$t = $t->fetch();
if (!$t) json_out(['erro' => 'Registro não encontrado'], 404);

$itens = $pdo->prepare('SELECT cprod AS cProd, xprod AS xProd, ean, ean_status AS eanStatus, ean_label AS eanLabel, ucom AS uCom, qcom AS qCom, vuncom AS vUnCom, vprod AS vProd FROM tratativa_itens WHERE tratativa_id = ? ORDER BY id');
$itens->execute([$id]);
$t['itens'] = $itens->fetchAll();

json_out(['tratativa' => $t]);
