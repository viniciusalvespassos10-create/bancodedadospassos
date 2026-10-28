<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
$usuarioLogado = exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['arquivo'])) {
    json_out(['erro' => 'Nenhum arquivo enviado'], 400);
}
$payload = json_decode(file_get_contents($_FILES['arquivo']['tmp_name']), true);
if (!is_array($payload)) json_out(['erro' => 'Arquivo de backup inválido'], 400);

$pdo = db();
$pdo->beginTransaction();
try {
    $importados = 0;

    if (!empty($payload['agendamentos']) && is_array($payload['agendamentos'])) {
        $stmtAg = $pdo->prepare('INSERT INTO agendamentos (data,hora,cliente,fornecedor,transportadora,placa,motorista,obs,valor_total,volumes,status,criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmtNota = $pdo->prepare('INSERT INTO agendamento_notas (agendamento_id,numero,serie,data_emissao,chave,emitente_nome,emitente_cnpj,valor_total,volumes,especie_vol,file_name,raw_xml) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmtItem = $pdo->prepare('INSERT INTO agendamento_itens (agendamento_id,nota_label,cprod,xprod,ncm,ean,ean_status,ean_label,ucom,qcom,vuncom,vprod,codigo_cliente) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmtHist = $pdo->prepare('INSERT INTO agendamento_historico (agendamento_id,data,descricao,usuario) VALUES (?,?,?,?)');

        foreach ($payload['agendamentos'] as $ag) {
            $stmtAg->execute([
                $ag['data'] ?? date('Y-m-d'), $ag['hora'] ?? '00:00', $ag['cliente'] ?? '(sem cliente)',
                $ag['fornecedor'] ?? null, $ag['transportadora'] ?? null, $ag['placa'] ?? null, $ag['motorista'] ?? null, $ag['obs'] ?? null,
                (float)($ag['valor_total'] ?? $ag['valorTotal'] ?? 0), (float)($ag['volumes'] ?? 0),
                $ag['status'] ?? 'pendente', $usuarioLogado['id'],
            ]);
            $novoId = (int)$pdo->lastInsertId();
            foreach ($ag['notas'] ?? [] as $n) {
                $stmtNota->execute([$novoId, $n['numero'] ?? null, $n['serie'] ?? null, $n['dataEmissao'] ?? ($n['data_emissao'] ?? null),
                    $n['chave'] ?? null, $n['emitenteNome'] ?? ($n['emitente_nome'] ?? null), $n['emitenteCNPJ'] ?? ($n['emitente_cnpj'] ?? null),
                    (float)($n['valorTotal'] ?? ($n['valor_total'] ?? 0)), (float)($n['volumes'] ?? 0), $n['especieVol'] ?? ($n['especie_vol'] ?? null),
                    $n['fileName'] ?? ($n['file_name'] ?? null), $n['rawXml'] ?? ($n['raw_xml'] ?? null)]);
            }
            foreach ($ag['itens'] ?? [] as $it) {
                $stmtItem->execute([$novoId, $it['notaLabel'] ?? ($it['nota_label'] ?? null), $it['cProd'] ?? ($it['cprod'] ?? null),
                    $it['xProd'] ?? ($it['xprod'] ?? null), $it['ncm'] ?? null, $it['ean'] ?? null,
                    $it['eanStatus'] ?? ($it['ean_status'] ?? null), $it['eanLabel'] ?? ($it['ean_label'] ?? null),
                    $it['uCom'] ?? ($it['ucom'] ?? null), (float)($it['qCom'] ?? ($it['qcom'] ?? 0)),
                    (float)($it['vUnCom'] ?? ($it['vuncom'] ?? 0)), (float)($it['vProd'] ?? ($it['vprod'] ?? 0)),
                    $it['codigoCliente'] ?? ($it['codigo_cliente'] ?? null)]);
            }
            foreach ($ag['historico'] ?? [] as $h) {
                $stmtHist->execute([$novoId, $h['data'] ?? date('c'), $h['descricao'] ?? '', $h['usuario'] ?? 'Importação']);
            }
            $importados++;
        }
    }

    if (!empty($payload['depara']) && is_array($payload['depara'])) {
        $stmtDepara = $pdo->prepare('INSERT INTO depara (ean, codigo, descricao, origem) VALUES (?,?,?,"backup")
            ON DUPLICATE KEY UPDATE codigo = VALUES(codigo), descricao = VALUES(descricao)');
        foreach ($payload['depara'] as $ean => $d) {
            if (is_array($d) && isset($d['codigo'])) {
                $eanVal = is_string($ean) ? $ean : ($d['ean'] ?? null);
                if ($eanVal) $stmtDepara->execute([$eanVal, $d['codigo'], $d['descricao'] ?? null]);
            }
        }
    }

    if (!empty($payload['tratativas']) && is_array($payload['tratativas'])) {
        $stmtTr = $pdo->prepare('INSERT INTO tratativas (tipo,file_name,numero,serie,cliente,fornecedor,valor_total,volumes,replaced,skipped,raw_xml,suffix,status,data,hora,criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmtTrItem = $pdo->prepare('INSERT INTO tratativa_itens (tratativa_id,cprod,xprod,ncm,ean,ean_status,ean_label,ucom,qcom,vuncom,vprod) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($payload['tratativas'] as $t) {
            $stmtTr->execute([
                $t['tipo'] ?? 'outlet', $t['fileName'] ?? ($t['file_name'] ?? null), $t['numero'] ?? null, $t['serie'] ?? null,
                $t['cliente'] ?? null, $t['fornecedor'] ?? null, (float)($t['valorTotal'] ?? ($t['valor_total'] ?? 0)),
                (float)($t['volumes'] ?? 0), (int)($t['replaced'] ?? 0), (int)($t['skipped'] ?? 0),
                $t['rawXml'] ?? ($t['raw_xml'] ?? null), $t['suffix'] ?? 'AV', $t['status'] ?? 'pendente',
                $t['data'] ?? date('Y-m-d'), $t['hora'] ?? '00:00', $usuarioLogado['id'],
            ]);
            $novoTrId = (int)$pdo->lastInsertId();
            foreach ($t['itens'] ?? [] as $it) {
                $stmtTrItem->execute([$novoTrId, $it['cProd'] ?? ($it['cprod'] ?? null), $it['xProd'] ?? ($it['xprod'] ?? null),
                    $it['ncm'] ?? null, $it['ean'] ?? null, $it['eanStatus'] ?? ($it['ean_status'] ?? null),
                    $it['eanLabel'] ?? ($it['ean_label'] ?? null), $it['uCom'] ?? ($it['ucom'] ?? null),
                    (float)($it['qCom'] ?? ($it['qcom'] ?? 0)), (float)($it['vUnCom'] ?? ($it['vuncom'] ?? 0)), (float)($it['vProd'] ?? ($it['vprod'] ?? 0))]);
            }
            $importados++;
        }
    }

    $pdo->commit();
    json_out(['ok' => true, 'importados' => $importados]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_out(['erro' => 'Erro ao importar backup: ' . $e->getMessage()], 500);
}
