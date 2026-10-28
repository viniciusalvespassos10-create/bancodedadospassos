<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/xml_builder.php';
exigir_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('id inválido'); }

$pdo = db();
$t = $pdo->prepare('SELECT raw_xml, file_name, suffix FROM tratativas WHERE id = ?');
$t->execute([$id]);
$t = $t->fetch();
if (!$t || !$t['raw_xml']) { http_response_code(404); exit('XML original não disponível'); }

$itensStmt = $pdo->prepare('SELECT ean, ean_status AS eanStatus FROM tratativa_itens WHERE tratativa_id = ? ORDER BY id');
$itensStmt->execute([$id]);
$itens = $itensStmt->fetchAll();

try {
    $result = build_product_code_xml($t['raw_xml'], $itens, $t['suffix']);
} catch (XmlBuildException $e) {
    http_response_code(500);
    exit('Erro ao gerar XML: ' . $e->getMessage());
}

$baseName = preg_replace('/\.xml$/i', '', $t['file_name'] ?: 'nf');
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . addslashes($baseName) . '_' . $t['suffix'] . '.xml"');
echo $result['xmlStr'];
exit;
