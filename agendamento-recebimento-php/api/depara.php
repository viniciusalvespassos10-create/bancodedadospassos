<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = db()->query('SELECT ean, codigo, descricao, origem FROM depara ORDER BY ean')->fetchAll();
    json_out(['depara' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = body_json();
    $ean = trim($in['ean'] ?? '');
    $codigo = trim($in['codigo'] ?? '');
    if ($ean === '' || $codigo === '') json_out(['erro' => 'Informe EAN e código'], 400);
    db()->prepare('INSERT INTO depara (ean, codigo, descricao, origem) VALUES (?,?,?,"manual")
        ON DUPLICATE KEY UPDATE codigo = VALUES(codigo), descricao = VALUES(descricao), origem = "manual"')
        ->execute([$ean, $codigo, $in['descricao'] ?? null]);
    json_out(['ok' => true]);
}

json_out(['erro' => 'Método não permitido'], 405);
