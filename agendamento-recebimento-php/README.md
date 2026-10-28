# Agendamento de Recebimento — Mercado Urso (PHP + MySQL)

Versão autônoma, para hospedar no seu próprio servidor, do painel de Agendamento de Recebimento (incluindo a aba "Tratativas – Outlet / Leilão"). Não depende da Claude nem de nenhum serviço externo — só de PHP e MySQL/MariaDB, que praticamente toda hospedagem (cPanel, Hostinger, Locaweb etc.) já oferece.

## O que tem

- Upload e leitura de XML de NF-e (emitente, itens, NCM, EAN, valores, volumes).
- Validação de EAN/GTIN (dígito verificador).
- Dicionário "De/Para" código do cliente × EAN (preenchido automaticamente e salvo a cada agendamento confirmado).
- Cadastro de agendamentos (cliente, fornecedor, transportadora, placa, motorista, observações).
- Anexo de DANFE em PDF (guardado no próprio servidor, em `uploads/danfes/`).
- Geração de XML com o código do produto substituído pelo EAN ("Gerar XML (código = EAN)"), baixado diretamente como `.xml` (sem precisar de `.zip` — essa limitação só existia na versão rodando dentro da Claude).
- Histórico de eventos por agendamento, status (pendente/confirmado/recebido/cancelado).
- Exportação para CSV e Excel (.xlsx), backup e importação em JSON.
- Aba "Tratativas – Outlet / Leilão": anexa XML, extrai o EAN de cada item e gera um novo XML com o código do produto = EAN + sufixo ("-AV" no Outlet, "-LL" no Leilão) — ou código original + sufixo quando não há EAN válido —, baixado automaticamente. Lista própria com os mesmos recursos de filtro/status/exportação.
- Login com usuário e senha (multiusuário) — qualquer pessoa que você cadastrar acessa o mesmo painel, com os mesmos dados, em tempo real (porque agora é um banco de dados de verdade, não mais o navegador de cada um).

### Diferença importante em relação à versão que rodava na Claude

Como agora há um banco de dados compartilhado de verdade (MySQL), **o problema de status "voltando sozinho" e de dados não aparecerem para outra pessoa não existe mais** — cada ação (mudar status, criar agendamento, etc.) é gravada direto no banco, sem o tipo de sincronização "eventual" que causava aquilo.

A única coisa que ficou diferente: o rascunho do formulário "Novo Agendamento" (notas anexadas mas ainda não confirmadas) não é salvo automaticamente se você atualizar a página no meio do preenchimento — precisa confirmar o agendamento (ou reanexar o XML) se recarregar antes de salvar. Isso é só o formulário em andamento; nada que já foi confirmado se perde.

## Requisitos da hospedagem

- PHP 7.4 ou superior (testado com PHP 8.4), com as extensões `pdo_mysql`, `dom`, `zip` e `fileinfo` (vêm habilitadas por padrão na grande maioria das hospedagens).
- Banco de dados MySQL ou MariaDB.
- Apache com `mod_rewrite`/`.htaccess` habilitado (padrão em hospedagens compartilhadas) — os arquivos `.htaccess` incluídos protegem a pasta de PDFs e alguns arquivos internos. Se sua hospedagem usa Nginx, veja a seção "Nginx" abaixo.

## Passo a passo para instalar

1. **Crie o banco de dados** no painel da sua hospedagem (cPanel → "Bancos de dados MySQL", ou equivalente). Anote: nome do banco, usuário e senha.

2. **Importe o `schema.sql`** — no phpMyAdmin, abra o banco criado, vá em "Importar" e selecione o arquivo `schema.sql` desta pasta. Isso cria todas as tabelas e já deixa um usuário de acesso pronto:
   - **Usuário:** `admin`
   - **Senha:** `mudar123`

   **Troque essa senha assim que entrar** (veja "Trocar/cadastrar senha" abaixo) — ela está documentada aqui, então não é segura por padrão.

3. **Edite `config.php`** com os dados do banco que você criou no passo 1:
   ```php
   define('DB_HOST', 'localhost');       // normalmente "localhost" na maioria das hospedagens
   define('DB_NAME', 'nome_do_seu_banco');
   define('DB_USER', 'usuario_do_banco');
   define('DB_PASS', 'senha_do_banco');
   ```

4. **Envie todos os arquivos** desta pasta para o seu servidor via FTP/SFTP ou pelo Gerenciador de Arquivos do painel de hospedagem (mantenha a estrutura de pastas — `api/`, `includes/`, `assets/`, `uploads/`).

5. **Dê permissão de escrita** na pasta `uploads/danfes/` (normalmente 755 ou 775 — se der erro ao anexar PDF, tente 775).

6. **Acesse `login.php`** pelo navegador (ex: `https://seudominio.com.br/agendamento/login.php`) e entre com `admin` / `mudar123`.

## Cadastrar mais usuários / trocar senha

Não tem tela própria pra isso ainda — faça direto pelo phpMyAdmin, na tabela `usuarios`:

- **Cadastrar um novo usuário:** insira uma linha nova com `nome`, `usuario` e `senha_hash`. Para gerar o hash da senha, rode este comando (se tiver acesso a terminal com PHP) ou peça pra alguém gerar:
  ```
  php -r "echo password_hash('a_senha_que_voce_quer', PASSWORD_DEFAULT), PHP_EOL;"
  ```
  Cole o resultado (começa com `$2y$...`) no campo `senha_hash`.

- **Trocar a senha do usuário admin:** gere um novo hash do mesmo jeito acima e dê um `UPDATE` na linha do usuário:
  ```sql
  UPDATE usuarios SET senha_hash = '$2y$...' WHERE usuario = 'admin';
  ```

- **Desativar um usuário sem apagar:** `UPDATE usuarios SET ativo = 0 WHERE usuario = 'fulano';`

## Nginx (se sua hospedagem não usa Apache)

Os arquivos `.htaccess` incluídos (que bloqueiam acesso direto aos PDFs e a `includes/`) só funcionam em Apache. No Nginx, adicione ao bloco `server` do seu site:

```nginx
location ~ ^/(includes|uploads/danfes)/ {
    deny all;
    return 403;
}
location ~ /(schema\.sql|config\.local\.php)$ {
    deny all;
    return 403;
}
```

## Estrutura dos arquivos

```
agendamento-recebimento-php/
  schema.sql            → importe isso no seu banco MySQL
  config.php             → edite com os dados do SEU banco
  auth.php, login.php, logout.php   → login
  index.php               → página principal (as 3 abas)
  includes/                → lógica compartilhada (parser de XML, validação de EAN, geração de XML, XLSX)
  api/                     → endpoints chamados pela tela (JSON + downloads)
  assets/css, assets/js    → visual e comportamento da tela
  uploads/danfes/          → PDFs de DANFE anexados (crie com permissão de escrita)
```

## Backup

Além do backup/importação em JSON de dentro do próprio painel (botões "Backup (.json)"), faça backups regulares do banco de dados inteiro pelo phpMyAdmin ("Exportar") ou pela ferramenta de backup da sua hospedagem — é a cópia mais completa (inclui tudo, inclusive o histórico).
