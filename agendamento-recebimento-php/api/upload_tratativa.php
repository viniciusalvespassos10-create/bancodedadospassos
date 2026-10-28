<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/nfe_parser.php';
require_once __DIR__ . '/../includes/xml_builder.php';
$usuarioLogado = exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['arquivos'])) {
    json_out(['erro' => 'Nenhum arquivo enviado'], 400);
}
$tipo = $_POST['tipo'] ?? '';
if (!isset(TRATATIVA_SUFFIX[$tipo])) json_out(['erro' => 'Tipo inválido'], 400);
$suffix = TRATATIVA_SUFFIX[$tipo];

$pdo = db();
$resultados = [];
$nomes = $_FILES['arquivos']['name'];
$tmp = $_FILES['arquivos']['tmp_name'];
$errs = $_FILES['arquivos']['error'];

foreach ($nomes as $i => $nome) {
    if ($errs[$i] !== UPLOAD_ERR_OK) { $resultados[] = ['arquivo' => $nome, 'erro' => 'Falha ao enviar o arquivo']; continue; }
    if (!preg_match('/\.xml$/i', $nome)) { $resultados[] = ['arquivo' => $nome, 'erro' => 'Não é um arquivo .xml']; continue; }
    $conteudo = file_get_contents($tmp[$i]);
    try {
        $nota = parse_nfe_xml($conteudo, $nome);
        $built = build_product_code_xml($nota['rawXml'], $nota['itens'], $suffix);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO tratativas (tipo,file_name,numero,serie,cliente,fornecedor,valor_total,volumes,replaced,skipped,raw_xml,suffix,status,data,hora,criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"pendente",CURDATE(),CURTIME(),?)');
        $stmt->execute([
            $tipo, $nome, $nota['numero'], $nota['serie'], $nota['destinatarioNome'], $nota['emitenteNome'],
            $nota['valorTotal'], $nota['volumes'], $built['replaced'], $built['skipped'], $nota['rawXml'], $suffix,
            $usuarioLogado['id'],
        ]);
        $tratativaId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare('INSERT INTO tratativa_itens (tratativa_id,cprod,xprod,ncm,ean,ean_status,ean_label,ucom,qcom,vuncom,vprod) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($nota['itens'] as $it) {
            $stmtItem->execute([
                $tratativaId, $it['cProd'], $it['xProd'], $it['ncm'], $it['ean'], $it['eanStatus'], $it['eanLabel'],
                $it['uCom'], $it['qCom'], $it['vUnCom'], $it['vProd'],
            ]);
        }
        $pdo->commit();

        $baseName = preg_replace('/\.xml$/i', '', $nome);
        $resultados[] = [
            'arquivo' => $nome, 'id' => $tratativaId,
            'replaced' => $built['replaced'], 'skipped' => $built['skipped'],
            'downloadUrl' => 'api/gerar_xml_tratativa.php?id=' . $tratativaId,
            'downloadName' => $baseName . '_' . $suffix . '.xml',
        ];
    } catch (NFeParseException|XmlBuildException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $resultados[] = ['arquivo' => $nome, 'erro' => $e->getMessage()];
    }
}

json_out(['resultados' => $resultados]);
