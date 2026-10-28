-- ============================================================
-- Agendamento de Recebimento — Mercado Urso (Vila Porto)
-- Esquema do banco de dados MySQL / MariaDB
-- Importe este arquivo inteiro no phpMyAdmin (ou via linha de
-- comando: mysql -u usuario -p nome_do_banco < schema.sql)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Usuários (login) ----------
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  usuario VARCHAR(60) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário inicial: login "admin", senha "mudar123" (TROQUE a senha assim que entrar —
-- veja o botão "Alterar minha senha" dentro do painel, ou gere um novo hash com
-- password_hash('sua_senha', PASSWORD_DEFAULT) e dê um UPDATE direto no banco).
INSERT INTO usuarios (nome, usuario, senha_hash) VALUES
  ('Administrador', 'admin', '$2y$12$yi9rTfY.z0q5tGWTEgYKg.XIrn/jIBx3aa4Z80r027PfZx78DcFV.')
ON DUPLICATE KEY UPDATE usuario = usuario;
-- (o hash acima corresponde à senha "mudar123")

-- ---------- Dicionário De/Para (código do cliente × EAN) ----------
CREATE TABLE IF NOT EXISTS depara (
  ean VARCHAR(20) PRIMARY KEY,
  codigo VARCHAR(60) NOT NULL,
  descricao VARCHAR(255) DEFAULT NULL,
  origem VARCHAR(30) DEFAULT NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Agendamentos ----------
CREATE TABLE IF NOT EXISTS agendamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  hora TIME NOT NULL,
  cliente VARCHAR(180) NOT NULL,
  fornecedor VARCHAR(180) DEFAULT NULL,
  transportadora VARCHAR(180) DEFAULT NULL,
  placa VARCHAR(20) DEFAULT NULL,
  motorista VARCHAR(120) DEFAULT NULL,
  obs TEXT,
  valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  volumes DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pendente','confirmado','recebido','cancelado') NOT NULL DEFAULT 'pendente',
  criado_por INT DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Notas fiscais anexadas a um agendamento ----------
CREATE TABLE IF NOT EXISTS agendamento_notas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  agendamento_id INT NOT NULL,
  numero VARCHAR(20) DEFAULT NULL,
  serie VARCHAR(10) DEFAULT NULL,
  data_emissao VARCHAR(40) DEFAULT NULL,
  chave VARCHAR(60) DEFAULT NULL,
  emitente_nome VARCHAR(180) DEFAULT NULL,
  emitente_cnpj VARCHAR(20) DEFAULT NULL,
  valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  volumes DECIMAL(10,2) NOT NULL DEFAULT 0,
  especie_vol VARCHAR(40) DEFAULT NULL,
  file_name VARCHAR(255) DEFAULT NULL,
  raw_xml LONGTEXT,
  danfe_path VARCHAR(255) DEFAULT NULL,
  danfe_filename VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Itens das notas de um agendamento ----------
CREATE TABLE IF NOT EXISTS agendamento_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  agendamento_id INT NOT NULL,
  nota_label VARCHAR(60) DEFAULT NULL,
  cprod VARCHAR(60) DEFAULT NULL,
  xprod VARCHAR(255) DEFAULT NULL,
  ncm VARCHAR(20) DEFAULT NULL,
  ean VARCHAR(20) DEFAULT NULL,
  ean_status VARCHAR(20) DEFAULT NULL,
  ean_label VARCHAR(60) DEFAULT NULL,
  ucom VARCHAR(10) DEFAULT NULL,
  qcom DECIMAL(14,4) NOT NULL DEFAULT 0,
  vuncom DECIMAL(14,4) NOT NULL DEFAULT 0,
  vprod DECIMAL(14,2) NOT NULL DEFAULT 0,
  codigo_cliente VARCHAR(60) DEFAULT NULL,
  FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Histórico de eventos de um agendamento ----------
CREATE TABLE IF NOT EXISTS agendamento_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  agendamento_id INT NOT NULL,
  data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  descricao VARCHAR(255) NOT NULL,
  usuario VARCHAR(80) DEFAULT NULL,
  FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Tratativas (Outlet / Leilão) ----------
CREATE TABLE IF NOT EXISTS tratativas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('outlet','leilao') NOT NULL,
  file_name VARCHAR(255) DEFAULT NULL,
  numero VARCHAR(20) DEFAULT NULL,
  serie VARCHAR(10) DEFAULT NULL,
  cliente VARCHAR(180) DEFAULT NULL,
  fornecedor VARCHAR(180) DEFAULT NULL,
  valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  volumes DECIMAL(10,2) NOT NULL DEFAULT 0,
  replaced INT NOT NULL DEFAULT 0,
  skipped INT NOT NULL DEFAULT 0,
  raw_xml LONGTEXT,
  suffix VARCHAR(5) NOT NULL,
  status ENUM('pendente','confirmado','recebido','cancelado') NOT NULL DEFAULT 'pendente',
  data DATE NOT NULL,
  hora TIME NOT NULL,
  criado_por INT DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_trat_status (status),
  INDEX idx_trat_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Itens de uma tratativa ----------
CREATE TABLE IF NOT EXISTS tratativa_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tratativa_id INT NOT NULL,
  cprod VARCHAR(60) DEFAULT NULL,
  xprod VARCHAR(255) DEFAULT NULL,
  ncm VARCHAR(20) DEFAULT NULL,
  ean VARCHAR(20) DEFAULT NULL,
  ean_status VARCHAR(20) DEFAULT NULL,
  ean_label VARCHAR(60) DEFAULT NULL,
  ucom VARCHAR(10) DEFAULT NULL,
  qcom DECIMAL(14,4) NOT NULL DEFAULT 0,
  vuncom DECIMAL(14,4) NOT NULL DEFAULT 0,
  vprod DECIMAL(14,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (tratativa_id) REFERENCES tratativas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
