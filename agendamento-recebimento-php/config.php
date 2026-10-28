<?php
/**
 * Configuração de conexão com o banco de dados.
 * Edite os quatro valores abaixo com os dados da sua hospedagem
 * (painel de controle / cPanel costuma mostrar isso em "Bancos de dados MySQL").
 */
if (file_exists(__DIR__ . '/config.local.php')) {
    // Usado apenas em ambiente de desenvolvimento/teste; não existe em produção.
    require __DIR__ . '/config.local.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'agendamento_recebimento');
    define('DB_USER', 'TROQUE_O_USUARIO');
    define('DB_PASS', 'TROQUE_A_SENHA');
}

// Pasta onde os PDFs de DANFE ficam salvos (precisa ter permissão de escrita, ex: 755 ou 775).
define('UPLOADS_DANFE_DIR', __DIR__ . '/uploads/danfes');
define('UPLOADS_DANFE_URL', 'uploads/danfes'); // caminho relativo usado nos links de download

// Tamanho máximo de PDF de DANFE aceito (em bytes). 20MB por padrão.
define('MAX_DANFE_BYTES', 20 * 1024 * 1024);

date_default_timezone_set('America/Sao_Paulo');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Erro ao conectar no banco de dados. Confira os dados em config.php. (' . htmlspecialchars($e->getMessage()) . ')');
        }
    }
    return $pdo;
}
