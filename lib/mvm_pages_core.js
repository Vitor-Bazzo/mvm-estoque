const { esc, renderLayout } = require('./mvm_layout');
const { saveDb, formatDateBR, formatCurrencyBR, obterDadosDashboardBI } = require('./mvm_db');

function handleDashboard(req, query, db, session) {
  if (!session.usuarioLogado) {
    const landing = `
<div class="page-container">
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 54px; margin-bottom: 14px;">📦</div>
        <h1 style="font-size: 28px; margin-bottom: 10px;">Bem-vindo ao Sistema MVM</h1>
        <p style="color: var(--muted); font-size: 16px; max-width: 500px; margin: 0 auto 24px;">
            Plataforma de gestão comercial de estoque e inteligência de vendas estilo Power BI.
        </p>
        <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <a href="/modulos/auth/login.php" class="cta-button" style="padding: 14px 28px; font-size: 15px;">Acessar o Sistema (Login)</a>
            <a href="/modulos/planos/planos.php" class="btn-secondary" style="padding: 14px 24px; font-size: 15px;">💎 Ver Planos &amp; Preços</a>
        </div>
    </div>
</div>`;
    return { html: renderLayout('Dashboard de Vendas - Power BI', 'index.php', session, landing) };
  }

  const uid = Number(session.idUsuario) || 1;
  const dadosBI = obterDadosDashboardBI(db, uid);
  const flash = session.flash_dashboard;
  delete session.flash_dashboard;

  const now = new Date();
  const dataHoraAtual = `${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  const mesAtual = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

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
                <div class="pbi-header-titles">
                    <h1>Painel Executivo &amp; Inteligência de Negócios (BI)</h1>
                    <p>Visão estratégica de vendas, estoque e distribuição por categorias • MVM Comércios</p>
                </div>
            </div>

            <div class="pbi-header-actions" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <div class="pbi-tag">
                    <span class="pbi-tag-pulse"></span>
                    Atualizado (${dataHoraAtual})
                </div>
                <div style="display: inline-flex; align-items: center; gap: 4px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); border-radius: 9px; padding: 2px 6px;">
                    <input type="month" id="mes-balanco-dash" value="${mesAtual}" style="border: none; background: transparent; color: var(--pbi-text-main); font-size: 12px; font-weight: 600; outline: none; padding: 4px;" title="Mês de referência do Balanço">
                    <a href="/exportacao/relatorio_balanco_pdf.php" onclick="this.href='/exportacao/relatorio_balanco_pdf.php?mes=' + document.getElementById('mes-balanco-dash').value" target="_blank" class="pbi-btn" style="border: none; box-shadow: none; padding: 6px 10px;" title="Gerar Balanço Mensal Oficial em PDF">
                        <span>📑</span> Balanço (PDF)
                    </a>
                </div>
                <a href="/exportacao/relatorio_inventario_pdf.php" target="_blank" class="pbi-btn" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); color: #ffffff; text-decoration: none;" title="Folha Oficial de Inventário Físico para Auditoria">
                    <span>📋</span> Inventário (PDF)
                </a>
                <a href="/modulos/movimentos/movimento.php" class="pbi-btn pbi-btn-primary">
                    <span>➕</span> Registrar Nova Venda
                </a>
            </div>
        </div>

        ${dadosBI.produtos_estoque_baixo.length > 0 ? `
            <div class="pbi-stock-alert-panel">
                <div class="pbi-stock-alert-header">
                    <div class="pbi-stock-alert-title-box">
                        <div class="pbi-stock-alert-icon-box">
                            <span class="pbi-alert-pulse-ring"></span>
                            <span class="pbi-alert-icon">⚠️</span>
                        </div>
                        <div>
                            <div class="pbi-stock-alert-title-row">
                                <h2>Alerta de Estoque Crítico</h2>
                                <span class="pbi-stock-badge-count">
                                    ${dadosBI.produtos_estoque_baixo.length} ${dadosBI.produtos_estoque_baixo.length === 1 ? 'produto em nível crítico' : 'produtos em nível crítico'}
                                </span>
                            </div>
                            <p>Os seguintes itens atingiram ou estão abaixo do estoque mínimo de segurança e precisam de reposição:</p>
                        </div>
                    </div>

                    <div class="pbi-stock-alert-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <form action="/scripts/disparar_alerta.php" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Deseja enviar o relatório consolidado de alertas para seu e-mail agora?')">
                            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                            <input type="hidden" name="origem" value="/index.php">
                            <button type="submit" class="pbi-btn" style="background:#00704A; color:#ffffff; border:none; cursor:pointer;" title="Enviar relatório gerencial de alertas por e-mail">
                                <span>📧</span> Notificar Gestor por E-mail
                            </button>
                        </form>
                        <a href="/modulos/movimentos/movimento.php" class="pbi-btn pbi-btn-stock-entry" title="Registrar entrada de produtos para repor estoque">
                            <span>📥</span> Repor Estoque
                        </a>
                        <a href="/modulos/produtos/produto.php" class="pbi-btn pbi-btn-stock-catalog" title="Ver catálogo de produtos">
                            <span>📦</span> Catálogo
                        </a>
                    </div>
                </div>

                <div class="pbi-stock-items-grid">
                    ${dadosBI.produtos_estoque_baixo.map(item => {
                      const qtd = Number(item.quantidade || 0);
                      const minItem = Math.max(1, Number(item.estoqueMinimo || 10));
                      const pctBarra = Math.min(100, Math.max(5, Math.round((qtd / minItem) * 100)));
                      const isEsgotado = qtd <= 0;
                      return `
                        <div class="pbi-stock-item-card ${isEsgotado ? 'is-empty' : ''}">
                            <div class="pbi-stock-item-thumb">
                                ${item.imagem ? `<img src="/uploads/produtos/${esc(item.imagem)}" alt="${esc(item.nomeProduto)}" class="pbi-stock-thumb-img">` : `<div class="pbi-stock-thumb-fallback">📦</div>`}
                            </div>
                            <div class="pbi-stock-item-info">
                                <div class="pbi-stock-item-header">
                                    <strong class="pbi-stock-item-name" title="${esc(item.nomeProduto)}">${esc(item.nomeProduto)}</strong>
                                    <span class="pbi-stock-item-cat">${esc(item.categoria)}</span>
                                </div>
                                <div class="pbi-stock-level-row">
                                    <div class="pbi-stock-bar-container">
                                        <div class="pbi-stock-bar-fill ${isEsgotado ? 'bar-danger' : 'bar-warning'}" style="width: ${pctBarra}%;"></div>
                                    </div>
                                    <div class="pbi-stock-qty-badge ${isEsgotado ? 'qty-danger' : 'qty-warning'}">
                                        ${isEsgotado ? '🚨 ESGOTADO (0 un)' : `⚠️ Restam ${qtd} ${qtd === 1 ? 'unidade' : 'unidades'}`}
                                    </div>
                                </div>
                                <div class="pbi-stock-meta-row">
                                    <span class="pbi-stock-meta-fornecedor" title="Fornecedor">🏢 ${esc(item.nomeFornecedor)}</span>
                                    <span class="pbi-stock-meta-preco">R$ ${formatCurrencyBR(item.preco)}</span>
                                    <a href="/modulos/produtos/produto_editar.php?id=${item.idProduto}" class="pbi-stock-item-edit-btn" title="Editar produto">Editar ✏️</a>
                                </div>
                            </div>
                        </div>`;
                    }).join('')}
                </div>
            </div>
        ` : ''}

        ${dadosBI.produtos_vencimento.length > 0 ? `
            <div class="pbi-stock-alert-panel" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(239, 68, 68, 0.05) 100%); border-color: rgba(245, 158, 11, 0.35); margin-bottom: 24px;">
                <div class="pbi-stock-alert-header">
                    <div class="pbi-stock-alert-title-box">
                        <div class="pbi-stock-alert-icon-box" style="background: #f59e0b; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);">
                            <span class="pbi-alert-icon">⏳</span>
                        </div>
                        <div>
                            <div class="pbi-stock-alert-title-row">
                                <h2 style="color: #d97706;">Alerta de Validade &amp; Lotes (Prioridade FEFO)</h2>
                                <span class="pbi-stock-badge-count" style="background: rgba(245, 158, 11, 0.15); color: #b45309; border-color: rgba(245, 158, 11, 0.3);">
                                    ${dadosBI.produtos_vencimento.length} ${dadosBI.produtos_vencimento.length === 1 ? 'item requer atenção' : 'itens requerem atenção'}
                                </span>
                            </div>
                            <p>Itens com validade crítica ou próxima. Aplique a regra <strong>FEFO (First Expired, First Out)</strong> priorizando a saída desses lotes:</p>
                        </div>
                    </div>
                    <div class="pbi-stock-alert-actions">
                        <a href="/modulos/movimentos/movimento.php" class="pbi-btn pbi-btn-primary" style="background: #d97706;"><span>🚀</span> Priorizar Saída</a>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-top: 14px;">
                    ${dadosBI.produtos_vencimento.map(itemV => {
                      const isVencido = itemV.statusVencimento === 'vencido';
                      const isUrgente = itemV.statusVencimento === 'urgente';
                      const corStatus = isVencido ? '#ef4444' : (isUrgente ? '#f97316' : '#eab308');
                      const bgStatus = isVencido ? 'rgba(239, 68, 68, 0.12)' : (isUrgente ? 'rgba(249, 115, 22, 0.12)' : 'rgba(234, 179, 8, 0.12)');
                      return `
                        <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                            <div>
                                <strong style="color: var(--text-main); font-size: 13.5px; display: block; margin-bottom: 2px;">${esc(itemV.nomeProduto)}</strong>
                                <span style="font-size: 11.5px; color: var(--text-muted); display: block;">Lote: <strong>${esc(itemV.lote)}</strong> &bull; ${itemV.quantidade} un em estoque</span>
                                <span style="font-size: 11.5px; color: var(--text-muted);">Vence em: <strong>${esc(itemV.dataValidade)}</strong></span>
                            </div>
                            <div style="text-align: right;">
                                <span style="display: inline-block; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; background: ${bgStatus}; color: ${corStatus}; border: 1px solid ${corStatus}33;">
                                    ${isVencido ? '🚨 VENCIDO' : (itemV.diasParaVencer === 0 ? '⚠️ Vence Hoje!' : `⏳ ${itemV.diasParaVencer} ${itemV.diasParaVencer === 1 ? 'dia' : 'dias'}`)}
                                </span>
                            </div>
                        </div>`;
                    }).join('')}
                </div>
            </div>
        ` : ''}

        <div class="pbi-kpi-grid">
            <div class="pbi-kpi-card accent-emerald">
                <div class="pbi-kpi-header"><span class="pbi-kpi-title">Faturamento Total (Vendas)</span><span class="pbi-kpi-icon">💰</span></div>
                <div class="pbi-kpi-value">R$ ${formatCurrencyBR(dadosBI.kpis.faturamento_total)}</div>
                <div class="pbi-kpi-footer"><span class="pbi-badge-pill success">Receita</span> Total em vendas finalizadas</div>
            </div>
            <div class="pbi-kpi-card accent-blue">
                <div class="pbi-kpi-header"><span class="pbi-kpi-title">Vendas Concluídas</span><span class="pbi-kpi-icon">🛒</span></div>
                <div class="pbi-kpi-value">${Number(dadosBI.kpis.total_vendas_qtd).toLocaleString('pt-BR')}</div>
                <div class="pbi-kpi-footer"><span class="pbi-badge-pill info">Transações</span> Pedidos emitidos no PDV</div>
            </div>
            <div class="pbi-kpi-card accent-amber">
                <div class="pbi-kpi-header"><span class="pbi-kpi-title">Estoque Disponível</span><span class="pbi-kpi-icon">📦</span></div>
                <div class="pbi-kpi-value">${Number(dadosBI.kpis.itens_em_estoque).toLocaleString('pt-BR')}</div>
                <div class="pbi-kpi-footer">
                    ${dadosBI.total_produtos_estoque_baixo > 0 ? `<span class="pbi-badge-pill danger">⚠️ ${dadosBI.total_produtos_estoque_baixo} acabando</span>` : `<span>Unidades físicas cadastradas</span>`}
                </div>
            </div>
            <div class="pbi-kpi-card accent-purple">
                <div class="pbi-kpi-header"><span class="pbi-kpi-title">Categorias Ativas</span><span class="pbi-kpi-icon">🏷️</span></div>
                <div class="pbi-kpi-value">${dadosBI.kpis.total_categorias}</div>
                <div class="pbi-kpi-footer"><span>Segmentos de produtos no catálogo</span></div>
            </div>
            <div class="pbi-kpi-card accent-yellow">
                <div class="pbi-kpi-header"><span class="pbi-kpi-title">Produto Campeão #1</span><span class="pbi-kpi-icon">🏆</span></div>
                <div class="pbi-kpi-value" style="font-size: 17px; line-height: 1.3;" title="${esc(dadosBI.kpis.produto_campeao)}">${esc(dadosBI.kpis.produto_campeao)}</div>
                <div class="pbi-kpi-footer"><span style="color: #ea580c; font-weight: 700;">Líder em saídas do estoque</span></div>
            </div>
            <div class="pbi-kpi-card accent-emerald">
                <div class="pbi-kpi-header"><span class="pbi-kpi-title">Lucro Bruto Estimado</span><span class="pbi-kpi-icon">📈</span></div>
                <div class="pbi-kpi-value" style="color: #10b981;">R$ ${formatCurrencyBR(dadosBI.kpis.lucro_bruto)}</div>
                <div class="pbi-kpi-footer"><span class="pbi-badge-pill success">${dadosBI.kpis.margem_lucro}% de Margem</span> Receita menos CMV (Custo)</div>
            </div>
        </div>

        <div class="pbi-grid-2cols">
            <div class="pbi-visual-card">
                <div class="pbi-visual-header">
                    <div class="pbi-visual-title-box">
                        <h3><span>🏆</span> 5 Produtos Mais Vendidos de Todo o Estoque</h3>
                        <p>Ranking dos itens com maior saída acumulada de vendas</p>
                    </div>
                    <div class="pbi-tag" style="padding: 4px 8px; font-size: 11px;">Top 5</div>
                </div>
                <div id="chart-top-produtos" class="pbi-chart-container"></div>
            </div>

            <div class="pbi-visual-card">
                <div class="pbi-visual-header">
                    <div class="pbi-visual-title-box">
                        <h3><span>🍩</span> Porcentagem de Cada Categoria</h3>
                        <p>Proporção do mix de produtos (exemplo: Bebidas com 50%)</p>
                    </div>
                    <div class="pbi-visual-controls">
                        <button type="button" class="pbi-pill-btn active" data-cat-mode="vendas">Mix Vendas</button>
                        <button type="button" class="pbi-pill-btn" data-cat-mode="estoque">Mix Estoque</button>
                    </div>
                </div>
                <div id="chart-categorias" class="pbi-chart-container"></div>
            </div>
        </div>

        <div class="pbi-grid-full">
            <div class="pbi-visual-card">
                <div class="pbi-visual-header">
                    <div class="pbi-visual-title-box">
                        <h3><span>📈</span> Quantidade em Reais de Vendas Feitas por Dia</h3>
                        <p>Evolução cronológica diária do faturamento de saídas comerciais (R$)</p>
                    </div>
                    <div class="pbi-visual-controls">
                        <button type="button" class="pbi-pill-btn" data-periodo="7">Últimos 7 dias</button>
                        <button type="button" class="pbi-pill-btn" data-periodo="15">Últimos 15 dias</button>
                        <button type="button" class="pbi-pill-btn active" data-periodo="0">Todo o Período</button>
                    </div>
                </div>
                <div id="chart-vendas-diarias" class="pbi-chart-container"></div>
            </div>
        </div>

        ${dadosBI.sugestoes_reposicao.length > 0 ? `
            <div class="pbi-visual-card" style="margin-bottom: 24px; margin-top: 10px;">
                <div class="pbi-visual-header" style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 16px;">
                    <div class="pbi-visual-title-box">
                        <h3 style="display: flex; align-items: center; gap: 8px;"><span>📋</span> Sugestão de Reposição Automática (Ponto de Pedido)</h3>
                        <p>Itens que atingiram o limite mínimo de segurança no estoque com cotação imediata para fornecedores</p>
                    </div>
                    <span class="pbi-badge-pill danger">${dadosBI.sugestoes_reposicao.length} ${dadosBI.sugestoes_reposicao.length === 1 ? 'item crítico' : 'itens críticos'}</span>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border); text-align: left; color: var(--text-muted); font-size: 12px;">
                                <th style="padding: 10px 8px;">PRODUTO</th>
                                <th style="padding: 10px 8px;">ESTOQUE ATUAL</th>
                                <th style="padding: 10px 8px;">MÍNIMO</th>
                                <th style="padding: 10px 8px; color: var(--primary);">SUGESTÃO DE COMPRA</th>
                                <th style="padding: 10px 8px;">CUSTO EST.</th>
                                <th style="padding: 10px 8px;">FORNECEDOR</th>
                                <th style="padding: 10px 8px; text-align: right;">AÇÃO</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${dadosBI.sugestoes_reposicao.map(rep => `
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 12px 8px;">
                                        <strong style="color: var(--text-main);">${esc(rep.nomeProduto)}</strong>
                                        <div style="font-size: 11px; color: var(--text-muted);">${esc(rep.categoria)}</div>
                                    </td>
                                    <td style="padding: 12px 8px;">
                                        <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 11.5px; background: rgba(239, 68, 68, 0.12); color: #ef4444;">${rep.estoqueAtual} un</span>
                                    </td>
                                    <td style="padding: 12px 8px; color: var(--text-muted);">${rep.estoqueMinimo} un</td>
                                    <td style="padding: 12px 8px;"><strong style="color: #00704A; font-size: 13.5px;">+${rep.qtdSugerida} un</strong></td>
                                    <td style="padding: 12px 8px; font-family: 'JetBrains Mono', monospace; font-weight: 600;">R$ ${formatCurrencyBR(rep.custoEstimado)}</td>
                                    <td style="padding: 12px 8px; color: var(--text-main);">🏢 ${esc(rep.nomeFornecedor)}</td>
                                    <td style="padding: 12px 8px; text-align: right;">
                                        ${rep.linkWhatsApp ? `
                                            <a href="${esc(rep.linkWhatsApp)}" target="_blank" rel="noopener noreferrer" style="background: #25D366; color: #fff; padding: 6px 12px; font-size: 12px; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                                                <span>💬</span> Pedir no WhatsApp
                                            </a>
                                        ` : `<a href="/modulos/fornecedores/fornecedor.php" class="btn-secondary" style="padding: 6px 10px; font-size: 11.5px; text-decoration: none;">Vincular Contato</a>`}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        ` : ''}

        <div class="pbi-visual-card" style="margin-bottom: 24px;">
            <div class="pbi-visual-header" style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 16px;">
                <div class="pbi-visual-title-box">
                    <h3 style="display: flex; align-items: center; gap: 8px;"><span>⚖️</span> Curva ABC de Estoque (Classificação Pareto)</h3>
                    <p>Hierarquia de relevância operacional e financeira para tomada de decisão no armazém</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                <div style="background: rgba(16, 185, 129, 0.08); border: 1.5px solid rgba(16, 185, 129, 0.35); border-radius: var(--radius-md); padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong style="font-size: 16px; color: #10b981;">Classe A (Críticos / VIP)</strong>
                        <span style="background: #10b981; color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px;">~80% do Valor</span>
                    </div>
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Itens de altíssimo valor e giro. Jamais podem faltar no armazém.</p>
                    <div style="font-size: 12.5px; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
                        Total: ${dadosBI.curva_abc.A.qtd_itens} produto(s) &bull; R$ ${formatCurrencyBR(dadosBI.curva_abc.A.total_faturado)}
                    </div>
                    <ul style="list-style: none; padding: 0; font-size: 12px; color: var(--text-main);">
                        ${dadosBI.curva_abc.A.produtos.slice(0, 4).map(p => `<li style="padding: 4px 0; border-top: 1px dashed rgba(16, 185, 129, 0.2); display: flex; justify-content: space-between;"><span>• ${esc(p.nomeProduto)}</span><strong>R$ ${formatCurrencyBR(p.baseCalculo)}</strong></li>`).join('')}
                    </ul>
                </div>

                <div style="background: rgba(245, 158, 11, 0.08); border: 1.5px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-md); padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong style="font-size: 16px; color: #f59e0b;">Classe B (Intermediários)</strong>
                        <span style="background: #f59e0b; color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px;">~15% do Valor</span>
                    </div>
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Relevância média. Controle de estoque moderado e reposições periódicas.</p>
                    <div style="font-size: 12.5px; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
                        Total: ${dadosBI.curva_abc.B.qtd_itens} produto(s) &bull; R$ ${formatCurrencyBR(dadosBI.curva_abc.B.total_faturado)}
                    </div>
                    <ul style="list-style: none; padding: 0; font-size: 12px; color: var(--text-main);">
                        ${dadosBI.curva_abc.B.produtos.slice(0, 4).map(p => `<li style="padding: 4px 0; border-top: 1px dashed rgba(245, 158, 11, 0.2); display: flex; justify-content: space-between;"><span>• ${esc(p.nomeProduto)}</span><strong>R$ ${formatCurrencyBR(p.baseCalculo)}</strong></li>`).join('')}
                    </ul>
                </div>

                <div style="background: rgba(148, 163, 184, 0.08); border: 1.5px solid rgba(148, 163, 184, 0.35); border-radius: var(--radius-md); padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong style="font-size: 16px; color: #64748b;">Classe C (Baixo Impacto)</strong>
                        <span style="background: #64748b; color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px;">~5% do Valor</span>
                    </div>
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Muitos itens com baixo impacto financeiro. Permite compras em lote espaçado.</p>
                    <div style="font-size: 12.5px; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
                        Total: ${dadosBI.curva_abc.C.qtd_itens} produto(s) &bull; R$ ${formatCurrencyBR(dadosBI.curva_abc.C.total_faturado)}
                    </div>
                    <ul style="list-style: none; padding: 0; font-size: 12px; color: var(--text-main);">
                        ${dadosBI.curva_abc.C.produtos.slice(0, 4).map(p => `<li style="padding: 4px 0; border-top: 1px dashed rgba(148, 163, 184, 0.2); display: flex; justify-content: space-between;"><span>• ${esc(p.nomeProduto)}</span><strong>R$ ${formatCurrencyBR(p.baseCalculo)}</strong></li>`).join('')}
                    </ul>
                </div>
            </div>
        </div>

        <div style="margin-top: 10px;">
            <h3 style="font-size: 15px; color: var(--pbi-text-main); margin-bottom: 10px; font-weight: 750;">⚡ Atalhos de Gestão Operacional</h3>
            <div class="pbi-quick-nav">
                <a href="/modulos/produtos/produto.php" class="pbi-nav-card"><div class="pbi-nav-icon">📦</div><div class="pbi-nav-text"><strong>Produtos</strong><span>Cadastrar e gerenciar catálogo</span></div></a>
                <a href="/modulos/clientes/cliente.php" class="pbi-nav-card"><div class="pbi-nav-icon">👥</div><div class="pbi-nav-text"><strong>Clientes</strong><span>Carteira de clientes e compras</span></div></a>
                <a href="/modulos/movimentos/movimento.php" class="pbi-nav-card"><div class="pbi-nav-icon">💳</div><div class="pbi-nav-text"><strong>Histórico de Vendas</strong><span>Entradas, saídas e devoluções</span></div></a>
                <a href="/modulos/fornecedores/fornecedor.php" class="pbi-nav-card"><div class="pbi-nav-icon">🏢</div><div class="pbi-nav-text"><strong>Fornecedores</strong><span>Gestão de parceiros comerciais</span></div></a>
            </div>
        </div>
    </div>

    <script>
        const dadosBI = ${JSON.stringify(dadosBI)};
    </script>
    <script src="/javaScript/dashboard_bi.js"></script>
</div>`;

  return { html: renderLayout('Dashboard de Vendas - Power BI', 'index.php', session, html) };
}

async function handleProdutos(req, body, query, db, session, subRoute) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;

  if (subRoute === 'produto_excluir.php') {
    const id = Number(body.id || query.id || 0);
    if (id > 0) {
      db.produtos = db.produtos.filter(p => !(p.idProduto === id && Number(p.idUsuario) === uid));
      await saveDb(db);
      session.flash_mensagem = { tipo: 'sucesso', texto: 'Produto removido do catálogo com sucesso!' };
    }
    return { redirect: '/modulos/produtos/produto.php' };
  }

  if (subRoute === 'produto_editar.php') {
    const id = Number(body.idProduto || query.id || query.idProduto || 0);
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
      if (body.imagem_data_url && String(body.imagem_data_url).startsWith('data:image/')) {
        prod.imagem = String(body.imagem_data_url);
      }
      await saveDb(db);
      session.flash_mensagem = { tipo: 'sucesso', texto: 'Produto atualizado com sucesso!' };
      return { redirect: '/modulos/produtos/produto.php' };
    }

    const htmlEdit = `
<div class="page-container">
    <div class="table-card" style="max-width:720px; margin:20px auto; padding:28px;">
        <div class="page-header-row" style="margin-bottom:20px;">
            <div class="page-header-titles">
                <h1>✏️ Editar Produto #${prod.idProduto}</h1>
                <p>Atualize informações de estoque, preço, lote e posição WMS</p>
            </div>
        </div>
        <form action="/modulos/produtos/produto_editar.php?id=${prod.idProduto}" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="idProduto" value="${prod.idProduto}">
            <div class="form-row-2">
                <div class="form-group"><label>Nome do Produto *</label><input type="text" name="nomeProduto" required value="${esc(prod.nomeProduto)}"></div>
                <div class="form-group"><label>Código de Barras (EAN-13)</label><input type="text" name="codigoBarras" value="${esc(prod.codigoBarras)}"></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label>Categoria</label><input type="text" name="categoria" value="${esc(prod.categoria)}"></div>
                <div class="form-group"><label>Preço de Venda (R$) *</label><input type="number" step="0.01" name="preco" required value="${prod.preco}"></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label>Preço de Custo (R$)</label><input type="number" step="0.01" name="precoCusto" value="${prod.precoCusto}"></div>
                <div class="form-group"><label>Quantidade Atual *</label><input type="number" name="quantidade" required value="${prod.quantidade}"></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label>Estoque Mínimo</label><input type="number" name="estoqueMinimo" value="${prod.estoqueMinimo}"></div>
                <div class="form-group"><label>Posição WMS *</label><input type="text" name="localizacao" value="${esc(prod.localizacao)}"></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label>Lote</label><input type="text" name="lote" value="${esc(prod.lote)}"></div>
                <div class="form-group"><label>Data de Validade</label><input type="date" name="dataValidade" value="${esc(prod.dataValidade || '')}"></div>
            </div>
            <div class="form-group"><label>Descrição</label><textarea name="descricao" rows="3">${esc(prod.descricao || '')}</textarea></div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:18px;">
                <a href="/modulos/produtos/produto.php" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>`;
    return { html: renderLayout('Editar Produto - MVM', 'produto_editar.php', session, htmlEdit) };
  }

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
      imagem: (body.imagem_data_url && String(body.imagem_data_url).startsWith('data:image/')) ? String(body.imagem_data_url) : null
    });
    await saveDb(db);
    session.flash_mensagem = { tipo: 'sucesso', texto: 'Produto cadastrado com sucesso no catálogo!' };
    return { redirect: '/modulos/produtos/produto.php' };
  }

  const busca = String(query.busca || '').trim();
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const forns = db.fornecedores.filter(f => Number(f.idUsuario) === uid);
  const flash = session.flash_mensagem;
  delete session.flash_mensagem;

  const html = `
<div class="page-container">
    ${flash ? `
        <div class="alert-box ${flash.tipo === 'sucesso' ? 'success' : 'error'}">
            <span>${flash.tipo === 'sucesso' ? '✅' : '⚠️'}</span>
            <p>${esc(flash.texto)}</p>
        </div>
    ` : ''}

    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Catálogo de Produtos</h1>
            <p>Gerencie o inventário, níveis de estoque, lotes e endereçamento logístico WMS</p>
        </div>
        <div class="page-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn-primary" data-open-modal="modal-novo-produto">
                <span>➕</span> Novo Produto
            </button>
            <a href="/exportacao/relatorio_inventario_pdf.php" target="_blank" class="btn-secondary" title="Gerar Folha Oficial de Inventário Físico para Contagem em PDF">
                <span>📋</span> Inventário (PDF)
            </a>
            <a href="/exportacao/relatorio_inventario_excel.php" class="btn-secondary" title="Baixar planilha de Contagem de Estoque Físico (.csv/.xlsx)">
                <span>📊</span> Inventário (.xlsx)
            </a>
            <form action="/scripts/disparar_alerta.php" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Deseja enviar o relatório de estoque crítico e validades para seu e-mail agora?')">
                <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                <input type="hidden" name="origem" value="/modulos/produtos/produto.php">
                <button type="submit" class="btn-secondary" title="Enviar relatório gerencial de alertas por e-mail">
                    <span>📧</span> Alertas por E-mail
                </button>
            </form>
            <button type="button" id="btnExportarExcel" class="btn-secondary">
                <span>📊</span> Excel
            </button>
            <button type="button" id="btnExportarPDF" class="btn-secondary">
                <span>📄</span> PDF
            </button>
        </div>
    </div>

    <div class="table-filter-bar">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Filtrar produtos instantaneamente..." data-table-search="#tabela-produtos" value="${esc(busca)}">
        </div>
        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;">${prods.length}</span> itens
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-produtos">
                <thead>
                    <tr>
                        <th style="width: 32%;">Produto &amp; Detalhes</th>
                        <th style="width: 15%;">Categoria</th>
                        <th style="width: 13%;">Preço (R$)</th>
                        <th style="width: 18%;">Status do Estoque</th>
                        <th style="width: 14%;">Fornecedor</th>
                        <th style="width: 8%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${prods.length === 0 ? `
                        <tr class="empty-row">
                            <td colspan="6" style="text-align: center; padding: 42px; color: var(--text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">📦</div>
                                <strong>Nenhum produto cadastrado neste perfil</strong>
                                <p style="font-size: 12.5px; margin-top: 4px;">Clique em "+ Novo Produto" para começar a montar o seu catálogo isolado.</p>
                            </td>
                        </tr>
                    ` : prods.map(p => {
                      const qtd = Number(p.quantidade || 0);
                      const min = Math.max(1, Number(p.estoqueMinimo || 10));
                      let statusClass = 'success';
                      let statusTexto = `Normal: ${qtd} un`;
                      if (qtd <= 0) {
                        statusClass = 'danger';
                        statusTexto = 'Esgotado (0 un)';
                      } else if (qtd <= min) {
                        statusClass = 'warning';
                        statusTexto = `Baixo: ${qtd} un (Mín: ${min})`;
                      }
                      const descCurta = String(p.descricao || 'Sem descrição').slice(0, 45);
                      return `
                        <tr>
                            <td>
                                <div class="cell-product">
                                    ${p.imagem ? `<img src="${String(p.imagem).startsWith('data:') ? esc(p.imagem) : '/uploads/produtos/' + esc(p.imagem)}" alt="${esc(p.nomeProduto)}" class="product-thumb">` : `<div class="product-thumb" title="Sem foto">📦</div>`}
                                    <div class="cell-product-info">
                                        <strong>${esc(p.nomeProduto)}</strong>
                                        <span>${esc(descCurta)}</span>
                                        <div style="margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap;">
                                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 2px 7px; background: rgba(99, 102, 241, 0.1); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-weight: 700;" title="Endereço no armazém (WMS)">
                                                📍 ${esc(p.localizacao || 'A-01-01')}
                                            </span>
                                            ${p.codigoBarras ? `
                                                <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; padding: 2px 7px; background: var(--bg-subtle); color: var(--text-muted); border: 1px solid var(--border); border-radius: 4px; font-family: 'JetBrains Mono', monospace;" title="Código de Barras EAN-13 / GTIN">
                                                    🏷️ ${esc(p.codigoBarras)}
                                                </span>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="status-pill neutral">${esc(p.categoria || 'Geral')}</span></td>
                            <td><strong style="font-family: 'JetBrains Mono', monospace; font-size: 13px;">R$ ${formatCurrencyBR(p.preco)}</strong></td>
                            <td><span class="status-pill ${statusClass}">${statusTexto}</span></td>
                            <td><span style="color: var(--text-muted); font-size: 12px;">${esc(p.nomeFornecedor || 'Não vinculado')}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="/modulos/relatorios/kardex.php?idProduto=${p.idProduto}" class="btn-icon" title="Ver Ficha Kardex (Extrato)">📜</a>
                                    <a href="/modulos/produtos/produto_editar.php?id=${p.idProduto}" class="btn-icon" title="Editar Produto">✏️</a>
                                    <button type="button" class="btn-icon delete" style="border:none; cursor:pointer;" onclick='abrirModalExclusao(${p.idProduto}, ${JSON.stringify(p.nomeProduto)})' title="Excluir Produto">🗑️</button>
                                </div>
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Listagem sincronizada em tempo real com o banco de dados</span>
            <span>Exibindo ${prods.length} registros</span>
        </div>
    </div>
</div>

<div id="modal-novo-produto" class="modal-container">
    <div class="drawer-content">
        <div class="drawer-header">
            <div>
                <h2>Cadastrar Novo Produto</h2>
                <p>Preencha as informações para registrar o item no catálogo</p>
            </div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>

        <form action="/modulos/produtos/produto.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="imagem_data_url" id="imagem_data_url" value="">
            <div class="drawer-body">
                <!-- CONSULTA INTELIGENTE VIA API EXTERNA (EAN-13 / GTIN) -->
                <div class="card-api-ean" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: var(--radius-sm); padding: 14px 16px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                            <span>⚡</span> Preenchimento Rápido via API (EAN-13)
                        </span>
                        <span style="font-size: 10px; background: rgba(99, 102, 241, 0.15); color: var(--primary); padding: 2px 7px; border-radius: 4px; font-weight: 700;">Open Food Facts Global</span>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="input-ean-api" placeholder="Digite ou bipe o código de barras (Ex: 7894900011517)..." style="flex: 1; padding: 9px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 13px; font-family: 'JetBrains Mono', monospace;" maxlength="14" onkeydown="if(event.key==='Enter'){event.preventDefault(); consultarEanAPI();}">
                        <button type="button" id="btn-consultar-ean" onclick="consultarEanAPI()" class="btn-primary" style="padding: 9px 14px; font-size: 12.5px; white-space: nowrap; gap: 6px;">
                            <span>🔍</span> Consultar API
                        </button>
                    </div>
                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 8px; font-size: 11px;">
                        <span style="color: var(--text-muted); font-weight: 600;">Exemplos rápidos:</span>
                        <button type="button" onclick="testarEan('7894900011517')" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-main);">🥤 Coca-Cola 2L</button>
                        <button type="button" onclick="testarEan('7891000100103')" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-main);">🥛 Leite Moça</button>
                        <button type="button" onclick="testarEan('7892840222949')" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-main);">🍟 Ruffles</button>
                    </div>
                    <div id="ean-status-msg" style="display:none; margin-top: 8px; font-size: 12px; padding: 7px 10px; border-radius: 4px;"></div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="nomeProduto">Nome do Produto *</label>
                        <input type="text" id="nomeProduto" name="nomeProduto" placeholder="Ex: Mouse Gamer Logitech G502" required>
                    </div>
                    <div class="form-group">
                        <label for="codigoBarras">Código de Barras (EAN-13 / GTIN)</label>
                        <input type="text" id="codigoBarras" name="codigoBarras" maxlength="20" placeholder="Ex: 7894900011517" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label for="categoria">Categoria</label><input type="text" id="categoria" name="categoria" placeholder="Ex: Bebidas, Informatica..."></div>
                    <div class="form-group"><label for="preco">Preço de Venda (R$) *</label><input type="number" step="0.01" min="0" id="preco" name="preco" placeholder="0,00" required></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label for="precoCusto">Preço de Custo (R$)</label><input type="number" step="0.01" min="0" id="precoCusto" name="precoCusto" placeholder="0,00"></div>
                    <div class="form-group"><label for="quantidade">Estoque Atual</label><input type="number" min="0" name="quantidade" id="quantidade" value="10"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label for="estoqueMinimo">Estoque Mínimo (Alerta)</label><input type="number" min="1" name="estoqueMinimo" id="estoqueMinimo" value="10"></div>
                    <div class="form-group">
                        <label for="nomeFornecedor">Fornecedor Parceiro</label>
                        <select id="nomeFornecedor" name="nomeFornecedor">
                            <option value="">-- Opcional --</option>
                            ${forns.map(f => `<option value="${esc(f.nomeFornecedor)}">${esc(f.nomeFornecedor)}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label for="lote">Lote de Fabricação (Opcional)</label><input type="text" id="lote" name="lote" placeholder="Ex: LOT-2026-A1"></div>
                    <div class="form-group"><label for="dataValidade">Data de Validade (Logística FEFO)</label><input type="date" id="dataValidade" name="dataValidade"></div>
                </div>
                <div class="form-group">
                    <label for="localizacao">Endereço no Armazém (WMS) *</label>
                    <input type="text" id="localizacao" name="localizacao" value="A-01-01" required>
                </div>
                <div class="form-group">
                    <label for="descricao">Descrição Completa</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="Detalhes, especificações e observações do item..."></textarea>
                </div>
                <div class="form-group">
                    <label for="imagem">Foto do Produto</label>
                    <input type="file" id="imagem" accept="image/*" onchange="converterImagemProduto(this, 'imagem_data_url')">
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Produto</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-confirmar-exclusao" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card">
        <div class="confirm-dialog-icon">🗑️</div>
        <h3>Excluir Produto</h3>
        <p>Tem certeza que deseja remover este produto permanentemente do estoque?</p>
        <div class="confirm-dialog-badge" id="confirm-dialog-item-nome">Nome do Produto</div>
        <p class="confirm-dialog-aviso">⚠️ Esta ação não pode ser desfeita.</p>
        <form id="form-excluir-produto-modal" action="/modulos/produtos/produto_excluir.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="id" id="input-excluir-id" value="">
            <div class="confirm-dialog-actions">
                <button type="button" class="btn-secondary" onclick="fecharModalExclusao()">Cancelar</button>
                <button type="submit" class="btn-danger-confirm"><span>🗑️</span> Sim, Excluir Produto</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalExclusao(id, nome) {
        const modal = document.getElementById('modal-confirmar-exclusao');
        const inputId = document.getElementById('input-excluir-id');
        const badgeNome = document.getElementById('confirm-dialog-item-nome');
        if (inputId) inputId.value = id;
        if (badgeNome) badgeNome.textContent = nome || 'Produto #' + id;
        if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    }
    function fecharModalExclusao() {
        const modal = document.getElementById('modal-confirmar-exclusao');
        if (modal) { modal.classList.remove('active'); document.body.style.overflow = ''; }
    }
    function converterImagemProduto(input, hiddenId) {
        const file = input.files && input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const hidden = document.getElementById(hiddenId);
            if (hidden) hidden.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    function testarEan(codigo) {
        document.getElementById('input-ean-api').value = codigo;
        consultarEanAPI();
    }
    async function consultarEanAPI() {
        const input = document.getElementById('input-ean-api');
        const btn = document.getElementById('btn-consultar-ean');
        const msg = document.getElementById('ean-status-msg');
        const ean = (input.value || '').replace(/\\D/g, '').trim();
        if (!ean || ean.length < 7) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239, 68, 68, 0.1)';
            msg.style.color = '#ef4444';
            msg.textContent = 'Por favor, informe um código de barras EAN válido (mínimo 7 dígitos).';
            return;
        }
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<span>⏳</span> Consultando...';
        btn.disabled = true;
        msg.style.display = 'none';
        try {
            const response = await fetch('/api/consulta_ean.php?ean=' + ean);
            const data = await response.json();
            if (data.sucesso) {
                if (data.nome) document.getElementById('nomeProduto').value = data.nome;
                if (data.categoria) document.getElementById('categoria').value = data.categoria;
                if (data.descricao) document.getElementById('descricao').value = data.descricao;
                const fornSelect = document.getElementById('nomeFornecedor');
                if (data.marca && fornSelect) {
                    let encontrou = false;
                    for (let opt of fornSelect.options) {
                        if (opt.value.toLowerCase().includes(data.marca.toLowerCase())) {
                            opt.selected = true;
                            encontrou = true;
                            break;
                        }
                    }
                    if (!encontrou) {
                        const desc = document.getElementById('descricao');
                        desc.value += (desc.value ? '\\n' : '') + 'Fabricante/Marca: ' + data.marca;
                    }
                }
                const eanInp = document.getElementById('codigoBarras');
                if (eanInp) eanInp.value = ean;
                msg.style.display = 'block';
                msg.style.background = 'rgba(16, 185, 129, 0.12)';
                msg.style.color = '#10b981';
                msg.innerHTML = '✅ <strong>Produto localizado na API Global!</strong> Dados de "<em>' + data.nome + '</em>" preenchidos automaticamente.';
                document.getElementById('preco').focus();
            } else {
                msg.style.display = 'block';
                msg.style.background = 'rgba(245, 158, 11, 0.12)';
                msg.style.color = '#f59e0b';
                msg.textContent = data.mensagem || 'Produto não encontrado na base de dados global.';
            }
        } catch (err) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239, 68, 68, 0.1)';
            msg.style.color = '#ef4444';
            msg.textContent = 'Falha ao conectar com o serviço de consulta da API.';
        } finally {
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    }
    const produtosData = ${JSON.stringify(prods)};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="/javaScript/exportarexcel.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="/javaScript/exportarPDF.js"></script>`;

  return { html: renderLayout('Catálogo de Produtos - MVM', 'produto.php', session, html) };
}

async function handleClientes(req, body, query, db, session, subRoute) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;

  if (subRoute === 'cliente_excluir.php') {
    const id = Number(body.id || query.id || 0);
    if (id > 0) {
      db.clientes = db.clientes.filter(c => !(c.idCliente === id && Number(c.idUsuario) === uid));
      await saveDb(db);
      session.flash_mensagem = { tipo: 'sucesso', texto: 'Cliente removido com sucesso!' };
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
      await saveDb(db);
      session.flash_mensagem = { tipo: 'sucesso', texto: 'Dados do cliente atualizados com sucesso!' };
      return { redirect: '/modulos/clientes/cliente.php' };
    }
    const htmlEdit = `
<div class="page-container">
    <div class="table-card" style="max-width:620px; margin:20px auto; padding:26px;">
        <h2>✏️ Editar Cliente #${cli.idCliente}</h2>
        <form action="/modulos/clientes/cliente_editar.php?id=${cli.idCliente}" method="POST">
            <input type="hidden" name="idCliente" value="${cli.idCliente}">
            <div class="form-group"><label>Nome Completo *</label><input type="text" name="nomeCliente" required value="${esc(cli.nomeCliente)}"></div>
            <div class="form-group"><label>E-mail *</label><input type="email" name="email" required value="${esc(cli.email)}"></div>
            <div class="form-group"><label>Telefone</label><input type="text" name="telefone" value="${esc(cli.telefone)}"></div>
            <div class="form-row-2">
                <div class="form-group"><label>Cidade</label><input type="text" name="cidade" value="${esc(cli.cidade)}"></div>
                <div class="form-group"><label>UF</label><input type="text" name="uf" maxlength="2" value="${esc(cli.uf)}"></div>
            </div>
            <div class="form-group"><label>Endereço</label><input type="text" name="endereco" value="${esc(cli.endereco)}"></div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <a href="/modulos/clientes/cliente.php" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>`;
    return { html: renderLayout('Editar Cliente - MVM', 'cliente_editar.php', session, htmlEdit) };
  }

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
    await saveDb(db);
    session.flash_mensagem = { tipo: 'sucesso', texto: 'Cliente cadastrado com sucesso!' };
    return { redirect: '/modulos/clientes/cliente.php' };
  }

  const busca = String(query.busca || '').trim();
  const clientes = db.clientes.filter(c => Number(c.idUsuario) === uid);
  const movs = db.movimentos.filter(m => Number(m.idUsuario) === uid && m.tipoMovimento === 'SAIDA' && !m.dataDevolucao);
  const flash = session.flash_mensagem;
  delete session.flash_mensagem;

  const html = `
<div class="page-container">
    ${flash ? `<div class="alert-box ${flash.tipo === 'sucesso' ? 'success' : 'error'}"><span>✅</span><p>${esc(flash.texto)}</p></div>` : ''}

    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Carteira de Clientes</h1>
            <p>Gerencie contatos, histórico de compras e canais de comunicação direta</p>
        </div>
        <div class="page-header-actions">
            <button type="button" class="btn-primary" data-open-modal="modal-novo-cliente"><span>➕</span> Novo Cliente</button>
            <button type="button" id="btnExportarExcel" class="btn-secondary"><span>📊</span> Excel</button>
            <button type="button" id="btnExportarPDF" class="btn-secondary"><span>📄</span> PDF</button>
        </div>
    </div>

    <div class="table-filter-bar">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Filtrar por nome, email ou cidade..." data-table-search="#tabela-clientes" value="${esc(busca)}">
        </div>
        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;">${clientes.length}</span> clientes
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-clientes">
                <thead>
                    <tr>
                        <th style="width: 26%;">Cliente</th>
                        <th style="width: 18%;">Telefone / Contato</th>
                        <th style="width: 18%;">Localização</th>
                        <th style="width: 18%;">Histórico de Compras</th>
                        <th style="width: 10%;">Endereço</th>
                        <th style="width: 10%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${clientes.map(c => {
                      const compras = movs.filter(m => Number(m.idCliente) === c.idCliente);
                      const qtdC = compras.length;
                      const valC = compras.reduce((s, m) => s + Number(m.valorTotal || 0), 0);
                      return `
                        <tr>
                            <td>
                                <div class="cell-product">
                                    <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px; background: linear-gradient(135deg, #635bff 0%, #4f46e5 100%);">
                                        ${esc(String(c.nomeCliente || 'C').slice(0, 1).toUpperCase())}
                                    </div>
                                    <div class="cell-product-info">
                                        <strong>${esc(c.nomeCliente)}</strong>
                                        <span>${esc(c.email)}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                ${c.telefone ? `<span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text);">📞 ${esc(c.telefone)}</span>` : `<span style="color: var(--text-light); font-size: 12px;">Não cadastrado</span>`}
                            </td>
                            <td>
                                ${c.cidade ? `<span class="status-pill neutral">📍 ${esc(c.cidade)}${c.uf ? ' - ' + esc(c.uf) : ''}</span>` : `<span style="color: var(--text-light); font-size: 12px;">Não informado</span>`}
                            </td>
                            <td>
                                ${qtdC > 0 ? `<div style="font-size: 12px;"><strong style="color: #10b981; font-family: 'JetBrains Mono', monospace;">R$ ${formatCurrencyBR(valC)}</strong><div style="color: var(--text-muted); font-size: 11px;">${qtdC} ${qtdC === 1 ? 'compra realizada' : 'compras realizadas'}</div></div>` : `<span style="color: var(--text-light); font-size: 12px;">Sem compras</span>`}
                            </td>
                            <td><span style="color: var(--text-muted); font-size: 12px;">${esc(c.endereco || 'Endereço não informado')}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a href="/modulos/movimentos/movimento.php?busca=${encodeURIComponent(c.nomeCliente)}" class="btn-icon" title="Ver Histórico de Compras">🛒</a>
                                    <a href="/modulos/clientes/cliente_editar.php?id=${c.idCliente}" class="btn-icon" title="Editar Cliente">✏️</a>
                                    <button type="button" class="btn-icon delete" style="border:none; cursor:pointer;" onclick='abrirModalExclusao(${c.idCliente}, ${JSON.stringify(c.nomeCliente)})' title="Excluir Cliente">🗑️</button>
                                </div>
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Base sincronizada com módulo de emissão de vendas</span>
            <span>Exibindo ${clientes.length} clientes</span>
        </div>
    </div>
</div>

<div id="modal-novo-cliente" class="modal-container">
    <div class="drawer-content">
        <div class="drawer-header">
            <div><h2>Cadastrar Novo Cliente</h2><p>Insira os dados do comprador para registro e emissão de compras</p></div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>
        <form action="/modulos/clientes/cliente.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <div class="drawer-body">
                <div class="form-group"><label for="nomeCliente">Nome Completo *</label><input type="text" id="nomeCliente" name="nomeCliente" placeholder="Ex: Ana Maria Silva" required></div>
                <div class="form-group"><label for="email">E-mail Comercial / Pessoal *</label><input type="email" id="email" name="email" placeholder="cliente@exemplo.com" required></div>
                <div class="form-group"><label for="telefone">Telefone / Celular</label><input type="text" id="telefone" name="telefone" placeholder="(11) 98765-4321"></div>
                <div class="form-row-2">
                    <div class="form-group"><label for="cidade">Cidade</label><input type="text" id="cidade" name="cidade" placeholder="Ex: São Paulo"></div>
                    <div class="form-group"><label for="uf">Estado (UF)</label><input type="text" id="uf" name="uf" maxlength="2" placeholder="SP" style="text-transform: uppercase;"></div>
                </div>
                <div class="form-group"><label for="endereco">Endereço Residencial / Comercial</label><input type="text" id="endereco" name="endereco" placeholder="Rua, número, complemento..."></div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Cliente</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-confirmar-exclusao" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card">
        <div class="confirm-dialog-icon">🗑️</div>
        <h3>Excluir Cliente</h3>
        <p>Tem certeza que deseja remover este cliente permanentemente da sua carteira?</p>
        <div class="confirm-dialog-badge" id="confirm-dialog-item-nome">Nome do Cliente</div>
        <form id="form-excluir-cliente-modal" action="/modulos/clientes/cliente_excluir.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="id" id="input-excluir-id" value="">
            <div class="confirm-dialog-actions">
                <button type="button" class="btn-secondary" onclick="fecharModalExclusao()">Cancelar</button>
                <button type="submit" class="btn-danger-confirm"><span>🗑️</span> Sim, Excluir Cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalExclusao(id, nome) {
        const modal = document.getElementById('modal-confirmar-exclusao');
        const inputId = document.getElementById('input-excluir-id');
        const badgeNome = document.getElementById('confirm-dialog-item-nome');
        if (inputId) inputId.value = id;
        if (badgeNome) badgeNome.textContent = nome || 'Cliente #' + id;
        if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    }
    function fecharModalExclusao() {
        const modal = document.getElementById('modal-confirmar-exclusao');
        if (modal) { modal.classList.remove('active'); document.body.style.overflow = ''; }
    }
    const clientesData = ${JSON.stringify(clientes)};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="/javaScript/exportarexcel.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="/javaScript/exportarPDF.js"></script>`;

  return { html: renderLayout('MVM - Carteira de Clientes', 'cliente.php', session, html) };
}

async function handleFornecedores(req, body, query, db, session, subRoute) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;

  if (subRoute === 'fornecedor_excluir.php') {
    const id = Number(body.id || query.id || 0);
    if (id > 0) {
      db.fornecedores = db.fornecedores.filter(f => !(f.idFornecedor === id && Number(f.idUsuario) === uid));
      await saveDb(db);
      session.flash_mensagem = { tipo: 'sucesso', texto: 'Fornecedor removido com sucesso!' };
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
      forn.endereco = String(body.endereco || forn.endereco || '').trim();
      await saveDb(db);
      session.flash_mensagem = { tipo: 'sucesso', texto: 'Fornecedor atualizado com sucesso!' };
      return { redirect: '/modulos/fornecedores/fornecedor.php' };
    }
    const htmlEdit = `
<div class="page-container">
    <div class="table-card" style="max-width:620px; margin:20px auto; padding:26px;">
        <h2>✏️ Editar Fornecedor #${forn.idFornecedor}</h2>
        <form action="/modulos/fornecedores/fornecedor_editar.php?id=${forn.idFornecedor}" method="POST">
            <input type="hidden" name="idFornecedor" value="${forn.idFornecedor}">
            <div class="form-group"><label>Razão Social / Nome *</label><input type="text" name="nomeFornecedor" required value="${esc(forn.nomeFornecedor)}"></div>
            <div class="form-row-2">
                <div class="form-group"><label>CNPJ *</label><input type="text" name="cnpj" required value="${esc(forn.cnpj)}"></div>
                <div class="form-group"><label>Segmento</label><input type="text" name="segmento" value="${esc(forn.segmento)}"></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label>E-mail</label><input type="email" name="email" value="${esc(forn.email)}"></div>
                <div class="form-group"><label>Telefone</label><input type="text" name="telefone" value="${esc(forn.telefone)}"></div>
            </div>
            <div class="form-row-2">
                <div class="form-group"><label>Cidade</label><input type="text" name="cidade" value="${esc(forn.cidade)}"></div>
                <div class="form-group"><label>UF</label><input type="text" name="uf" maxlength="2" value="${esc(forn.uf)}"></div>
            </div>
            <div class="form-group"><label>Endereço</label><input type="text" name="endereco" value="${esc(forn.endereco || '')}"></div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <a href="/modulos/fornecedores/fornecedor.php" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>`;
    return { html: renderLayout('Editar Fornecedor - MVM', 'fornecedor_editar.php', session, htmlEdit) };
  }

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
    await saveDb(db);
    session.flash_mensagem = { tipo: 'sucesso', texto: 'Fornecedor cadastrado com sucesso!' };
    return { redirect: '/modulos/fornecedores/fornecedor.php' };
  }

  const busca = String(query.busca || '').trim();
  const forns = db.fornecedores.filter(f => Number(f.idUsuario) === uid);
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const flash = session.flash_mensagem;
  delete session.flash_mensagem;

  const html = `
<div class="page-container">
    ${flash ? `<div class="alert-box ${flash.tipo === 'sucesso' ? 'success' : 'error'}"><span>✅</span><p>${esc(flash.texto)}</p></div>` : ''}

    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Gestão de Fornecedores</h1>
            <p>Controle de distribuidoras, segmentos industriais e canais de reposição de estoque</p>
        </div>
        <div class="page-header-actions">
            <button type="button" class="btn-primary" data-open-modal="modal-novo-fornecedor"><span>➕</span> Novo Fornecedor</button>
            <button type="button" id="btnExportarExcel" class="btn-secondary"><span>📊</span> Excel</button>
            <button type="button" id="btnExportarPDF" class="btn-secondary"><span>📄</span> PDF</button>
        </div>
    </div>

    <div class="table-filter-bar">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Filtrar por empresa, CNPJ, segmento ou cidade..." data-table-search="#tabela-fornecedores" value="${esc(busca)}">
        </div>
        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;">${forns.length}</span> parceiros
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-fornecedores">
                <thead>
                    <tr>
                        <th style="width: 24%;">Fornecedor &amp; CNPJ</th>
                        <th style="width: 14%;">Segmento</th>
                        <th style="width: 16%;">Mix no Catálogo</th>
                        <th style="width: 18%;">Contato &amp; E-mail</th>
                        <th style="width: 18%;">Localização &amp; Endereço</th>
                        <th style="width: 10%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${forns.map(f => {
                      const mix = prods.filter(p => p.nomeFornecedor === f.nomeFornecedor || Number(p.idFornecedor) === f.idFornecedor);
                      const totP = mix.length;
                      const totEst = mix.reduce((s, p) => s + Number(p.quantidade || 0), 0);
                      let telLimpo = String(f.telefone || '').replace(/\D/g, '');
                      if (telLimpo && telLimpo.length <= 11) telLimpo = '55' + telLimpo;
                      const msgZap = encodeURIComponent(`Olá ${f.nomeFornecedor}! Aqui é da MVM Estoque. Gostaríamos de solicitar uma cotação e previsão de reposição de itens. Poderia nos atender?`);
                      return `
                        <tr>
                            <td>
                                <div class="cell-product">
                                    <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">🏢</div>
                                    <div class="cell-product-info">
                                        <strong>${esc(f.nomeFornecedor)}</strong>
                                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px;">CNPJ: ${esc(f.cnpj)}</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="status-pill info">${esc(f.segmento || 'Geral')}</span></td>
                            <td>
                                ${totP > 0 ? `<div style="font-size: 12px;"><strong style="color: var(--primary);">${totP} ${totP === 1 ? 'produto' : 'produtos'}</strong><div style="color: var(--text-muted); font-size: 11px;">${totEst} un. em estoque</div></div>` : `<span style="color: var(--text-light); font-size: 12px;">Sem itens vinculados</span>`}
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    ${f.telefone ? `<span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text);">📞 ${esc(f.telefone)}</span>` : ''}
                                    ${f.email ? `<span style="font-size: 11px; color: var(--text-muted);">✉️ ${esc(f.email)}</span>` : ''}
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 12px;">
                                    <strong>${esc(f.cidade || 'Cidade não inf.')}${f.uf ? ' - ' + esc(f.uf) : ''}</strong>
                                    ${f.endereco ? `<div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">${esc(f.endereco)}</div>` : ''}
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="/modulos/produtos/produto.php?busca=${encodeURIComponent(f.nomeFornecedor)}" class="btn-icon" title="Ver Produtos deste Fornecedor">📦</a>
                                    ${telLimpo ? `<a href="https://wa.me/${telLimpo}?text=${msgZap}" target="_blank" rel="noopener noreferrer" class="btn-icon" style="color: #25D366; background: rgba(37, 211, 102, 0.12);" title="Solicitar Cotação no WhatsApp">💬</a>` : ''}
                                    <a href="/modulos/fornecedores/fornecedor_editar.php?id=${f.idFornecedor}" class="btn-icon" title="Editar Fornecedor">✏️</a>
                                    <button type="button" class="btn-icon delete" style="border:none; cursor:pointer;" onclick='abrirModalExclusao(${f.idFornecedor}, ${JSON.stringify(f.nomeFornecedor)})' title="Excluir Fornecedor">🗑️</button>
                                </div>
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Rede de fornecimento e distribuição comercial</span>
            <span>Exibindo ${forns.length} parceiros</span>
        </div>
    </div>
</div>

<div id="modal-novo-fornecedor" class="modal-container">
    <div class="drawer-content">
        <div class="drawer-header">
            <div><h2>Cadastrar Novo Fornecedor</h2><p>Preencha os dados da distribuidora parceira</p></div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>
        <form action="/modulos/fornecedores/fornecedor.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <div class="drawer-body">
                <div class="form-group"><label for="nomeFornecedor">Razão Social / Nome Fantasia *</label><input type="text" id="nomeFornecedor" name="nomeFornecedor" placeholder="Ex: Tech Distribuidora Ltda" required></div>
                <div class="form-row-2">
                    <div class="form-group"><label for="cnpj">CNPJ *</label><input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0001-00" required></div>
                    <div class="form-group"><label for="segmento">Segmento / Ramo</label><input type="text" id="segmento" name="segmento" placeholder="Ex: Bebidas, Informatica"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label for="email">E-mail Comercial</label><input type="email" id="email" name="email" placeholder="pedidos@empresa.com"></div>
                    <div class="form-group"><label for="telefone">Telefone</label><input type="text" id="telefone" name="telefone" placeholder="(11) 3300-1122"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label for="cidade">Cidade</label><input type="text" id="cidade" name="cidade" placeholder="Ex: São Paulo"></div>
                    <div class="form-group"><label for="uf">Estado (UF)</label><input type="text" id="uf" name="uf" maxlength="2" placeholder="SP" style="text-transform: uppercase;"></div>
                </div>
                <div class="form-group"><label for="endereco">Endereço Comercial</label><input type="text" id="endereco" name="endereco" placeholder="Av, Rua, Galpão..."></div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Fornecedor</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-confirmar-exclusao" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card">
        <div class="confirm-dialog-icon">🗑️</div>
        <h3>Excluir Fornecedor</h3>
        <p>Tem certeza que deseja remover este fornecedor permanentemente do sistema?</p>
        <div class="confirm-dialog-badge" id="confirm-dialog-item-nome">Nome do Fornecedor</div>
        <form id="form-excluir-fornecedor-modal" action="/modulos/fornecedores/fornecedor_excluir.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="id" id="input-excluir-id" value="">
            <div class="confirm-dialog-actions">
                <button type="button" class="btn-secondary" onclick="fecharModalExclusao()">Cancelar</button>
                <button type="submit" class="btn-danger-confirm"><span>🗑️</span> Sim, Excluir Fornecedor</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalExclusao(id, nome) {
        const modal = document.getElementById('modal-confirmar-exclusao');
        const inputId = document.getElementById('input-excluir-id');
        const badgeNome = document.getElementById('confirm-dialog-item-nome');
        if (inputId) inputId.value = id;
        if (badgeNome) badgeNome.textContent = nome || 'Fornecedor #' + id;
        if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    }
    function fecharModalExclusao() {
        const modal = document.getElementById('modal-confirmar-exclusao');
        if (modal) { modal.classList.remove('active'); document.body.style.overflow = ''; }
    }
    const fornecedoresData = ${JSON.stringify(forns)};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="/javaScript/exportarexcel.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="/javaScript/exportarPDF.js"></script>`;

  return { html: renderLayout('MVM - Gestão de Fornecedores', 'fornecedor.php', session, html) };
}

module.exports = {
  handleDashboard,
  handleProdutos,
  handleClientes,
  handleFornecedores
};
