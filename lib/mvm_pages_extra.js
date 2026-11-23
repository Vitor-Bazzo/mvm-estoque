const { esc, renderLayout } = require('./mvm_layout');
const { saveDb, addDaysDateTimeStr, formatDateBR, formatDateTimeBR, formatCurrencyBR, calcularCicloVidaProdutos } = require('./mvm_db');

async function handleMovimentos(req, body, query, db, session, subRoute) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const clientes = db.clientes.filter(c => Number(c.idUsuario) === uid);
  const forns = db.fornecedores.filter(f => Number(f.idUsuario) === uid);

  if (subRoute === 'movimento_excluir.php') {
    const id = Number(body.id || query.id || 0);
    const mov = db.movimentos.find(m => m.idMovimento === id && Number(m.idUsuario) === uid);
    if (mov) {
      const prod = prods.find(p => p.idProduto === Number(mov.idProduto));
      if (prod && !mov.dataDevolucao) {
        if (mov.tipoMovimento === 'SAIDA') prod.quantidade += Number(mov.quantidade);
        else if (mov.tipoMovimento === 'ENTRADA') prod.quantidade = Math.max(0, prod.quantidade - Number(mov.quantidade));
      }
      db.movimentos = db.movimentos.filter(m => m.idMovimento !== id);
      await saveDb(db);
      session.mensagem_movimento = ['ok', 'Movimentação excluída e saldo de estoque conciliado com sucesso.'];
    }
    return { redirect: '/modulos/movimentos/movimento.php' };
  }

  if (subRoute === 'movimento_devolucao.php') {
    const id = Number(body.id || query.id || 0);
    const mov = db.movimentos.find(m => m.idMovimento === id && Number(m.idUsuario) === uid);
    if (mov && !mov.dataDevolucao) {
      mov.dataDevolucao = addDaysDateTimeStr(0);
      const prod = prods.find(p => p.idProduto === Number(mov.idProduto));
      if (prod && mov.tipoMovimento === 'SAIDA') {
        prod.quantidade += Number(mov.quantidade);
      }
      await saveDb(db);
      session.animacao_caminhao_estoque = { produto: prod ? prod.nomeProduto : 'Produto', quantidade: mov.quantidade };
      session.mensagem_movimento = ['ok', 'Devolução/estorno registrado e estoque recomposto com sucesso!'];
    }
    return { redirect: '/modulos/movimentos/movimento.php' };
  }

  if (subRoute === 'recibo.php') {
    const id = Number(query.id || 0);
    const pedido = String(query.pedido || '');
    const modoInicial = query.modo === 'picking' ? 'picking' : 'ticket';
    let itensMov = [];
    if (pedido) {
      itensMov = db.movimentos.filter(m => m.codigoPedido === pedido && Number(m.idUsuario) === uid);
    } else if (id > 0) {
      const mInd = db.movimentos.find(m => m.idMovimento === id && Number(m.idUsuario) === uid);
      if (mInd) {
        itensMov = mInd.codigoPedido
          ? db.movimentos.filter(m => m.codigoPedido === mInd.codigoPedido && Number(m.idUsuario) === uid)
          : [mInd];
      }
    }
    if (itensMov.length === 0) {
      return {
        statusCode: 404,
        html: '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Recibo não encontrado</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Registro de movimentação não encontrado ou sem permissão de acesso.</h2><a href="/modulos/movimentos/movimento.php">Voltar para Movimentações</a></body></html>'
      };
    }
    const primeiro = itensMov[0];
    const cli = db.clientes.find(c => c.idCliente === Number(primeiro.idCliente));
    const forn = db.fornecedores.find(f => f.idFornecedor === Number(primeiro.idFornecedor));
    const docId = primeiro.codigoPedido || ('MOV-#' + String(primeiro.idMovimento).padStart(6, '0'));
    const itensDetalhados = itensMov.map(m => {
      const p = db.produtos.find(x => x.idProduto === Number(m.idProduto)) || { nomeProduto: 'Produto MVM', preco: 0, localizacao: 'A-01-01' };
      return { ...m, nomeProduto: p.nomeProduto, preco: Number(m.precoUnitario || p.preco), localizacao: p.localizacao || 'A-01-01' };
    }).sort((a, b) => String(a.localizacao).localeCompare(String(b.localizacao)));
    const totalGeral = itensDetalhados.reduce((s, it) => s + (Number(it.quantidade) * Number(it.preco)), 0);
    const totalPecas = itensDetalhados.reduce((s, it) => s + Number(it.quantidade), 0);

    const htmlRecibo = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>${modoInicial === 'picking' ? 'Ordem de Separação (Picking List)' : 'Comprovante'} ${esc(docId)} - MVM</title><style>body{font-family:'JetBrains Mono',monospace;background:#f1f5f9;padding:30px;color:#0f172a;} .ticket{max-width:480px;margin:0 auto;background:#fff;padding:26px;border:1.5px dashed #334155;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,0.08);} table{width:100%;border-collapse:collapse;margin:12px 0;font-size:12px;} th,td{padding:6px 4px;border-bottom:1px solid #e2e8f0;text-align:left;} @media print{.no-print{display:none!important;}}</style></head><body><div class="ticket"><div style="text-align:center;"><img src="/img/mascote_mvm.svg" style="width:48px;height:48px;border-radius:50%;margin-bottom:8px;"><h2 style="margin:0 0 4px;color:${modoInicial === 'picking' ? '#2563eb' : '#00704A'};">${modoInicial === 'picking' ? '📋 ORDEM DE SEPARAÇÃO (WMS)' : '🧾 COMPROVANTE MVM ESTOQUE'}</h2><p style="margin:0 0 14px;font-size:12px;color:#475569;">Documento: <strong>${esc(docId)}</strong> &bull; ${esc(formatDateTimeBR(primeiro.dataMovimento))}</p></div><hr><p style="font-size:12px;line-height:1.6;"><strong>Operação:</strong> ${esc(primeiro.tipoMovimento)}<br><strong>${cli ? 'Cliente' : (forn ? 'Fornecedor' : 'Destino')}:</strong> ${esc(cli ? cli.nomeCliente : (forn ? forn.nomeFornecedor : 'Consumidor Balcão'))}</p><table><thead><tr>${modoInicial === 'picking' ? '<th>[ ]</th><th>WMS</th>' : ''}<th>Item</th><th>Qtd</th><th style="text-align:right;">Subtotal</th></tr></thead><tbody>${itensDetalhados.map(it => `<tr>${modoInicial === 'picking' ? `<td>☐</td><td><strong>📍 ${esc(it.localizacao)}</strong></td>` : ''}<td>${esc(it.nomeProduto)}${it.lote ? `<br><small>Lote: ${esc(it.lote)}</small>` : ''}</td><td>${it.quantidade} un</td><td style="text-align:right;">R$ ${formatCurrencyBR(it.quantidade * it.preco)}</td></tr>`).join('')}</tbody></table><hr><div style="display:flex;justify-content:space-between;font-size:14px;font-weight:800;margin-top:10px;"><span>Total (${totalPecas} un):</span><span style="color:#00704A;">R$ ${formatCurrencyBR(totalGeral)}</span></div><div class="no-print" style="text-align:center;margin-top:18px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;"><a href="/modulos/movimentos/recibo.php?pedido=${encodeURIComponent(docId)}&modo=${modoInicial === 'picking' ? 'ticket' : 'picking'}" style="padding:8px 14px;background:#e2e8f0;color:#0f172a;border-radius:6px;text-decoration:none;font-size:12px;font-weight:700;">${modoInicial === 'picking' ? '🧾 Ver Recibo' : '📋 Modo Picking List'}</a><button onclick="window.print()" style="padding:9px 18px;background:#00704A;color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;">🖨️ Imprimir</button><a href="/modulos/movimentos/movimento.php" style="padding:9px 14px;color:#334155;text-decoration:none;font-weight:700;">Voltar</a></div></div></body></html>`;
    return { html: htmlRecibo };
  }

  if (req.method === 'POST') {
    const acao = String(body.acao || 'registrar');
    const dtMov = addDaysDateTimeStr(0);
    const ymd = dtMov.slice(0, 10).replace(/-/g, '');

    if (acao === 'carrinho_saida') {
      let itens = [];
      try { itens = JSON.parse(body.itens_carrinho_json || '[]'); } catch (e) { itens = []; }
      const idCliente = body.idClienteCarrinho ? Number(body.idClienteCarrinho) : null;
      const obs = String(body.observacaoCarrinho || 'Venda via Carrinho PDV').trim();
      let maxId = db.movimentos.reduce((m, x) => Math.max(m, x.idMovimento), 0);
      const codPedido = `PED-${ymd}-${String(maxId + 1).padStart(4, '0')}`;
      let totalGeral = 0;

      itens.forEach(it => {
        const prod = prods.find(p => p.idProduto === Number(it.idProduto));
        const qtd = Number(it.quantidade || 0);
        if (prod && qtd > 0 && prod.quantidade >= qtd) {
          prod.quantidade -= qtd;
          const valTot = Number((qtd * prod.preco).toFixed(2));
          totalGeral += valTot;
          db.movimentos.unshift({
            idMovimento: ++maxId,
            idUsuario: uid,
            codigoPedido: codPedido,
            idProduto: prod.idProduto,
            idCliente,
            idFornecedor: null,
            tipoMovimento: 'SAIDA',
            quantidade: qtd,
            observacao: obs,
            lote: prod.lote,
            dataValidade: prod.dataValidade,
            motivoAjuste: null,
            precoUnitario: prod.preco,
            custoUnitario: prod.precoCusto,
            valorTotal: valTot,
            dataMovimento: dtMov,
            dataDevolucao: null
          });
        }
      });
      await saveDb(db);
      session.animacao_aviao_venda = { produto: `${itens.length} item(ns) no pedido ${codPedido}`, quantidade: itens.length, total: totalGeral };
      session.mensagem_movimento = ['ok', `Pedido <strong>${codPedido}</strong> finalizado com sucesso! Total: R$ ${formatCurrencyBR(totalGeral)}.`];
      return { redirect: '/modulos/movimentos/movimento.php' };
    }

    if (acao === 'ajuste_inventario') {
      const idProd = Number(body.idProdutoAjuste || 0);
      const fisico = Math.max(0, Number(body.estoqueFisicoReal ?? 0));
      const motivo = String(body.motivoAjuste || 'Contagem de Inventário').trim();
      const obsExtra = String(body.observacaoAjuste || '').trim();
      const prod = prods.find(p => p.idProduto === idProd);
      if (prod) {
        const diff = fisico - prod.quantidade;
        if (diff !== 0) {
          const tipoMov = diff > 0 ? 'ENTRADA' : 'SAIDA';
          const qtdAjuste = Math.abs(diff);
          const novoId = db.movimentos.reduce((m, x) => Math.max(m, x.idMovimento), 0) + 1;
          const codAjuste = `INV-${ymd}-${String(novoId).padStart(4, '0')}`;
          prod.quantidade = fisico;
          db.movimentos.unshift({
            idMovimento: novoId,
            idUsuario: uid,
            codigoPedido: codAjuste,
            idProduto: prod.idProduto,
            idCliente: null,
            idFornecedor: null,
            tipoMovimento: tipoMov,
            quantidade: qtdAjuste,
            observacao: `Ajuste (${motivo})${obsExtra ? ': ' + obsExtra : ''}`,
            lote: prod.lote,
            dataValidade: prod.dataValidade,
            motivoAjuste: motivo,
            precoUnitario: prod.preco,
            custoUnitario: prod.precoCusto,
            valorTotal: Number((qtdAjuste * prod.preco).toFixed(2)),
            dataMovimento: dtMov,
            dataDevolucao: null
          });
          await saveDb(db);
          session.mensagem_movimento = ['ok', `Ajuste de inventário <strong>${codAjuste}</strong> aplicado! Novo saldo de "${esc(prod.nomeProduto)}": <strong>${fisico} un</strong>.`];
        }
      }
      return { redirect: '/modulos/movimentos/movimento.php' };
    }

    // Movimento rápido (aba 2)
    const idProd = Number(body.idProduto || 0);
    const tipo = String(body.tipoMovimento || 'SAIDA').toUpperCase();
    const qtd = Number(body.quantidade || 0);
    const prod = prods.find(p => p.idProduto === idProd);
    if (!prod || qtd <= 0) {
      session.mensagem_movimento = ['erro', 'Selecione um produto e quantidade válida.'];
    } else if (tipo === 'SAIDA' && prod.quantidade < qtd) {
      session.mensagem_movimento = ['erro', `Estoque insuficiente para "${prod.nomeProduto}". Disponível: ${prod.quantidade} un.`];
    } else {
      prod.quantidade = tipo === 'SAIDA' ? prod.quantidade - qtd : prod.quantidade + qtd;
      const novoId = db.movimentos.reduce((m, x) => Math.max(m, x.idMovimento), 0) + 1;
      const valTot = Number((qtd * prod.preco).toFixed(2));
      db.movimentos.unshift({
        idMovimento: novoId,
        idUsuario: uid,
        codigoPedido: `PED-${ymd}-${String(novoId).padStart(4, '0')}`,
        idProduto: prod.idProduto,
        idCliente: body.idCliente ? Number(body.idCliente) : null,
        idFornecedor: body.idFornecedor ? Number(body.idFornecedor) : null,
        tipoMovimento: tipo,
        quantidade: qtd,
        observacao: String(body.observacao || '').trim(),
        lote: body.lote || prod.lote,
        dataValidade: body.dataValidade || prod.dataValidade,
        motivoAjuste: null,
        precoUnitario: prod.preco,
        custoUnitario: prod.precoCusto,
        valorTotal: valTot,
        dataMovimento: dtMov,
        dataDevolucao: null
      });
      await saveDb(db);
      if (tipo === 'SAIDA') {
        session.animacao_aviao_venda = { produto: prod.nomeProduto, quantidade: qtd, total: valTot };
      }
      session.mensagem_movimento = ['ok', `Movimentação registrada com sucesso! Saldo atualizado para ${prod.quantidade} un.`];
    }
    return { redirect: '/modulos/movimentos/movimento.php' };
  }

  const buscaInicialMov = String(query.busca || '').trim();
  const flashMov = session.mensagem_movimento;
  delete session.mensagem_movimento;

  const movs = db.movimentos
    .filter(m => Number(m.idUsuario) === uid)
    .slice()
    .sort((a, b) => String(b.dataMovimento).localeCompare(String(a.dataMovimento)) || b.idMovimento - a.idMovimento);

  const html = `
<div class="page-container">
    ${flashMov ? `
        <div class="alert-box ${flashMov[0] === 'ok' ? 'success' : 'error'}">
            <span>${flashMov[0] === 'ok' ? '✅' : '⚠️'}</span>
            <p>${flashMov[1]}</p>
        </div>
    ` : ''}

    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Vendas &amp; Movimentações de Estoque</h1>
            <p>Operações de frente de estoque, vendas com carrinho e entradas de reposição por lote</p>
        </div>
        <div class="page-header-actions">
            <button type="button" class="btn-secondary btn-toggle-animations is-active" title="Ativar ou desativar animações">
                <span class="anim-icon">✨</span>
                <span class="anim-toggle-text">Animações: <strong class="anim-status-label">Ativas</strong></span>
                <span class="anim-switch"><span class="anim-knob"></span></span>
            </button>
            <button type="button" class="btn-primary" data-open-modal="modal-novo-movimento" style="display: inline-flex; align-items: center; gap: 8px;">
                <span>➕</span> Nova Operação / Carrinho
            </button>
        </div>
    </div>

    <div class="table-filter-bar" style="flex-wrap: wrap; gap: 12px;">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" id="input-busca-movimentos" placeholder="Filtrar por produto, pedido, cliente, lote..." data-table-search="#tabela-movimentos" value="${esc(buscaInicialMov)}">
        </div>
        <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
            <button type="button" class="pbi-pill-btn active" onclick="filtrarTipoMovimento('TODOS', this)">Todos</button>
            <button type="button" class="pbi-pill-btn" onclick="filtrarTipoMovimento('SAIDA', this)">🛒 Vendas / Saídas</button>
            <button type="button" class="pbi-pill-btn" onclick="filtrarTipoMovimento('ENTRADA', this)">📥 Entradas</button>
            <button type="button" class="pbi-pill-btn" onclick="filtrarTipoMovimento('AJUSTE', this)">⚖️ Ajustes</button>
            <button type="button" class="pbi-pill-btn" onclick="filtrarTipoMovimento('ESTORNADO', this)">↩️ Estornados</button>
        </div>
        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;">${movs.length}</span> registros
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-movimentos">
                <thead>
                    <tr>
                        <th style="width: 14%;">Data / Hora</th>
                        <th style="width: 13%;">Tipo / Pedido</th>
                        <th style="width: 25%;">Produto</th>
                        <th style="width: 17%;">Cliente / Fornecedor</th>
                        <th style="width: 9%;">Qtd</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 12%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${movs.map(m => {
                      const prod = prods.find(p => p.idProduto === Number(m.idProduto)) || { nomeProduto: 'Produto #' + m.idProduto, localizacao: 'A-01-01' };
                      const cli = clientes.find(c => c.idCliente === Number(m.idCliente));
                      const forn = forns.find(f => f.idFornecedor === Number(m.idFornecedor));
                      const isSaida = m.tipoMovimento === 'SAIDA';
                      const isDevolvido = Boolean(m.dataDevolucao);
                      const isAjuste = Boolean(m.motivoAjuste);
                      const tipoClass = isSaida ? 'info' : 'success';
                      const tipoTexto = isSaida ? 'Saída' : 'Entrada';
                      const descMov = `${prod.nomeProduto} (${m.quantidade} un - ${tipoTexto})`;
                      return `
                        <tr data-tipo-mov="${esc(m.tipoMovimento)}" data-ajuste="${isAjuste ? '1' : '0'}" data-estornado="${isDevolvido ? '1' : '0'}">
                            <td><span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-muted);">${esc(formatDateTimeBR(m.dataMovimento))}</span></td>
                            <td>
                                <span class="status-pill ${tipoClass}">${tipoTexto}</span>
                                ${isAjuste ? `<span class="status-pill neutral" style="font-size: 10px; padding: 1px 6px; margin-left: 3px;">⚖️ ${esc(m.motivoAjuste)}</span>` : ''}
                                ${m.codigoPedido ? `<div style="font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 700; color: var(--primary); margin-top: 3px;">${esc(m.codigoPedido)}</div>` : ''}
                            </td>
                            <td>
                                <strong>${esc(prod.nomeProduto)}</strong>
                                <div style="margin-top: 3px;">
                                    <span style="display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; padding: 1px 6px; background: rgba(99, 102, 241, 0.08); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-weight: 700;">📍 ${esc(prod.localizacao || 'A-01-01')}</span>
                                </div>
                                ${m.observacao ? `<div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">${esc(String(m.observacao).slice(0, 42))}</div>` : ''}
                            </td>
                            <td>
                                ${cli ? `<span style="font-weight: 600; color: var(--text-main);">👤 ${esc(cli.nomeCliente)}</span>` : forn ? `<span style="font-weight: 600; color: var(--text-main);">🏢 ${esc(forn.nomeFornecedor)}</span>` : `<span style="color: var(--text-muted); font-size: 12px;">Balcão / Geral</span>`}
                            </td>
                            <td><strong style="font-size: 13px; font-family: 'JetBrains Mono', monospace;">${m.quantidade} un</strong></td>
                            <td>
                                ${isDevolvido ? `<span class="status-pill warning">Estornado</span>` : `<span class="status-pill success">Concluído</span>`}
                            </td>
                            <td>
                                <div class="row-actions" style="justify-content: flex-end; gap: 6px;">
                                    <a href="/modulos/movimentos/recibo.php?id=${m.idMovimento}&modo=picking" target="_blank" class="btn-icon" style="color: #2563eb; background: rgba(37, 99, 235, 0.12);" title="Picking List (WMS)">📋</a>
                                    <a href="/modulos/movimentos/recibo.php?id=${m.idMovimento}" target="_blank" class="btn-icon" style="color: var(--primary); background: rgba(0, 112, 74, 0.12);" title="Ver Recibo Comercial">🧾</a>
                                    <a href="/modulos/relatorios/kardex.php?idProduto=${m.idProduto}" class="btn-icon" title="Ver no Extrato Kardex">📜</a>
                                    ${!isDevolvido ? `
                                        <form action="/modulos/movimentos/movimento_devolucao.php" method="POST" style="display:inline;" onsubmit="return confirm('Confirma a devolução/estorno desta movimentação?');">
                                            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                                            <input type="hidden" name="id" value="${m.idMovimento}">
                                            <button type="submit" class="btn-icon" style="border:none; cursor:pointer;" title="Registrar Devolução / Estorno">↩️</button>
                                        </form>
                                    ` : ''}
                                    <button type="button" class="btn-icon delete" style="border:none; cursor:pointer;" onclick='abrirModalExclusao(${m.idMovimento}, ${JSON.stringify(descMov)})' title="Excluir Registro">🗑️</button>
                                </div>
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Registro seguro e auditado com proteção contra concorrência e saldo negativo</span>
            <span>Exibindo ${movs.length} registros</span>
        </div>
    </div>
</div>

<style>
    .drawer-operacoes { max-width: 620px !important; background: var(--card) !important; border-left: 1px solid var(--border) !important; display: flex !important; flex-direction: column !important; }
    .tabs-segmented-wrapper { display: flex; background: var(--bg-subtle); padding: 5px; border-radius: var(--radius); margin: 16px 24px 0 24px; border: 1px solid var(--border); gap: 4px; }
    .tab-seg-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 12px; border-radius: var(--radius-sm); border: none; background: transparent; color: var(--text-muted); font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
    .tab-seg-btn.active { background: var(--primary); color: #ffffff !important; font-weight: 700; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35); }
    .card-add-box { background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 18px; }
    .card-add-title { font-size: 13px; font-weight: 700; color: var(--text); display: block; margin-bottom: 12px; }
    .row-add-inputs { display: grid; grid-template-columns: 120px 1fr; gap: 12px; align-items: flex-end; }
    .col-input-qtd label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px; }
    .input-qtd-custom { width: 100% !important; height: 42px !important; background: var(--card) !important; border: 1px solid var(--border) !important; border-radius: var(--radius-sm) !important; color: var(--text) !important; font-size: 15px !important; font-weight: 700 !important; text-align: center !important; }
    .btn-inserir-carrinho { width: 100% !important; height: 42px !important; background: var(--primary) !important; color: #ffffff !important; border: none !important; border-radius: var(--radius-sm) !important; font-size: 13px !important; font-weight: 700 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; cursor: pointer !important; }
    .carrinho-table-container { max-height: 180px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--card); margin-bottom: 18px; }
    .carrinho-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .carrinho-table thead th { background: var(--bg-subtle); border-bottom: 1px solid var(--border); padding: 8px 12px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; text-align: left; }
    .carrinho-table tbody td { padding: 9px 12px; border-bottom: 1px solid var(--border-subtle); color: var(--text); }
    .carrinho-empty-state { text-align: center; padding: 24px 16px !important; color: var(--text-muted); font-size: 12.5px; }
    .total-venda-card { background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(16, 185, 129, 0.15) 100%); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: var(--radius); padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
    .total-venda-card .label-total { font-size: 13.5px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 8px; }
    .total-venda-card .valor-total { font-size: 24px; font-weight: 800; color: #10b981; }
    .drawer-footer-actions { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; background: var(--card); position: sticky; bottom: 0; }
    .btn-acao-cancelar { padding: 10px 18px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-subtle); color: var(--text); font-size: 13px; font-weight: 600; cursor: pointer; }
    .btn-acao-confirmar { padding: 10px 22px; border-radius: var(--radius-sm); border: none; background: var(--primary); color: #ffffff; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn-acao-confirmar:disabled { opacity: 0.45; cursor: not-allowed; }
</style>

<div id="modal-novo-movimento" class="modal-container">
    <div class="drawer-content drawer-operacoes">
        <div class="drawer-header">
            <div><h2>Central de Operações de Estoque</h2><p>Venda com múltiplos produtos ou reposição de estoque por lote</p></div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>

        <div class="tabs-segmented-wrapper">
            <button type="button" class="tab-seg-btn active" onclick="alternarAbaOperacao('carrinho', this)"><span>🛒</span> Venda Multi-Itens</button>
            <button type="button" class="tab-seg-btn" onclick="alternarAbaOperacao('rapido', this)"><span>⚡</span> Movimento / Lote</button>
            <button type="button" class="tab-seg-btn" onclick="alternarAbaOperacao('ajuste', this)"><span>⚖️</span> Ajuste Inventário</button>
        </div>

        <div id="aba-carrinho" class="conteudo-aba">
            <form action="/modulos/movimentos/movimento.php" method="POST" id="form-carrinho" onsubmit="return validarEnvioCarrinho()">
                <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                <input type="hidden" name="acao" value="carrinho_saida">
                <input type="hidden" name="itens_carrinho_json" id="itens_carrinho_json" value="[]">
                <div class="drawer-body">
                    <div class="form-group">
                        <label for="idClienteCarrinho">Cliente Comprador (Opcional)</label>
                        <select name="idClienteCarrinho" id="idClienteCarrinho">
                            <option value="">-- Venda de Balcão / Consumidor Final --</option>
                            ${clientes.map(c => `<option value="${c.idCliente}">${esc(c.nomeCliente)} (${esc(c.email)})</option>`).join('')}
                        </select>
                    </div>
                    <div class="card-add-box">
                        <span class="card-add-title">➕ Adicionar Produto ao Pedido:</span>
                        <div style="margin-bottom: 12px; background: var(--card); border: 1px dashed var(--primary); border-radius: var(--radius-sm); padding: 9px 12px;">
                            <label for="inp-bipagem-rapida" style="display: block; font-size: 10.5px; font-weight: 800; text-transform: uppercase; color: var(--primary); margin-bottom: 4px;">⚡ Leitor de Código de Barras / Bipagem Rápida (PDV):</label>
                            <div style="display: flex; gap: 6px;">
                                <input type="text" id="inp-bipagem-rapida" placeholder="Bipe o código EAN, Lote ou digite o nome e tecle Enter..." style="flex: 1; padding: 7px 10px; font-size: 12.5px; font-family: 'JetBrains Mono', monospace; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-subtle); color: var(--text);" onkeydown="if(event.key==='Enter'){event.preventDefault(); biparProdutoCarrinho();}">
                                <button type="button" onclick="biparProdutoCarrinho()" class="btn-secondary" style="padding: 7px 12px; font-size: 12px; white-space: nowrap;">Bipar ↵</button>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label for="sel-add-produto" style="font-size: 11px; text-transform: uppercase; color: var(--text-muted);">Produto em Estoque:</label>
                            <select id="sel-add-produto" onchange="atualizarInfoProdutoCarrinho()">
                                <option value="">-- Selecione o Produto --</option>
                                ${prods.map(p => `<option value="${p.idProduto}" data-ean="${esc(p.codigoBarras || '')}" data-preco="${p.preco}" data-estoque="${p.quantidade}" data-nome="${esc(p.nomeProduto)}" data-localizacao="${esc(p.localizacao || 'A-01-01')}" data-lote="${esc(p.lote || '')}" data-validade="${p.dataValidade ? esc(formatDateBR(p.dataValidade)) : ''}">${esc(p.nomeProduto)} • R$ ${formatCurrencyBR(p.preco)} (Disp: ${p.quantidade} un) [📍 ${esc(p.localizacao || 'A-01-01')}]</option>`).join('')}
                            </select>
                        </div>
                        <div class="row-add-inputs">
                            <div class="col-input-qtd"><label for="inp-add-qtd">Quantidade:</label><input type="number" id="inp-add-qtd" class="input-qtd-custom" min="1" value="1"></div>
                            <div><button type="button" onclick="adicionarItemAoCarrinho()" class="btn-inserir-carrinho"><span>➕</span> Inserir no Carrinho</button></div>
                        </div>
                        <div id="carrinho-fefo-hint" style="display:none; align-items:center; gap:6px; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#f59e0b; font-size:12px; padding:8px 12px; border-radius:var(--radius-sm); margin-top:10px; width:100%; box-sizing:border-box;">
                            <span>⏳</span> <span>Dica FEFO: Lote sugerido: <strong id="carrinho-fefo-lote"></strong></span>
                            <span style="margin-left:8px; border-left:1px solid rgba(245,158,11,0.3); padding-left:8px;">📍 Armazém: <strong id="carrinho-wms-loc"></strong></span>
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <span style="font-size: 12.5px; font-weight: 700; color: var(--text); display: block; margin-bottom: 8px;">Itens do Pedido (<span id="carrinho-total-itens" style="color: var(--primary);">0</span>):</span>
                        <div class="carrinho-table-container">
                            <table class="carrinho-table">
                                <thead><tr><th>Item</th><th style="width: 65px; text-align: center;">Qtd</th><th style="width: 90px; text-align: right;">Subtotal</th><th style="width: 40px;"></th></tr></thead>
                                <tbody id="carrinho-itens-tbody"><tr><td colspan="4" class="carrinho-empty-state">Nenhum item adicionado ainda ao pedido.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="total-venda-card"><span class="label-total"><span>💰</span> Total do Pedido:</span><strong id="carrinho-total-valor" class="valor-total">R$ 0,00</strong></div>
                    <div class="form-group" style="margin-bottom: 0;"><label for="observacaoCarrinho">Observações do Pedido</label><input type="text" name="observacaoCarrinho" id="observacaoCarrinho" placeholder="Ex: Pedido balcão, entrega agendada..."></div>
                </div>
                <div class="drawer-footer-actions">
                    <button type="button" class="btn-acao-cancelar" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn-acao-confirmar" id="btn-finalizar-carrinho" disabled>Finalizar Pedido de Venda</button>
                </div>
            </form>
        </div>

        <div id="aba-rapido" class="conteudo-aba" style="display: none;">
            <form action="/modulos/movimentos/movimento.php" method="POST">
                <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                <input type="hidden" name="acao" value="registrar">
                <div class="drawer-body">
                    <div class="form-group">
                        <label for="idProduto">Produto *</label>
                        <select name="idProduto" id="idProduto" required onchange="aoMudarProdutoRapido(this)">
                            <option value="">-- Selecione o Produto --</option>
                            ${prods.map(p => `<option value="${p.idProduto}" data-lote="${esc(p.lote || '')}">${esc(p.nomeProduto)} (Estoque: ${p.quantidade} un) [📍 ${esc(p.localizacao || 'A-01-01')}]</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="tipoMovimento">Tipo de Operação *</label>
                            <select name="tipoMovimento" id="tipoMovimento" required onchange="alternarCamposTipoOperacao(this.value)">
                                <option value="SAIDA">Saída / Venda Comercial</option>
                                <option value="ENTRADA">Entrada / Reposição de Carga</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="quantidade">Quantidade *</label><input type="number" name="quantidade" id="quantidade" min="1" value="1" required></div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group"><label for="loteMov">Lote do Produto</label><input type="text" name="lote" id="loteMov" placeholder="Ex: LOT-2026-A1"></div>
                        <div class="form-group"><label for="dataValidadeMov">Data de Validade (FEFO)</label><input type="date" name="dataValidade" id="dataValidadeMov"></div>
                    </div>
                    <div id="aviso-fefo-rapido" style="display:none; align-items:center; gap:6px; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#f59e0b; font-size:12px; padding:8px 12px; border-radius:var(--radius-sm); margin-bottom:12px;">
                        <span>⏳</span> <span><strong>Atenção FEFO:</strong> Este produto possui lote cadastrado com validade.</span>
                    </div>
                    <div class="form-group" id="grp-cliente">
                        <label for="idCliente">Cliente Comprador</label>
                        <select name="idCliente" id="idCliente">
                            <option value="">-- Balcão / Não especificado --</option>
                            ${clientes.map(c => `<option value="${c.idCliente}">${esc(c.nomeCliente)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group" id="grp-fornecedor" style="display: none;">
                        <label for="idFornecedor">Fornecedor da Mercadoria</label>
                        <select name="idFornecedor" id="idFornecedor">
                            <option value="">-- Não especificado --</option>
                            ${forns.map(f => `<option value="${f.idFornecedor}">${esc(f.nomeFornecedor)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group"><label for="observacao">Observações</label><textarea name="observacao" id="observacao" rows="2"></textarea></div>
                </div>
                <div class="drawer-footer-actions">
                    <button type="button" class="btn-acao-cancelar" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn-acao-confirmar">Gravar Movimentação</button>
                </div>
            </form>
        </div>

        <div id="aba-ajuste" class="conteudo-aba" style="display: none;">
            <form action="/modulos/movimentos/movimento.php" method="POST">
                <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                <input type="hidden" name="acao" value="ajuste_inventario">
                <div class="drawer-body">
                    <div style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: var(--radius-sm); padding: 12px 14px; margin-bottom: 16px; font-size: 12.5px; color: var(--text);">
                        <strong>⚖️ Conciliação de Inventário Físico:</strong> Informe a quantidade real contada na prateleira. O sistema calculará automaticamente a sobra ou perda e registrará o lançamento auditado no Kardex.
                    </div>
                    <div class="form-group">
                        <label for="idProdutoAjuste">Produto Auditado *</label>
                        <select name="idProdutoAjuste" id="idProdutoAjuste" required onchange="atualizarCalculoAjuste()">
                            <option value="">-- Selecione o Produto --</option>
                            ${prods.map(p => `<option value="${p.idProduto}" data-estoque="${p.quantidade}">${esc(p.nomeProduto)} (Saldo Sistema: ${p.quantidade} un) [📍 ${esc(p.localizacao || 'A-01-01')}]</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group"><label>Saldo Atual no Sistema</label><input type="text" id="inp-estoque-sistema" value="-" readonly style="font-family:'JetBrains Mono',monospace; font-weight:700; opacity:0.75;"></div>
                        <div class="form-group"><label for="estoqueFisicoReal">Contagem Física Real (Prateleira) *</label><input type="number" name="estoqueFisicoReal" id="estoqueFisicoReal" min="0" required placeholder="Qtd contada..." oninput="atualizarCalculoAjuste()" style="font-family:'JetBrains Mono',monospace; font-weight:700;"></div>
                    </div>
                    <div id="box-divergencia-ajuste" style="display: none; padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 13px; font-weight: 700;"></div>
                    <div class="form-group">
                        <label for="motivoAjuste">Motivo Oficial do Ajuste *</label>
                        <select name="motivoAjuste" id="motivoAjuste" required>
                            <option value="Contagem de Inventário">📋 Contagem de Inventário Físico</option>
                            <option value="Avaria / Quebra">🛠️ Avaria / Produto Danificado</option>
                            <option value="Perda por Validade (FEFO)">⏳ Baixa por Vencimento de Validade</option>
                            <option value="Sobra Física Identificada">📦 Sobra Física Identificada</option>
                            <option value="Correção de Lançamento">✏️ Correção de Erro Operacional</option>
                        </select>
                    </div>
                    <div class="form-group"><label for="observacaoAjuste">Justificativa / Observações de Auditoria</label><textarea name="observacaoAjuste" id="observacaoAjuste" rows="2"></textarea></div>
                </div>
                <div class="drawer-footer-actions">
                    <button type="button" class="btn-acao-cancelar" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn-acao-confirmar">Aplicar Ajuste de Estoque</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modal-confirmar-exclusao" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card">
        <div class="confirm-dialog-icon">🗑️</div>
        <h3>Excluir Movimento</h3>
        <p>Tem certeza que deseja remover esta movimentação permanentemente do sistema?</p>
        <div class="confirm-dialog-badge" id="confirm-dialog-item-nome">Registro de Movimento</div>
        <form id="form-excluir-movimento-modal" action="/modulos/movimentos/movimento_excluir.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="id" id="input-excluir-id" value="">
            <div class="confirm-dialog-actions">
                <button type="button" class="btn-secondary" onclick="fecharModalExclusao()">Cancelar</button>
                <button type="submit" class="btn-danger-confirm"><span>🗑️</span> Sim, Excluir Movimento</button>
            </div>
        </form>
    </div>
</div>

<script>
    let carrinhoItens = [];
    function alternarAbaOperacao(aba, btn) {
        document.querySelectorAll('.tab-seg-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('aba-carrinho').style.display = (aba === 'carrinho') ? 'block' : 'none';
        document.getElementById('aba-rapido').style.display = (aba === 'rapido') ? 'block' : 'none';
        document.getElementById('aba-ajuste').style.display = (aba === 'ajuste') ? 'block' : 'none';
    }
    function biparProdutoCarrinho() {
        const inp = document.getElementById('inp-bipagem-rapida');
        const sel = document.getElementById('sel-add-produto');
        if (!inp || !sel) return;
        const termo = inp.value.trim().toUpperCase();
        if (!termo) return;
        let idProcurado = null;
        const matchMvm = termo.match(/^(?:MVM|#)?0*(\\d+)$/);
        if (matchMvm) idProcurado = parseInt(matchMvm[1], 10);
        for (let i = 0; i < sel.options.length; i++) {
            const opt = sel.options[i];
            if (!opt.value) continue;
            const idOpt = parseInt(opt.value, 10);
            const eanOpt = (opt.dataset.ean || '').toUpperCase();
            const loteOpt = (opt.dataset.lote || '').toUpperCase();
            const nomeOpt = (opt.dataset.nome || '').toUpperCase();
            if ((eanOpt && eanOpt === termo) || (idProcurado !== null && idOpt === idProcurado) || (loteOpt && loteOpt === termo) || nomeOpt.includes(termo)) {
                sel.value = opt.value;
                adicionarItemAoCarrinho();
                inp.value = '';
                inp.focus();
                return;
            }
        }
        alert('Nenhum produto encontrado para "' + inp.value + '".');
    }
    function atualizarInfoProdutoCarrinho() {
        const sel = document.getElementById('sel-add-produto');
        const opt = sel.options[sel.selectedIndex];
        const hint = document.getElementById('carrinho-fefo-hint');
        const loteHint = document.getElementById('carrinho-fefo-lote');
        const wmsHint = document.getElementById('carrinho-wms-loc');
        if (opt && opt.value) {
            hint.style.display = 'inline-flex';
            loteHint.textContent = (opt.dataset.lote || 'Padrão') + (opt.dataset.validade ? ' (Vence: ' + opt.dataset.validade + ')' : '');
            if (wmsHint) wmsHint.textContent = opt.dataset.localizacao || 'A-01-01';
        } else {
            hint.style.display = 'none';
        }
    }
    function alternarCamposTipoOperacao(tipo) {
        document.getElementById('grp-cliente').style.display = (tipo === 'SAIDA') ? 'block' : 'none';
        document.getElementById('grp-fornecedor').style.display = (tipo === 'ENTRADA') ? 'block' : 'none';
    }
    function aoMudarProdutoRapido(sel) {
        const opt = sel.options[sel.selectedIndex];
        const aviso = document.getElementById('aviso-fefo-rapido');
        if (opt && opt.dataset.lote) {
            aviso.style.display = 'inline-flex';
            document.getElementById('loteMov').value = opt.dataset.lote;
        } else {
            aviso.style.display = 'none';
        }
    }
    function atualizarCalculoAjuste() {
        const sel = document.getElementById('idProdutoAjuste');
        const inpSistema = document.getElementById('inp-estoque-sistema');
        const inpFisico = document.getElementById('estoqueFisicoReal');
        const boxDiv = document.getElementById('box-divergencia-ajuste');
        if (!sel || !inpSistema || !inpFisico || !boxDiv) return;
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) { inpSistema.value = '-'; boxDiv.style.display = 'none'; return; }
        const estSistema = parseInt(opt.dataset.estoque || '0', 10);
        inpSistema.value = estSistema + ' un';
        if (inpFisico.value === '') inpFisico.value = estSistema;
        const estFisico = parseInt(inpFisico.value, 10);
        if (isNaN(estFisico)) { boxDiv.style.display = 'none'; return; }
        const diff = estFisico - estSistema;
        boxDiv.style.display = 'block';
        if (diff === 0) {
            boxDiv.style.background = 'rgba(148,163,184,0.12)'; boxDiv.style.color = 'var(--text-muted)';
            boxDiv.textContent = '✓ Sem divergência: Contagem igual ao sistema (' + estSistema + ' un).';
        } else if (diff > 0) {
            boxDiv.style.background = 'rgba(16,185,129,0.12)'; boxDiv.style.color = '#10b981';
            boxDiv.textContent = '📈 Sobra identificada: +' + diff + ' un (ENTRADA de ajuste para ' + estFisico + ' un).';
        } else {
            boxDiv.style.background = 'rgba(239,68,68,0.12)'; boxDiv.style.color = '#ef4444';
            boxDiv.textContent = '📉 Baixa / Perda identificada: ' + diff + ' un (SAÍDA de ajuste para ' + estFisico + ' un).';
        }
    }
    function adicionarItemAoCarrinho() {
        const sel = document.getElementById('sel-add-produto');
        const qtdInput = document.getElementById('inp-add-qtd');
        const idProduto = parseInt(sel.value);
        const qtd = parseInt(qtdInput.value);
        if (!idProduto || qtd <= 0 || isNaN(qtd)) { alert('Selecione um produto e quantidade válida.'); return; }
        const opt = sel.options[sel.selectedIndex];
        const estoque = parseInt(opt.dataset.estoque || 0);
        const preco = parseFloat(opt.dataset.preco || 0);
        const nome = opt.dataset.nome || 'Produto';
        const localizacao = opt.dataset.localizacao || 'A-01-01';
        const idx = carrinhoItens.findIndex(item => item.idProduto === idProduto);
        const ja = idx >= 0 ? carrinhoItens[idx].quantidade : 0;
        if (ja + qtd > estoque) { alert('Estoque insuficiente.'); return; }
        if (idx >= 0) carrinhoItens[idx].quantidade += qtd;
        else carrinhoItens.push({ idProduto, nome, preco, quantidade: qtd, localizacao });
        qtdInput.value = 1;
        sel.value = '';
        renderizarCarrinho();
    }
    function removerItemCarrinho(i) { carrinhoItens.splice(i, 1); renderizarCarrinho(); }
    function renderizarCarrinho() {
        const tbody = document.getElementById('carrinho-itens-tbody');
        const totalItensSpan = document.getElementById('carrinho-total-itens');
        const totalValorStrong = document.getElementById('carrinho-total-valor');
        const inputJson = document.getElementById('itens_carrinho_json');
        const btnFinalizar = document.getElementById('btn-finalizar-carrinho');
        if (carrinhoItens.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="carrinho-empty-state">Nenhum item adicionado ainda ao pedido.</td></tr>';
            totalItensSpan.textContent = '0';
            totalValorStrong.textContent = 'R$ 0,00';
            inputJson.value = '[]';
            btnFinalizar.disabled = true;
            return;
        }
        let html = '', totalQtd = 0, totalValor = 0;
        carrinhoItens.forEach((item, idx) => {
            const sub = item.quantidade * item.preco;
            totalQtd += item.quantidade;
            totalValor += sub;
            html += '<tr><td><strong>' + item.nome + '</strong><div style="font-size:11px;color:var(--primary);">📍 ' + item.localizacao + '</div></td><td style="text-align:center;font-weight:700;">' + item.quantidade + ' un</td><td style="text-align:right;">R$ ' + sub.toFixed(2).replace('.', ',') + '</td><td style="text-align:center;"><button type="button" onclick="removerItemCarrinho(' + idx + ')" style="background:none;border:none;cursor:pointer;color:#ef4444;">✕</button></td></tr>';
        });
        tbody.innerHTML = html;
        totalItensSpan.textContent = totalQtd;
        totalValorStrong.textContent = 'R$ ' + totalValor.toFixed(2).replace('.', ',');
        inputJson.value = JSON.stringify(carrinhoItens);
        btnFinalizar.disabled = false;
    }
    function validarEnvioCarrinho() { return carrinhoItens.length > 0; }
    function filtrarTipoMovimento(filtro, btn) {
        if (btn && btn.parentElement) {
            btn.parentElement.querySelectorAll('.pbi-pill-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
        let visiveis = 0;
        document.querySelectorAll('#tabela-movimentos tbody tr').forEach(row => {
            const tipo = row.getAttribute('data-tipo-mov');
            const isAjuste = row.getAttribute('data-ajuste') === '1';
            const isEstornado = row.getAttribute('data-estornado') === '1';
            let mostrar = true;
            if (filtro === 'SAIDA') mostrar = (tipo === 'SAIDA' && !isEstornado);
            else if (filtro === 'ENTRADA') mostrar = (tipo === 'ENTRADA' && !isEstornado);
            else if (filtro === 'AJUSTE') mostrar = isAjuste;
            else if (filtro === 'ESTORNADO') mostrar = isEstornado;
            row.style.display = mostrar ? '' : 'none';
            if (mostrar) visiveis++;
        });
        const counter = document.querySelector('[data-item-counter]');
        if (counter) counter.textContent = visiveis;
    }
    function abrirModalExclusao(id, descricao) {
        const modal = document.getElementById('modal-confirmar-exclusao');
        const inputId = document.getElementById('input-excluir-id');
        const badgeNome = document.getElementById('confirm-dialog-item-nome');
        if (inputId) inputId.value = id;
        if (badgeNome) badgeNome.textContent = descricao || 'Movimento #' + id;
        if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    }
    function fecharModalExclusao() {
        const modal = document.getElementById('modal-confirmar-exclusao');
        if (modal) { modal.classList.remove('active'); document.body.style.overflow = ''; }
    }
</script>`;

  return { html: renderLayout('MVM - Vendas & Movimentação', 'movimento.php', session, html) };
}

function handleKardex(req, query, db, session) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;
  const listaProdutos = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const idProdutoSelecionado = Number(query.idProduto || query.produto || (listaProdutos[0] && listaProdutos[0].idProduto) || 1);
  const dataInicio = String(query.dataInicio || '').trim();
  const dataFim = String(query.dataFim || '').trim();
  const produtoAtual = listaProdutos.find(p => p.idProduto === idProdutoSelecionado) || listaProdutos[0];
  const todosMovs = db.movimentos
    .filter(m => Number(m.idUsuario) === uid && Number(m.idProduto) === (produtoAtual ? produtoAtual.idProduto : 0))
    .slice()
    .sort((a, b) => String(a.dataMovimento).localeCompare(String(b.dataMovimento)) || a.idMovimento - b.idMovimento);

  let saldoLiquidoTotal = 0;
  let totalEntradas = 0;
  let totalSaidas = 0;
  todosMovs.forEach(m => {
    const q = Number(m.quantidade || 0);
    if (m.tipoMovimento === 'ENTRADA') saldoLiquidoTotal += q;
    else if (m.tipoMovimento === 'SAIDA' && !m.dataDevolucao) saldoLiquidoTotal -= q;
  });

  let saldoAcumulado = Math.max(0, Number(produtoAtual ? produtoAtual.quantidade : 0) - saldoLiquidoTotal);
  const historicoFiltrado = [];
  todosMovs.forEach(m => {
    const q = Number(m.quantidade || 0);
    const dataMovYmd = String(m.dataMovimento).slice(0, 10);
    let sinal = '+';
    let classeSinal = 'entrada';
    if (m.tipoMovimento === 'ENTRADA') {
      saldoAcumulado += q;
    } else if (m.tipoMovimento === 'SAIDA') {
      if (!m.dataDevolucao) {
        saldoAcumulado -= q;
        sinal = '-';
        classeSinal = 'saida';
      } else {
        sinal = '↩';
        classeSinal = 'devolucao';
      }
    }
    if (dataInicio && dataMovYmd < dataInicio) return;
    if (dataFim && dataMovYmd > dataFim) return;
    if (m.tipoMovimento === 'ENTRADA') totalEntradas += q;
    else if (m.tipoMovimento === 'SAIDA' && !m.dataDevolucao) totalSaidas += q;

    const cli = db.clientes.find(c => c.idCliente === Number(m.idCliente));
    const forn = db.fornecedores.find(f => f.idFornecedor === Number(m.idFornecedor));
    historicoFiltrado.push({
      ...m,
      sinal,
      classeSinal,
      saldoApos: saldoAcumulado,
      nomeCliente: cli ? cli.nomeCliente : null,
      nomeFornecedor: forn ? forn.nomeFornecedor : null
    });
  });
  const historicoExibicao = historicoFiltrado.reverse();

  const custoUnit = produtoAtual && Number(produtoAtual.precoCusto) > 0 ? Number(produtoAtual.precoCusto) : Number((produtoAtual ? produtoAtual.preco : 0) * 0.6);
  const custoTotalEst = Number(produtoAtual ? produtoAtual.quantidade : 0) * custoUnit;
  const margemUnit = produtoAtual && Number(produtoAtual.preco) > 0 ? Number((((produtoAtual.preco - custoUnit) / produtoAtual.preco) * 100).toFixed(1)) : 0;
  const mesAtual = new Date().toISOString().slice(0, 7);

  const html = `
<style>
    .kardex-bar { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between; }
    .kardex-bar-select { flex: 1; min-width: 280px; display: flex; align-items: center; gap: 10px; }
    .kardex-bar-select select { width: 100%; padding: 9px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-subtle); color: var(--text); font-size: 13.5px; font-weight: 600; }
    .kardex-bar-dates { display: flex; align-items: center; gap: 8px; }
    .kardex-bar-dates input[type="date"] { padding: 8px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-subtle); color: var(--text); font-size: 12.5px; }
    .kardex-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 22px; }
    .k-stat-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 20px; }
    .k-stat-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); display: block; margin-bottom: 6px; }
    .k-stat-value { font-size: 26px; font-weight: 800; display: block; }
    .k-stat-sub { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; display: block; }
    .pill-saldo { display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700; padding: 4px 10px; border-radius: 6px; background: var(--bg-subtle); border: 1px solid var(--border); color: var(--text); }
    .btn-recibo-link { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-subtle); color: var(--text); font-size: 12px; font-weight: 600; text-decoration: none; }
</style>

<div class="page-container">
    <div class="page-header-row no-print">
        <div class="page-header-titles">
            <h1>Ficha Kardex</h1>
            <p>Histórico de movimentações e saldo em estoque do produto</p>
        </div>
        <div class="page-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <a href="/exportacao/relatorio_kardex_pdf.php?idProduto=${idProdutoSelecionado}" target="_blank" class="btn-secondary"><span>📄</span> Kardex (PDF)</a>
            <a href="/exportacao/relatorio_kardex_excel.php?idProduto=${idProdutoSelecionado}" class="btn-secondary"><span>📊</span> Kardex (.xlsx)</a>
            <div style="display: inline-flex; align-items: center; gap: 4px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 3px 6px;">
                <input type="month" id="mes-balanco-kardex" value="${mesAtual}" style="border: none; background: transparent; color: var(--text); font-size: 12px; font-weight: 600; outline: none; padding: 3px 4px;">
                <a href="/exportacao/relatorio_balanco_pdf.php" onclick="this.href='/exportacao/relatorio_balanco_pdf.php?mes=' + document.getElementById('mes-balanco-kardex').value" target="_blank" class="btn-secondary" style="padding: 5px 10px; border: none;"><span>📑</span> Balanço (PDF)</a>
                <a href="/exportacao/relatorio_balanco_excel.php" onclick="this.href='/exportacao/relatorio_balanco_excel.php?mes=' + document.getElementById('mes-balanco-kardex').value" class="btn-secondary" style="padding: 5px 10px; border: none;"><span>📈</span> .xlsx</a>
            </div>
            <button type="button" onclick="window.print()" class="btn-secondary"><span>🖨️</span> Imprimir</button>
            <a href="/modulos/movimentos/movimento.php" class="btn-primary"><span>➕</span> Nova Movimentação</a>
        </div>
    </div>

    <div class="kardex-bar no-print">
        <form method="GET" action="/modulos/relatorios/kardex.php" style="display: contents;">
            <div class="kardex-bar-select">
                <label for="idProduto" style="font-size: 13px; font-weight: 700; color: var(--text); white-space: nowrap;">Produto:</label>
                <select name="idProduto" id="idProduto" onchange="this.form.submit()">
                    ${listaProdutos.map(item => `<option value="${item.idProduto}" ${item.idProduto === idProdutoSelecionado ? 'selected' : ''}>${esc(item.nomeProduto)} (Estoque: ${item.quantidade} un)</option>`).join('')}
                </select>
            </div>
            <div class="kardex-bar-dates">
                <input type="date" name="dataInicio" value="${esc(dataInicio)}" title="Data Inicial">
                <span style="color: var(--text-muted); font-size: 12px;">até</span>
                <input type="date" name="dataFim" value="${esc(dataFim)}" title="Data Final">
                <button type="submit" class="btn-primary">Filtrar</button>
                ${(dataInicio || dataFim) ? `<a href="/modulos/relatorios/kardex.php?idProduto=${idProdutoSelecionado}" class="btn-secondary" style="padding: 8px 12px; text-decoration: none;" title="Limpar datas">✕</a>` : ''}
            </div>
        </form>
    </div>

    ${produtoAtual ? `
        <div class="kardex-stats-grid">
            <div class="k-stat-card">
                <span class="k-stat-label">Estoque Físico Atual</span>
                <strong class="k-stat-value" style="color: #10b981;">${produtoAtual.quantidade} <small style="font-size: 14px; font-weight: 500; color: var(--text-muted);">un</small></strong>
                <span class="k-stat-sub">Saldo em prateleira</span>
            </div>
            <div class="k-stat-card">
                <span class="k-stat-label">Entradas no Período</span>
                <strong class="k-stat-value" style="color: #3b82f6;">+${totalEntradas} <small style="font-size: 14px; font-weight: 500; color: var(--text-muted);">un</small></strong>
                <span class="k-stat-sub">Reposições recebidas</span>
            </div>
            <div class="k-stat-card">
                <span class="k-stat-label">Saídas / Vendas</span>
                <strong class="k-stat-value" style="color: #ef4444;">-${totalSaidas} <small style="font-size: 14px; font-weight: 500; color: var(--text-muted);">un</small></strong>
                <span class="k-stat-sub">Vendas a clientes</span>
            </div>
            <div class="k-stat-card">
                <span class="k-stat-label">Valor em Estoque (Venda)</span>
                <strong class="k-stat-value">R$ ${formatCurrencyBR(produtoAtual.quantidade * produtoAtual.preco)}</strong>
                <span class="k-stat-sub">Preço venda: R$ ${formatCurrencyBR(produtoAtual.preco)}</span>
            </div>
            <div class="k-stat-card">
                <span class="k-stat-label">Custo Médio (CMP) &amp; Margem</span>
                <strong class="k-stat-value" style="color: var(--primary);">R$ ${formatCurrencyBR(custoTotalEst)}</strong>
                <span class="k-stat-sub">CMP: R$ ${formatCurrencyBR(custoUnit)} &bull; Margem: ${margemUnit}%</span>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header-custom" style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h3 style="margin: 0; font-size: 15px; color: var(--text);">Extrato: <strong>${esc(produtoAtual.nomeProduto)}</strong></h3>
                    <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 3px 8px; background: rgba(99, 102, 241, 0.1); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-weight: 700;">📍 Armazém: ${esc(produtoAtual.localizacao || 'A-01-01')}</span>
                </div>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">${historicoExibicao.length} lançamentos</span>
            </div>
            <div class="table-responsive">
                <table class="data-table" id="tabela-kardex">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Data / Hora</th>
                            <th style="width: 14%;">Documento</th>
                            <th style="width: 16%;">Operação</th>
                            <th style="width: 25%;">Origem / Destino</th>
                            <th style="width: 12%; text-align: center;">Movimentação</th>
                            <th style="width: 12%; text-align: center;">Saldo em Estoque</th>
                            <th style="width: 6%; text-align: right;">Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${historicoExibicao.map(item => {
                          const isEntrada = item.tipoMovimento === 'ENTRADA';
                          const isDevolvido = Boolean(item.dataDevolucao);
                          const badgeClass = isDevolvido ? 'neutral' : (isEntrada ? 'success' : 'info');
                          const badgeTexto = isDevolvido ? 'Estorno' : (isEntrada ? 'Entrada' : 'Saída');
                          return `
                            <tr>
                                <td><span style="font-weight: 600; color: var(--text); font-size: 12.5px;">${esc(formatDateTimeBR(item.dataMovimento))}</span></td>
                                <td><span style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: 700; color: var(--primary);">${esc(item.codigoPedido || 'MOV-#' + item.idMovimento)}</span></td>
                                <td><span class="status-pill ${badgeClass}">${badgeTexto}</span></td>
                                <td>
                                    <span style="font-weight: 600; font-size: 12.5px; color: var(--text);">${esc(item.nomeCliente || item.nomeFornecedor || 'Balcão')}</span>
                                    ${item.observacao ? `<span style="font-size: 11px; color: var(--text-muted); display: block;">${esc(String(item.observacao).slice(0, 38))}</span>` : ''}
                                </td>
                                <td style="text-align: center;">
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 13px; color: ${item.classeSinal === 'entrada' ? '#10b981' : (item.classeSinal === 'saida' ? '#ef4444' : '#64748b')};">${item.sinal}${item.quantidade} un</strong>
                                </td>
                                <td style="text-align: center;"><span class="pill-saldo">${item.saldoApos} un</span></td>
                                <td style="text-align: right;"><a href="/modulos/movimentos/recibo.php?id=${item.idMovimento}" target="_blank" class="btn-recibo-link">🧾</a></td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    ` : `
        <div class="table-card" style="padding: 40px; text-align: center; color: var(--text-muted);">
            <div style="font-size: 32px; margin-bottom: 8px;">📜</div>
            <strong>Nenhum produto cadastrado para exibir o Kardex</strong>
            <p style="font-size: 12.5px; margin-top: 4px;">Cadastre produtos no catálogo para acompanhar o extrato de movimentações.</p>
        </div>
    `}
</div>`;

  return { html: renderLayout('Ficha Kardex - MVM', 'kardex.php', session, html) };
}

function handleEtiquetas(req, db, session) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;
  const produtos = db.produtos.filter(p => Number(p.idUsuario) === uid);

  const html = `
<div class="page-container">
    <div class="page-header-row no-print">
        <div class="page-header-titles">
            <h1>Gerador de Etiquetas de Prateleira &amp; Código de Barras</h1>
            <p>Selecione os produtos para gerar etiquetas padronizadas prontas para impressão e gôndolas</p>
        </div>
        <div class="page-header-actions">
            <button type="button" onclick="imprimirEtiquetas()" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                <span>🖨️</span> Imprimir Etiquetas
            </button>
        </div>
    </div>

    <div class="table-card no-print" style="margin-bottom: 24px; padding: 18px 24px;">
        <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;">
            <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer;">
                <input type="checkbox" id="check-todos" checked onchange="alternarTodos(this)" style="width: 17px; height: 17px;">
                Selecionar Todos (<span id="count-selecionados">${produtos.length}</span> selecionados)
            </label>
            <div style="display: flex; gap: 12px; align-items: center;">
                <label for="formato-grid" style="font-size: 13px; color: var(--text-muted); font-weight: 600;">Tamanho da Etiqueta:</label>
                <select id="formato-grid" onchange="document.getElementById('grid-etiquetas').className='grid-etiquetas-'+this.value" style="padding: 7px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 13px;">
                    <option value="padrao">Padrão Gôndola (3 por linha)</option>
                    <option value="grande">Grande / Caixa Master (2 por linha)</option>
                    <option value="compacta">Compacta / Almoxarifado (4 por linha)</option>
                </select>
            </div>
        </div>
    </div>

    <div id="grid-etiquetas" class="grid-etiquetas-padrao">
        ${produtos.length === 0 ? `
            <div class="table-card no-print" style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; color: var(--text-muted);">
                <div style="font-size: 36px; margin-bottom: 10px;">🏷️</div>
                <h3>Nenhum produto cadastrado para gerar etiquetas</h3>
                <p style="font-size: 13px; margin-top: 6px;">Cadastre produtos no catálogo para gerar as etiquetas de código de barras.</p>
                <a href="/modulos/produtos/produto.php" class="btn-primary" style="margin-top: 16px; display: inline-flex;">Ir para Catálogo</a>
            </div>
        ` : produtos.map(p => {
          const codBarraProd = p.codigoBarras || ('MVM' + String(p.idProduto).padStart(6, '0'));
          return `
            <div class="etiqueta-card" id="etiqueta-${p.idProduto}">
                <div class="etiqueta-check no-print">
                    <input type="checkbox" class="prod-check" value="${p.idProduto}" checked onchange="atualizarContagem()">
                </div>
                <div class="etiqueta-header">
                    <div class="etiqueta-brand">
                        <img src="/img/mascote_mvm.svg?v=3" alt="MVM" class="etiqueta-mini-logo">
                        <span>MVM ESTOQUE</span>
                    </div>
                    <span class="etiqueta-cat">${esc(p.categoria || 'Geral')}</span>
                </div>
                <div class="etiqueta-nome" title="${esc(p.nomeProduto)}">${esc(p.nomeProduto)}</div>
                <div class="etiqueta-preco-box">
                    <span class="etiqueta-moeda">R$</span>
                    <span class="etiqueta-valor">${formatCurrencyBR(p.preco)}</span>
                </div>
                <div class="etiqueta-endereco-box" style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px; padding: 3px 8px; margin: 5px 0; font-size: 11px;">
                    <span style="color: #64748b; font-weight: 700; font-size: 10px;">ENDEREÇO WMS:</span>
                    <strong style="color: #0f172a; font-family: 'JetBrains Mono', monospace; font-size: 11px;">📍 ${esc(p.localizacao || 'A-01-01')}</strong>
                </div>
                <div class="etiqueta-barcode-box">
                    <svg class="barcode-svg" data-code="${esc(codBarraProd)}"></svg>
                    <div class="etiqueta-code-text">CÓD: ${String(p.idProduto).padStart(6, '0')}${p.codigoBarras ? ' • EAN: ' + esc(p.codigoBarras) : ''}</div>
                </div>
            </div>`;
        }).join('')}
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<style>
.grid-etiquetas-padrao { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 20px; margin-bottom: 40px; }
.grid-etiquetas-grande { display: grid; grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 24px; margin-bottom: 40px; }
.grid-etiquetas-compacta { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-bottom: 40px; }
.etiqueta-card { background: #ffffff; color: #0f172a; border: 2px dashed #94a3b8; border-radius: 8px; padding: 16px 18px; position: relative; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
.etiqueta-card.desmarcada { opacity: 0.35; filter: grayscale(1); }
.etiqueta-check { position: absolute; top: 10px; right: 10px; z-index: 2; }
.etiqueta-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #00704A; padding-bottom: 6px; margin-bottom: 10px; }
.etiqueta-brand { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; color: #00704A; }
.etiqueta-mini-logo { width: 20px; height: 20px; border-radius: 50%; }
.etiqueta-cat { font-size: 10.5px; font-weight: 700; color: #475569; background: #f1f5f9; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; }
.etiqueta-nome { font-size: 14px; font-weight: 800; line-height: 1.35; color: #0f172a; margin-bottom: 10px; height: 38px; overflow: hidden; }
.etiqueta-preco-box { display: flex; align-items: baseline; gap: 4px; margin-bottom: 10px; }
.etiqueta-moeda { font-size: 13px; font-weight: 700; color: #00704A; }
.etiqueta-valor { font-size: 24px; font-weight: 900; font-family: 'JetBrains Mono', monospace; color: #00704A; }
.etiqueta-barcode-box { text-align: center; background: #f8fafc; padding: 8px 6px 4px; border-radius: 6px; border: 1px solid #e2e8f0; }
.etiqueta-barcode-box svg { max-width: 100%; height: 38px; display: block; margin: 0 auto; }
.etiqueta-code-text { font-family: 'JetBrains Mono', monospace; font-size: 9.5px; color: #64748b; font-weight: 600; margin-top: 3px; }
@media print { .no-print, .main-header, .main-footer { display: none !important; } .etiqueta-card.desmarcada { display: none !important; } body, main { margin: 0 !important; padding: 0 !important; background: #fff !important; } }
</style>
<script>
function atualizarContagem() {
    const checks = document.querySelectorAll('.prod-check');
    let count = 0;
    checks.forEach(c => {
        c.closest('.etiqueta-card').classList.toggle('desmarcada', !c.checked);
        if (c.checked) count++;
    });
    const span = document.getElementById('count-selecionados');
    if (span) span.textContent = count;
    const todos = document.getElementById('check-todos');
    if (todos) todos.checked = (count === checks.length && checks.length > 0);
}
function alternarTodos(master) {
    document.querySelectorAll('.prod-check').forEach(c => { c.checked = master.checked; });
    atualizarContagem();
}
function imprimirEtiquetas() {
    const count = document.querySelectorAll('.prod-check:checked').length;
    if (count === 0) { alert('Selecione pelo menos uma etiqueta para imprimir.'); return; }
    window.print();
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.barcode-svg').forEach(svg => {
        const code = svg.getAttribute('data-code') || 'MVM000001';
        if (window.JsBarcode) {
            JsBarcode(svg, code, { format: 'CODE128', lineColor: '#0f172a', width: 1.8, height: 36, displayValue: false, margin: 0 });
        }
    });
});
</script>`;

  return { html: renderLayout('Gerador de Etiquetas de Estoque - MVM', 'etiquetas.php', session, html) };
}

function handleMascote(session) {
  const html = `
<div class="page-container" style="max-width: 1000px; margin: 0 auto; padding: 30px 20px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <span style="display: inline-block; padding: 6px 16px; background: rgba(0, 112, 74, 0.15); color: #00704A; border-radius: 50px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; border: 1px solid rgba(0, 112, 74, 0.3);">
            Identidade Visual &amp; Branding
        </span>
        <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 12px;">Emblema Oficial: O Selo Circular de Logística MVM</h1>
        <p style="color: var(--text-muted); font-size: 16px; max-width: 650px; margin: 0 auto;">
            Identidade corporativa minimalista combinando a silhueta ágil da formiga, a caixa isométrica de armazenagem e as setas do ciclo contínuo de controle de estoque.
        </p>
    </div>

    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 40px; box-shadow: var(--shadow-md); margin-bottom: 35px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px; align-items: center;">
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: radial-gradient(circle at center, rgba(16, 185, 129, 0.15) 0%, transparent 70%); padding: 30px; border-radius: var(--radius-md);">
            <div style="width: 280px; height: 280px; filter: drop-shadow(0 20px 30px rgba(0, 0, 0, 0.45));">
                <img src="/img/mascote_mvm.svg?v=5" alt="Emblema Oficial MVM Estoque" style="width: 100%; height: 100%; display: block; border-radius: 50%; object-fit: cover;">
            </div>
            <div style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                <a href="/img/mascote_mvm.svg?v=5" download="mascote_mvm_estoque.svg" class="btn-primary" style="padding: 10px 18px; font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <span>📥</span> Baixar Vetor SVG
                </a>
                <a href="/img/mascote_mvm.png" download="mascote_mvm_estoque_1024x1024.png" class="btn-secondary" style="padding: 10px 18px; font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border-radius: var(--radius-sm);">
                    <span>🖼️</span> Baixar PNG (1024px)
                </a>
            </div>
        </div>

        <div>
            <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 20px; color: var(--text-main);">Conceito &amp; Elementos de Logística</h2>
            <div style="display: flex; flex-direction: column; gap: 18px;">
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">🐜</div>
                    <div><strong style="font-size: 15px; color: var(--text-main);">A Formiga como Símbolo</strong><p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">Representa a superação de carga, precisão, disciplina e trabalho em equipe incansável.</p></div>
                </div>
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">🔄</div>
                    <div><strong style="font-size: 15px; color: var(--text-main);">Setas de Rotação &amp; Fluxo</strong><p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">O ciclo contínuo em volta da mercadoria simboliza o método FEFO, a auditoria Kardex e a agilidade nas devoluções.</p></div>
                </div>
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">📦</div>
                    <div><strong style="font-size: 15px; color: var(--text-main);">Caixa Isométrica Central</strong><p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">A encomenda selada com fita e código de barras personifica a segurança e o controle unitário de cada item armazenado.</p></div>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <div style="background: #0f172a; border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid #1e293b;">
            <div style="font-size: 13px; color: #94a3b8; font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Aplicação em Fundo Escuro</div>
            <div style="width: 120px; height: 120px; margin: 0 auto;"><img src="/img/mascote_mvm.svg?v=5" alt="Fundo Escuro" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"></div>
            <p style="color: #cbd5e1; font-size: 13px; margin-top: 15px;">Perfeito para Modo Noturno, dashboards BI e telas de sistema.</p>
        </div>
        <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid #e2e8f0;">
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Aplicação em Fundo Claro</div>
            <div style="width: 120px; height: 120px; margin: 0 auto;"><img src="/img/mascote_mvm.svg?v=5" alt="Fundo Claro" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"></div>
            <p style="color: #475569; font-size: 13px; margin-top: 15px;">Ideal para cabeçalho, relatórios em papel timbrado e notas fiscais.</p>
        </div>
        <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid var(--border);">
            <div style="font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Tamanhos Reduzidos</div>
            <div style="display: flex; align-items: center; justify-content: center; gap: 20px; height: 120px;">
                <img src="/img/mascote_mvm.svg?v=5" alt="64px" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;">
                <img src="/img/mascote_mvm.svg?v=5" alt="40px" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                <img src="/img/mascote_mvm.svg?v=5" alt="24px" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
            </div>
            <p style="color: var(--text-muted); font-size: 13px;">Legibilidade preservada mesmo em favicons e badges pequenos.</p>
        </div>
    </div>
</div>`;
  return { html: renderLayout('Mascote Oficial - MVM Estoque', 'mascote.php', session, html) };
}

async function handleApiRest(req, body, query, db, session) {
  const token = String((req.headers && req.headers['x-api-key']) || query.api_key || query.token || '').trim();
  let uid = session.usuarioLogado ? (Number(session.idUsuario) || 1) : 0;
  if (!uid && token) {
    const u = db.usuarios.find(x => x.apiToken === token);
    if (u) uid = u.idUsuario;
    else if (token === 'mvm_live_token_77a89b42' || token === 'mvm_demo') uid = 1;
  }
  if (!uid) {
    return {
      statusCode: 401,
      json: {
        status: 'error',
        code: 401,
        mensagem: 'Acesso não autorizado. Envie o cabeçalho X-API-KEY válido ou realize login na plataforma.',
        exemplo_header: 'X-API-KEY: mvm_live_token_77a89b42'
      }
    };
  }

  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);

  if (req.method === 'POST') {
    const idProduto = Number(body.idProduto || body.id || 0);
    const tipo = String(body.tipoMovimento || body.tipo || 'SAIDA').toUpperCase();
    const quantidade = Number(body.quantidade || 0);
    const observacao = String(body.observacao || 'Movimentação realizada via API REST').trim();
    if (idProduto <= 0 || quantidade <= 0 || !['ENTRADA', 'SAIDA'].includes(tipo)) {
      return { statusCode: 422, json: { status: 'error', code: 422, mensagem: 'Parâmetros inválidos. Informe idProduto, tipoMovimento (ENTRADA|SAIDA) e quantidade > 0.' } };
    }
    const prod = prods.find(p => p.idProduto === idProduto);
    if (!prod) {
      return { statusCode: 404, json: { status: 'error', code: 404, mensagem: `Produto ID ${idProduto} não encontrado.` } };
    }
    if (tipo === 'SAIDA' && prod.quantidade < quantidade) {
      return { statusCode: 409, json: { status: 'error', code: 409, mensagem: `Estoque insuficiente. Disponível: ${prod.quantidade} un.` } };
    }
    const saldoAnterior = prod.quantidade;
    prod.quantidade = tipo === 'SAIDA' ? prod.quantidade - quantidade : prod.quantidade + quantidade;
    const novoId = db.movimentos.reduce((m, x) => Math.max(m, x.idMovimento), 0) + 1;
    const dtMov = addDaysDateTimeStr(0);
    const codPed = `API-${dtMov.slice(0, 10).replace(/-/g, '')}-${String(novoId).padStart(4, '0')}`;
    const valorTotal = Number((quantidade * prod.preco).toFixed(2));
    db.movimentos.unshift({
      idMovimento: novoId, idUsuario: uid, codigoPedido: codPed, idProduto, idCliente: null, idFornecedor: null,
      tipoMovimento: tipo, quantidade, observacao, lote: prod.lote, dataValidade: prod.dataValidade,
      motivoAjuste: null, precoUnitario: prod.preco, custoUnitario: prod.precoCusto, valorTotal,
      dataMovimento: dtMov, dataDevolucao: null
    });
    await saveDb(db);
    return {
      statusCode: 201,
      json: {
        status: 'success',
        code: 201,
        mensagem: 'Movimentação gravada e saldo de estoque atualizado com sucesso.',
        transacao: {
          id_movimento: novoId, codigo_documento: codPed, id_produto: idProduto,
          nome_produto: prod.nomeProduto, localizacao_wms: prod.localizacao || 'A-01-01',
          tipo, quantidade, saldo_anterior: saldoAnterior, saldo_atual: prod.quantidade,
          valor_total_rs: valorTotal, data_hora: new Date().toISOString()
        }
      }
    };
  }

  const busca = String(query.busca || '').trim().toLowerCase();
  const statusFiltro = String(query.status || '').trim().toUpperCase();
  let itensCriticos = 0;
  let valorTotal = 0;
  const dados = [];
  prods.forEach(p => {
    const q = Number(p.quantidade || 0);
    const min = Number(p.estoqueMinimo || 10);
    let st = 'NORMAL';
    if (q <= 0) { st = 'ESGOTADO'; itensCriticos++; }
    else if (q <= min) { st = 'BAIXO'; itensCriticos++; }
    if (busca && !`${p.nomeProduto} ${p.categoria} ${p.localizacao} ${p.codigoBarras}`.toLowerCase().includes(busca)) return;
    if (statusFiltro && st !== statusFiltro) return;
    valorTotal += q * Number(p.preco || 0);
    dados.push({
      id: p.idProduto, codigo_barras: p.codigoBarras, nome: p.nomeProduto,
      categoria: p.categoria || 'Geral', estoque_atual: q, estoque_minimo: min,
      status_estoque: st, localizacao_wms: p.localizacao || 'A-01-01',
      preco_venda: Number(p.preco), preco_custo: Number(p.precoCusto)
    });
  });

  return {
    json: {
      status: 'success',
      versao_api: '1.0.0',
      ambiente: 'Vercel Cloud Serverless',
      data_consulta: new Date().toISOString(),
      total_itens: dados.length,
      itens_criticos: itensCriticos,
      valor_total_rs: Number(valorTotal.toFixed(2)),
      dados
    }
  };
}

async function handleCicloVida(req, body, query, db, session) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;

  if (req.method === 'POST' && body.acao === 'aplicar_promocao') {
    const idProd = Number(body.idProduto || 0);
    const descontoPct = Math.min(50, Math.max(5, Number(body.desconto || 15)));
    const prod = db.produtos.find(p => p.idProduto === idProd && Number(p.idUsuario) === uid);
    if (prod) {
      const precoAntigo = Number(prod.preco || 0);
      const novoPreco = Number((precoAntigo * (1 - descontoPct / 100)).toFixed(2));
      prod.preco = novoPreco;
      await saveDb(db);
      session.flash_plm = {
        tipo: 'sucesso',
        texto: `🔥 Ação inteligente aplicada! Preço de <strong>${esc(prod.nomeProduto)}</strong> reduzido em ${descontoPct}% (de R$ ${formatCurrencyBR(precoAntigo)} para <strong>R$ ${formatCurrencyBR(novoPreco)}</strong>) para acelerar o giro.`
      };
    }
    return { redirect: '/modulos/relatorios/ciclo_vida.php' };
  }

  if (req.method === 'POST' && body.acao === 'importar_sugestao') {
    const nome = String(body.nome || '').trim();
    if (nome) {
      const novoId = db.produtos.reduce((m, x) => Math.max(m, x.idProduto), 0) + 1;
      const ean = String(body.ean || '').trim() || ('7894900' + String(novoId).padStart(6, '0'));
      const preco = Number(body.preco || 49.90);
      const precoCusto = Number(body.precoCusto || (preco * 0.62));
      db.produtos.push({
        idProduto: novoId,
        idUsuario: uid,
        idFornecedor: null,
        codigoBarras: ean,
        nomeProduto: nome,
        categoria: String(body.categoria || 'Geral').trim(),
        preco,
        precoCusto: Number(precoCusto.toFixed(2)),
        quantidade: 10,
        estoqueMinimo: 5,
        localizacao: 'A-01-01',
        descricao: 'Produto importado via Sugestão Inteligente de Mercado MVM.',
        nomeFornecedor: '',
        lote: 'LOT-WEB-' + new Date().getFullYear(),
        dataValidade: null,
        imagem: null
      });
      await saveDb(db);
      session.flash_plm = {
        tipo: 'sucesso',
        texto: `🚀 Produto <strong>${esc(nome)}</strong> importado para o seu catálogo com sucesso! Pronto para vender.`
      };
    }
    return { redirect: '/modulos/relatorios/ciclo_vida.php' };
  }

  const { resumo, itens } = calcularCicloVidaProdutos(db, uid);
  const faseFiltro = String(query.fase || 'TODOS').toUpperCase();
  const itensExibidos = faseFiltro === 'TODOS' ? itens : itens.filter(i => i.fase === faseFiltro);
  const flash = session.flash_plm;
  delete session.flash_plm;

  const totalCapital = Object.values(resumo).reduce((s, r) => s + r.capital, 0);

  const html = `
<div class="page-container">
    ${flash ? `
        <div class="alert-box ${flash.tipo === 'sucesso' ? 'success' : 'error'}" style="margin-bottom: 20px;">
            <span>${flash.tipo === 'sucesso' ? '🔥' : '⚠️'}</span>
            <p>${flash.texto}</p>
        </div>
    ` : ''}

    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>♻️ Gestão Inteligente do Ciclo de Vida dos Produtos (PLM)</h1>
            <p>Classificação automática de maturidade comercial, cobertura de estoque, capital parado e ações de queima FEFO</p>
        </div>
        <div class="page-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="/modulos/produtos/produto.php" class="btn-secondary"><span>📦</span> Catálogo</a>
            <a href="/modulos/movimentos/movimento.php" class="btn-primary"><span>➕</span> Registrar Saída / Reposição</a>
        </div>
    </div>

    <!-- Cards de Fases do Ciclo de Vida -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        ${['LANCAMENTO', 'CRESCIMENTO', 'MATURIDADE', 'DECLINIO'].map(chave => {
          const info = resumo[chave];
          const ativo = faseFiltro === chave;
          return `
            <a href="/modulos/relatorios/ciclo_vida.php?fase=${ativo ? 'TODOS' : chave}" style="text-decoration:none; background: var(--card); border: 2px solid ${ativo ? info.cor : 'var(--border)'}; border-top: 4px solid ${info.cor}; border-radius: var(--radius); padding: 18px 20px; display: block; transition: transform 0.2s ease;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:12px; font-weight:800; text-transform:uppercase; color:${info.cor};">${info.icone} ${info.label}</span>
                    <span style="font-size:11px; font-weight:700; color:var(--text-muted);">${ativo ? 'Filtrado ✓' : 'Filtrar'}</span>
                </div>
                <div style="font-size:28px; font-weight:900; color:var(--text);">${info.qtd} <small style="font-size:13px; font-weight:600; color:var(--text-muted);">SKU(s)</small></div>
                <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Capital alocado: <strong style="color:var(--text);">R$ ${formatCurrencyBR(info.capital)}</strong></div>
            </a>`;
        }).join('')}
    </div>

    <!-- Gráfico + Resumo Estratégico -->
    <div class="pbi-grid-2cols" style="margin-bottom: 24px;">
        <div class="pbi-visual-card">
            <div class="pbi-visual-header">
                <div class="pbi-visual-title-box">
                    <h3><span>📊</span> Distribuição do Portfólio por Estágio (PLM)</h3>
                    <p>Quantidade de SKUs em Lançamento, Crescimento, Maturidade e Declínio</p>
                </div>
            </div>
            <div id="chart-plm-fases" style="min-height: 250px;"></div>
        </div>

        <div class="pbi-visual-card">
            <div class="pbi-visual-header">
                <div class="pbi-visual-title-box">
                    <h3><span>💡</span> Diagnóstico Automático de Capital</h3>
                    <p>Visão consolidada do capital imobilizado e oportunidades de giro</p>
                </div>
            </div>
            <div style="padding: 10px 4px; display:flex; flex-direction:column; gap:12px;">
                <div style="background:var(--bg-subtle); border:1px solid var(--border); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11.5px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Capital Total Imobilizado em Custo</div>
                    <div style="font-size:24px; font-weight:900; color:var(--primary); margin-top:2px;">R$ ${formatCurrencyBR(totalCapital)}</div>
                </div>
                <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.28); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11.5px; color:#ef4444; text-transform:uppercase; font-weight:800;">⚠️ Atenção: Capital em Declínio / Risco FEFO</div>
                    <div style="font-size:22px; font-weight:900; color:#ef4444; margin-top:2px;">R$ ${formatCurrencyBR(resumo.DECLINIO.capital)} <small style="font-size:12px; font-weight:600;">(${resumo.DECLINIO.qtd} item(ns))</small></div>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Use a ação de promoção automática abaixo para recuperar liquidez antes do vencimento.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtro -->
    <div class="table-filter-bar" style="flex-wrap:wrap; gap:10px;">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Filtrar produto, fase ou recomendação..." data-table-search="#tabela-plm">
        </div>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <a href="/modulos/relatorios/ciclo_vida.php?fase=TODOS" class="pbi-pill-btn ${faseFiltro === 'TODOS' ? 'active' : ''}" style="text-decoration:none;">Todos (${itens.length})</a>
            <a href="/modulos/relatorios/ciclo_vida.php?fase=LANCAMENTO" class="pbi-pill-btn ${faseFiltro === 'LANCAMENTO' ? 'active' : ''}" style="text-decoration:none;">🚀 Lançamento</a>
            <a href="/modulos/relatorios/ciclo_vida.php?fase=CRESCIMENTO" class="pbi-pill-btn ${faseFiltro === 'CRESCIMENTO' ? 'active' : ''}" style="text-decoration:none;">📈 Crescimento</a>
            <a href="/modulos/relatorios/ciclo_vida.php?fase=MATURIDADE" class="pbi-pill-btn ${faseFiltro === 'MATURIDADE' ? 'active' : ''}" style="text-decoration:none;">⚖️ Maturidade</a>
            <a href="/modulos/relatorios/ciclo_vida.php?fase=DECLINIO" class="pbi-pill-btn ${faseFiltro === 'DECLINIO' ? 'active' : ''}" style="text-decoration:none;">📉 Declínio / Risco</a>
        </div>
    </div>

    <!-- Tabela Inteligente PLM -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-plm">
                <thead>
                    <tr>
                        <th style="width: 24%;">Produto &amp; Preço</th>
                        <th style="width: 14%;">Estágio (PLM)</th>
                        <th style="width: 14%;">Giro &amp; Cobertura</th>
                        <th style="width: 14%;">Capital / Margem</th>
                        <th style="width: 18%;">Diagnóstico Inteligente</th>
                        <th style="width: 16%; text-align: right;">Ação Recomendada</th>
                    </tr>
                </thead>
                <tbody>
                    ${itensExibidos.length === 0 ? `
                        <tr class="empty-row">
                            <td colspan="6" style="text-align:center; padding:42px; color:var(--text-muted);">
                                <div style="font-size:32px; margin-bottom:8px;">♻️</div>
                                <strong>Nenhum produto encontrado neste estágio</strong>
                                <p style="font-size:12.5px; margin-top:4px;">Cadastre produtos ou registre vendas para alimentar a análise do Ciclo de Vida.</p>
                            </td>
                        </tr>
                    ` : itensExibidos.map(it => `
                        <tr>
                            <td>
                                <strong>${esc(it.nomeProduto)}</strong>
                                <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;">
                                    Estoque: <strong>${it.quantidade} un</strong> &bull; Preço: <strong>R$ ${formatCurrencyBR(it.preco)}</strong>
                                </div>
                            </td>
                            <td>
                                <span style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:800; background:${it.faseInfo.cor}1f; color:${it.faseInfo.cor}; border:1px solid ${it.faseInfo.cor}44;">
                                    ${it.faseInfo.icone} ${it.faseInfo.label}
                                </span>
                            </td>
                            <td>
                                <div style="font-size:12.5px; font-weight:700;">${it.totalVendido} un vendidas</div>
                                <div style="font-size:11px; color:var(--text-muted);">Cobertura: ${it.diasCobertura >= 999 ? 'Sem giro' : it.diasCobertura + ' dias'}</div>
                            </td>
                            <td>
                                <div style="font-family:'JetBrains Mono',monospace; font-size:12.5px; font-weight:700;">R$ ${formatCurrencyBR(it.capitalParado)}</div>
                                <div style="font-size:11px; color:${it.margemPct >= 20 ? '#10b981' : '#f59e0b'};">Margem: ${it.margemPct}%</div>
                            </td>
                            <td>
                                <div style="font-size:12px; color:var(--text); font-weight:600;">${esc(it.motivo)}</div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Sugestão: ${esc(it.acaoSugerida)}</div>
                            </td>
                            <td style="text-align: right;">
                                ${it.podePromocao ? `
                                    <form action="/modulos/relatorios/ciclo_vida.php" method="POST" style="display:inline;" onsubmit="return confirm('Aplicar desconto promocional de 15% neste produto para acelerar o giro?');">
                                        <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                                        <input type="hidden" name="acao" value="aplicar_promocao">
                                        <input type="hidden" name="idProduto" value="${it.idProduto}">
                                        <input type="hidden" name="desconto" value="15">
                                        <button type="submit" class="btn-primary" style="padding:6px 11px; font-size:11.5px; background:#ef4444; border:none;">🔥 Queima -15%</button>
                                    </form>
                                ` : `
                                    <a href="/modulos/relatorios/kardex.php?idProduto=${it.idProduto}" class="btn-secondary" style="padding:6px 11px; font-size:11.5px;">📜 Ver Kardex</a>
                                `}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    </div>

    <!-- SUGESTÕES INTELIGENTES DA INTERNET PARA AUMENTAR FATURAMENTO -->
    <div class="pbi-visual-card" style="margin-top: 26px;">
        <div class="pbi-visual-header" style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div class="pbi-visual-title-box">
                <h3><span>🌐</span> Sugestões de Produtos da Internet para Aumentar seu Faturamento</h3>
                <p id="plm-sugestoes-sub">Analisando seu perfil comercial e buscando oportunidades reais na web...</p>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="input-nicho-plm" placeholder="Ex: bebidas, informatica, cosmeticos..." style="padding:8px 12px; border-radius:8px; border:1px solid var(--border); background:var(--bg-subtle); color:var(--text); font-size:12.5px;">
                <button type="button" onclick="carregarSugestoesWeb()" class="btn-primary" style="padding:8px 14px; font-size:12.5px;">🔍 Buscar na Internet</button>
            </div>
        </div>
        <div id="grid-sugestoes-web" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap:16px;">
            <div style="grid-column:1/-1; text-align:center; padding:30px; color:var(--text-muted);">⏳ Buscando produtos compatíveis com o seu perfil na internet...</div>
        </div>
    </div>
</div>

<script>
async function carregarSugestoesWeb() {
    const grid = document.getElementById('grid-sugestoes-web');
    const sub = document.getElementById('plm-sugestoes-sub');
    const nicho = (document.getElementById('input-nicho-plm').value || '').trim();
    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:30px; color:var(--text-muted);">⏳ Pesquisando produtos na internet para o seu perfil...</div>';
    try {
        const resp = await fetch('/api/sugestoes_produtos.php' + (nicho ? '?nicho=' + encodeURIComponent(nicho) : ''));
        const data = await resp.json();
        if (!data.sucesso || !data.sugestoes || data.sugestoes.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:24px; color:var(--text-muted);">Nenhuma sugestão encontrada para este termo. Tente outro nicho acima.</div>';
            return;
        }
        sub.innerHTML = 'Perfil detectado: <strong>' + data.perfil_nicho + '</strong> &bull; Fonte: <strong>' + data.fonte + '</strong>';
        grid.innerHTML = data.sugestoes.map(s => {
            const imgHtml = s.imagem
                ? '<img src="' + s.imagem + '" alt="" style="width:58px;height:58px;object-fit:contain;border-radius:8px;background:#fff;padding:4px;border:1px solid var(--border);flex-shrink:0;">'
                : '<div style="width:58px;height:58px;border-radius:8px;background:var(--bg-subtle);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">🚀</div>';
            return '<div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;flex-direction:column;justify-content:space-between;gap:12px;">'
                + '<div style="display:flex;gap:12px;align-items:flex-start;">' + imgHtml
                + '<div><strong style="font-size:13.5px;color:var(--text);display:block;line-height:1.35;">' + s.nome + '</strong>'
                + '<span style="font-size:11px;color:#10b981;font-weight:700;display:block;margin-top:4px;">💡 ' + s.motivo + '</span></div></div>'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;background:var(--card);padding:10px;border-radius:8px;border:1px solid var(--border);font-size:12px;">'
                + '<div><span style="color:var(--text-muted);display:block;font-size:10.5px;">Preço Sugerido</span><strong style="color:var(--primary);font-size:14px;">R$ ' + Number(s.preco).toFixed(2).replace('.', ',') + '</strong></div>'
                + '<div><span style="color:var(--text-muted);display:block;font-size:10.5px;">Potencial Extra/Mês</span><strong style="color:#10b981;font-size:14px;">+R$ ' + Number(s.potencialMes).toFixed(2).replace('.', ',') + '</strong></div>'
                + '</div>'
                + '<div style="display:flex;gap:8px;align-items:center;justify-content:space-between;">'
                + '<a href="' + s.link + '" target="_blank" rel="noopener noreferrer" style="font-size:11.5px;color:#eab308;font-weight:700;text-decoration:none;">🛒 Ver no Mercado Livre ↗</a>'
                + '<form action="/modulos/relatorios/ciclo_vida.php" method="POST" style="margin:0;">'
                + '<input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">'
                + '<input type="hidden" name="acao" value="importar_sugestao">'
                + '<input type="hidden" name="nome" value="' + s.nome.replace(/"/g, '&quot;') + '">'
                + '<input type="hidden" name="categoria" value="' + (s.categoria || 'Geral').replace(/"/g, '&quot;') + '">'
                + '<input type="hidden" name="ean" value="' + (s.ean || '') + '">'
                + '<input type="hidden" name="preco" value="' + s.preco + '">'
                + '<input type="hidden" name="precoCusto" value="' + s.precoCusto + '">'
                + '<button type="submit" class="btn-primary" style="padding:7px 12px;font-size:11.5px;">➕ Importar para Catálogo</button>'
                + '</form></div></div>';
        }).join('');
    } catch (e) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:24px; color:#ef4444;">Erro ao consultar sugestões na internet.</div>';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    carregarSugestoesWeb();
    if (window.ApexCharts && document.querySelector('#chart-plm-fases')) {
        new ApexCharts(document.querySelector('#chart-plm-fases'), {
            chart: { type: 'donut', height: 250 },
            series: [${resumo.LANCAMENTO.qtd}, ${resumo.CRESCIMENTO.qtd}, ${resumo.MATURIDADE.qtd}, ${resumo.DECLINIO.qtd}],
            labels: ['🚀 Lançamento', '📈 Crescimento', '⚖️ Maturidade', '📉 Declínio / Risco'],
            colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
            legend: { position: 'bottom' }
        }).render();
    }
});
</script>`;

  return { html: renderLayout('Ciclo de Vida dos Produtos (PLM) - MVM', 'ciclo_vida.php', session, html) };
}

function handleApiDocs(req, db, session) {
  const uid = Number(session.idUsuario) || 1;
  const u = db.usuarios.find(x => x.idUsuario === uid) || db.usuarios[0];
  const tokenExemplo = (u && u.apiToken) || 'mvm_live_token_77a89b42';
  const host = (req.headers && req.headers.host) || 'mvm-estoque.vercel.app';
  const html = `
<div class="page-container" style="max-width:1050px;">
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>⚡ Portal de APIs RESTful v1 — MVM Estoque</h1>
            <p>Integre ERPs, e-commerces e coletores de dados diretamente ao seu armazém</p>
        </div>
        <div style="background:var(--card); border:1px solid var(--border); padding:10px 16px; border-radius:10px;">
            <span style="font-size:11px; color:var(--text-muted); display:block;">Sua Chave X-API-KEY:</span>
            <code style="font-weight:800; color:var(--primary); font-family:'JetBrains Mono',monospace;">${esc(tokenExemplo)}</code>
        </div>
    </div>
    <div class="table-card" style="padding:24px; margin-bottom:20px;">
        <h3 style="margin-top:0;"><span style="background:#dbeafe;color:#1e40af;padding:3px 9px;border-radius:4px;font-size:12px;">GET</span> /api/v1/estoque.php</h3>
        <p style="color:var(--text-muted); font-size:13.5px;">Consulta em tempo real todos os produtos, posições WMS, níveis críticos e valor total estocado.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:14px; border-radius:8px; overflow-x:auto; font-size:12px;">curl -H "X-API-KEY: ${esc(tokenExemplo)}" "https://${esc(host)}/api/v1/estoque.php"</pre>
        <a href="/api/v1/estoque.php?token=${esc(tokenExemplo)}" target="_blank" class="btn-primary" style="display:inline-flex; margin-top:8px;">🚀 Testar GET Agora no Navegador</a>
    </div>
    <div class="table-card" style="padding:24px;">
        <h3 style="margin-top:0;"><span style="background:#dcfce7;color:#166534;padding:3px 9px;border-radius:4px;font-size:12px;">POST</span> /api/v1/estoque.php</h3>
        <p style="color:var(--text-muted); font-size:13.5px;">Registra automaticamente uma entrada ou saída de mercadoria com atualização de saldo.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:14px; border-radius:8px; overflow-x:auto; font-size:12px;">curl -X POST -H "Content-Type: application/json" -H "X-API-KEY: ${esc(tokenExemplo)}" \\
  -d '{"idProduto": 1, "tipoMovimento": "SAIDA", "quantidade": 2, "observacao": "Pedido E-commerce"}' \\
  "https://${esc(host)}/api/v1/estoque.php"</pre>
    </div>
</div>`;
  return { html: renderLayout('Documentação & Teste de APIs - MVM', 'api_docs.php', session, html) };
}

module.exports = {
  handleMovimentos,
  handleKardex,
  handleEtiquetas,
  handleCicloVida,
  handleMascote,
  handleApiRest,
  handleApiDocs
};
