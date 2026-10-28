<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';
exigir_login();

$tipo = $_GET['tipo'] ?? ''; // agendamentos | tratativas
$formato = $_GET['formato'] ?? 'csv'; // csv | xlsx
$pdo = db();

if ($tipo === 'agendamentos') {
    $headers = ['Data','Hora','Cliente','Fornecedor','NF','Cod_Fornecedor','Descricao','EAN','Status_EAN','Cod_Cliente','Qtd','Unidade','Valor_Total','Status_Agendamento'];
    $numericCols = [10 => true, 12 => true]; // Qtd, Valor_Total
    $rows = [];
    $ags = $pdo->query('SELECT id, data, hora, cliente, fornecedor, status FROM agendamentos ORDER BY data DESC, hora DESC')->fetchAll();
    $itensStmt = $pdo->prepare('SELECT nota_label, cprod, xprod, ean, ean_label, codigo_cliente, qcom, ucom, vprod FROM agendamento_itens WHERE agendamento_id = ?');
    foreach ($ags as $ag) {
        $itensStmt->execute([$ag['id']]);
        $itens = $itensStmt->fetchAll();
        if (!$itens) {
            $rows[] = [fmt_date_br($ag['data']), $ag['hora'], $ag['cliente'], $ag['fornecedor'], '', '', '', '', '', '', '', '', '', STATUS_LABEL[$ag['status']]];
            continue;
        }
        foreach ($itens as $it) {
            $rows[] = [
                fmt_date_br($ag['data']), $ag['hora'], $ag['cliente'], $ag['fornecedor'],
                $it['nota_label'], $it['cprod'], $it['xprod'], $it['ean'], $it['ean_label'], $it['codigo_cliente'],
                (float)$it['qcom'], $it['ucom'], (float)$it['vprod'], STATUS_LABEL[$ag['status']],
            ];
        }
    }
    $baseFileName = 'agendamentos_recebimento';
} elseif ($tipo === 'tratativas') {
    $headers = ['Data','Hora','Tipo','Cliente','Fornecedor','NF','Cod_Original','Descricao','EAN','Status_EAN','Codigo_Gerado','Qtd','Unidade','Valor_Total','Status'];
    $numericCols = [11 => true, 13 => true]; // Qtd, Valor_Total
    $rows = [];
    $trs = $pdo->query('SELECT id, data, hora, tipo, cliente, fornecedor, numero, suffix, status FROM tratativas ORDER BY data DESC, hora DESC')->fetchAll();
    $itensStmt = $pdo->prepare('SELECT cprod, xprod, ean, ean_status, ean_label, qcom, ucom, vprod FROM tratativa_itens WHERE tratativa_id = ?');
    foreach ($trs as $t) {
        $itensStmt->execute([$t['id']]);
        $itens = $itensStmt->fetchAll();
        if (!$itens) {
            $rows[] = [fmt_date_br($t['data']), $t['hora'], TRATATIVA_TIPO_LABEL[$t['tipo']], $t['cliente'], $t['fornecedor'], $t['numero'], '', '', '', '', '', '', '', '', STATUS_LABEL[$t['status']]];
            continue;
        }
        foreach ($itens as $it) {
            $usavel = ean_usavel($it['ean'], $it['ean_status']);
            $codigoGerado = ($usavel ? $it['ean'] : $it['cprod']) . '-' . $t['suffix'];
            $rows[] = [
                fmt_date_br($t['data']), $t['hora'], TRATATIVA_TIPO_LABEL[$t['tipo']], $t['cliente'], $t['fornecedor'], $t['numero'],
                $it['cprod'], $it['xprod'], $it['ean'], $it['ean_label'], $codigoGerado,
                (float)$it['qcom'], $it['ucom'], (float)$it['vprod'], STATUS_LABEL[$t['status']],
            ];
        }
    }
    $baseFileName = 'tratativas_outlet_leilao';
} else {
    http_response_code(400);
    exit('Tipo inválido');
}

if ($formato === 'xlsx') {
    $bytes = build_xlsx($headers, $rows, $numericCols);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $baseFileName . '.xlsx"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
} else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseFileName . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM para Excel abrir acentuação corretamente
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $r) { fputcsv($out, $r, ';'); }
    fclose($out);
}
exit;
