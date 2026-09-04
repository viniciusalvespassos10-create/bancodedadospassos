# Dashboards Vila Porto

Código-fonte dos painéis publicados como Claude Artifacts para a Vila Porto International Business. Estes arquivos são a cópia de referência/backup versionada no Git — a versão que as pessoas realmente acessam é a publicada nos links abaixo (edições feitas pelo próprio dashboard, como importar dados novos, atualizam o Artifact publicado e não este repositório automaticamente).

## Painéis

- **Central Vila Porto** (`central-vila-porto.html`) — menu principal com acesso aos módulos.
  Publicado em: https://claude.ai/code/artifact/a2f16c2a-b621-4e3d-8b7c-670900dd50ba

- **Faturamento** (`faturamento-vila-porto.html`) — receita, metas anuais e desempenho mensal por armazém e cliente.
  Publicado em: https://claude.ai/code/artifact/fe45068f-5374-4a27-a5e3-f2bf44e8ecbf

- **Estoque Vila Velha** (`estoque-vila-velha.html`) — ocupação de endereços por cliente no Estabelecimento 15, Vila Velha ES.
  Publicado em: https://claude.ai/code/artifact/db7de67a-aceb-4217-974b-46959bddf9ef

- **Apurações de Serviços** (`apuracoes-servicos.html`) — lista de clientes com o modelo de cobrança combinado com cada um, e link para o painel de apuração individual do cliente quando existir.
  Publicado em: https://claude.ai/code/artifact/8276edd7-6693-42a3-ae15-cebce9e7eb29

- **Apuração Cacique** (`apuracao-cacique.html`) — apuração detalhada de serviços (descargas, armazenagem, seguro) da Companhia Cacique de Café Solúvel. Vinculado a partir de Apurações de Serviços.
  Publicado em: https://claude.ai/code/artifact/c8c6fd21-b70d-4cfb-b121-58f38b52fc36

- **Apuração Olam** (`apuracao-olam.html`) — apuração de serviços (embalagens, caixas, bags, seguro e serviços extras) da Olam Agrícola. Vinculado a partir de Apurações de Serviços.
  Publicado em: https://claude.ai/code/artifact/6a04de0e-9b4d-4b34-aca2-b02e7d5ed8c2

## Como funciona a sincronização

O Faturamento, o Estoque Vila Velha e as Apurações de Serviços usam o recurso `artifact` do Claude (auto-publicação): ao importar dados novos ou editar a lista de clientes, a própria página busca seu HTML atual, atualiza o bloco `<script id="seedData">` com os dados novos e publica uma nova versão de si mesma. Assim, qualquer pessoa com o link vê os dados mais recentes ao abrir a página — sem precisar de login ou banco de dados externo.

A Apuração Cacique e a Apuração Olam usam o recurso `downloads` (para exportar CSV/relatório) em vez do `artifact` — por isso não fazem auto-publicação de dados e, por decisão de escopo, não têm o link público "Anyone with the link" habilitado (as duas capacidades são mutuamente exclusivas na plataforma).

## Publicar uma alteração

Estes arquivos HTML são o formato de conteúdo de um Claude Artifact (sem as tags `<!DOCTYPE>`, `<html>`, `<head>` ou `<body>` — a plataforma adiciona isso automaticamente ao publicar). Para atualizar o painel publicado a partir de uma edição feita aqui no repositório, peça ao Claude para ler o arquivo e republicar no link do Artifact correspondente.
