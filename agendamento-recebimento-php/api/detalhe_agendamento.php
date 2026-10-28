<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_out(['erro' => 'id inválido'], 400);

$pdo = db();
$ag = $pdo->prepare('SELECT * FROM agendamentos WHERE id = ?');
$ag->execute([$id]);
$ag = $ag->fetch();
if (!$ag) json_out(['erro' => 'Agendamento não encontrado'], 404);

$notas = $pdo->prepare('SELECT id, numero, serie, emitente_nome, valor_total, chave, file_name, danfe_filename, (danfe_path IS NOT NULL) AS tem_danfe, (raw_xml IS NOT NULL) AS tem_raw_xml FROM agendamento_notas WHERE agendamento_id = ?');
$notas->execute([$id]);
$ag['notas'] = $notas->fetchAll();

$itens = $pdo->prepare('SELECT * FROM agendamento_itens WHERE agendamento_id = ? ORDER BY id');
$itens->execute([$id]);
$ag['itens'] = $itens->fetchAll();

$hist = $pdo->prepare('SELECT data, descricao, usuario FROM agendamento_historico WHERE agendamento_id = ? ORDER BY data');
$hist->execute([$id]);
$ag['historico'] = $hist->fetchAll();

json_out(['agendamento' => $ag]);
