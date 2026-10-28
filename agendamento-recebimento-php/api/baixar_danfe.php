<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigir_login();

$notaId = (int)($_GET['nota_id'] ?? 0);
if (!$notaId) { http_response_code(400); exit('id inválido'); }

$nota = db()->prepare('SELECT danfe_path, danfe_filename, numero, agendamento_id, file_name FROM agendamento_notas WHERE id = ?');
$nota->execute([$notaId]);
$nota = $nota->fetch();
if (!$nota || !$nota['danfe_path']) { http_response_code(404); exit('DANFE não encontrado'); }

$full = UPLOADS_DANFE_DIR . '/' . basename($nota['danfe_path']);
if (!is_file($full)) { http_response_code(404); exit('Arquivo não encontrado no servidor'); }

registrar_historico((int)$nota['agendamento_id'], 'DANFE da NF ' . ($nota['numero'] ?: $nota['file_name']) . ' baixado', 'Cliente');

$downloadName = $nota['danfe_filename'] ?: 'danfe.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . addslashes($downloadName) . '"');
header('Content-Length: ' . filesize($full));
readfile($full);
exit;
