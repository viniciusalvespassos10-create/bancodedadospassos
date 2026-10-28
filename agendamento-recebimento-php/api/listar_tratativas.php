<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

$where = [];
$params = [];
if (!empty($_GET['tipo'])) { $where[] = 'tipo = ?'; $params[] = $_GET['tipo']; }
if (!empty($_GET['status'])) { $where[] = 'status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['data'])) { $where[] = 'data = ?'; $params[] = $_GET['data']; }
if (!empty($_GET['busca'])) {
    $where[] = '(cliente LIKE ? OR fornecedor LIKE ? OR numero LIKE ?)';
    $busca = '%' . $_GET['busca'] . '%';
    $params[] = $busca; $params[] = $busca; $params[] = $busca;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$pdo = db();
$stmt = $pdo->prepare("SELECT t.id, t.tipo, t.file_name, t.numero, t.serie, t.cliente, t.fornecedor, t.valor_total, t.volumes, t.replaced, t.skipped, t.suffix, t.status, t.data, t.hora, t.criado_em,
  (SELECT COUNT(*) FROM tratativa_itens ti WHERE ti.tratativa_id = t.id) AS itens_count FROM tratativas t $whereSql ORDER BY t.data DESC, t.hora DESC, t.id DESC");
$stmt->execute($params);
$tratativas = $stmt->fetchAll();
foreach ($tratativas as &$t) {
    $t['valorTotal'] = (float)$t['valor_total'];
    $t['volumes'] = (float)$t['volumes'];
    $t['itensCount'] = (int)$t['itens_count'];
}
unset($t);

json_out(['tratativas' => $tratativas]);
