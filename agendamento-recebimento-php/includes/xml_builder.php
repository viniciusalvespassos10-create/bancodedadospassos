<?php
require_once __DIR__ . '/functions.php';

class XmlBuildException extends Exception {}

/**
 * Gera uma cópia do XML original com o <cProd> de cada item substituído pelo
 * EAN (quando aproveitável) ou mantido como o código original + sufixo, se
 * $suffix for informado (uso do Outlet/Leilão: "-AV"/"-LL"). Sem sufixo, replica
 * o comportamento do botão "Gerar XML (código = EAN)": só troca quando há EAN
 * válido, sem mexer nos itens sem EAN.
 *
 * @param array $itens cada item precisa ter 'ean' e 'eanStatus' (na mesma ordem dos <det> do XML).
 * @return array{xmlStr:string,replaced:int,skipped:int}
 */
function build_product_code_xml(string $rawXml, array $itens, ?string $suffix = null): array {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $doc = new DOMDocument();
    $ok = $doc->loadXML($rawXml, LIBXML_NONET | LIBXML_NOENT);
    if (!$ok || libxml_get_errors()) {
        libxml_clear_errors();
        throw new XmlBuildException('XML original inválido');
    }

    $detList = $doc->getElementsByTagName('det');
    $replaced = 0;
    $skipped = 0;
    $i = 0;
    foreach ($detList as $det) {
        /** @var DOMElement $det */
        $prod = $det->getElementsByTagName('prod')->item(0);
        $item = $itens[$i] ?? null;
        $i++;
        if (!$prod || !$item) continue;
        $cProdEls = $prod->getElementsByTagName('cProd');
        if ($cProdEls->length === 0) continue;
        $cProdEl = $cProdEls->item(0);
        $usavel = ean_usavel($item['ean'] ?? null, $item['eanStatus'] ?? null);

        if ($suffix !== null) {
            // Modo Outlet/Leilão: sempre adiciona o sufixo (EAN + sufixo, ou código original + sufixo).
            $base = $usavel ? $item['ean'] : $cProdEl->textContent;
            $cProdEl->textContent = $base . '-' . $suffix;
            if ($usavel) { $replaced++; } else { $skipped++; }
        } else {
            // Modo "Gerar XML (código = EAN)": só troca quando há EAN aproveitável.
            if ($usavel) {
                $cProdEl->textContent = $item['ean'];
                $replaced++;
            } else {
                $skipped++;
            }
        }
    }

    $xmlStr = $doc->saveXML();
    if (!preg_match('/^\s*<\?xml/i', $xmlStr)) {
        $xmlStr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $xmlStr;
    }
    return ['xmlStr' => $xmlStr, 'replaced' => $replaced, 'skipped' => $skipped];
}
