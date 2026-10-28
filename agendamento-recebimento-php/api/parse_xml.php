<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/nfe_parser.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['arquivos'])) {
    json_out(['erro' => 'Nenhum arquivo enviado'], 400);
}

$resultados = [];
$nomes = $_FILES['arquivos']['name'];
$tmp = $_FILES['arquivos']['tmp_name'];
$errs = $_FILES['arquivos']['error'];

foreach ($nomes as $i => $nome) {
    if ($errs[$i] !== UPLOAD_ERR_OK) {
        $resultados[] = ['arquivo' => $nome, 'erro' => 'Falha ao enviar o arquivo'];
        continue;
    }
    if (!preg_match('/\.xml$/i', $nome)) {
        $resultados[] = ['arquivo' => $nome, 'erro' => 'Não é um arquivo .xml'];
        continue;
    }
    $conteudo = file_get_contents($tmp[$i]);
    try {
        $nota = parse_nfe_xml($conteudo, $nome);
        aplicar_sugestoes_depara($nota['itens']);
        $resultados[] = ['arquivo' => $nome, 'nota' => $nota];
    } catch (NFeParseException $e) {
        $resultados[] = ['arquivo' => $nome, 'erro' => $e->getMessage()];
    }
}

json_out(['resultados' => $resultados]);
