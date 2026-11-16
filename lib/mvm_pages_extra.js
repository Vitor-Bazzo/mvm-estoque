const { esc, renderLayout } = require('./mvm_layout');
const { saveDb, addDaysDateTimeStr, formatDateBR, formatDateTimeBR, formatCurrencyBR } = require('./mvm_db');

function handleMovimentos(req, body, query, db, session, subRoute) {
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
      saveDb(db);
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
      saveDb(db);
    }
    return { redirect: '/modulos/movimentos/movimento.php' };
  }

  if (subRoute === 'recibo.php') {
    const id = Number(query.id || 0);
    const mov = db.movimentos.find(m => m.idMovimento === id && Number(m.idUsuario) === uid) || db.movimentos[0];
    const prod = db.produtos.find(p => p.idProduto === Number(mov.idProduto)) || { nomeProduto: 'Produto MVM', preco: 0 };
    const cli = db.clientes.find(c => c.idCliente === Number(mov.idCliente));
    const htmlRecibo = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Recibo #${mov.idMovimento} - MVM</title><style>body{font-family:monospace;background:#f8fafc;padding:30px;} .ticket{max-width:380px;margin:0 auto;background:#fff;padding:24px;border:1px dashed #334155;border-radius:8px;}</style></head><body><div class="ticket"><h2 style="text-align:center;margin:0 0 6px;">MVM ESTOQUE &amp; LOGÍSTICA</h2><p style="text-align:center;margin:0 0 16px;font-size:12px;">Comprovante de Movimentação #${mov.idMovimento}<br>Pedido: ${esc(mov.codigoPedido || 'PED-AVULSO')}</p><hr><p><strong>Data:</strong> ${esc(formatDateTimeBR(mov.dataMovimento))}<br><strong>Tipo:</strong> ${esc(mov.tipoMovimento)}<br><strong>Cliente:</strong> ${esc(cli ? cli.nomeCliente : 'Consumidor Balcão')}</p><hr><p><strong>Item:</strong> ${esc(prod.nomeProduto)}<br><strong>Qtd:</strong> ${mov.quantidade} un x R$ ${formatCurrencyBR(mov.precoUnitario || prod.preco)}<br><strong>TOTAL: R$ ${formatCurrencyBR(mov.valorTotal || (mov.quantidade * prod.preco))}</strong></p><hr><div style="text-align:center;margin-top:16px;"><button onclick="window.print()" style="padding:8px 16px;cursor:pointer;">🖨️ Imprimir Cupom</button> <a href="/modulos/movimentos/movimento.php" style="margin-left:10px;">Voltar</a></div></div></body></html>`;
    return { html: htmlRecibo };
  }

  let msg = '';
  let erro = '';
  if (req.method === 'POST') {
    const tipo = String(body.tipoMovimento || 'SAIDA').toUpperCase();
    const idProd = Number(body.idProduto || (Array.isArray(body['idProduto[]']) ? body['idProduto[]'][0] : body['idProduto[]']) || 0);
    const qtd = Number(body.quantidade || (Array.isArray(body['quantidade[]']) ? body['quantidade[]'][0] : body['quantidade[]']) || 0);
    const prod = prods.find(p => p.idProduto === idProd);

    if (!prod || qtd <= 0) {
      erro = 'Selecione um produto válido e informe uma quantidade maior que zero.';
    } else if (tipo === 'SAIDA' && prod.quantidade < qtd) {
      erro = `Estoque insuficiente para "${prod.nomeProduto}". Saldo disponível: ${prod.quantidade} un.`;
    } else {
      if (tipo === 'SAIDA') prod.quantidade -= qtd;
      else if (tipo === 'ENTRADA') prod.quantidade += qtd;
      else if (tipo === 'AJUSTE') {
        const dir = String(body.direcaoAjuste || 'SOMAR').toUpperCase();
        prod.quantidade = dir === 'SUBTRAIR' ? Math.max(0, prod.quantidade - qtd) : prod.quantidade + qtd;
      }

      const novoId = db.movimentos.reduce((m, x) => Math.max(m, x.idMovimento), 0) + 1;
      const dtMov = addDaysDateTimeStr(0);
      const ymd = dtMov.slice(0, 10).replace(/-/g, '');
      db.movimentos.unshift({
        idMovimento: novoId,
        idUsuario: uid,
        codigoPedido: `PED-${ymd}-${String(novoId).padStart(4, '0')}`,
        idProduto: prod.idProduto,
        idCliente: body.idCliente ? Number(body.idCliente) : null,
        idFornecedor: body.idFornecedor ? Number(body.idFornecedor) : null,
        tipoMovimento: tipo,
        quantidade: qtd,
        observacao: String(body.observacao || `Lançamento ${tipo} via PDV Cloud`).trim(),
        lote: prod.lote,
        dataValidade: prod.dataValidade,
        motivoAjuste: body.motivoAjuste || null,
        precoUnitario: prod.preco,
        custoUnitario: prod.precoCusto,
        valorTotal: Number((qtd * prod.preco).toFixed(2)),
        dataMovimento: dtMov,
        dataDevolucao: null
      });
      saveDb(db);
      msg = `Movimentação (${tipo}) de ${qtd}x "${prod.nomeProduto}" registrada com sucesso!`;
    }
  }

  const movs = db.movimentos.filter(m => Number(m.idUsuario) === uid).slice().sort((a, b) => b.idMovimento - a.idMovimento);

  const html = `
<div class="page-container">
    <h1 style="margin:0 0 18px;">↔️ PDV &amp; Controle de Movimentações de Estoque</h1>
    ${msg ? `<div class="alert-box success" style="margin-bottom:18px;"><span>✅</span><p>${esc(msg)}</p></div>` : ''}
    ${erro ? `<div class="alert-box error" style="margin-bottom:18px;"><span>⚠️</span><p>${esc(erro)}</p></div>` : ''}

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; margin-bottom:26px;">
        <h3 style="margin-top:0;">⚡ Registrar Nova Operação (Saída / Entrada / Ajuste)</h3>
        <form action="/modulos/movimentos/movimento.php" method="POST">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:14px;">
                <div class="form-group">
                    <label>Tipo de Operação *</label>
                    <select name="tipoMovimento" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-surface-2); color:var(--text-primary); font-weight:700;">
                        <option value="SAIDA">📤 SAÍDA (Venda ao Cliente)</option>
                        <option value="ENTRADA">📥 ENTRADA (Compra / Reposição)</option>
                        <option value="AJUSTE">⚖️ AJUSTE (Auditoria de Inventário)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Produto *</label>
                    <select name="idProduto" required style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-surface-2); color:var(--text-primary);">
                        ${prods.map(p => `<option value="${p.idProduto}">${esc(p.nomeProduto)} (Saldo: ${p.quantidade} un — R$ ${formatCurrencyBR(p.preco)})</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantidade *</label>
                    <input type="number" name="quantidade" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label>Cliente (Para Vendas)</label>
                    <select name="idCliente" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-surface-2); color:var(--text-primary);">
                        <option value="">Consumidor Balcão</option>
                        ${clientes.map(c => `<option value="${c.idCliente}">${esc(c.nomeCliente)}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Observação / Nota Fiscal</label>
                    <input type="text" name="observacao" placeholder="Ex: Venda balcão / NF-e">
                </div>
            </div>
            <button type="submit" class="btn-submit" style="width:auto; padding:10px 26px; margin-top:8px;">Confirmar Lançamento</button>
        </form>
    </div>

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; overflow-x:auto;">
        <h3 style="margin-top:0;">📜 Histórico de Movimentações (${movs.length})</h3>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--border-subtle); text-align:left;">
                    <th style="padding:10px;">Pedido / Data</th>
                    <th style="padding:10px;">Tipo</th>
                    <th style="padding:10px;">Produto</th>
                    <th style="padding:10px;">Qtd</th>
                    <th style="padding:10px;">Total (R$)</th>
                    <th style="padding:10px;">Observação</th>
                    <th style="padding:10px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                ${movs.map(m => {
                  const p = prods.find(x => x.idProduto === Number(m.idProduto));
                  const cor = m.tipoMovimento === 'SAIDA' ? '#ef4444' : (m.tipoMovimento === 'ENTRADA' ? '#10b981' : '#f59e0b');
                  return `
                    <tr style="border-bottom:1px solid var(--border-subtle); opacity:${m.dataDevolucao ? '0.55' : '1'};">
                        <td style="padding:10px;"><strong>${esc(m.codigoPedido || '#' + m.idMovimento)}</strong><br><span style="font-size:11px; color:var(--text-muted);">${esc(formatDateTimeBR(m.dataMovimento))}</span></td>
                        <td style="padding:10px;"><span style="color:${cor}; font-weight:800;">${esc(m.tipoMovimento)}</span>${m.dataDevolucao ? '<br><small>(Devolvido)</small>' : ''}</td>
                        <td style="padding:10px;"><strong>${esc(p ? p.nomeProduto : 'Produto #' + m.idProduto)}</strong></td>
                        <td style="padding:10px;"><strong>${m.quantidade} un</strong></td>
                        <td style="padding:10px;">R$ ${formatCurrencyBR(m.valorTotal)}</td>
                        <td style="padding:10px;">${esc(m.observacao || '')}</td>
                        <td style="padding:10px; display:flex; gap:8px; flex-wrap:wrap;">
                            <a href="/modulos/movimentos/recibo.php?id=${m.idMovimento}" target="_blank" style="color:#38bdf8; font-weight:700; text-decoration:none;">🧾 Recibo</a>
                            ${!m.dataDevolucao && m.tipoMovimento === 'SAIDA' ? `<a href="/modulos/movimentos/movimento_devolucao.php?id=${m.idMovimento}" style="color:#f59e0b; font-weight:700; text-decoration:none;">↩️ Devolver</a>` : ''}
                            <a href="/modulos/movimentos/movimento_excluir.php?id=${m.idMovimento}" onclick="return confirm('Estornar e excluir registro?')" style="color:#ef4444; font-weight:700; text-decoration:none;">Excluir</a>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
    </div>
</div>`;

  return { html: renderLayout('Movimentações e Vendas - MVM', 'movimento.php', session, html) };
}

function handleKardex(req, query, db, session) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const idProd = Number(query.produto || (prods[0] && prods[0].idProduto) || 1);
  const prod = prods.find(p => p.idProduto === idProd) || prods[0];
  const movs = db.movimentos.filter(m => Number(m.idUsuario) === uid && Number(m.idProduto) === (prod ? prod.idProduto : 0));

  const html = `
<div class="page-container">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <div>
            <h1 style="margin:0;">📜 Ficha Kardex &amp; Rastreabilidade Fiscal</h1>
            <p style="margin:4px 0 0; color:var(--text-secondary); font-size:13.5px;">Auditoria contábil de entradas, saídas, Custo Médio Ponderado (CMP) e margem</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="/exportacao/relatorio_kardex_pdf.php?produto=${prod ? prod.idProduto : 1}" target="_blank" class="btn-submit" style="width:auto; padding:9px 16px; text-decoration:none; background:#ef4444;">📄 Exportar PDF</a>
            <a href="/exportacao/relatorio_kardex_excel.php?produto=${prod ? prod.idProduto : 1}" class="btn-submit" style="width:auto; padding:9px 16px; text-decoration:none; background:#10b981;">📊 Exportar Excel</a>
        </div>
    </div>

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:20px; margin-bottom:22px;">
        <form action="/modulos/relatorios/kardex.php" method="GET" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0; flex:1; min-width:240px;">
                <label>Selecionar SKU para Auditoria Kardex</label>
                <select name="produto" onchange="this.form.submit()" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-surface-2); color:var(--text-primary); font-weight:700;">
                    ${prods.map(p => `<option value="${p.idProduto}" ${prod && p.idProduto === prod.idProduto ? 'selected' : ''}>#${p.idProduto} - ${esc(p.nomeProduto)} (Saldo: ${p.quantidade} un)</option>`).join('')}
                </select>
            </div>
        </form>
    </div>

    ${prod ? `
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:22px;">
            <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:14px; padding:16px;">
                <div style="font-size:11.5px; color:var(--text-muted);">Saldo Atual em Estoque</div>
                <div style="font-size:24px; font-weight:900; color:#10b981;">${prod.quantidade} un</div>
                <div style="font-size:12px;">Posição WMS: <code>${esc(prod.localizacao || 'A-01-01')}</code></div>
            </div>
            <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:14px; padding:16px;">
                <div style="font-size:11.5px; color:var(--text-muted);">Custo Médio Ponderado (CMP)</div>
                <div style="font-size:24px; font-weight:900;">R$ ${formatCurrencyBR(prod.precoCusto)}</div>
                <div style="font-size:12px;">Preço de Venda: R$ ${formatCurrencyBR(prod.preco)}</div>
            </div>
            <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:14px; padding:16px;">
                <div style="font-size:11.5px; color:var(--text-muted);">Valor Total Estocado (Venda)</div>
                <div style="font-size:24px; font-weight:900; color:#3b82f6;">R$ ${formatCurrencyBR(prod.quantidade * prod.preco)}</div>
                <div style="font-size:12px;">Lote: ${esc(prod.lote || 'Padrão')}</div>
            </div>
        </div>
    ` : ''}

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--border-subtle); text-align:left;">
                    <th style="padding:10px;">Data / Hora</th>
                    <th style="padding:10px;">Documento</th>
                    <th style="padding:10px;">Operação</th>
                    <th style="padding:10px;">Quantidade</th>
                    <th style="padding:10px;">Valor Unit.</th>
                    <th style="padding:10px;">Valor Total</th>
                    <th style="padding:10px;">Histórico</th>
                </tr>
            </thead>
            <tbody>
                ${movs.map(m => `
                    <tr style="border-bottom:1px solid var(--border-subtle);">
                        <td style="padding:10px;">${esc(formatDateTimeBR(m.dataMovimento))}</td>
                        <td style="padding:10px;"><code>${esc(m.codigoPedido || '#' + m.idMovimento)}</code></td>
                        <td style="padding:10px;"><strong>${esc(m.tipoMovimento)}</strong></td>
                        <td style="padding:10px;">${m.quantidade} un</td>
                        <td style="padding:10px;">R$ ${formatCurrencyBR(m.precoUnitario)}</td>
                        <td style="padding:10px;">R$ ${formatCurrencyBR(m.valorTotal)}</td>
                        <td style="padding:10px;">${esc(m.observacao || '')}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
</div>`;

  return { html: renderLayout('Ficha Kardex - MVM', 'kardex.php', session, html) };
}

function handleEtiquetas(req, db, session) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);

  const html = `
<div class="page-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1 style="margin:0;">🏷️ Central de Etiquetas Térmicas &amp; Código de Barras EAN-13</h1>
            <p style="margin:4px 0 0; color:var(--text-secondary); font-size:13.5px;">Etiquetas prontas para impressão térmica de gôndola e endereçamento WMS</p>
        </div>
        <button onclick="window.print()" class="btn-submit" style="width:auto; padding:10px 22px;">🖨️ Imprimir Etiquetas</button>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:16px;">
        ${prods.map(p => `
            <div style="background:#fff; color:#0f172a; border:2px dashed #94a3b8; border-radius:12px; padding:16px; text-align:center;">
                <div style="font-size:11px; font-weight:800; color:#475569; text-transform:uppercase;">${esc(p.categoria || 'MVM')} &bull; WMS: ${esc(p.localizacao || 'A-01-01')}</div>
                <div style="font-size:14px; font-weight:800; margin:6px 0;">${esc(p.nomeProduto)}</div>
                <div style="font-size:22px; font-weight:900; color:#00704A; margin-bottom:8px;">R$ ${formatCurrencyBR(p.preco)}</div>
                <svg class="barcode" jsbarcode-value="${esc(p.codigoBarras || '7894900011517')}" jsbarcode-height="40" jsbarcode-width="1.5" jsbarcode-fontsize="12"></svg>
            </div>
        `).join('')}
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.JsBarcode) {
        JsBarcode(".barcode").init();
    }
});
</script>`;

  return { html: renderLayout('Etiquetas e Códigos de Barras - MVM', 'etiquetas.php', session, html) };
}

function handleMascote(session) {
  const html = `
<div class="page-container">
    <div style="max-width:740px; margin:20px auto; background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:20px; padding:32px; text-align:center;">
        <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM" style="width:130px; height:130px; border-radius:50%; box-shadow:0 10px 28px rgba(0,112,74,0.35); margin-bottom:16px;">
        <h1 style="margin:0 0 10px;">🐜 Conheça o Mascote Oficial MVM</h1>
        <p style="color:var(--text-secondary); line-height:1.7; font-size:15px;">
            A formiga engenheira da <strong>MVM Gestão de Estoque</strong> simboliza organização coletiva, precisão logística, força operacional e trabalho incansável no controle de armazéns de alta performance.
        </p>
        <div style="margin-top:22px;">
            <a href="/index.php" class="btn-submit" style="width:auto; padding:10px 24px; text-decoration:none; display:inline-block;">← Voltar ao Dashboard</a>
        </div>
    </div>
</div>`;
  return { html: renderLayout('Mascote Oficial - MVM', 'mascote.php', session, html) };
}

function handleApiRest(req, body, query, db, session) {
  const uid = Number(session.idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  return {
    json: {
      status: 'success',
      versao_api: '1.0.0',
      ambiente: 'Vercel Cloud Serverless',
      data_consulta: new Date().toISOString(),
      total_itens: prods.length,
      dados: prods.map(p => ({
        id: p.idProduto,
        codigo_barras: p.codigoBarras,
        nome: p.nomeProduto,
        categoria: p.categoria,
        estoque_atual: p.quantidade,
        estoque_minimo: p.estoqueMinimo,
        localizacao_wms: p.localizacao,
        preco_venda: p.preco,
        preco_custo: p.precoCusto
      }))
    }
  };
}

function handleExportacao(relPath, query, db, session) {
  const uid = Number(session.idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Relatório Oficial MVM Estoque</title><style>body{font-family:sans-serif;padding:30px;color:#0f172a;} table{width:100%;border-collapse:collapse;margin-top:20px;} th,td{border:1px solid #cbd5e1;padding:8px 10px;text-align:left;font-size:12px;} th{background:#f1f5f9;}</style></head><body onload="window.print()"><div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #00704A;padding-bottom:12px;"><div><h1 style="margin:0;font-size:20px;">MVM GESTÃO DE ESTOQUE &amp; LOGÍSTICA</h1><p style="margin:4px 0 0;font-size:12px;">Relatório Oficial de Inventário e Balanço Patrimonial</p></div><button onclick="window.print()" style="padding:8px 16px;cursor:pointer;">🖨️ Imprimir / Salvar PDF</button></div><table><thead><tr><th>ID</th><th>EAN-13</th><th>Produto</th><th>Categoria</th><th>WMS</th><th>Saldo</th><th>Custo Unit.</th><th>Venda Unit.</th><th>Subtotal Venda</th></tr></thead><tbody>${prods.map(p => `<tr><td>#${p.idProduto}</td><td>${esc(p.codigoBarras || '')}</td><td>${esc(p.nomeProduto)}</td><td>${esc(p.categoria || '')}</td><td>${esc(p.localizacao || 'A-01-01')}</td><td>${p.quantidade} un</td><td>R$ ${formatCurrencyBR(p.precoCusto)}</td><td>R$ ${formatCurrencyBR(p.preco)}</td><td>R$ ${formatCurrencyBR(p.quantidade * p.preco)}</td></tr>`).join('')}</tbody></table></body></html>`;
  return { html };
}

module.exports = {
  handleMovimentos,
  handleKardex,
  handleEtiquetas,
  handleMascote,
  handleApiRest,
  handleExportacao
};
