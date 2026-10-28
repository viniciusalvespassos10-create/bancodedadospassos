<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

$where = [];
$params = [];
if (!empty($_GET['status'])) { $where[] = 'a.status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['data'])) { $where[] = 'a.data = ?'; $params[] = $_GET['data']; }
if (!empty($_GET['busca'])) {
    $where[] = '(a.cliente LIKE ? OR a.fornecedor LIKE ? OR EXISTS (SELECT 1 FROM agendamento_notas n WHERE n.agendamento_id = a.id AND n.numero LIKE ?))';
    $busca = '%' . $_GET['busca'] . '%';
    $params[] = $busca; $params[] = $busca; $params[] = $busca;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$pdo = db();
$stmt = $pdo->prepare("SELECT a.* FROM agendamentos a $whereSql ORDER BY a.data DESC, a.hora DESC, a.id DESC");
$stmt->execute($params);
$agendamentos = $stmt->fetchAll();

if ($agendamentos) {
    $ids = array_column($agendamentos, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $notasStmt = $pdo->prepare("SELECT * FROM agendamento_notas WHERE agendamento_id IN ($placeholders)");
    $notasStmt->execute($ids);
    $notasByAg = [];
    foreach ($notasStmt->fetchAll() as $n) { $notasByAg[$n['agendamento_id']][] = $n; }

    $itensStmt = $pdo->prepare("SELECT agendamento_id, COUNT(*) AS qtd FROM agendamento_itens WHERE agendamento_id IN ($placeholders) GROUP BY agendamento_id");
    $itensStmt->execute($ids);
    $itensCountByAg = [];
    foreach ($itensStmt->fetchAll() as $r) { $itensCountByAg[$r['agendamento_id']] = (int)$r['qtd']; }

    foreach ($agendamentos as &$ag) {
        $notas = $notasByAg[$ag['id']] ?? [];
        $ag['notas'] = array_map(function ($n) {
            return [
                'id' => (int)$n['id'],
                'numero' => $n['numero'], 'serie' => $n['serie'],
                'emitenteNome' => $n['emitente_nome'], 'fileName' => $n['file_name'],
                'temRawXml' => !empty($n['raw_xml']),
                'temDanfe' => !empty($n['danfe_path']),
                'danfeFileName' => $n['danfe_filename'],
            ];
        }, $notas);
        $ag['itensCount'] = $itensCountByAg[$ag['id']] ?? 0;
        $ag['valorTotal'] = (float)$ag['valor_total'];
        $ag['volumes'] = (float)$ag['volumes'];
    }
    unset($ag);
}

json_out(['agendamentos' => $agendamentos]);
