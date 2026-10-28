<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/xml_builder.php';
exigir_login();

$notaId = (int)($_GET['nota_id'] ?? 0);
if (!$notaId) { http_response_code(400); exit('id inválido'); }

$pdo = db();
$nota = $pdo->prepare('SELECT agendamento_id, raw_xml, file_name, numero FROM agendamento_notas WHERE id = ?');
$nota->execute([$notaId]);
$nota = $nota->fetch();
if (!$nota || !$nota['raw_xml']) { http_response_code(404); exit('XML original não disponível para esta nota'); }

$label = $nota['numero'] ? ('NF ' . $nota['numero']) : $nota['file_name'];
$itensStmt = $pdo->prepare('SELECT ean, ean_status AS eanStatus FROM agendamento_itens WHERE agendamento_id = ? AND nota_label = ? ORDER BY id');
$itensStmt->execute([$nota['agendamento_id'], $label]);
$itens = $itensStmt->fetchAll();

try {
    $result = build_product_code_xml($nota['raw_xml'], $itens);
} catch (XmlBuildException $e) {
    http_response_code(500);
    exit('Erro ao gerar XML: ' . $e->getMessage());
}

registrar_historico((int)$nota['agendamento_id'], 'XML gerado com código = EAN para ' . $label . ' (' . $result['replaced'] . ' código(s) substituído(s))', 'Sistema');

$baseName = preg_replace('/\.xml$/i', '', $nota['file_name'] ?: 'nota');
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . addslashes($baseName) . '_codigoEAN.xml"');
echo $result['xmlStr'];
exit;
