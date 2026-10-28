<?php
/**
 * Gerador mínimo de planilha .xlsx (uma única aba), sem bibliotecas externas —
 * usa só ZipArchive (padrão do PHP). Suficiente para exportar tabelas simples
 * com texto e números; não cobre fórmulas, estilos ou múltiplas abas.
 */
function xlsx_col_letter(int $index): string {
    $letter = '';
    $index++;
    while ($index > 0) {
        $rem = ($index - 1) % 26;
        $letter = chr(65 + $rem) . $letter;
        $index = intdiv($index - 1, 26);
    }
    return $letter;
}

function xlsx_xml_escape(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * @param string[] $headers
 * @param array<int, array<int, string|int|float|null>> $rows cada valor é string (texto) ou int/float (número)
 * @param bool[] $numericCols mapa índice-da-coluna (0-based) => true se a coluna inteira deve ser tratada como número
 */
function build_xlsx(array $headers, array $rows, array $numericCols = []): string {
    $sheetRows = [];
    $allRows = array_merge([$headers], $rows);
    foreach ($allRows as $r => $row) {
        $cells = [];
        foreach (array_values($row) as $c => $val) {
            $ref = xlsx_col_letter($c) . ($r + 1);
            $isHeader = $r === 0;
            $isNumeric = !$isHeader && !empty($numericCols[$c]) && is_numeric($val);
            if ($isNumeric) {
                $cells[] = '<c r="' . $ref . '"><v>' . (0 + $val) . '</v></c>';
            } else {
                $cells[] = '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_xml_escape((string)$val) . '</t></is></c>';
            }
        }
        $sheetRows[] = '<row r="' . ($r + 1) . '">' . implode('', $cells) . '</row>';
    }
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetData>' . implode('', $sheetRows) . '</sheetData>' .
        '</worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Dados" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '</Relationships>';

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $bytes = file_get_contents($tmpFile);
    unlink($tmpFile);
    return $bytes;
}
