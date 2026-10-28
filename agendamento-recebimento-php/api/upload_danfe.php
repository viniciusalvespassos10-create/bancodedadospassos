<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Método não permitido'], 405);
$notaId = (int)($_POST['nota_id'] ?? 0);
if (!$notaId || empty($_FILES['arquivo'])) json_out(['erro' => 'Dados inválidos'], 400);

$file = $_FILES['arquivo'];
if ($file['error'] !== UPLOAD_ERR_OK) json_out(['erro' => 'Falha ao enviar o arquivo'], 400);
if ($file['size'] > MAX_DANFE_BYTES) json_out(['erro' => 'PDF muito grande (limite de ' . (MAX_DANFE_BYTES / 1024 / 1024) . 'MB)'], 400);

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if ($mime !== 'application/pdf' && !preg_match('/\.pdf$/i', $file['name'])) {
    json_out(['erro' => 'Selecione um arquivo PDF'], 400);
}

$pdo = db();
$nota = $pdo->prepare('SELECT agendamento_id, danfe_path FROM agendamento_notas WHERE id = ?');
$nota->execute([$notaId]);
$nota = $nota->fetch();
if (!$nota) json_out(['erro' => 'Nota não encontrada'], 404);

if (!is_dir(UPLOADS_DANFE_DIR)) mkdir(UPLOADS_DANFE_DIR, 0775, true);

// Remove o PDF anterior, se houver, antes de salvar o novo.
if ($nota['danfe_path']) {
    $old = UPLOADS_DANFE_DIR . '/' . basename($nota['danfe_path']);
    if (is_file($old)) @unlink($old);
}

$safeName = 'danfe_' . $notaId . '_' . bin2hex(random_bytes(4)) . '.pdf';
$dest = UPLOADS_DANFE_DIR . '/' . $safeName;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    json_out(['erro' => 'Não foi possível salvar o arquivo no servidor'], 500);
}

$pdo->prepare('UPDATE agendamento_notas SET danfe_path = ?, danfe_filename = ? WHERE id = ?')
    ->execute([$safeName, $file['name'], $notaId]);

$numero = $pdo->prepare('SELECT numero, file_name FROM agendamento_notas WHERE id = ?');
$numero->execute([$notaId]);
$numero = $numero->fetch();
registrar_historico((int)$nota['agendamento_id'], 'DANFE anexado à NF ' . ($numero['numero'] ?: $numero['file_name']), 'Cliente');

json_out(['ok' => true, 'fileName' => $file['name']]);
