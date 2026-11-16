const { esc, renderLayout } = require('./mvm_layout');
const { saveDb, formatDateBR, formatCurrencyBR, obterDadosDashboardBI } = require('./mvm_db');

function handleDashboard(req, query, db, session) {
  if (!session.usuarioLogado) {
    const landing = `
<div class="page-container">
    <div class="welcome-hero" style="text-align:center; padding:60px 24px; background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:20px; max-width:760px; margin:40px auto;">
        <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM" style="width:96px; height:96px; border-radius:50%; margin-bottom:16px; box-shadow:0 8px 24px rgba(0,112,74,0.35);">
        <h1 style="font-size:32px; font-weight:900; margin:0 0 12px; color:var(--text-primary);">MVM Gestão de Estoque &amp; BI</h1>
        <p style="font-size:15.5px; color:var(--text-secondary); max-width:560px; margin:0 auto 26px; line-height:1.6;">
            Plataforma inteligente para controle de inventário WMS, frente de caixa PDV com código de barras, ficha fiscal Kardex e Dashboard Power BI em tempo real.
        </p>
        <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
            <a href="/modulos/auth/login.php" class="btn-submit" style="width:auto; padding:12px 28px; text-decoration:none; display:inline-block;">🔐 Entrar no Sistema</a>
            <a href="/modulos/auth/criar_conta.php" class="btn-secondary" style="padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:700; border:1px solid var(--border-subtle); color:var(--text-primary);">✨ Criar Nova Conta</a>
            <a href="/modulos/planos/planos.php" style="padding:12px 22px; border-radius:10px; text-decoration:none; font-weight:700; background:rgba(229,9,20,0.12); color:#e50914; border:1px solid rgba(229,9,20,0.3);">💎 Ver Planos</a>
        </div>
    </div>
</div>`;
    return { html: renderLayout('Dashboard de Vendas - Power BI', 'index.php', session, landing) };
  }

  const uid = Number(session.idUsuario) || 1;
  const dadosBI = obterDadosDashboardBI(db, uid);
  const flash = session.flash_dashboard;
  delete session.flash_dashboard;

  const mesBalanco = String(query.mes_balanco || new Date().toISOString().slice(0, 7));

  const html = `
<div class="page-container">
    ${flash ? `
        <div class="alert-box ${flash.tipo === 'sucesso' ? 'success' : 'error'}" style="margin-bottom: 24px;">
            <span>${flash.tipo === 'sucesso' ? '✨' : '⚠️'}</span>
            <p>${flash.texto}</p>
        </div>
    ` : ''}

    <div class="powerbi-wrapper">
        <div class="pbi-header-bar">
            <div class="pbi-header-info">
                <div class="pbi-logo-badge" title="Visual Power BI">📊</div>
                <div class="pbi-title-group">
                    <h1>MVM Analytics &bull; Visão Executiva de Vendas e Estoque</h1>
                    <p>Painel interativo em tempo real alimentado pelo banco de dados em nuvem MVM</p>
                </div>
            </div>
            <div class="pbi-status-pill" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <div style="display: inline-flex; align-items: center; gap: 4px; background: rgba(15, 23, 42, 0.45); padding: 3px 8px; border-radius: 8px; border: 1px solid rgba(148, 163, 184, 0.25);">
                    <label for="mes_balanco_dash" style="font-size: 11px; color: #cbd5e1; font-weight: 600;">Mês:</label>
                    <input type="month" id="mes_balanco_dash" value="${esc(mesBalanco)}" style="background: transparent; border: none; color: #fff; font-size: 11.5px; font-weight: 700; outline: none; cursor: pointer;">
                </div>
                <a href="/exportacao/relatorio_balanco_pdf.php?mes=${esc(mesBalanco)}" target="_blank" style="text-decoration: none; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.35); padding: 5px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 700;">📄 Balanço PDF</a>
                <a href="/exportacao/relatorio_balanco_excel.php?mes=${esc(mesBalanco)}" style="text-decoration: none; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.35); padding: 5px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 700;">📊 Balanço Excel</a>
                <span class="pbi-live-dot"></span>
                <span>Conectado ao Banco MVM</span>
            </div>
        </div>

        ${dadosBI.total_produtos_estoque_baixo > 0 ? `
            <div class="stock-alert-banner" role="alert">
                <div class="stock-alert-header">
                    <div class="stock-alert-title-group">
                        <span class="stock-alert-icon">🚨</span>
                        <div>
                            <h3>Alerta Crítico de Reposição de Estoque</h3>
                            <p>Existem <strong>${dadosBI.total_produtos_estoque_baixo} produto(s)</strong> abaixo do estoque mínimo de segurança.</p>
                        </div>
                    </div>
                    <div class="stock-alert-actions">
                        <a href="/modulos/movimentos/movimento.php" class="btn-alert-replenish">+ Registrar Entrada de Estoque</a>
                    </div>
                </div>
            </div>
        ` : ''}

        <!-- KPIs -->
        <div class="pbi-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
            <div class="pbi-kpi-card kpi-green">
                <div class="pbi-kpi-top">
                    <span class="pbi-kpi-label">Faturamento Bruto (Vendas)</span>
                    <div class="pbi-kpi-icon">💰</div>
                </div>
                <div class="pbi-kpi-value">R$ ${formatCurrencyBR(dadosBI.kpis.faturamento_total)}</div>
                <div class="pbi-kpi-sub"><strong>${dadosBI.kpis.total_vendas_qtd}</strong> vendas concluídas</div>
            </div>

            <div class="pbi-kpi-card kpi-blue">
                <div class="pbi-kpi-top">
                    <span class="pbi-kpi-label">Lucro Bruto &amp; Margem</span>
                    <div class="pbi-kpi-icon">📈</div>
                </div>
                <div class="pbi-kpi-value" style="color: ${dadosBI.kpis.lucro_bruto >= 0 ? '#10b981' : '#ef4444'};">
                    R$ ${formatCurrencyBR(dadosBI.kpis.lucro_bruto)}
                </div>
                <div class="pbi-kpi-sub">
                    Margem: <strong>${String(dadosBI.kpis.margem_lucro).replace('.', ',')}%</strong> &bull; CMV: R$ ${formatCurrencyBR(dadosBI.kpis.cmv_total)}
                </div>
            </div>

            <div class="pbi-kpi-card kpi-amber">
                <div class="pbi-kpi-top">
                    <span class="pbi-kpi-label">Unidades em Estoque</span>
                    <div class="pbi-kpi-icon">📦</div>
                </div>
                <div class="pbi-kpi-value">${Number(dadosBI.kpis.itens_em_estoque).toLocaleString('pt-BR')} un</div>
                <div class="pbi-kpi-sub">Distribuídas em <strong>${dadosBI.kpis.total_categorias}</strong> categorias</div>
            </div>

            <div class="pbi-kpi-card kpi-purple">
                <div class="pbi-kpi-top">
                    <span class="pbi-kpi-label">Ticket Médio / Campeão</span>
                    <div class="pbi-kpi-icon">🏆</div>
                </div>
                <div class="pbi-kpi-value">R$ ${formatCurrencyBR(dadosBI.kpis.ticket_medio)}</div>
                <div class="pbi-kpi-sub" title="${esc(dadosBI.kpis.produto_campeao)}">Líder: <strong>${esc(dadosBI.kpis.produto_campeao)}</strong></div>
            </div>
        </div>

        <!-- 3 GRÁFICOS APEXCHARTS -->
        <div class="pbi-charts-grid">
            <div class="pbi-visual-card">
                <div class="pbi-visual-header">
                    <div>
                        <div class="pbi-visual-title"><span>🏅</span> Top 5 Produtos Mais Vendidos</div>
                        <div class="pbi-visual-subtitle">Ranking por volume de unidades vendidas (Saídas)</div>
                    </div>
                    <span class="pbi-visual-badge">Barras Horizontais</span>
                </div>
                <div class="pbi-visual-body"><div id="chartTopProdutos" class="pbi-chart-canvas"></div></div>
            </div>

            <div class="pbi-visual-card">
                <div class="pbi-visual-header">
                    <div>
                        <div class="pbi-visual-title"><span>🍩</span> Participação por Categorias (%)</div>
                        <div class="pbi-visual-subtitle">Proporção de itens por departamento em estoque</div>
                    </div>
                    <span class="pbi-visual-badge">Donut Chart</span>
                </div>
                <div class="pbi-visual-body"><div id="chartCategorias" class="pbi-chart-canvas"></div></div>
            </div>

            <div class="pbi-visual-card pbi-span-full">
                <div class="pbi-visual-header">
                    <div>
                        <div class="pbi-visual-title"><span>📈</span> Evolução Diária de Vendas em Reais (R$)</div>
                        <div class="pbi-visual-subtitle">Histórico financeiro consolidado dia a dia</div>
                    </div>
                    <span class="pbi-visual-badge">Série Temporal (R$)</span>
                </div>
                <div class="pbi-visual-body"><div id="chartVendasDiarias" class="pbi-chart-canvas"></div></div>
            </div>
        </div>

        <!-- CURVA ABC + REPOSIÇÃO WHATSAPP -->
        <div class="pbi-charts-grid" style="margin-top: 22px;">
            <div class="pbi-visual-card">
                <div class="pbi-visual-header">
                    <div>
                        <div class="pbi-visual-title"><span>📊</span> Curva ABC de Estoque (Lei de Pareto 80/20)</div>
                        <div class="pbi-visual-subtitle">Classificação estratégica por relevância financeira</div>
                    </div>
                    <span class="pbi-visual-badge">Inteligência ABC</span>
                </div>
                <div class="pbi-visual-body" style="min-height:auto; display:block;">
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:14px;">
                        <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); padding:10px; border-radius:10px; text-align:center;">
                            <div style="font-size:11px; font-weight:800; color:#10b981;">CLASSE A (80% Fat.)</div>
                            <div style="font-size:20px; font-weight:800;">${dadosBI.curva_abc.A.qtd_itens} itens</div>
                            <div style="font-size:11px;">R$ ${formatCurrencyBR(dadosBI.curva_abc.A.total_faturado)}</div>
                        </div>
                        <div style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3); padding:10px; border-radius:10px; text-align:center;">
                            <div style="font-size:11px; font-weight:800; color:#3b82f6;">CLASSE B (15% Fat.)</div>
                            <div style="font-size:20px; font-weight:800;">${dadosBI.curva_abc.B.qtd_itens} itens</div>
                            <div style="font-size:11px;">R$ ${formatCurrencyBR(dadosBI.curva_abc.B.total_faturado)}</div>
                        </div>
                        <div style="background:rgba(148,163,184,0.12); border:1px solid rgba(148,163,184,0.3); padding:10px; border-radius:10px; text-align:center;">
                            <div style="font-size:11px; font-weight:800; color:#64748b;">CLASSE C (5% Fat.)</div>
                            <div style="font-size:20px; font-weight:800;">${dadosBI.curva_abc.C.qtd_itens} itens</div>
                            <div style="font-size:11px;">R$ ${formatCurrencyBR(dadosBI.curva_abc.C.total_faturado)}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pbi-visual-card">
                <div class="pbi-visual-header">
                    <div>
                        <div class="pbi-visual-title"><span>🛒</span> Sugestão Automática de Compra (Ponto de Pedido)</div>
                        <div class="pbi-visual-subtitle">Cálculo de lote ideal de reposição e cotação no WhatsApp</div>
                    </div>
                    <span class="pbi-visual-badge">Automação de Compras</span>
                </div>
                <div class="pbi-visual-body" style="min-height:auto; display:block;">
                    ${dadosBI.sugestoes_reposicao.length === 0 ? `
                        <div style="padding:18px; text-align:center; color:#10b981; font-weight:700;">✅ Todos os produtos estão acima do ponto de pedido!</div>
                    ` : dadosBI.sugestoes_reposicao.map(sr => `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border-radius:10px; background:var(--bg-surface-2); border:1px solid var(--border-subtle); margin-bottom:8px; gap:10px; flex-wrap:wrap;">
                            <div>
                                <div style="font-weight:700; font-size:13px;">${esc(sr.nomeProduto)}</div>
                                <div style="font-size:11.5px; color:var(--text-muted);">Saldo: <strong style="color:#ef4444;">${sr.estoqueAtual} un</strong> (Mín: ${sr.estoqueMinimo}) &bull; Sugerido comprar: <strong>+${sr.qtdSugerida} un</strong></div>
                            </div>
                            ${sr.linkWhatsApp ? `<a href="${esc(sr.linkWhatsApp)}" target="_blank" style="background:#25D366; color:#fff; text-decoration:none; padding:6px 12px; border-radius:8px; font-size:11.5px; font-weight:700;">💬 Pedir no WhatsApp</a>` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const topNomes = ${JSON.stringify(dadosBI.top_produtos.nomes)};
    const topQtds  = ${JSON.stringify(dadosBI.top_produtos.quantidades)};
    const catLabels = ${JSON.stringify(dadosBI.categorias_estoque.labels)};
    const catSeries = ${JSON.stringify(dadosBI.categorias_estoque.series)};
    const diaDatas  = ${JSON.stringify(dadosBI.vendas_diarias.datas_curtas)};
    const diaVals   = ${JSON.stringify(dadosBI.vendas_diarias.valores)};

    if (window.ApexCharts) {
        new ApexCharts(document.querySelector("#chartTopProdutos"), {
            chart: { type: 'bar', height: 280, toolbar: { show: false } },
            series: [{ name: 'Unidades Vendidas', data: topQtds }],
            xaxis: { categories: topNomes },
            plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
            colors: ['#00704A']
        }).render();

        new ApexCharts(document.querySelector("#chartCategorias"), {
            chart: { type: 'donut', height: 280 },
            series: catSeries,
            labels: catLabels,
            colors: ['#00704A', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899']
        }).render();

        new ApexCharts(document.querySelector("#chartVendasDiarias"), {
            chart: { type: 'area', height: 290, toolbar: { show: false } },
            series: [{ name: 'Faturamento (R$)', data: diaVals }],
            xaxis: { categories: diaDatas },
            stroke: { curve: 'smooth', width: 3 },
            colors: ['#10b981'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.05 } }
        }).render();
    }
});
</script>`;

  return { html: renderLayout('Dashboard de Vendas - Power BI', 'index.php', session, html) };
}

function handleProdutos(req, body, query, db, session, subRoute) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;

  if (subRoute === 'produto_excluir.php') {
    const id = Number(body.id || query.id || 0);
    if (id > 0) {
      db.produtos = db.produtos.filter(p => !(p.idProduto === id && Number(p.idUsuario) === uid));
      saveDb(db);
    }
    return { redirect: '/modulos/produtos/produto.php' };
  }

  if (subRoute === 'produto_editar.php') {
    const id = Number(body.idProduto || query.id || 0);
    const prod = db.produtos.find(p => p.idProduto === id && Number(p.idUsuario) === uid);
    if (!prod) return { redirect: '/modulos/produtos/produto.php' };

    if (req.method === 'POST') {
      prod.nomeProduto = String(body.nomeProduto || prod.nomeProduto).trim();
      prod.codigoBarras = String(body.codigoBarras || prod.codigoBarras || '').trim();
      prod.categoria = String(body.categoria || prod.categoria || 'Geral').trim();
      prod.preco = Number(String(body.preco || prod.preco).replace(',', '.'));
      prod.precoCusto = Number(String(body.precoCusto || prod.precoCusto || 0).replace(',', '.'));
      prod.quantidade = Number(body.quantidade ?? prod.quantidade);
      prod.estoqueMinimo = Number(body.estoqueMinimo ?? prod.estoqueMinimo);
      prod.localizacao = String(body.localizacao || prod.localizacao || 'A-01-01').trim();
      prod.nomeFornecedor = String(body.nomeFornecedor || prod.nomeFornecedor || '').trim();
      prod.lote = String(body.lote || prod.lote || '').trim();
      prod.dataValidade = body.dataValidade || null;
      prod.descricao = String(body.descricao || prod.descricao || '').trim();
      saveDb(db);
      return { redirect: '/modulos/produtos/produto.php' };
    }

    const htmlEdit = `
<div class="page-container">
    <div class="card" style="max-width:700px; margin:20px auto; padding:26px; background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px;">
        <h2 style="margin-top:0;">✏️ Editar Produto #${prod.idProduto}</h2>
        <form action="/modulos/produtos/produto_editar.php?id=${prod.idProduto}" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="idProduto" value="${prod.idProduto}">
            <div class="form-group"><label>Nome do Produto *</label><input type="text" name="nomeProduto" required value="${esc(prod.nomeProduto)}"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div class="form-group"><label>Código de Barras (EAN-13)</label><input type="text" name="codigoBarras" value="${esc(prod.codigoBarras)}"></div>
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" value="${esc(prod.categoria)}"></div>
                <div class="form-group"><label>Preço de Venda (R$)</label><input type="number" step="0.01" name="preco" required value="${prod.preco}"></div>
                <div class="form-group"><label>Preço de Custo (R$)</label><input type="number" step="0.01" name="precoCusto" value="${prod.precoCusto}"></div>
                <div class="form-group"><label>Quantidade Atual</label><input type="number" name="quantidade" required value="${prod.quantidade}"></div>
                <div class="form-group"><label>Estoque Mínimo</label><input type="number" name="estoqueMinimo" value="${prod.estoqueMinimo}"></div>
                <div class="form-group"><label>Posição WMS</label><input type="text" name="localizacao" value="${esc(prod.localizacao)}"></div>
                <div class="form-group"><label>Lote</label><input type="text" name="lote" value="${esc(prod.lote)}"></div>
            </div>
            <div style="display:flex; gap:10px; margin-top:16px;">
                <button type="submit" class="btn-submit">Salvar Alterações</button>
                <a href="/modulos/produtos/produto.php" class="btn-secondary" style="padding:10px 18px; text-decoration:none; border-radius:8px;">Voltar</a>
            </div>
        </form>
    </div>
</div>`;
    return { html: renderLayout('Editar Produto', 'produto_editar.php', session, htmlEdit) };
  }

  let msg = '';
  if (req.method === 'POST') {
    const novoId = db.produtos.reduce((m, x) => Math.max(m, x.idProduto), 0) + 1;
    const codigoBarras = String(body.codigoBarras || '').trim() || ('7894900' + String(novoId).padStart(6, '0'));
    db.produtos.push({
      idProduto: novoId,
      idUsuario: uid,
      idFornecedor: null,
      codigoBarras,
      nomeProduto: String(body.nomeProduto || 'Novo Produto').trim(),
      categoria: String(body.categoria || 'Geral').trim(),
      preco: Number(String(body.preco || 0).replace(',', '.')),
      precoCusto: Number(String(body.precoCusto || 0).replace(',', '.')),
      quantidade: Number(body.quantidade || 0),
      estoqueMinimo: Number(body.estoqueMinimo || 10),
      localizacao: String(body.localizacao || 'A-01-01').trim(),
      descricao: String(body.descricao || '').trim(),
      nomeFornecedor: String(body.nomeFornecedor || '').trim(),
      lote: String(body.lote || '').trim(),
      dataValidade: body.dataValidade || null,
      imagem: null
    });
    saveDb(db);
    msg = 'Produto cadastrado com sucesso no catálogo!';
  }

  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const forns = db.fornecedores.filter(f => Number(f.idUsuario) === uid);

  const html = `
<div class="page-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="margin:0; font-size:24px;">📦 Catálogo de Produtos &amp; Endereçamento WMS</h1>
            <p style="margin:4px 0 0; color:var(--text-secondary); font-size:13.5px;">Gerencie preços, custos, códigos EAN-13, lotes e localização física</p>
        </div>
        <a href="/modulos/relatorios/etiquetas.php" class="btn-submit" style="width:auto; padding:10px 18px; text-decoration:none;">🏷️ Gerar Etiquetas de Código de Barras</a>
    </div>

    ${msg ? `<div class="alert-box success" style="margin-bottom:18px;"><span>✅</span><p>${esc(msg)}</p></div>` : ''}

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; margin-bottom:26px;">
        <h3 style="margin-top:0;">➕ Cadastrar Novo Produto</h3>
        <form action="/modulos/produtos/produto.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
                <div class="form-group"><label>Nome do Produto *</label><input type="text" name="nomeProduto" required placeholder="Ex: Monitor Gamer 24"></div>
                <div class="form-group"><label>Código de Barras (EAN-13)</label><input type="text" name="codigoBarras" placeholder="Gerado automaticamente se vazio"></div>
                <div class="form-group"><label>Categoria *</label><input type="text" name="categoria" required placeholder="Ex: Informatica"></div>
                <div class="form-group"><label>Preço de Venda (R$) *</label><input type="number" step="0.01" name="preco" required placeholder="0.00"></div>
                <div class="form-group"><label>Preço de Custo (R$) *</label><input type="number" step="0.01" name="precoCusto" required placeholder="0.00"></div>
                <div class="form-group"><label>Estoque Inicial *</label><input type="number" name="quantidade" required value="10"></div>
                <div class="form-group"><label>Estoque Mínimo *</label><input type="number" name="estoqueMinimo" required value="5"></div>
                <div class="form-group"><label>Posição WMS *</label><input type="text" name="localizacao" value="A-01-01"></div>
                <div class="form-group">
                    <label>Fornecedor</label>
                    <select name="nomeFornecedor" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-surface-2); color:var(--text-primary);">
                        <option value="">Selecione...</option>
                        ${forns.map(f => `<option value="${esc(f.nomeFornecedor)}">${esc(f.nomeFornecedor)}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group"><label>Lote</label><input type="text" name="lote" placeholder="Ex: LOT-2026-A"></div>
                <div class="form-group"><label>Data de Validade</label><input type="date" name="dataValidade"></div>
            </div>
            <button type="submit" class="btn-submit" style="margin-top:10px; width:auto; padding:10px 24px;">Salvar Produto</button>
        </form>
    </div>

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; overflow-x:auto;">
        <h3 style="margin-top:0;">📋 Produtos Cadastrados (${prods.length})</h3>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--border-subtle); text-align:left;">
                    <th style="padding:10px;">ID / EAN-13</th>
                    <th style="padding:10px;">Produto</th>
                    <th style="padding:10px;">Categoria</th>
                    <th style="padding:10px;">WMS</th>
                    <th style="padding:10px;">Venda / Custo</th>
                    <th style="padding:10px;">Saldo</th>
                    <th style="padding:10px;">Validade</th>
                    <th style="padding:10px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                ${prods.map(p => `
                    <tr style="border-bottom:1px solid var(--border-subtle);">
                        <td style="padding:10px;"><strong>#${p.idProduto}</strong><br><span style="font-size:11px; color:var(--text-muted);">${esc(p.codigoBarras || '-')}</span></td>
                        <td style="padding:10px;"><strong>${esc(p.nomeProduto)}</strong><br><span style="font-size:11px; color:var(--text-muted);">${esc(p.nomeFornecedor || '')}</span></td>
                        <td style="padding:10px;">${esc(p.categoria || '-')}</td>
                        <td style="padding:10px;"><code>${esc(p.localizacao || 'A-01-01')}</code></td>
                        <td style="padding:10px;"><strong>R$ ${formatCurrencyBR(p.preco)}</strong><br><span style="font-size:11px; color:var(--text-muted);">Custo: R$ ${formatCurrencyBR(p.precoCusto)}</span></td>
                        <td style="padding:10px;"><strong style="color:${p.quantidade <= p.estoqueMinimo ? '#ef4444' : '#10b981'};">${p.quantidade} un</strong> (Mín: ${p.estoqueMinimo})</td>
                        <td style="padding:10px;">${p.dataValidade ? esc(formatDateBR(p.dataValidade)) : 'Indeterminada'}</td>
                        <td style="padding:10px; display:flex; gap:8px;">
                            <a href="/modulos/produtos/produto_editar.php?id=${p.idProduto}" style="color:#3b82f6; font-weight:700; text-decoration:none;">Editar</a>
                            <a href="/modulos/produtos/produto_excluir.php?id=${p.idProduto}" onclick="return confirm('Excluir este produto?')" style="color:#ef4444; font-weight:700; text-decoration:none;">Excluir</a>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
</div>`;

  return { html: renderLayout('Catálogo de Produtos - MVM', 'produto.php', session, html) };
}

function handleClientes(req, body, query, db, session, subRoute) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;

  if (subRoute === 'cliente_excluir.php') {
    const id = Number(body.id || query.id || 0);
    if (id > 0) {
      db.clientes = db.clientes.filter(c => !(c.idCliente === id && Number(c.idUsuario) === uid));
      saveDb(db);
    }
    return { redirect: '/modulos/clientes/cliente.php' };
  }

  if (subRoute === 'cliente_editar.php') {
    const id = Number(body.idCliente || query.id || 0);
    const cli = db.clientes.find(c => c.idCliente === id && Number(c.idUsuario) === uid);
    if (!cli) return { redirect: '/modulos/clientes/cliente.php' };
    if (req.method === 'POST') {
      cli.nomeCliente = String(body.nomeCliente || cli.nomeCliente).trim();
      cli.email = String(body.email || cli.email).trim();
      cli.telefone = String(body.telefone || cli.telefone || '').trim();
      cli.endereco = String(body.endereco || cli.endereco || '').trim();
      cli.cidade = String(body.cidade || cli.cidade || '').trim();
      cli.uf = String(body.uf || cli.uf || '').trim();
      saveDb(db);
      return { redirect: '/modulos/clientes/cliente.php' };
    }
    const htmlEdit = `
<div class="page-container">
    <div style="max-width:600px; margin:20px auto; padding:24px; background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px;">
        <h2>✏️ Editar Cliente #${cli.idCliente}</h2>
        <form action="/modulos/clientes/cliente_editar.php?id=${cli.idCliente}" method="POST">
            <input type="hidden" name="idCliente" value="${cli.idCliente}">
            <div class="form-group"><label>Nome *</label><input type="text" name="nomeCliente" required value="${esc(cli.nomeCliente)}"></div>
            <div class="form-group"><label>E-mail *</label><input type="email" name="email" required value="${esc(cli.email)}"></div>
            <div class="form-group"><label>Telefone</label><input type="text" name="telefone" value="${esc(cli.telefone)}"></div>
            <div class="form-group"><label>Endereço</label><input type="text" name="endereco" value="${esc(cli.endereco)}"></div>
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                <div class="form-group"><label>Cidade</label><input type="text" name="cidade" value="${esc(cli.cidade)}"></div>
                <div class="form-group"><label>UF</label><input type="text" name="uf" value="${esc(cli.uf)}"></div>
            </div>
            <button type="submit" class="btn-submit">Salvar</button>
        </form>
    </div>
</div>`;
    return { html: renderLayout('Editar Cliente', 'cliente_editar.php', session, htmlEdit) };
  }

  let msg = '';
  if (req.method === 'POST') {
    const novoId = db.clientes.reduce((m, x) => Math.max(m, x.idCliente), 0) + 1;
    db.clientes.push({
      idCliente: novoId,
      idUsuario: uid,
      nomeCliente: String(body.nomeCliente || '').trim(),
      email: String(body.email || '').trim(),
      telefone: String(body.telefone || '').trim(),
      endereco: String(body.endereco || '').trim(),
      cidade: String(body.cidade || '').trim(),
      uf: String(body.uf || '').trim()
    });
    saveDb(db);
    msg = 'Cliente cadastrado com sucesso!';
  }

  const clientes = db.clientes.filter(c => Number(c.idUsuario) === uid);
  const movs = db.movimentos.filter(m => Number(m.idUsuario) === uid && m.tipoMovimento === 'SAIDA' && !m.dataDevolucao);

  const html = `
<div class="page-container">
    <h1 style="margin:0 0 18px;">👥 Carteira de Clientes &amp; Histórico de Compras</h1>
    ${msg ? `<div class="alert-box success" style="margin-bottom:18px;"><span>✅</span><p>${esc(msg)}</p></div>` : ''}

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; margin-bottom:24px;">
        <h3 style="margin-top:0;">➕ Novo Cliente</h3>
        <form action="/modulos/clientes/cliente.php" method="POST">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
                <div class="form-group"><label>Nome Completo / Razão Social *</label><input type="text" name="nomeCliente" required></div>
                <div class="form-group"><label>E-mail *</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Telefone / WhatsApp</label><input type="text" name="telefone"></div>
                <div class="form-group"><label>Endereço</label><input type="text" name="endereco"></div>
                <div class="form-group"><label>Cidade</label><input type="text" name="cidade"></div>
                <div class="form-group"><label>UF</label><input type="text" name="uf" maxlength="2"></div>
            </div>
            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px;">Cadastrar Cliente</button>
        </form>
    </div>

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--border-subtle); text-align:left;">
                    <th style="padding:10px;">Cliente</th>
                    <th style="padding:10px;">Contato</th>
                    <th style="padding:10px;">Localidade</th>
                    <th style="padding:10px;">Pedidos / Total Comprado</th>
                    <th style="padding:10px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                ${clientes.map(c => {
                  const compras = movs.filter(m => Number(m.idCliente) === c.idCliente);
                  const totalGasto = compras.reduce((s, m) => s + Number(m.valorTotal || 0), 0);
                  return `
                    <tr style="border-bottom:1px solid var(--border-subtle);">
                        <td style="padding:10px;"><strong>${esc(c.nomeCliente)}</strong></td>
                        <td style="padding:10px;">${esc(c.email)}<br><span style="font-size:11px; color:var(--text-muted);">${esc(c.telefone || '')}</span></td>
                        <td style="padding:10px;">${esc(c.cidade || '')} - ${esc(c.uf || '')}</td>
                        <td style="padding:10px;"><strong>${compras.length} pedido(s)</strong> &bull; <span style="color:#10b981; font-weight:700;">R$ ${formatCurrencyBR(totalGasto)}</span></td>
                        <td style="padding:10px; display:flex; gap:8px;">
                            <a href="/modulos/clientes/cliente_editar.php?id=${c.idCliente}" style="color:#3b82f6; font-weight:700; text-decoration:none;">Editar</a>
                            <a href="/modulos/clientes/cliente_excluir.php?id=${c.idCliente}" onclick="return confirm('Excluir cliente?')" style="color:#ef4444; font-weight:700; text-decoration:none;">Excluir</a>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
    </div>
</div>`;

  return { html: renderLayout('Carteira de Clientes - MVM', 'cliente.php', session, html) };
}

function handleFornecedores(req, body, query, db, session, subRoute) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;

  if (subRoute === 'fornecedor_excluir.php') {
    const id = Number(body.id || query.id || 0);
    if (id > 0) {
      db.fornecedores = db.fornecedores.filter(f => !(f.idFornecedor === id && Number(f.idUsuario) === uid));
      saveDb(db);
    }
    return { redirect: '/modulos/fornecedores/fornecedor.php' };
  }

  if (subRoute === 'fornecedor_editar.php') {
    const id = Number(body.idFornecedor || query.id || 0);
    const forn = db.fornecedores.find(f => f.idFornecedor === id && Number(f.idUsuario) === uid);
    if (!forn) return { redirect: '/modulos/fornecedores/fornecedor.php' };
    if (req.method === 'POST') {
      forn.nomeFornecedor = String(body.nomeFornecedor || forn.nomeFornecedor).trim();
      forn.segmento = String(body.segmento || forn.segmento || '').trim();
      forn.cnpj = String(body.cnpj || forn.cnpj).trim();
      forn.email = String(body.email || forn.email || '').trim();
      forn.telefone = String(body.telefone || forn.telefone || '').trim();
      forn.cidade = String(body.cidade || forn.cidade || '').trim();
      forn.uf = String(body.uf || forn.uf || '').trim();
      saveDb(db);
      return { redirect: '/modulos/fornecedores/fornecedor.php' };
    }
    const htmlEdit = `
<div class="page-container">
    <div style="max-width:600px; margin:20px auto; padding:24px; background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px;">
        <h2>✏️ Editar Fornecedor #${forn.idFornecedor}</h2>
        <form action="/modulos/fornecedores/fornecedor_editar.php?id=${forn.idFornecedor}" method="POST">
            <input type="hidden" name="idFornecedor" value="${forn.idFornecedor}">
            <div class="form-group"><label>Razão Social / Nome *</label><input type="text" name="nomeFornecedor" required value="${esc(forn.nomeFornecedor)}"></div>
            <div class="form-group"><label>Segmento</label><input type="text" name="segmento" value="${esc(forn.segmento)}"></div>
            <div class="form-group"><label>CNPJ *</label><input type="text" name="cnpj" required value="${esc(forn.cnpj)}"></div>
            <div class="form-group"><label>E-mail</label><input type="email" name="email" value="${esc(forn.email)}"></div>
            <div class="form-group"><label>Telefone</label><input type="text" name="telefone" value="${esc(forn.telefone)}"></div>
            <button type="submit" class="btn-submit">Salvar</button>
        </form>
    </div>
</div>`;
    return { html: renderLayout('Editar Fornecedor', 'fornecedor_editar.php', session, htmlEdit) };
  }

  let msg = '';
  if (req.method === 'POST') {
    const novoId = db.fornecedores.reduce((m, x) => Math.max(m, x.idFornecedor), 0) + 1;
    db.fornecedores.push({
      idFornecedor: novoId,
      idUsuario: uid,
      nomeFornecedor: String(body.nomeFornecedor || '').trim(),
      segmento: String(body.segmento || 'Geral').trim(),
      cnpj: String(body.cnpj || '').trim(),
      email: String(body.email || '').trim(),
      telefone: String(body.telefone || '').trim(),
      endereco: String(body.endereco || '').trim(),
      cidade: String(body.cidade || '').trim(),
      uf: String(body.uf || '').trim()
    });
    saveDb(db);
    msg = 'Fornecedor homologado com sucesso!';
  }

  const forns = db.fornecedores.filter(f => Number(f.idUsuario) === uid);
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);

  const html = `
<div class="page-container">
    <h1 style="margin:0 0 18px;">🏢 Fornecedores Homologados &amp; Mix de Suprimentos</h1>
    ${msg ? `<div class="alert-box success" style="margin-bottom:18px;"><span>✅</span><p>${esc(msg)}</p></div>` : ''}

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; margin-bottom:24px;">
        <h3 style="margin-top:0;">➕ Cadastrar Fornecedor</h3>
        <form action="/modulos/fornecedores/fornecedor.php" method="POST">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
                <div class="form-group"><label>Nome / Razão Social *</label><input type="text" name="nomeFornecedor" required></div>
                <div class="form-group"><label>Segmento</label><input type="text" name="segmento" placeholder="Ex: Informatica, Bebidas"></div>
                <div class="form-group"><label>CNPJ *</label><input type="text" name="cnpj" required placeholder="00.000.000/0001-00"></div>
                <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
                <div class="form-group"><label>Telefone / WhatsApp</label><input type="text" name="telefone"></div>
                <div class="form-group"><label>Cidade / UF</label><input type="text" name="cidade"></div>
            </div>
            <button type="submit" class="btn-submit" style="width:auto; padding:10px 24px;">Salvar Fornecedor</button>
        </form>
    </div>

    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; padding:22px; overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--border-subtle); text-align:left;">
                    <th style="padding:10px;">Fornecedor</th>
                    <th style="padding:10px;">CNPJ / Segmento</th>
                    <th style="padding:10px;">Contato</th>
                    <th style="padding:10px;">Mix Vinculado</th>
                    <th style="padding:10px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                ${forns.map(f => {
                  const mix = prods.filter(p => p.nomeFornecedor === f.nomeFornecedor || Number(p.idFornecedor) === f.idFornecedor);
                  return `
                    <tr style="border-bottom:1px solid var(--border-subtle);">
                        <td style="padding:10px;"><strong>${esc(f.nomeFornecedor)}</strong></td>
                        <td style="padding:10px;">${esc(f.cnpj)}<br><span style="font-size:11px; color:var(--text-muted);">${esc(f.segmento || '')}</span></td>
                        <td style="padding:10px;">${esc(f.email || '')}<br><span style="font-size:11px; color:var(--text-muted);">${esc(f.telefone || '')}</span></td>
                        <td style="padding:10px;"><strong>${mix.length} SKU(s)</strong> no catálogo</td>
                        <td style="padding:10px; display:flex; gap:8px;">
                            <a href="/modulos/fornecedores/fornecedor_editar.php?id=${f.idFornecedor}" style="color:#3b82f6; font-weight:700; text-decoration:none;">Editar</a>
                            <a href="/modulos/fornecedores/fornecedor_excluir.php?id=${f.idFornecedor}" onclick="return confirm('Excluir fornecedor?')" style="color:#ef4444; font-weight:700; text-decoration:none;">Excluir</a>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
    </div>
</div>`;

  return { html: renderLayout('Fornecedores - MVM', 'fornecedor.php', session, html) };
}

module.exports = {
  handleDashboard,
  handleProdutos,
  handleClientes,
  handleFornecedores
};
