# Dashboards Vila Porto

Código-fonte dos painéis publicados como Claude Artifacts para a Vila Porto International Business. Estes arquivos são a cópia de referência/backup versionada no Git — a versão que as pessoas realmente acessam é a publicada nos links abaixo (edições feitas pelo próprio dashboard, como importar dados novos, atualizam o Artifact publicado e não este repositório automaticamente).

## Painéis

- **Central Vila Porto** (`central-vila-porto.html`) — menu principal com acesso aos módulos, com card próprio para Recebimentos Mercado Urso. O painel com a lista de cards é mostrado direto, sem senha; os cards de Faturamento, Ocupação & Capacidade e Apurações de Serviços têm um ícone de cadeado e pedem senha só no clique, antes de abrir o módulo (ver nota de segurança abaixo) — o card de Recebimentos Mercado Urso não tem cadeado e abre direto, sem senha. Depois de digitada corretamente uma vez, o navegador lembra e não pede de novo para nenhum card protegido.
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

- **Apuração MOT** (`apuracao-mot.html`) — apuração de serviços (armazenagem + expedição e seguro) da MOT Comércio e Importação, com geração de demonstrativo por e-mail. Vinculado a partir de Apurações de Serviços.
  Publicado em: https://claude.ai/code/artifact/857a0afc-7e9a-4c6e-b84c-8c5ac1307502

- **Apuração Microware** (`apuracao-microware.html`) — "Recebimento Diário", painel de recebimento/notas fiscais da Microware Tecnologia de Informação, com KPIs, gráficos, tabela de notas fiscais, importação de planilha (SheetJS) e apuração mensal de serviços (CRC). Vinculado a partir de Apurações de Serviços.
  Publicado em: https://claude.ai/code/artifact/fab2fcda-ac01-4e81-8028-e39697e09dbe

- **Agendamento de Recebimento** (`recebimentos-mercado-urso.html`) — painel de agendamento de recebimentos do Mercado Urso: upload e leitura de XML de NF-e (extrai emitente, itens, NCM, EAN, valores e volumes declarados no transporte), validação de EAN/GTIN (dígito verificador), dicionário "De/Para" código×EAN, cadastro de agendamentos com cliente/fornecedor/transportadora/placa/motorista, anexo de DANFE em PDF, geração de XML com código do produto substituído pelo EAN, histórico e status (pendente/confirmado/recebido/cancelado), exportação para CSV/XLSX e backup/importação em JSON. Tem uma terceira aba "Tratativas – Outlet / Leilão" com dois campos de anexo de XML da NF-e no topo, um para Outlet e outro para Leilão: ao anexar o XML, o EAN de cada item é extraído e um novo XML é gerado com o código do produto = EAN + sufixo ("-AV" no Outlet, "-LL" no Leilão) quando o item tem EAN válido, ou código original + sufixo quando não tem (nota sem GTIN preenchido, por exemplo) — o sufixo é sempre adicionado, baixado automaticamente (dentro de um .zip — ver nota sobre extensões abaixo). Abaixo do upload, uma tela no mesmo formato da aba "Agendamentos" lista Outlet e Leilão juntos numa única tabela (com coluna "Tipo"), com KPIs (total/pendentes/confirmados/recebidos), busca, filtro por tipo/status/data, status editável, detalhe por registro, botão "baixar novamente" e backup/importação/exportação (.json/.xlsx) próprios dessa lista. Módulo próprio, vinculado a partir do Painel Principal (Central Vila Porto); não faz parte de Apurações de Serviços.
  Publicado em: https://claude.ai/artifact/G1QPBbftMh87wh5LFy2ShQ

## Como funciona a sincronização

Todos os sete painéis de dados (Faturamento, Estoque Vila Velha, Apurações de Serviços, Apuração Cacique, Apuração Olam, Apuração MOT e Apuração Microware) usam o recurso `artifact` do Claude (auto-publicação): a própria página busca seu HTML atual, atualiza o bloco `<script id="seedData">` com os dados novos e publica uma nova versão de si mesma. Assim, as alterações são salvas automaticamente — sem precisar clicar em "salvar" — e qualquer pessoa que abrir o link depois (inclusive após fechar e voltar) vê os dados mais recentes, sem precisar de login ou banco de dados externo.

- Faturamento, Estoque Vila Velha e Apurações de Serviços publicam a cada ação relevante do usuário (importar dados, editar um cliente, adicionar/remover).
- Apuração Cacique publica ao salvar um snapshot, excluir um histórico, limpar dados, e também ao fechar/trocar de aba (para não perder o que foi digitado nas tabelas).
- Apuração Olam publica ao salvar uma apuração, excluir um registro do histórico, ou limpar os dados.
- Apuração MOT publica pouco depois de parar de digitar nos campos (debounce), e também ao limpar os dados.
- Apuração Microware é diferente dos demais: ela guarda seus dados apenas no `localStorage` do navegador de quem está usando (não usa o recurso `artifact` para auto-publicar). Ou seja, dados importados/digitados nela ficam salvos só naquele navegador/computador — não sincronizam entre dispositivos nem aparecem para outra pessoa que abra o link.
- Agendamento de Recebimento (Mercado Urso) também guarda os dados apenas no `localStorage` do navegador (sem `artifact`), pelo mesmo motivo do Microware — agendamentos, DANFEs anexados e o dicionário De/Para ficam só naquele navegador/computador. Usa o recurso `downloads` para exportar XML, CSV/XLSX, backup JSON e baixar o DANFE anexado. Além dos agendamentos já confirmados, o formulário "Novo Agendamento" em andamento (notas XML anexadas, DANFEs, código×EAN preenchido, campos do formulário) fica salvo como rascunho a cada alteração e ao fechar/trocar de aba, e é restaurado automaticamente ao reabrir o painel — só é descartado ao confirmar o agendamento ou clicar em "Limpar formulário". O botão "Gerar XML (código = EAN)" salva o resultado dentro de um `.zip` em vez de um `.xml` direto, porque o recurso `downloads` da plataforma só aceita uma lista fixa de extensões e `.xml` não está nela — basta extrair o `.zip` para obter o `.xml`.

Apuração Cacique, Apuração Olam e Agendamento de Recebimento (Mercado Urso) também usam o recurso `downloads` (para exportar CSV/relatório/XML/XLSX), além do `artifact` (nos dois primeiros). Por decisão de escopo/limitação da plataforma, essas continuam sem o link público "Anyone with the link" habilitado — a capacidade `downloads` bloqueia o compartilhamento público, independente de o `artifact` estar presente. Apuração MOT não usa `downloads` (o "Gerar demonstrativo" apenas copia texto para a área de transferência), então pode ter o link público habilitado se desejado.

## Senha dos cards do Painel Principal (Central Vila Porto)

A tela do Painel Principal (lista de módulos) é mostrada direto, sem senha. Os cards de Faturamento, Ocupação & Capacidade e Apurações de Serviços têm um ícone de cadeado; ao clicar num desses cards, a página pede uma senha antes de abrir o módulo. O card de Recebimentos Mercado Urso não tem cadeado e abre direto, sem senha. **Importante: isso não é uma proteção de segurança real.** É um JavaScript simples rodando no navegador de quem acessa — a senha fica no código-fonte da página (em `central-vila-porto.html`, função `initCardLocks`) e qualquer pessoa com conhecimento básico de navegador (aba "Ver código-fonte" ou DevTools) consegue ver a senha ou pular a verificação. Serve como uma barreira contra acesso casual (alguém que receba o link sem querer, por exemplo), não contra alguém que queira de fato contornar. Se a Vila Porto precisar de controle de acesso real (por usuário, com login), isso exigiria uma solução diferente, fora do que um Claude Artifact estático suporta.

Depois de digitar a senha certa uma vez (em qualquer card protegido), o navegador guarda isso em `localStorage` e não pede de novo para nenhum card protegido nesse mesmo navegador/dispositivo — para pedir de novo, é só limpar os dados do site ou acessar de outro navegador/dispositivo.

## Publicar uma alteração

Estes arquivos HTML são o formato de conteúdo de um Claude Artifact (sem as tags `<!DOCTYPE>`, `<html>`, `<head>` ou `<body>` — a plataforma adiciona isso automaticamente ao publicar). Para atualizar o painel publicado a partir de uma edição feita aqui no repositório, peça ao Claude para ler o arquivo e republicar no link do Artifact correspondente.
