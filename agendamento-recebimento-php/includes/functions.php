<?php
/**
 * Funções utilitárias compartilhadas (validação de EAN/GTIN, formatação).
 * Porta fiel da lógica usada na versão original (JavaScript) do painel.
 */
require_once __DIR__ . '/../config.php';

/** Mod-10 (dígito verificador), válido para GTIN-8/12/13/14. */
function check_digit_gtin(string $numStr): bool {
    $digits = array_map('intval', str_split($numStr));
    $check = array_pop($digits);
    $sum = 0;
    $len = count($digits);
    for ($i = $len - 1, $w = 0; $i >= 0; $i--, $w++) {
        $sum += $digits[$i] * (($w % 2 === 0) ? 3 : 1);
    }
    $calc = (10 - ($sum % 10)) % 10;
    return $calc === $check;
}

/** @return array{status:string,label:string,cls:string,clean:string} */
function classify_ean(?string $rawEan): array {
    $ean = strtoupper(trim((string)$rawEan));
    if ($ean === '' || $ean === 'SEM GTIN' || $ean === 'SEMGTIN') {
        return ['status' => 'sem_gtin', 'label' => 'Sem GTIN', 'cls' => 'neutral', 'clean' => ''];
    }
    if (!preg_match('/^\d+$/', $ean)) {
        return ['status' => 'invalido', 'label' => 'Formato inválido', 'cls' => 'bad', 'clean' => $ean];
    }
    if (!in_array(strlen($ean), [8, 12, 13, 14], true)) {
        return ['status' => 'invalido', 'label' => 'Tamanho inválido (' . strlen($ean) . ')', 'cls' => 'bad', 'clean' => $ean];
    }
    if (check_digit_gtin($ean)) {
        return ['status' => 'ok', 'label' => 'Válido', 'cls' => 'ok', 'clean' => $ean];
    }
    return ['status' => 'divergente', 'label' => 'Dígito verificador não confere', 'cls' => 'warn', 'clean' => $ean];
}

/** Um EAN é "aproveitável" para gerar o código do produto quando não é vazio/sem-GTIN/inválido. */
function ean_usavel(?string $ean, ?string $eanStatus): bool {
    return !empty($ean) && $eanStatus !== 'sem_gtin' && $eanStatus !== 'invalido';
}

function fmt_money($v): string {
    return number_format((float)$v, 2, ',', '.');
}

function fmt_num($v): string {
    $v = (float)$v;
    // até 3 casas decimais, sem zeros à direita desnecessários
    $s = rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    return $s === '' ? '0' : $s;
}

function fmt_date_br(?string $isoDate): string {
    if (!$isoDate) return '—';
    $t = strtotime($isoDate);
    return $t ? date('d/m/Y', $t) : '—';
}

/** Gera um id legível único, só para compatibilidade com o formato usado no app antigo (não é mais a chave primária). */
function gen_uid(string $prefix = 'ag'): string {
    return $prefix . '_' . base_convert((string)round(microtime(true) * 1000), 10, 36) . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
}

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function json_out($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

const STATUS_LABEL = ['pendente' => 'Pendente', 'confirmado' => 'Confirmado', 'recebido' => 'Recebido', 'cancelado' => 'Cancelado'];
const STATUS_CLS = ['pendente' => 'warn', 'confirmado' => 'info', 'recebido' => 'ok', 'cancelado' => 'bad'];
const TRATATIVA_SUFFIX = ['outlet' => 'AV', 'leilao' => 'LL'];
const TRATATIVA_TIPO_LABEL = ['outlet' => 'Outlet', 'leilao' => 'Leilão'];

function registrar_historico(int $agendamentoId, string $descricao, string $usuario = 'Sistema'): void {
    db()->prepare('INSERT INTO agendamento_historico (agendamento_id, descricao, usuario) VALUES (?,?,?)')
        ->execute([$agendamentoId, $descricao, $usuario]);
}

/** Preenche sugestoes de "código do cliente" nos itens a partir do dicionário De/Para (por EAN). */
function aplicar_sugestoes_depara(array &$itens): void {
    $eans = array_values(array_unique(array_filter(array_map(fn($it) => $it['ean'] ?? '', $itens))));
    if (!$eans) return;
    $placeholders = implode(',', array_fill(0, count($eans), '?'));
    $stmt = db()->prepare("SELECT ean, codigo FROM depara WHERE ean IN ($placeholders)");
    $stmt->execute($eans);
    $map = [];
    foreach ($stmt->fetchAll() as $row) { $map[$row['ean']] = $row['codigo']; }
    foreach ($itens as &$it) {
        if (!empty($it['ean']) && isset($map[$it['ean']])) {
            $it['codigoCliente'] = $map[$it['ean']];
        }
    }
    unset($it);
}
