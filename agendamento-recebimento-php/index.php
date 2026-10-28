<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/functions.php';
$usuarioLogado = exigir_login();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agendamento de Recebimento — Mercado Urso</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  .topbar{display:flex;align-items:center;justify-content:space-between;padding:14px 0;}
  .topbar .user-info{display:flex;align-items:center;gap:12px;font-size:13px;color:var(--text-dim);}
  .topbar .user-info a{color:var(--text-dim);text-decoration:none;border:1px solid var(--border);padding:6px 12px;border-radius:8px;}
  .topbar .user-info a:hover{color:var(--text);border-color:var(--accent);}
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <h1 style="font-size:18px;margin:0;">Agendamento de Recebimento <span style="color:var(--text-faint);font-weight:400;">— Mercado Urso</span></h1>
    <div class="user-info">
      <span><?= h($usuarioLogado['nome']) ?></span>
      <a href="logout.php">Sair</a>
    </div>
  </div>

  <nav class="tabs">
    <button data-view="novo" class="active">Novo Agendamento</button>
    <button data-view="lista">Agendamentos <span class="count" id="countAgendamentos">0</span></button>
    <button data-view="tratativas">Tratativas – Outlet / Leilão</button>
  </nav>

  <!-- ============ VIEW: NOVO AGENDAMENTO ============ -->
  <section class="view active" id="view-novo">
    <div class="grid">
      <div>
        <div class="card">
          <h2>1. Anexar nota(s) do fornecedor (XML da NF-e)</h2>
          <p class="desc">Aceita um ou mais arquivos XML de NF-e. Código do produto (cProd) e EAN (cEAN) de cada item são extraídos automaticamente do XML.</p>
          <div class="dropzone" id="dropzone">
            <div class="ico">📄</div>
            <div class="main">Clique para selecionar ou arraste o(s) XML aqui</div>
            <div class="hint">Formato aceito: .xml — múltiplos arquivos permitidos</div>
          </div>
          <input type="file" id="fileInput" accept=".xml,text/xml" multiple style="display:none">
          <div id="notasContainer" style="margin-top:14px;"></div>
        </div>

        <div class="card" id="itensCard" style="display:none;">
          <h2>2. Conferência de itens (código x EAN)</h2>
          <p class="desc">Confirme o EAN e informe/valide o código interno do cliente para cada item. Itens com EAN já mapeado são preenchidos automaticamente.</p>
          <div class="tbl-wrap">
            <table>
              <thead>
                <tr>
                  <th>NF</th><th>Cód. Fornecedor</th><th>Descrição</th><th>EAN (XML)</th>
                  <th>Status</th><th>Código Cliente</th><th class="num">Qtd</th><th class="num">Vlr Total</th>
                </tr>
              </thead>
              <tbody id="itensBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <div>
        <div class="card">
          <h2>Dados do agendamento</h2>
          <p class="desc">Informações da entrega a ser agendada no armazém.</p>
          <div class="row2">
            <div class="field"><label for="fData">Data prevista *</label><input type="date" id="fData"></div>
            <div class="field"><label for="fHora">Horário previsto *</label><input type="time" id="fHora"></div>
          </div>
          <div class="field">
            <label for="fCliente">Cliente *</label>
            <input type="text" id="fCliente" list="clientesList" placeholder="Ex: Olam Agrícola">
            <datalist id="clientesList"></datalist>
          </div>
          <div class="field">
            <label for="fFornecedor">Fornecedor</label>
            <input type="text" id="fFornecedor" placeholder="Preenchido automaticamente pelo XML (editável)">
          </div>
          <div class="row2">
            <div class="field">
              <label for="fTransportadora">Transportadora</label>
              <input type="text" id="fTransportadora" list="transpList" placeholder="Opcional">
              <datalist id="transpList"></datalist>
            </div>
            <div class="field"><label for="fPlaca">Placa do veículo</label><input type="text" id="fPlaca" placeholder="AAA-0A00" style="text-transform:uppercase;"></div>
          </div>
          <div class="field"><label for="fMotorista">Motorista</label><input type="text" id="fMotorista" placeholder="Opcional"></div>
          <div class="field"><label for="fObs">Observações</label><textarea id="fObs" placeholder="Informações adicionais para a equipe de recebimento"></textarea></div>
          <div class="btn-row">
            <button class="btn primary" id="btnSalvar">Confirmar agendamento</button>
            <button class="btn ghost" id="btnLimpar">Limpar formulário</button>
          </div>
          <p class="footnote" id="resumoNota"></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ VIEW: LISTA DE AGENDAMENTOS ============ -->
  <section class="view" id="view-lista">
    <div class="kpis">
      <div class="kpi"><div class="lbl">Total agendamentos</div><div class="val" id="kpiTotal">0</div></div>
      <div class="kpi"><div class="lbl">Pendentes</div><div class="val warn" id="kpiPendentes">0</div></div>
      <div class="kpi"><div class="lbl">Confirmados</div><div class="val accent" id="kpiConfirmados">0</div></div>
      <div class="kpi"><div class="lbl">Recebidos</div><div class="val success" id="kpiRecebidos">0</div></div>
    </div>

    <div class="card">
      <div class="toolbar">
        <div class="filters">
          <input type="search" id="buscaAg" placeholder="Buscar por cliente, fornecedor ou NF...">
          <select id="filtroStatus">
            <option value="">Todos os status</option>
            <option value="pendente">Pendente</option>
            <option value="confirmado">Confirmado</option>
            <option value="recebido">Recebido</option>
            <option value="cancelado">Cancelado</option>
          </select>
          <input type="date" id="filtroData">
          <button class="btn sm ghost" id="btnLimparFiltros">Limpar filtros</button>
        </div>
        <div class="btn-row">
          <button class="btn sm" id="btnExportJson">Backup (.json)</button>
          <button class="btn sm" id="btnImportJson">Importar backup</button>
          <button class="btn sm" id="btnExportCsv">Exportar CSV</button>
          <button class="btn sm" id="btnExportXlsx">Exportar Excel (.xlsx)</button>
          <input type="file" id="importInput" accept=".json" style="display:none">
        </div>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Data / Hora</th><th>Cliente</th><th>Fornecedor</th><th>NF(s)</th>
              <th class="num">Itens</th><th class="num">Valor Total</th><th>Status</th><th>XML</th><th>PDF</th><th>Ações</th>
            </tr>
          </thead>
          <tbody id="listaBody"></tbody>
        </table>
      </div>
      <div class="empty" id="listaEmpty" style="display:none;">
        <div class="ico">📭</div>
        Nenhum agendamento encontrado.
      </div>
    </div>
  </section>

  <!-- ============ VIEW: TRATATIVAS - OUTLET / LEILÃO ============ -->
  <section class="view" id="view-tratativas">
    <div class="row2">
      <div class="card">
        <h2>Outlet</h2>
        <p class="desc">Anexe o(s) XML da NF-e: o EAN de cada item é extraído e um novo XML é gerado com o código do produto substituído pelo EAN + sufixo "-AV", baixado automaticamente.</p>
        <div class="dropzone" id="dropzoneOutlet">
          <div class="ico">📄</div>
          <div class="main">Clique para selecionar ou arraste o(s) XML aqui</div>
          <div class="hint">Formato aceito: .xml — múltiplos arquivos permitidos</div>
        </div>
        <input type="file" id="fileInputOutlet" accept=".xml,text/xml" multiple style="display:none">
      </div>
      <div class="card">
        <h2>Leilão</h2>
        <p class="desc">Anexe o(s) XML da NF-e: o EAN de cada item é extraído e um novo XML é gerado com o código do produto substituído pelo EAN + sufixo "-LL", baixado automaticamente.</p>
        <div class="dropzone" id="dropzoneLeilao">
          <div class="ico">📄</div>
          <div class="main">Clique para selecionar ou arraste o(s) XML aqui</div>
          <div class="hint">Formato aceito: .xml — múltiplos arquivos permitidos</div>
        </div>
        <input type="file" id="fileInputLeilao" accept=".xml,text/xml" multiple style="display:none">
      </div>
    </div>

    <div class="kpis" style="margin-top:20px;">
      <div class="kpi"><div class="lbl">Total tratativas</div><div class="val" id="kpiTratTotal">0</div></div>
      <div class="kpi"><div class="lbl">Pendentes</div><div class="val warn" id="kpiTratPendentes">0</div></div>
      <div class="kpi"><div class="lbl">Confirmados</div><div class="val accent" id="kpiTratConfirmados">0</div></div>
      <div class="kpi"><div class="lbl">Recebidos</div><div class="val success" id="kpiTratRecebidos">0</div></div>
    </div>

    <div class="card">
      <div class="toolbar">
        <div class="filters">
          <input type="search" id="buscaTrat" placeholder="Buscar por cliente, fornecedor ou NF...">
          <select id="filtroTipoTrat">
            <option value="">Outlet e Leilão</option>
            <option value="outlet">Outlet</option>
            <option value="leilao">Leilão</option>
          </select>
          <select id="filtroStatusTrat">
            <option value="">Todos os status</option>
            <option value="pendente">Pendente</option>
            <option value="confirmado">Confirmado</option>
            <option value="recebido">Recebido</option>
            <option value="cancelado">Cancelado</option>
          </select>
          <input type="date" id="filtroDataTrat">
          <button class="btn sm ghost" id="btnLimparFiltrosTrat">Limpar filtros</button>
        </div>
        <div class="btn-row">
          <button class="btn sm" id="btnExportJsonTrat">Backup (.json)</button>
          <button class="btn sm" id="btnImportJsonTrat">Importar backup</button>
          <button class="btn sm" id="btnExportXlsxTrat">Exportar Excel (.xlsx)</button>
          <input type="file" id="importInputTrat" accept=".json" style="display:none">
        </div>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Data / Hora</th><th>Tipo</th><th>Cliente</th><th>Fornecedor</th><th>NF</th>
              <th class="num">Itens</th><th class="num">Valor Total</th><th>Status</th><th>XML</th><th>Ações</th>
            </tr>
          </thead>
          <tbody id="tratativasBody"></tbody>
        </table>
      </div>
      <div class="empty" id="tratativasEmpty" style="display:none;">
        <div class="ico">📭</div>
        Nenhuma tratativa encontrada.
      </div>
    </div>
  </section>

</div>

<!-- Detail modal -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <button class="close" id="modalClose">✕</button>
    <div id="modalContent"></div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="assets/js/app.js"></script>
</body>
</html>
