(function () {
  'use strict';

  // ---------- Helpers ----------
  function escapeHtml(s) {
    return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function escapeAttr(s) { return escapeHtml(s); }
  function fmtMoney(v) { return (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function fmtNum(v) { return (Number(v) || 0).toLocaleString('pt-BR', { maximumFractionDigits: 3 }); }
  function fmtDateBR(iso) {
    if (!iso) return '—';
    var p = String(iso).slice(0, 10).split('-');
    return p.length === 3 ? (p[2] + '/' + p[1] + '/' + p[0]) : '—';
  }
  var STATUS_LABEL = { pendente: 'Pendente', confirmado: 'Confirmado', recebido: 'Recebido', cancelado: 'Cancelado' };
  var STATUS_CLS = { pendente: 'warn', confirmado: 'info', recebido: 'ok', cancelado: 'bad' };
  var TRATATIVA_TIPO_LABEL = { outlet: 'Outlet', leilao: 'Leilão' };

  function eanUsavel(ean, eanStatus) {
    return !!ean && eanStatus !== 'sem_gtin' && eanStatus !== 'invalido';
  }

  var toastEl = document.getElementById('toast');
  var toastTimer = null;
  function toast(msg, type) {
    toastEl.textContent = msg;
    toastEl.className = 'toast show' + (type ? ' ' + type : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.className = 'toast'; }, 3200);
  }

  function apiJson(url, opts) {
    opts = opts || {};
    return fetch(url, opts).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) throw new Error(data.erro || 'Erro na requisição');
        return data;
      });
    });
  }
  function postJson(url, body) {
    return apiJson(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  }

  function triggerDownload(url) {
    var a = document.createElement('a');
    a.href = url;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  // ---------- Confirm modal ----------
  function showConfirmModal(title, message, confirmLabel, onConfirm) {
    document.getElementById('modalContent').innerHTML =
      '<h2 style="margin-top:0;">' + escapeHtml(title) + '</h2>' +
      '<p class="footnote" style="margin-bottom:20px;font-size:13px;">' + escapeHtml(message) + '</p>' +
      '<div class="btn-row">' +
        '<button class="btn danger" id="confirmModalYes">' + escapeHtml(confirmLabel) + '</button>' +
        '<button class="btn ghost" id="confirmModalNo">Cancelar</button>' +
      '</div>';
    document.getElementById('modalBg').classList.add('active');
    document.getElementById('confirmModalYes').addEventListener('click', function () {
      document.getElementById('modalBg').classList.remove('active');
      onConfirm();
    });
    document.getElementById('confirmModalNo').addEventListener('click', function () {
      document.getElementById('modalBg').classList.remove('active');
    });
  }
  document.getElementById('modalClose').addEventListener('click', function () {
    document.getElementById('modalBg').classList.remove('active');
  });
  document.getElementById('modalBg').addEventListener('click', function (e) {
    if (e.target === document.getElementById('modalBg')) document.getElementById('modalBg').classList.remove('active');
  });

  // ---------- Tabs ----------
  var tabButtons = document.querySelectorAll('nav.tabs button');
  tabButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabButtons.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      document.querySelectorAll('.view').forEach(function (v) { v.classList.remove('active'); });
      document.getElementById('view-' + btn.dataset.view).classList.add('active');
      if (btn.dataset.view === 'lista') carregarAgendamentos();
      if (btn.dataset.view === 'tratativas') carregarTratativas();
    });
  });

  // ================= NOVO AGENDAMENTO =================
  var uploadedNotas = []; // { fileName, rawXml, numero, serie, ..., itens:[...], _danfeFile, _danfeFileName }

  var dropzone = document.getElementById('dropzone');
  var fileInput = document.getElementById('fileInput');
  dropzone.addEventListener('click', function () { fileInput.click(); });
  dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('drag'); });
  dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('drag'); });
  dropzone.addEventListener('drop', function (e) { e.preventDefault(); dropzone.classList.remove('drag'); handleFiles(e.dataTransfer.files); });
  fileInput.addEventListener('change', function (e) { handleFiles(e.target.files); fileInput.value = ''; });

  function handleFiles(fileList) {
    var files = Array.prototype.slice.call(fileList).filter(function (f) { return /\.xml$/i.test(f.name); });
    if (!files.length) { toast('Selecione arquivo(s) .xml da NF-e', 'error'); return; }
    var fd = new FormData();
    files.forEach(function (f) { fd.append('arquivos[]', f); });
    apiJson('api/parse_xml.php', { method: 'POST', body: fd }).then(function (data) {
      data.resultados.forEach(function (r) {
        if (r.erro) { toast(r.erro, 'error'); return; }
        var nota = r.nota;
        nota._danfeFile = null;
        nota._danfeFileName = null;
        uploadedNotas.push(nota);
        toast('Nota ' + (nota.numero || r.arquivo) + ' importada: ' + nota.itens.length + ' item(ns)', 'success');
      });
      renderNotas(); renderItens(); prefillFormFromNotas();
    }).catch(function (e) { toast(e.message || 'Erro ao ler XML', 'error'); });
  }

  function prefillFormFromNotas() {
    if (uploadedNotas.length && !document.getElementById('fFornecedor').value) {
      document.getElementById('fFornecedor').value = uploadedNotas[0].emitenteNome || '';
    }
    if (uploadedNotas.length && !document.getElementById('fCliente').value) {
      document.getElementById('fCliente').value = uploadedNotas[0].destinatarioNome || '';
    }
    var resumo = document.getElementById('resumoNota');
    if (uploadedNotas.length) {
      var totalItens = uploadedNotas.reduce(function (s, n) { return s + n.itens.length; }, 0);
      var totalValor = uploadedNotas.reduce(function (s, n) { return s + n.valorTotal; }, 0);
      resumo.textContent = uploadedNotas.length + ' nota(s) anexada(s) · ' + totalItens + ' item(ns) · Total R$ ' + fmtMoney(totalValor);
    } else {
      resumo.textContent = '';
    }
  }

  function renderNotas() {
    var c = document.getElementById('notasContainer');
    c.innerHTML = '';
    uploadedNotas.forEach(function (nota, idx) {
      var div = document.createElement('div');
      div.className = 'nota-card';
      var dataFmt = nota.dataEmissao ? fmtDateBR(nota.dataEmissao) : '—';
      var usaveis = nota.itens.filter(function (it) { return eanUsavel(it.ean, it.eanStatus); }).length;
      div.innerHTML =
        '<div class="nota-head">' +
          '<div class="meta">' +
            'NF <b>' + (nota.numero || '—') + '</b> · Série <b>' + (nota.serie || '—') + '</b><br>' +
            'Emitente: <b>' + escapeHtml(nota.emitenteNome || '—') + '</b><br>' +
            'Emissão: <b>' + dataFmt + '</b> · Chave: <b style="font-size:10.5px;">' + (nota.chave || '—') + '</b><br>' +
            'Itens: <b>' + nota.itens.length + '</b> (' + usaveis + ' com EAN aproveitável) · Arquivo: ' + escapeHtml(nota.fileName) +
          '</div>' +
          '<div style="text-align:right;">' +
            '<div class="valor">R$ ' + fmtMoney(nota.valorTotal) + '</div>' +
            '<div class="btn-row" style="justify-content:flex-end;margin-top:8px;">' +
              '<button class="remove-nota" data-idx="' + idx + '">remover nota</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="divider"></div>' +
        '<div class="danfe-row" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">' +
          (nota._danfeFileName
            ? ('<span class="badge ok">DANFE anexado</span>' +
               '<span style="font-size:12px;color:var(--text-dim);">' + escapeHtml(nota._danfeFileName) + '</span>' +
               '<button class="btn sm danger" data-rm-danfe="' + idx + '">remover DANFE</button>')
            : ('<div class="dropzone sm" data-danfe-drop-idx="' + idx + '">' +
                 '<div class="ico">📎</div>' +
                 '<div class="main">Clique ou arraste o DANFE (PDF) aqui</div>' +
                 '<div class="hint">opcional — enviado ao confirmar o agendamento</div>' +
                 '<input type="file" accept="application/pdf,.pdf" data-danfe-idx="' + idx + '" style="display:none;">' +
               '</div>')
          ) +
        '</div>';
      c.appendChild(div);
    });
    c.querySelectorAll('.remove-nota').forEach(function (btn) {
      btn.addEventListener('click', function () {
        uploadedNotas.splice(parseInt(btn.dataset.idx, 10), 1);
        renderNotas(); renderItens(); prefillFormFromNotas();
      });
    });
    c.querySelectorAll('[data-danfe-idx]').forEach(function (input) {
      input.addEventListener('change', function (e) {
        var idx = parseInt(input.dataset.danfeIdx, 10);
        var file = e.target.files[0];
        if (!file) return;
        attachDanfeLocal(idx, file);
      });
    });
    c.querySelectorAll('[data-danfe-drop-idx]').forEach(function (zone) {
      var idx = parseInt(zone.dataset.danfeDropIdx, 10);
      zone.addEventListener('click', function () { zone.querySelector('[data-danfe-idx]').click(); });
      zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('drag'); });
      zone.addEventListener('dragleave', function () { zone.classList.remove('drag'); });
      zone.addEventListener('drop', function (e) {
        e.preventDefault(); zone.classList.remove('drag');
        var file = e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) attachDanfeLocal(idx, file);
      });
    });
    c.querySelectorAll('[data-rm-danfe]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idx = parseInt(btn.dataset.rmDanfe, 10);
        uploadedNotas[idx]._danfeFile = null;
        uploadedNotas[idx]._danfeFileName = null;
        renderNotas();
      });
    });
  }

  function attachDanfeLocal(idx, file) {
    if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') { toast('Selecione um arquivo PDF', 'error'); return; }
    if (file.size > 20 * 1024 * 1024) { toast('PDF muito grande (limite de 20MB)', 'error'); return; }
    uploadedNotas[idx]._danfeFile = file;
    uploadedNotas[idx]._danfeFileName = file.name;
    renderNotas();
    toast('DANFE anexado à nota — será enviado ao confirmar o agendamento', 'success');
  }

  function allUploadedItens() {
    var arr = [];
    uploadedNotas.forEach(function (n) { arr = arr.concat(n.itens); });
    return arr;
  }

  function renderItens() {
    var itensCard = document.getElementById('itensCard');
    var body = document.getElementById('itensBody');
    var itens = allUploadedItens();
    body.innerHTML = '';
    if (!itens.length) { itensCard.style.display = 'none'; return; }
    itensCard.style.display = '';
    itens.forEach(function (it, idx) {
      var tr = document.createElement('tr');
      var matched = it.codigoCliente ? 'matched' : '';
      tr.innerHTML =
        '<td class="mono">' + escapeHtml(it.notaLabel) + '</td>' +
        '<td class="mono">' + escapeHtml(it.cProd) + '</td>' +
        '<td>' + escapeHtml(it.xProd) + '</td>' +
        '<td class="mono">' + (it.ean || '—') + '</td>' +
        '<td><span class="badge ' + it.eanCls + '">' + it.eanLabel + '</span></td>' +
        '<td><input class="mini-input ' + matched + '" data-idx="' + idx + '" value="' + escapeAttr(it.codigoCliente) + '" placeholder="código..."></td>' +
        '<td class="num">' + fmtNum(it.qCom) + ' ' + escapeHtml(it.uCom) + '</td>' +
        '<td class="num">R$ ' + fmtMoney(it.vProd) + '</td>';
      body.appendChild(tr);
    });
    body.querySelectorAll('.mini-input').forEach(function (inp) {
      inp.addEventListener('input', function () {
        var idx = parseInt(inp.dataset.idx, 10);
        var itens = allUploadedItens();
        itens[idx].codigoCliente = inp.value;
        inp.classList.toggle('matched', !!inp.value);
      });
    });
  }

  function resetForm() {
    uploadedNotas = [];
    ['fData', 'fHora', 'fCliente', 'fFornecedor', 'fTransportadora', 'fPlaca', 'fMotorista', 'fObs'].forEach(function (id) {
      document.getElementById(id).value = '';
    });
    renderNotas(); renderItens(); prefillFormFromNotas();
  }
  document.getElementById('btnLimpar').addEventListener('click', resetForm);

  document.getElementById('btnSalvar').addEventListener('click', function () {
    var data = document.getElementById('fData').value;
    var hora = document.getElementById('fHora').value;
    var cliente = document.getElementById('fCliente').value.trim();
    if (!data || !hora || !cliente) { toast('Preencha data, horário e cliente para confirmar o agendamento', 'error'); return; }

    var itens = allUploadedItens();
    var payload = {
      data: data, hora: hora, cliente: cliente,
      fornecedor: document.getElementById('fFornecedor').value.trim(),
      transportadora: document.getElementById('fTransportadora').value.trim(),
      placa: document.getElementById('fPlaca').value.trim().toUpperCase(),
      motorista: document.getElementById('fMotorista').value.trim(),
      obs: document.getElementById('fObs').value.trim(),
      notas: uploadedNotas.map(function (n) {
        return { numero: n.numero, serie: n.serie, dataEmissao: n.dataEmissao, chave: n.chave,
          emitenteNome: n.emitenteNome, emitenteCNPJ: n.emitenteCNPJ, valorTotal: n.valorTotal,
          volumes: n.volumes || 0, especieVol: n.especieVol || '', fileName: n.fileName, rawXml: n.rawXml };
      }),
      itens: itens,
    };

    var btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    postJson('api/salvar_agendamento.php', payload).then(function (res) {
      var uploads = [];
      uploadedNotas.forEach(function (n, i) {
        if (n._danfeFile && res.notaIds[i]) {
          var fd = new FormData();
          fd.append('nota_id', res.notaIds[i]);
          fd.append('arquivo', n._danfeFile);
          uploads.push(apiJson('api/upload_danfe.php', { method: 'POST', body: fd }).catch(function (e) {
            toast('Agendamento salvo, mas falhou ao anexar um DANFE: ' + e.message, 'error');
          }));
        }
      });
      Promise.all(uploads).then(function () {
        toast('Agendamento confirmado para ' + fmtDateBR(data) + ' às ' + hora, 'success');
        resetForm();
        document.querySelector('nav.tabs button[data-view="lista"]').click();
      });
    }).catch(function (e) {
      toast(e.message || 'Erro ao salvar agendamento', 'error');
    }).finally(function () { btn.disabled = false; });
  });

  refreshDatalists();
  function refreshDatalists() {
    apiJson('api/listar_agendamentos.php').then(function (data) {
      var clientes = Array.from(new Set(data.agendamentos.map(function (a) { return a.cliente; }).filter(Boolean)));
      var transp = Array.from(new Set(data.agendamentos.map(function (a) { return a.transportadora; }).filter(Boolean)));
      document.getElementById('clientesList').innerHTML = clientes.map(function (c) { return '<option value="' + escapeAttr(c) + '">'; }).join('');
      document.getElementById('transpList').innerHTML = transp.map(function (c) { return '<option value="' + escapeAttr(c) + '">'; }).join('');
      document.getElementById('countAgendamentos').textContent = data.agendamentos.length;
    });
  }

  // ================= LISTA DE AGENDAMENTOS =================
  function carregarAgendamentos() {
    var params = new URLSearchParams();
    var busca = document.getElementById('buscaAg').value.trim();
    var status = document.getElementById('filtroStatus').value;
    var dataF = document.getElementById('filtroData').value;
    if (busca) params.set('busca', busca);
    if (status) params.set('status', status);
    if (dataF) params.set('data', dataF);

    apiJson('api/listar_agendamentos.php?' + params.toString()).then(function (res) {
      renderLista(res.agendamentos);
    });
  }

  function renderLista(agendamentos) {
    var body = document.getElementById('listaBody');
    body.innerHTML = '';
    document.getElementById('listaEmpty').style.display = agendamentos.length ? 'none' : 'block';

    agendamentos.forEach(function (ag) {
      var tr = document.createElement('tr');
      var nfs = ag.notas.map(function (n) { return n.numero || '?'; }).join(', ') || '—';
      var temDanfe = ag.notas.some(function (n) { return !!n.temDanfe; });
      var temXml = ag.notas.some(function (n) { return !!n.temRawXml; });
      tr.innerHTML =
        '<td>' + fmtDateBR(ag.data) + '<br><span style="color:var(--text-faint);font-size:11px;">' + (ag.hora || '').slice(0, 5) + '</span></td>' +
        '<td>' + escapeHtml(ag.cliente) + '</td>' +
        '<td>' + escapeHtml(ag.fornecedor || '—') + '</td>' +
        '<td class="mono"><span class="nf-link" data-hist-id="' + ag.id + '" title="Ver histórico">' + escapeHtml(nfs) + '</span>' + (temDanfe ? ' 📎' : '') + '</td>' +
        '<td class="num">' + ag.itensCount + (ag.volumes ? ' <span class="badge neutral" title="Volumes declarados na nota">' + fmtNum(ag.volumes) + ' vol.</span>' : '') + '</td>' +
        '<td class="num">R$ ' + fmtMoney(ag.valorTotal) + '</td>' +
        '<td><select class="status-select" data-id="' + ag.id + '">' +
            Object.keys(STATUS_LABEL).map(function (s) { return '<option value="' + s + '"' + (s === ag.status ? ' selected' : '') + '>' + STATUS_LABEL[s] + '</option>'; }).join('') +
          '</select></td>' +
        '<td>' + (temXml ? ag.notas.filter(function (n) { return n.temRawXml; }).map(function (n) {
            return '<a class="btn sm" href="api/gerar_xml_agendamento.php?nota_id=' + n.id + '" target="_blank" rel="noopener">XML NF ' + escapeHtml(n.numero || '') + '</a>';
          }).join(' ') : '<span style="color:var(--text-faint);font-size:11px;">—</span>') + '</td>' +
        '<td>' + (temDanfe ? ag.notas.filter(function (n) { return n.temDanfe; }).map(function (n) {
            return '<a class="btn sm" href="api/baixar_danfe.php?nota_id=' + n.id + '" target="_blank" rel="noopener">PDF NF ' + escapeHtml(n.numero || '') + '</a>';
          }).join(' ') : '<span style="color:var(--text-faint);font-size:11px;">—</span>') + '</td>' +
        '<td>' +
          '<button class="btn sm ghost" data-view-id="' + ag.id + '">ver</button> ' +
          '<button class="btn sm danger" data-del-id="' + ag.id + '">excluir</button>' +
        '</td>';
      body.appendChild(tr);
    });

    body.querySelectorAll('.status-select').forEach(function (sel) {
      sel.addEventListener('change', function () {
        postJson('api/atualizar_status_agendamento.php', { id: sel.dataset.id, status: sel.value }).then(function () {
          toast('Status atualizado', 'success');
          carregarAgendamentos();
        }).catch(function (e) { toast(e.message, 'error'); carregarAgendamentos(); });
      });
    });
    body.querySelectorAll('[data-hist-id]').forEach(function (el) {
      el.addEventListener('click', function () { openDetail(el.dataset.histId); });
    });
    body.querySelectorAll('[data-view-id]').forEach(function (btn) {
      btn.addEventListener('click', function () { openDetail(btn.dataset.viewId); });
    });
    body.querySelectorAll('[data-del-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var delId = btn.dataset.delId;
        showConfirmModal('Excluir agendamento', 'Essa ação não pode ser desfeita. Deseja realmente excluir este agendamento?', 'Excluir', function () {
          postJson('api/excluir_agendamento.php', { id: delId }).then(function () {
            toast('Agendamento excluído', 'success');
            carregarAgendamentos();
          }).catch(function (e) { toast(e.message, 'error'); });
        });
      });
    });

    document.getElementById('kpiTotal').textContent = agendamentos.length;
    document.getElementById('kpiPendentes').textContent = agendamentos.filter(function (a) { return a.status === 'pendente'; }).length;
    document.getElementById('kpiConfirmados').textContent = agendamentos.filter(function (a) { return a.status === 'confirmado'; }).length;
    document.getElementById('kpiRecebidos').textContent = agendamentos.filter(function (a) { return a.status === 'recebido'; }).length;
  }

  ['buscaAg', 'filtroStatus', 'filtroData'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', carregarAgendamentos);
    document.getElementById(id).addEventListener('change', carregarAgendamentos);
  });
  document.getElementById('btnLimparFiltros').addEventListener('click', function () {
    document.getElementById('buscaAg').value = '';
    document.getElementById('filtroStatus').value = '';
    document.getElementById('filtroData').value = '';
    carregarAgendamentos();
  });

  function openDetail(id) {
    apiJson('api/detalhe_agendamento.php?id=' + id).then(function (res) {
      var ag = res.agendamento;
      var itensRows = ag.itens.map(function (it) {
        return '<tr>' +
          '<td class="mono">' + escapeHtml(it.nota_label) + '</td>' +
          '<td class="mono">' + escapeHtml(it.cprod) + '</td>' +
          '<td>' + escapeHtml(it.xprod) + '</td>' +
          '<td class="mono">' + (it.ean || '—') + '</td>' +
          '<td><span class="badge ' + (it.ean_status === 'ok' ? 'ok' : (it.ean_status === 'sem_gtin' ? 'neutral' : 'warn')) + '">' + it.ean_label + '</span></td>' +
          '<td class="mono">' + escapeHtml(it.codigo_cliente || '—') + '</td>' +
          '<td class="num">' + fmtNum(it.qcom) + ' ' + escapeHtml(it.ucom) + '</td>' +
          '<td class="num">R$ ' + fmtMoney(it.vprod) + '</td>' +
        '</tr>';
      }).join('');
      var notasInfo = ag.notas.map(function (n) {
        var danfeLink = n.tem_danfe ? (' · <a href="api/baixar_danfe.php?nota_id=' + n.id + '" target="_blank" rel="noopener" style="font-weight:600;">baixar DANFE</a>') : '';
        var xmlLink = n.tem_raw_xml ? (' · <a href="api/gerar_xml_agendamento.php?nota_id=' + n.id + '" target="_blank" rel="noopener" style="font-weight:600;">gerar XML (código=EAN)</a>') : '';
        return '<div class="footnote">NF <b style="color:var(--text)">' + (n.numero || '—') + '</b> · ' + escapeHtml(n.emitente_nome || '—') + ' · R$ ' + fmtMoney(n.valor_total) + ' · Chave: ' + (n.chave || '—') + danfeLink + xmlLink + '</div>';
      }).join('');
      var histRows = (ag.historico || []).map(function (h) {
        var d = new Date(h.data.replace(' ', 'T'));
        var dataFmt = isNaN(d.getTime()) ? '—' : (d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR'));
        return '<tr><td class="mono">' + dataFmt + '</td><td>' + escapeHtml(h.descricao) + '</td><td>' + escapeHtml(h.usuario) + '</td></tr>';
      }).join('');

      document.getElementById('modalContent').innerHTML =
        '<h2 style="margin-top:0;">Agendamento — ' + escapeHtml(ag.cliente) + '</h2>' +
        '<div class="pill-summary" style="margin-bottom:14px;">' +
          '<span class="badge ' + STATUS_CLS[ag.status] + '">' + STATUS_LABEL[ag.status] + '</span>' +
          '<span class="badge neutral">' + fmtDateBR(ag.data) + ' · ' + ag.hora.slice(0, 5) + '</span>' +
          '<span class="badge neutral">' + ag.itens.length + ' item(ns)</span>' +
          '<span class="badge neutral">R$ ' + fmtMoney(ag.valor_total) + '</span>' +
        '</div>' +
        '<div class="row2" style="margin-bottom:14px;">' +
          '<div class="footnote"><b style="color:var(--text)">Fornecedor:</b> ' + escapeHtml(ag.fornecedor || '—') + '</div>' +
          '<div class="footnote"><b style="color:var(--text)">Transportadora:</b> ' + escapeHtml(ag.transportadora || '—') + ' ' + (ag.placa ? ('· Placa ' + escapeHtml(ag.placa)) : '') + '</div>' +
        '</div>' +
        (ag.motorista ? '<div class="footnote"><b style="color:var(--text)">Motorista:</b> ' + escapeHtml(ag.motorista) + '</div>' : '') +
        (ag.obs ? '<div class="footnote"><b style="color:var(--text)">Obs:</b> ' + escapeHtml(ag.obs) + '</div>' : '') +
        '<div class="divider"></div>' +
        '<h3>Notas anexadas</h3>' + (notasInfo || '<div class="footnote">Nenhuma nota anexada.</div>') +
        '<div class="divider"></div>' +
        '<h3>Itens</h3>' +
        '<div class="tbl-wrap"><table><thead><tr><th>NF</th><th>Cód. Fornecedor</th><th>Descrição</th><th>EAN</th><th>Status</th><th>Cód. Cliente</th><th class="num">Qtd</th><th class="num">Vlr Total</th></tr></thead>' +
        '<tbody>' + (itensRows || '<tr><td colspan="8" class="footnote">Nenhum item.</td></tr>') + '</tbody></table></div>' +
        '<div class="divider"></div>' +
        '<h3>Histórico</h3>' +
        '<div class="tbl-wrap"><table><thead><tr><th>Data / Hora</th><th>Descrição</th><th>Usuário</th></tr></thead>' +
        '<tbody>' + (histRows || '<tr><td colspan="3" class="footnote">Nenhum evento registrado.</td></tr>') + '</tbody></table></div>';

      document.getElementById('modalBg').classList.add('active');
    }).catch(function (e) { toast(e.message, 'error'); });
  }

  // ---------- Export / Import (Agendamentos) ----------
  document.getElementById('btnExportJson').addEventListener('click', function () { triggerDownload('api/backup_json.php?tipo=agendamentos'); });
  document.getElementById('btnExportCsv').addEventListener('click', function () { triggerDownload('api/exportar.php?tipo=agendamentos&formato=csv'); });
  document.getElementById('btnExportXlsx').addEventListener('click', function () { triggerDownload('api/exportar.php?tipo=agendamentos&formato=xlsx'); });
  document.getElementById('btnImportJson').addEventListener('click', function () { document.getElementById('importInput').click(); });
  document.getElementById('importInput').addEventListener('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    var fd = new FormData();
    fd.append('arquivo', file);
    apiJson('api/importar_backup.php', { method: 'POST', body: fd }).then(function (res) {
      toast(res.importados + ' registro(s) importado(s) com sucesso', 'success');
      carregarAgendamentos(); refreshDatalists();
    }).catch(function (e) { toast(e.message || 'Arquivo de backup inválido', 'error'); });
    e.target.value = '';
  });

  // ================= TRATATIVAS: OUTLET / LEILÃO =================
  function setupTratativaDropzone(tipo, dzId, inputId) {
    var dz = document.getElementById(dzId);
    var input = document.getElementById(inputId);
    dz.addEventListener('click', function () { input.click(); });
    dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('drag'); });
    dz.addEventListener('dragleave', function () { dz.classList.remove('drag'); });
    dz.addEventListener('drop', function (e) { e.preventDefault(); dz.classList.remove('drag'); handleTratativaFiles(tipo, e.dataTransfer.files); });
    input.addEventListener('change', function (e) { handleTratativaFiles(tipo, e.target.files); input.value = ''; });
  }
  function handleTratativaFiles(tipo, fileList) {
    var files = Array.prototype.slice.call(fileList).filter(function (f) { return /\.xml$/i.test(f.name); });
    if (!files.length) { toast('Selecione arquivo(s) .xml da NF-e', 'error'); return; }
    var fd = new FormData();
    fd.append('tipo', tipo);
    files.forEach(function (f) { fd.append('arquivos[]', f); });
    apiJson('api/upload_tratativa.php', { method: 'POST', body: fd }).then(function (data) {
      var delay = 0;
      data.resultados.forEach(function (r) {
        if (r.erro) { toast(r.erro, 'error'); return; }
        toast(r.replaced + ' código(s) substituído(s) pelo EAN' + (r.skipped ? ' · ' + r.skipped + ' item(ns) sem EAN válido mantiveram o código original' : ''), 'success');
        (function (url) { setTimeout(function () { triggerDownload(url); }, delay); })(r.downloadUrl);
        delay += 400;
      });
      carregarTratativas();
    }).catch(function (e) { toast(e.message || 'Erro ao processar XML', 'error'); });
  }
  setupTratativaDropzone('outlet', 'dropzoneOutlet', 'fileInputOutlet');
  setupTratativaDropzone('leilao', 'dropzoneLeilao', 'fileInputLeilao');

  function carregarTratativas() {
    var params = new URLSearchParams();
    var busca = document.getElementById('buscaTrat').value.trim();
    var tipo = document.getElementById('filtroTipoTrat').value;
    var status = document.getElementById('filtroStatusTrat').value;
    var dataF = document.getElementById('filtroDataTrat').value;
    if (busca) params.set('busca', busca);
    if (tipo) params.set('tipo', tipo);
    if (status) params.set('status', status);
    if (dataF) params.set('data', dataF);
    apiJson('api/listar_tratativas.php?' + params.toString()).then(function (res) { renderTratativasLista(res.tratativas); });
  }

  function renderTratativasLista(tratativas) {
    var body = document.getElementById('tratativasBody');
    body.innerHTML = '';
    document.getElementById('tratativasEmpty').style.display = tratativas.length ? 'none' : 'block';

    tratativas.forEach(function (t) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + fmtDateBR(t.data) + '<br><span style="color:var(--text-faint);font-size:11px;">' + (t.hora || '').slice(0, 5) + '</span></td>' +
        '<td><b>' + TRATATIVA_TIPO_LABEL[t.tipo] + '</b></td>' +
        '<td>' + escapeHtml(t.cliente || '—') + '</td>' +
        '<td>' + escapeHtml(t.fornecedor || '—') + '</td>' +
        '<td class="mono">' + escapeHtml(t.numero || t.file_name || '—') + '</td>' +
        '<td class="num">' + t.itensCount + (t.volumes ? ' <span class="badge neutral" title="Volumes declarados na nota">' + fmtNum(t.volumes) + ' vol.</span>' : '') + '</td>' +
        '<td class="num">R$ ' + fmtMoney(t.valorTotal) + '</td>' +
        '<td><select class="status-select" data-trat-id="' + t.id + '">' +
            Object.keys(STATUS_LABEL).map(function (s) { return '<option value="' + s + '"' + (s === t.status ? ' selected' : '') + '>' + STATUS_LABEL[s] + '</option>'; }).join('') +
          '</select></td>' +
        '<td><a class="btn sm" href="api/gerar_xml_tratativa.php?id=' + t.id + '" target="_blank" rel="noopener">baixar novamente</a></td>' +
        '<td>' +
          '<button class="btn sm ghost" data-trat-view="' + t.id + '">ver</button> ' +
          '<button class="btn sm danger" data-trat-del="' + t.id + '">excluir</button>' +
        '</td>';
      body.appendChild(tr);
    });

    body.querySelectorAll('.status-select[data-trat-id]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        postJson('api/atualizar_status_tratativa.php', { id: sel.dataset.tratId, status: sel.value }).then(function () {
          toast('Status atualizado', 'success');
          carregarTratativas();
        }).catch(function (e) { toast(e.message, 'error'); carregarTratativas(); });
      });
    });
    body.querySelectorAll('[data-trat-view]').forEach(function (btn) {
      btn.addEventListener('click', function () { openTratativaDetail(btn.dataset.tratView); });
    });
    body.querySelectorAll('[data-trat-del]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var delId = btn.dataset.tratDel;
        showConfirmModal('Excluir tratativa', 'Essa ação não pode ser desfeita. Deseja realmente excluir este registro?', 'Excluir', function () {
          postJson('api/excluir_tratativa.php', { id: delId }).then(function () {
            toast('Registro excluído', 'success');
            carregarTratativas();
          }).catch(function (e) { toast(e.message, 'error'); });
        });
      });
    });

    document.getElementById('kpiTratTotal').textContent = tratativas.length;
    document.getElementById('kpiTratPendentes').textContent = tratativas.filter(function (t) { return t.status === 'pendente'; }).length;
    document.getElementById('kpiTratConfirmados').textContent = tratativas.filter(function (t) { return t.status === 'confirmado'; }).length;
    document.getElementById('kpiTratRecebidos').textContent = tratativas.filter(function (t) { return t.status === 'recebido'; }).length;
  }

  ['buscaTrat', 'filtroTipoTrat', 'filtroStatusTrat', 'filtroDataTrat'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', carregarTratativas);
    document.getElementById(id).addEventListener('change', carregarTratativas);
  });
  document.getElementById('btnLimparFiltrosTrat').addEventListener('click', function () {
    document.getElementById('buscaTrat').value = '';
    document.getElementById('filtroTipoTrat').value = '';
    document.getElementById('filtroStatusTrat').value = '';
    document.getElementById('filtroDataTrat').value = '';
    carregarTratativas();
  });

  function openTratativaDetail(id) {
    apiJson('api/detalhe_tratativa.php?id=' + id).then(function (res) {
      var t = res.tratativa;
      var itensRows = (t.itens || []).map(function (it) {
        var usavel = eanUsavel(it.ean, it.eanStatus);
        var codigoGerado = (usavel ? it.ean : it.cProd) + '-' + t.suffix;
        return '<tr>' +
          '<td class="mono">' + escapeHtml(it.cProd) + '</td>' +
          '<td>' + escapeHtml(it.xProd) + '</td>' +
          '<td class="mono">' + (it.ean || '—') + '</td>' +
          '<td><span class="badge ' + (it.eanStatus === 'ok' ? 'ok' : (it.eanStatus === 'sem_gtin' ? 'neutral' : 'warn')) + '">' + it.eanLabel + '</span></td>' +
          '<td class="mono">' + escapeHtml(codigoGerado) + '</td>' +
          '<td class="num">' + fmtNum(it.qCom) + ' ' + escapeHtml(it.uCom) + '</td>' +
          '<td class="num">R$ ' + fmtMoney(it.vProd) + '</td>' +
        '</tr>';
      }).join('');

      document.getElementById('modalContent').innerHTML =
        '<h2 style="margin-top:0;">' + TRATATIVA_TIPO_LABEL[t.tipo] + ' — ' + escapeHtml(t.cliente || '—') + '</h2>' +
        '<div class="pill-summary" style="margin-bottom:14px;">' +
          '<span class="badge ' + STATUS_CLS[t.status] + '">' + STATUS_LABEL[t.status] + '</span>' +
          '<span class="badge neutral">' + fmtDateBR(t.data) + ' · ' + t.hora.slice(0, 5) + '</span>' +
          '<span class="badge neutral">' + (t.itens ? t.itens.length : 0) + ' item(ns)</span>' +
          '<span class="badge neutral">R$ ' + fmtMoney(t.valor_total) + '</span>' +
        '</div>' +
        '<div class="row2" style="margin-bottom:14px;">' +
          '<div class="footnote"><b style="color:var(--text)">Fornecedor:</b> ' + escapeHtml(t.fornecedor || '—') + '</div>' +
          '<div class="footnote"><b style="color:var(--text)">NF:</b> ' + escapeHtml(t.numero || '—') + ' · Série ' + escapeHtml(t.serie || '—') + '</div>' +
        '</div>' +
        '<div class="divider"></div>' +
        '<h3>Itens (código gerado com sufixo -' + t.suffix + ')</h3>' +
        '<div class="tbl-wrap"><table><thead><tr><th>Cód. Original</th><th>Descrição</th><th>EAN</th><th>Status</th><th>Código Gerado</th><th class="num">Qtd</th><th class="num">Vlr Total</th></tr></thead>' +
        '<tbody>' + (itensRows || '<tr><td colspan="7" class="footnote">Nenhum item.</td></tr>') + '</tbody></table></div>';

      document.getElementById('modalBg').classList.add('active');
    }).catch(function (e) { toast(e.message, 'error'); });
  }

  document.getElementById('btnExportJsonTrat').addEventListener('click', function () { triggerDownload('api/backup_json.php?tipo=tratativas'); });
  document.getElementById('btnExportXlsxTrat').addEventListener('click', function () { triggerDownload('api/exportar.php?tipo=tratativas&formato=xlsx'); });
  document.getElementById('btnImportJsonTrat').addEventListener('click', function () { document.getElementById('importInputTrat').click(); });
  document.getElementById('importInputTrat').addEventListener('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    var fd = new FormData();
    fd.append('arquivo', file);
    apiJson('api/importar_backup.php', { method: 'POST', body: fd }).then(function (res) {
      toast(res.importados + ' registro(s) importado(s) com sucesso', 'success');
      carregarTratativas();
    }).catch(function (e) { toast(e.message || 'Arquivo de backup inválido', 'error'); });
    e.target.value = '';
  });

  // ---------- Init ----------
  var hoje = new Date();
  document.getElementById('fData').value = hoje.toISOString().slice(0, 10);
  renderNotas(); renderItens();
})();
