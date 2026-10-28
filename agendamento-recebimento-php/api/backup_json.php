<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

$tipo = $_GET['tipo'] ?? 'agendamentos'; // agendamentos | tratativas
$pdo = db();

if ($tipo === 'tratativas') {
    $trs = $pdo->query('SELECT * FROM tratativas ORDER BY id')->fetchAll();
    $itensStmt = $pdo->prepare('SELECT cprod AS cProd, xprod AS xProd, ncm, ean, ean_status AS eanStatus, ean_label AS eanLabel, ucom AS uCom, qcom AS qCom, vuncom AS vUnCom, vprod AS vProd FROM tratativa_itens WHERE tratativa_id = ?');
    foreach ($trs as &$t) {
        $itensStmt->execute([$t['id']]);
        $t['itens'] = $itensStmt->fetchAll();
    }
    unset($t);
    $payload = ['tratativas' => $trs, 'exportadoEm' => date('c')];
    $fileName = 'tratativas_outlet_leilao_backup.json';
} else {
    $ags = $pdo->query('SELECT * FROM agendamentos ORDER BY id')->fetchAll();
    $notasStmt = $pdo->prepare('SELECT * FROM agendamento_notas WHERE agendamento_id = ?');
    $itensStmt = $pdo->prepare('SELECT nota_label AS notaLabel, cprod AS cProd, xprod AS xProd, ncm, ean, ean_status AS eanStatus, ean_label AS eanLabel, ucom AS uCom, qcom AS qCom, vuncom AS vUnCom, vprod AS vProd, codigo_cliente AS codigoCliente FROM agendamento_itens WHERE agendamento_id = ?');
    $histStmt = $pdo->prepare('SELECT data, descricao, usuario FROM agendamento_historico WHERE agendamento_id = ?');
    foreach ($ags as &$ag) {
        $notasStmt->execute([$ag['id']]);
        $ag['notas'] = $notasStmt->fetchAll();
        $itensStmt->execute([$ag['id']]);
        $ag['itens'] = $itensStmt->fetchAll();
        $histStmt->execute([$ag['id']]);
        $ag['historico'] = $histStmt->fetchAll();
    }
    unset($ag);
    $depara = $pdo->query('SELECT ean, codigo, descricao, origem FROM depara')->fetchAll();
    $payload = ['agendamentos' => $ags, 'depara' => $depara, 'exportadoEm' => date('c')];
    $fileName = 'agendamentos_recebimento_backup.json';
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
