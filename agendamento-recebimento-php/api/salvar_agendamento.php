<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
$usuarioLogado = exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);

$in = body_json();
$data = trim($in['data'] ?? '');
$hora = trim($in['hora'] ?? '');
$cliente = trim($in['cliente'] ?? '');
if ($data === '' || $hora === '' || $cliente === '') {
    json_out(['erro' => 'Preencha data, horário e cliente'], 400);
}

$notas = $in['notas'] ?? [];
$itens = $in['itens'] ?? [];

$valorTotal = array_sum(array_map(fn($n) => (float)($n['valorTotal'] ?? 0), $notas));
$volumes = array_sum(array_map(fn($n) => (float)($n['volumes'] ?? 0), $notas));

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO agendamentos (data,hora,cliente,fornecedor,transportadora,placa,motorista,obs,valor_total,volumes,status,criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $data, $hora, $cliente,
        trim($in['fornecedor'] ?? ''), trim($in['transportadora'] ?? ''), strtoupper(trim($in['placa'] ?? '')),
        trim($in['motorista'] ?? ''), trim($in['obs'] ?? ''),
        $valorTotal, $volumes, 'pendente', $usuarioLogado['id'],
    ]);
    $agendamentoId = (int)$pdo->lastInsertId();

    $stmtNota = $pdo->prepare('INSERT INTO agendamento_notas (agendamento_id,numero,serie,data_emissao,chave,emitente_nome,emitente_cnpj,valor_total,volumes,especie_vol,file_name,raw_xml) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $notaIds = [];
    foreach ($notas as $n) {
        $stmtNota->execute([
            $agendamentoId, $n['numero'] ?? null, $n['serie'] ?? null, $n['dataEmissao'] ?? null,
            $n['chave'] ?? null, $n['emitenteNome'] ?? null, $n['emitenteCNPJ'] ?? null,
            (float)($n['valorTotal'] ?? 0), (float)($n['volumes'] ?? 0), $n['especieVol'] ?? null,
            $n['fileName'] ?? null, $n['rawXml'] ?? null,
        ]);
        $notaIds[] = (int)$pdo->lastInsertId();
        $historicoDesc = 'Nota fiscal ' . (!empty($n['numero']) ? ('nº ' . $n['numero']) : ($n['fileName'] ?? '')) . ' anexada';
        registrar_historico($agendamentoId, $historicoDesc, 'Cliente');
    }

    $stmtItem = $pdo->prepare('INSERT INTO agendamento_itens (agendamento_id,nota_label,cprod,xprod,ncm,ean,ean_status,ean_label,ucom,qcom,vuncom,vprod,codigo_cliente) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($itens as $it) {
        $stmtItem->execute([
            $agendamentoId, $it['notaLabel'] ?? null, $it['cProd'] ?? null, $it['xProd'] ?? null, $it['ncm'] ?? null,
            $it['ean'] ?? null, $it['eanStatus'] ?? null, $it['eanLabel'] ?? null, $it['uCom'] ?? null,
            (float)($it['qCom'] ?? 0), (float)($it['vUnCom'] ?? 0), (float)($it['vProd'] ?? 0), $it['codigoCliente'] ?? null,
        ]);
        // Atualiza o dicionário De/Para com os códigos confirmados agora.
        if (!empty($it['ean']) && !empty($it['codigoCliente'])) {
            $pdo->prepare('INSERT INTO depara (ean, codigo, descricao, origem) VALUES (?,?,?,"agendamento")
                ON DUPLICATE KEY UPDATE codigo = VALUES(codigo), descricao = COALESCE(VALUES(descricao), descricao), origem = "agendamento"')
                ->execute([$it['ean'], $it['codigoCliente'], $it['xProd'] ?? null]);
        }
    }

    registrar_historico($agendamentoId, 'Agendamento criado', 'Cliente');

    $pdo->commit();
    json_out(['id' => $agendamentoId, 'notaIds' => $notaIds]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_out(['erro' => 'Erro ao salvar agendamento: ' . $e->getMessage()], 500);
}
