<?php
require_once __DIR__ . '/functions.php';

class NFeParseException extends Exception {}

function nfe_text_of(?DOMElement $parent, string $tag): string {
    if (!$parent) return '';
    $els = $parent->getElementsByTagName($tag);
    return $els->length ? trim($els->item(0)->textContent) : '';
}

function nfe_num($v): float {
    $v = str_replace(',', '.', (string)($v ?? '0'));
    return is_numeric($v) ? (float)$v : 0.0;
}

/**
 * Lê o XML de uma NF-e e devolve um array associativo com os dados usados pelo
 * painel (emitente, destinatário, itens, valores, volumes). Lança
 * NFeParseException em caso de XML inválido ou que não seja uma NF-e.
 */
function parse_nfe_xml(string $xmlText, string $fileName): array {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $doc = new DOMDocument();
    $ok = $doc->loadXML($xmlText, LIBXML_NONET | LIBXML_NOENT);
    if (!$ok || libxml_get_errors()) {
        libxml_clear_errors();
        throw new NFeParseException('XML inválido ou corrompido em "' . $fileName . '"');
    }

    $infNFeList = $doc->getElementsByTagName('infNFe');
    if ($infNFeList->length === 0) {
        throw new NFeParseException('Não foi encontrada uma NF-e válida em "' . $fileName . '" (tag infNFe ausente)');
    }
    /** @var DOMElement $infNFe */
    $infNFe = $infNFeList->item(0);
    $ide = $infNFe->getElementsByTagName('ide')->item(0);
    $emit = $infNFe->getElementsByTagName('emit')->item(0);
    $dest = $infNFe->getElementsByTagName('dest')->item(0);
    $total = $infNFe->getElementsByTagName('ICMSTot')->item(0);

    $chave = '';
    $idAttr = $infNFe->getAttribute('Id');
    if ($idAttr) {
        $chave = preg_replace('/^NFe/i', '', $idAttr);
    }

    $transp = $infNFe->getElementsByTagName('transp')->item(0);
    $volEls = $transp ? $transp->getElementsByTagName('vol') : null;
    $volumes = 0.0;
    $especieVol = '';
    if ($volEls) {
        foreach ($volEls as $i => $vol) {
            $volumes += nfe_num(nfe_text_of($vol, 'qVol'));
            if ($i === 0) $especieVol = nfe_text_of($vol, 'esp');
        }
    }

    $nota = [
        'fileName' => $fileName,
        'rawXml' => $xmlText,
        'numero' => nfe_text_of($ide, 'nNF'),
        'serie' => nfe_text_of($ide, 'serie'),
        'dataEmissao' => nfe_text_of($ide, 'dhEmi') ?: nfe_text_of($ide, 'dEmi'),
        'chave' => $chave,
        'emitenteNome' => nfe_text_of($emit, 'xNome'),
        'emitenteCNPJ' => nfe_text_of($emit, 'CNPJ') ?: nfe_text_of($emit, 'CPF'),
        'destinatarioNome' => nfe_text_of($dest, 'xNome'),
        'valorTotal' => nfe_num(nfe_text_of($total, 'vNF')),
        'volumes' => $volumes,
        'especieVol' => $especieVol,
        'itens' => [],
    ];

    $detList = $infNFe->getElementsByTagName('det');
    $i = 0;
    foreach ($detList as $det) {
        /** @var DOMElement $det */
        $prod = $det->getElementsByTagName('prod')->item(0);
        if (!$prod) { $i++; continue; }
        $cEAN = nfe_text_of($prod, 'cEAN');
        $eanInfo = classify_ean($cEAN);
        $nota['itens'][] = [
            'notaLabel' => $nota['numero'] ? ('NF ' . $nota['numero']) : $fileName,
            'nItem' => $det->getAttribute('nItem') ?: (string)($i + 1),
            'cProd' => nfe_text_of($prod, 'cProd'),
            'xProd' => nfe_text_of($prod, 'xProd'),
            'ncm' => nfe_text_of($prod, 'NCM'),
            'ean' => $eanInfo['clean'] ?: ($cEAN ?: ''),
            'eanStatus' => $eanInfo['status'],
            'eanLabel' => $eanInfo['label'],
            'eanCls' => $eanInfo['cls'],
            'uCom' => nfe_text_of($prod, 'uCom'),
            'qCom' => nfe_num(nfe_text_of($prod, 'qCom')),
            'vUnCom' => nfe_num(nfe_text_of($prod, 'vUnCom')),
            'vProd' => nfe_num(nfe_text_of($prod, 'vProd')),
            'codigoCliente' => '',
        ];
        $i++;
    }

    return $nota;
}
