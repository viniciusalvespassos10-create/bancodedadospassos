# Dashboards Vila Porto

Código-fonte dos painéis publicados como Claude Artifacts para a Vila Porto International Business. Estes arquivos são a cópia de referência/backup versionada no Git — a versão que as pessoas realmente acessam é a publicada nos links abaixo (edições feitas pelo próprio dashboard, como importar dados novos, atualizam o Artifact publicado e não este repositório automaticamente).

## Painéis

- **Central Vila Porto** (`central-vila-porto.html`) — menu principal com acesso aos módulos.
  Publicado em: https://claude.ai/code/artifact/a2f16c2a-b621-4e3d-8b7c-670900dd50ba

- **Faturamento** (`faturamento-vila-porto.html`) — receita, metas anuais e desempenho mensal por armazém e cliente.
  Publicado em: https://claude.ai/code/artifact/fe45068f-5374-4a27-a5e3-f2bf44e8ecbf

- **Estoque Vila Velha** (`estoque-vila-velha.html`) — ocupação de endereços por cliente no Estabelecimento 15, Vila Velha ES.
  Publicado em: https://claude.ai/code/artifact/db7de67a-aceb-4217-974b-46959bddf9ef

## Como funciona a sincronização

O Faturamento e o Estoque Vila Velha usam o recurso `artifact` do Claude (auto-publicação): ao importar dados novos ou clicar em "Atualizar/Processar", a própria página busca seu HTML atual, atualiza o bloco `<script id="seedData">` com os dados novos e publica uma nova versão de si mesma. Assim, qualquer pessoa com o link vê os dados mais recentes ao abrir a página — sem precisar de login ou banco de dados externo.

## Publicar uma alteração

Estes arquivos HTML são o formato de conteúdo de um Claude Artifact (sem as tags `<!DOCTYPE>`, `<html>`, `<head>` ou `<body>` — a plataforma adiciona isso automaticamente ao publicar). Para atualizar o painel publicado a partir de uma edição feita aqui no repositório, peça ao Claude para ler o arquivo e republicar no link do Artifact correspondente.
