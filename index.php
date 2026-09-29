<?php
$titulo_pagina = 'Dashboard de Vendas - Power BI';
require_once 'templates/header.php';
require_once 'config/dados_dashboard.php';
require_once 'config/upload.php';

// Busca os dados consolidados para os gráficos e KPIs
$dadosBI = obterDadosDashboardBI();
?>

<div class="page-container">
    <?php if (isset($_SESSION['usuarioLogado'])): ?>
        
        <!-- Mensagem de Flash (se houver) -->
        <?php if (!empty($_SESSION['flash_dashboard'])): ?>
            <div class="alert-box <?= $_SESSION['flash_dashboard']['tipo'] === 'sucesso' ? 'success' : 'error' ?>" style="margin-bottom: 24px;">
                <span><?= $_SESSION['flash_dashboard']['tipo'] === 'sucesso' ? '✨' : '⚠️' ?></span>
                <p><?= $_SESSION['flash_dashboard']['texto'] ?></p>
            </div>
            <?php unset($_SESSION['flash_dashboard']); ?>
        <?php endif; ?>

        <div class="powerbi-wrapper">
            
            <!-- Barra Superior do Dashboard (Estilo Power BI Executive Header) -->
            <div class="pbi-header-bar">
                <div class="pbi-header-info">
                    <div class="pbi-logo-badge" title="Visual Power BI">
                        📊
                    </div>
                    <div class="pbi-header-titles">
                        <h1>Painel Executivo & Inteligência de Negócios (BI)</h1>
                        <p>Visão estratégica de vendas, estoque e distribuição por categorias • MVM Comércios</p>
                    </div>
                </div>

                <div class="pbi-header-actions">
                    <div class="pbi-tag">
                        <span class="pbi-tag-pulse"></span>
                        Atualizado em tempo real (<?= date('d/m/Y H:i') ?>)
                    </div>
                    <a href="movimento.php" class="pbi-btn pbi-btn-primary">
                        <span>➕</span> Registrar Nova Venda
                    </a>
                </div>
            </div>

            <!-- ALERTA DE PRODUTOS COM ESTOQUE BAIXO (< 20 UNIDADES) -->
            <?php if (!empty($dadosBI['produtos_estoque_baixo'])): ?>
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
                                        <?= count($dadosBI['produtos_estoque_baixo']) ?> <?= count($dadosBI['produtos_estoque_baixo']) === 1 ? 'produto acabando' : 'produtos acabando' ?> (&lt; 20 un)
                                    </span>
                                </div>
                                <p>
                                    Os seguintes itens estão abaixo do estoque de segurança (menos de 20 unidades) e precisam de reposição:
                                </p>
                            </div>
                        </div>

                        <div class="pbi-stock-alert-actions">
                            <a href="movimento.php" class="pbi-btn pbi-btn-stock-entry" title="Registrar entrada de produtos para repor estoque">
                                <span>📥</span> Repor Estoque
                            </a>
                            <a href="produto.php" class="pbi-btn pbi-btn-stock-catalog" title="Ver catálogo de produtos">
                                <span>📦</span> Catálogo
                            </a>
                        </div>
                    </div>

                    <!-- Cards dos produtos com estoque baixo -->
                    <div class="pbi-stock-items-grid">
                        <?php foreach ($dadosBI['produtos_estoque_baixo'] as $item): ?>
                            <?php 
                                $qtd = (int)$item['quantidade'];
                                $porcentagemBarra = min(100, max(5, round(($qtd / 20) * 100)));
                                $isEsgotado = $qtd <= 0;
                            ?>
                            <div class="pbi-stock-item-card <?= $isEsgotado ? 'is-empty' : '' ?>">
                                <div class="pbi-stock-item-thumb">
                                    <?php if (!empty($item['imagem'])): ?>
                                        <img src="<?= CAMINHO_UPLOAD_PRODUTOS . htmlspecialchars($item['imagem']) ?>" alt="<?= htmlspecialchars($item['nomeProduto']) ?>" class="pbi-stock-thumb-img">
                                    <?php else: ?>
                                        <div class="pbi-stock-thumb-fallback">📦</div>
                                    <?php endif; ?>
                                </div>
                                <div class="pbi-stock-item-info">
                                    <div class="pbi-stock-item-header">
                                        <strong class="pbi-stock-item-name" title="<?= htmlspecialchars($item['nomeProduto']) ?>">
                                            <?= htmlspecialchars($item['nomeProduto']) ?>
                                        </strong>
                                        <span class="pbi-stock-item-cat"><?= htmlspecialchars($item['categoria']) ?></span>
                                    </div>
                                    
                                    <div class="pbi-stock-level-row">
                                        <div class="pbi-stock-bar-container">
                                            <div class="pbi-stock-bar-fill <?= $isEsgotado ? 'bar-danger' : 'bar-warning' ?>" style="width: <?= $porcentagemBarra ?>%;"></div>
                                        </div>
                                        <div class="pbi-stock-qty-badge <?= $isEsgotado ? 'qty-danger' : 'qty-warning' ?>">
                                            <?php if ($isEsgotado): ?>
                                                🚨 ESGOTADO (0 un)
                                            <?php else: ?>
                                                ⚠️ Restam <?= $qtd ?> <?= $qtd === 1 ? 'unidade' : 'unidades' ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="pbi-stock-meta-row">
                                        <span class="pbi-stock-meta-fornecedor" title="Fornecedor">
                                            🏢 <?= htmlspecialchars($item['nomeFornecedor']) ?>
                                        </span>
                                        <span class="pbi-stock-meta-preco">
                                            R$ <?= number_format((float)$item['preco'], 2, ',', '.') ?>
                                        </span>
                                        <a href="produto_editar.php?idProduto=<?= $item['idProduto'] ?>" class="pbi-stock-item-edit-btn" title="Editar produto">
                                            Editar ✏️
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ALERTA DE LOTES E VALIDADE PRÓXIMA (LOGÍSTICA FEFO) -->
            <?php if (!empty($dadosBI['produtos_vencimento'])): ?>
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
                                        <?= count($dadosBI['produtos_vencimento']) ?> <?= count($dadosBI['produtos_vencimento']) === 1 ? 'item requer atenção' : 'itens requerem atenção' ?>
                                    </span>
                                </div>
                                <p>
                                    Itens com validade crítica ou próxima. Aplique a regra <strong>FEFO (First Expired, First Out)</strong> priorizando a saída desses lotes:
                                </p>
                            </div>
                        </div>

                        <div class="pbi-stock-alert-actions">
                            <a href="movimento.php" class="pbi-btn pbi-btn-primary" style="background: #d97706;" title="Priorizar saída comercial destes itens">
                                <span>🚀</span> Priorizar Saída
                            </a>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-top: 14px;">
                        <?php foreach ($dadosBI['produtos_vencimento'] as $itemV): ?>
                            <?php 
                                $isVencido = $itemV['statusVencimento'] === 'vencido';
                                $isUrgente = $itemV['statusVencimento'] === 'urgente';
                                $corStatus = $isVencido ? '#ef4444' : ($isUrgente ? '#f97316' : '#eab308');
                                $bgStatus = $isVencido ? 'rgba(239, 68, 68, 0.12)' : ($isUrgente ? 'rgba(249, 115, 22, 0.12)' : 'rgba(234, 179, 8, 0.12)');
                            ?>
                            <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                                <div>
                                    <strong style="color: var(--text-main); font-size: 13.5px; display: block; margin-bottom: 2px;">
                                        <?= htmlspecialchars($itemV['nomeProduto']) ?>
                                    </strong>
                                    <span style="font-size: 11.5px; color: var(--text-muted); display: block;">
                                        Lote: <strong><?= htmlspecialchars($itemV['lote']) ?></strong> &bull; <?= $itemV['quantidade'] ?> un em estoque
                                    </span>
                                    <span style="font-size: 11.5px; color: var(--text-muted);">
                                        Vence em: <strong><?= $itemV['dataValidade'] ?></strong>
                                    </span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="display: inline-block; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; background: <?= $bgStatus ?>; color: <?= $corStatus ?>; border: 1px solid <?= $corStatus ?>33;">
                                        <?php if ($isVencido): ?>
                                            🚨 VENCIDO
                                        <?php elseif ($itemV['diasParaVencer'] === 0): ?>
                                            ⚠️ Vence Hoje!
                                        <?php else: ?>
                                            ⏳ <?= $itemV['diasParaVencer'] ?> <?= $itemV['diasParaVencer'] === 1 ? 'dia' : 'dias' ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Alerta Informativo se estiver usando dados de demonstração -->
            <?php if (!empty($dadosBI['dados_demo'])): ?>
                <div class="pbi-alert-banner">
                    <div class="pbi-alert-text">
                        <span style="font-size: 20px;">💡</span>
                        <div>
                            <strong>Modo de Demonstração Ativo:</strong>
                            O seu banco de dados possui poucas saídas cadastradas. Estamos exibindo uma simulação rica com a categoria <strong>Bebidas a 50%</strong> e vendas diárias para você testar todo o potencial dos gráficos.
                        </div>
                    </div>
                    <a href="config/popular_dados_exemplo.php?executar=sim" class="pbi-alert-btn" onclick="return confirm('Deseja gravar as vendas e produtos de teste no seu banco MySQL agora?')">
                        ⚡ Gravar Vendas de Teste no Banco
                    </a>
                </div>
            <?php endif; ?>

            <!-- CARDS DE INDICADORES / KPIS (Estilo Cartões Power BI) -->
            <div class="pbi-kpi-grid">
                <!-- KPI 1: Faturamento Total -->
                <div class="pbi-kpi-card accent-emerald">
                    <div class="pbi-kpi-header">
                        <span class="pbi-kpi-title">Faturamento Total (Vendas)</span>
                        <span class="pbi-kpi-icon">💰</span>
                    </div>
                    <div class="pbi-kpi-value">
                        R$ <?= number_format($dadosBI['kpis']['faturamento_total'], 2, ',', '.') ?>
                    </div>
                    <div class="pbi-kpi-footer">
                        <span class="pbi-badge-pill success">Receita</span> Total em vendas finalizadas
                    </div>
                </div>

                <!-- KPI 2: Total de Vendas Realizadas -->
                <div class="pbi-kpi-card accent-blue">
                    <div class="pbi-kpi-header">
                        <span class="pbi-kpi-title">Vendas Concluídas</span>
                        <span class="pbi-kpi-icon">🛒</span>
                    </div>
                    <div class="pbi-kpi-value">
                        <?= number_format($dadosBI['kpis']['total_vendas_qtd'], 0, ',', '.') ?>
                    </div>
                    <div class="pbi-kpi-footer">
                        <span class="pbi-badge-pill info">Transações</span> Pedidos emitidos no PDV
                    </div>
                </div>

                <!-- KPI 3: Itens em Estoque -->
                <div class="pbi-kpi-card accent-amber">
                    <div class="pbi-kpi-header">
                        <span class="pbi-kpi-title">Estoque Disponível</span>
                        <span class="pbi-kpi-icon">📦</span>
                    </div>
                    <div class="pbi-kpi-value">
                        <?= number_format($dadosBI['kpis']['itens_em_estoque'], 0, ',', '.') ?>
                    </div>
                    <div class="pbi-kpi-footer">
                        <?php if (!empty($dadosBI['total_produtos_estoque_baixo'])): ?>
                            <span class="pbi-badge-pill danger" title="Itens com quantidade abaixo de 20 unidades">
                                ⚠️ <?= $dadosBI['total_produtos_estoque_baixo'] ?> acabando (&lt; 20 un)
                            </span>
                        <?php else: ?>
                            <span>Unidades físicas cadastradas</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KPI 4: Categorias Ativas -->
                <div class="pbi-kpi-card accent-purple">
                    <div class="pbi-kpi-header">
                        <span class="pbi-kpi-title">Categorias Ativas</span>
                        <span class="pbi-kpi-icon">🏷️</span>
                    </div>
                    <div class="pbi-kpi-value">
                        <?= $dadosBI['kpis']['total_categorias'] ?>
                    </div>
                    <div class="pbi-kpi-footer">
                        <span>Segmentos de produtos no catálogo</span>
                    </div>
                </div>

                <!-- KPI 5: Produto Campeão de Vendas -->
                <div class="pbi-kpi-card accent-yellow">
                    <div class="pbi-kpi-header">
                        <span class="pbi-kpi-title">Produto Campeão #1</span>
                        <span class="pbi-kpi-icon">🏆</span>
                    </div>
                    <div class="pbi-kpi-value" style="font-size: 17px; line-height: 1.3;" title="<?= htmlspecialchars($dadosBI['kpis']['produto_campeao']) ?>">
                        <?= htmlspecialchars($dadosBI['kpis']['produto_campeao']) ?>
                    </div>
                    <div class="pbi-kpi-footer">
                        <span style="color: #ea580c; font-weight: 700;">Líder em saídas do estoque</span>
                    </div>
                </div>

                <!-- KPI 6: Lucro Bruto & Margem Comercial -->
                <div class="pbi-kpi-card accent-emerald">
                    <div class="pbi-kpi-header">
                        <span class="pbi-kpi-title">Lucro Bruto Estimado</span>
                        <span class="pbi-kpi-icon">📈</span>
                    </div>
                    <div class="pbi-kpi-value" style="color: #10b981;">
                        R$ <?= number_format($dadosBI['kpis']['lucro_bruto'] ?? 0, 2, ',', '.') ?>
                    </div>
                    <div class="pbi-kpi-footer">
                        <span class="pbi-badge-pill success">
                            <?= $dadosBI['kpis']['margem_lucro'] ?? 0 ?>% de Margem
                        </span>
                        Receita menos CMV (Custo)
                    </div>
                </div>
            </div>

            <!-- LINHA 1 DE GRÁFICOS POWER BI (2 COLUNAS) -->
            <div class="pbi-grid-2cols">
                
                <!-- GRÁFICO 1: TOP 5 PRODUTOS MAIS VENDIDOS -->
                <div class="pbi-visual-card">
                    <div class="pbi-visual-header">
                        <div class="pbi-visual-title-box">
                            <h3>
                                <span>🏆</span> 5 Produtos Mais Vendidos de Todo o Estoque
                            </h3>
                            <p>Ranking dos itens com maior saída acumulada de vendas</p>
                        </div>
                        <div class="pbi-tag" style="padding: 4px 8px; font-size: 11px;">
                            Top 5
                        </div>
                    </div>
                    <div id="chart-top-produtos" class="pbi-chart-container"></div>
                </div>

                <!-- GRÁFICO 2: PORCENTAGEM DE CADA CATEGORIA (EX: BEBIDAS COM 50%) -->
                <div class="pbi-visual-card">
                    <div class="pbi-visual-header">
                        <div class="pbi-visual-title-box">
                            <h3>
                                <span>🍩</span> Porcentagem de Cada Categoria
                            </h3>
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

            <!-- LINHA 2 DE GRÁFICOS POWER BI (LARGURA TOTAL) -->
            <div class="pbi-grid-full">
                
                <!-- GRÁFICO 3: QUANTIDADE EM REAIS DE VENDAS FEITAS EM UM DIA -->
                <div class="pbi-visual-card">
                    <div class="pbi-visual-header">
                        <div class="pbi-visual-title-box">
                            <h3>
                                <span>📈</span> Quantidade em Reais de Vendas Feitas por Dia
                            </h3>
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

            <!-- PAINEL 1: SUGESTÃO INTELIGENTE DE REPOSIÇÃO DE COMPRAS (PONTO DE PEDIDO) -->
            <?php if (!empty($dadosBI['sugestoes_reposicao'])): ?>
                <div class="pbi-visual-card" style="margin-bottom: 24px; margin-top: 10px;">
                    <div class="pbi-visual-header" style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 16px;">
                        <div class="pbi-visual-title-box">
                            <h3 style="display: flex; align-items: center; gap: 8px;">
                                <span>📋</span> Sugestão de Reposição Automática (Ponto de Pedido)
                            </h3>
                            <p>Itens que atingiram o limite mínimo de segurança no estoque com cotação imediata para fornecedores</p>
                        </div>
                        <span class="pbi-badge-pill danger">
                            <?= count($dadosBI['sugestoes_reposicao']) ?> <?= count($dadosBI['sugestoes_reposicao']) === 1 ? 'item crítico' : 'itens críticos' ?>
                        </span>
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
                                <?php foreach ($dadosBI['sugestoes_reposicao'] as $rep): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 12px 8px;">
                                            <strong style="color: var(--text-main);"><?= htmlspecialchars($rep['nomeProduto']) ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($rep['categoria']) ?></div>
                                        </td>
                                        <td style="padding: 12px 8px;">
                                            <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 11.5px; background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                                                <?= $rep['estoqueAtual'] ?> un
                                            </span>
                                        </td>
                                        <td style="padding: 12px 8px; color: var(--text-muted);">
                                            <?= $rep['estoqueMinimo'] ?> un
                                        </td>
                                        <td style="padding: 12px 8px;">
                                            <strong style="color: #00704A; font-size: 13.5px;">+<?= $rep['qtdSugerida'] ?> un</strong>
                                        </td>
                                        <td style="padding: 12px 8px; font-family: 'JetBrains Mono', monospace; font-weight: 600;">
                                            R$ <?= number_format($rep['custoEstimado'], 2, ',', '.') ?>
                                        </td>
                                        <td style="padding: 12px 8px; color: var(--text-main);">
                                            🏢 <?= htmlspecialchars($rep['nomeFornecedor']) ?>
                                        </td>
                                        <td style="padding: 12px 8px; text-align: right;">
                                            <?php if (!empty($rep['linkWhatsApp'])): ?>
                                                <a href="<?= $rep['linkWhatsApp'] ?>" target="_blank" rel="noopener noreferrer" style="background: #25D366; color: #fff; padding: 6px 12px; font-size: 12px; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                                                    <span>💬</span> Pedir no WhatsApp
                                                </a>
                                            <?php else: ?>
                                                <a href="fornecedor.php" class="btn-secondary" style="padding: 6px 10px; font-size: 11.5px; text-decoration: none;">
                                                    Vincular Contato
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- PAINEL 2: CLASSIFICAÇÃO CURVA ABC (REGRA DE PARETO 80/20) -->
            <?php if (!empty($dadosBI['curva_abc'])): ?>
                <div class="pbi-visual-card" style="margin-bottom: 24px;">
                    <div class="pbi-visual-header" style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 16px;">
                        <div class="pbi-visual-title-box">
                            <h3 style="display: flex; align-items: center; gap: 8px;">
                                <span>⚖️</span> Curva ABC de Estoque (Classificação Pareto)
                            </h3>
                            <p>Hierarquia de relevância operacional e financeira para tomada de decisão no armazém</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                        <!-- CLASSE A -->
                        <div style="background: rgba(16, 185, 129, 0.08); border: 1.5px solid rgba(16, 185, 129, 0.35); border-radius: var(--radius-md); padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="font-size: 16px; color: #10b981;">Classe A (Críticos / VIP)</strong>
                                <span style="background: #10b981; color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px;">~80% do Valor</span>
                            </div>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                                Itens de altíssimo valor e giro. Jamais podem faltar no armazém.
                            </p>
                            <div style="font-size: 12.5px; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
                                Total: <?= $dadosBI['curva_abc']['A']['qtd_itens'] ?> produto(s) &bull; R$ <?= number_format($dadosBI['curva_abc']['A']['total_faturado'], 2, ',', '.') ?>
                            </div>
                            <ul style="list-style: none; padding: 0; font-size: 12px; color: var(--text-main);">
                                <?php foreach (array_slice($dadosBI['curva_abc']['A']['produtos'], 0, 4) as $prodA): ?>
                                    <li style="padding: 4px 0; border-top: 1px dashed rgba(16, 185, 129, 0.2); display: flex; justify-content: space-between;">
                                        <span>• <?= htmlspecialchars($prodA['nomeProduto']) ?></span>
                                        <strong>R$ <?= number_format($prodA['baseCalculo'], 2, ',', '.') ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- CLASSE B -->
                        <div style="background: rgba(245, 158, 11, 0.08); border: 1.5px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-md); padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="font-size: 16px; color: #f59e0b;">Classe B (Intermediários)</strong>
                                <span style="background: #f59e0b; color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px;">~15% do Valor</span>
                            </div>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                                Relevância média. Controle de estoque moderado e reposições periódicas.
                            </p>
                            <div style="font-size: 12.5px; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
                                Total: <?= $dadosBI['curva_abc']['B']['qtd_itens'] ?> produto(s) &bull; R$ <?= number_format($dadosBI['curva_abc']['B']['total_faturado'], 2, ',', '.') ?>
                            </div>
                            <ul style="list-style: none; padding: 0; font-size: 12px; color: var(--text-main);">
                                <?php foreach (array_slice($dadosBI['curva_abc']['B']['produtos'], 0, 4) as $prodB): ?>
                                    <li style="padding: 4px 0; border-top: 1px dashed rgba(245, 158, 11, 0.2); display: flex; justify-content: space-between;">
                                        <span>• <?= htmlspecialchars($prodB['nomeProduto']) ?></span>
                                        <strong>R$ <?= number_format($prodB['baseCalculo'], 2, ',', '.') ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- CLASSE C -->
                        <div style="background: rgba(148, 163, 184, 0.08); border: 1.5px solid rgba(148, 163, 184, 0.35); border-radius: var(--radius-md); padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="font-size: 16px; color: #64748b;">Classe C (Baixo Impacto)</strong>
                                <span style="background: #64748b; color: #fff; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 4px;">~5% do Valor</span>
                            </div>
                            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                                Muitos itens com baixo impacto financeiro. Permite compras em lote espaçado.
                            </p>
                            <div style="font-size: 12.5px; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
                                Total: <?= $dadosBI['curva_abc']['C']['qtd_itens'] ?> produto(s) &bull; R$ <?= number_format($dadosBI['curva_abc']['C']['total_faturado'], 2, ',', '.') ?>
                            </div>
                            <ul style="list-style: none; padding: 0; font-size: 12px; color: var(--text-main);">
                                <?php foreach (array_slice($dadosBI['curva_abc']['C']['produtos'], 0, 4) as $prodC): ?>
                                    <li style="padding: 4px 0; border-top: 1px dashed rgba(148, 163, 184, 0.2); display: flex; justify-content: space-between;">
                                        <span>• <?= htmlspecialchars($prodC['nomeProduto']) ?></span>
                                        <strong>R$ <?= number_format($prodC['baseCalculo'], 2, ',', '.') ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ACESSOS RÁPIDOS DO SISTEMA -->
            <div style="margin-top: 10px;">
                <h3 style="font-size: 15px; color: var(--pbi-text-main); margin-bottom: 10px; font-weight: 750;">
                    ⚡ Atalhos de Gestão Operacional
                </h3>
                <div class="pbi-quick-nav">
                    <a href="produto.php" class="pbi-nav-card">
                        <div class="pbi-nav-icon">📦</div>
                        <div class="pbi-nav-text">
                            <strong>Produtos</strong>
                            <span>Cadastrar e gerenciar catálogo</span>
                        </div>
                    </a>

                    <a href="cliente.php" class="pbi-nav-card">
                        <div class="pbi-nav-icon">👥</div>
                        <div class="pbi-nav-text">
                            <strong>Clientes</strong>
                            <span>Carteira de clientes e compras</span>
                        </div>
                    </a>

                    <a href="movimento.php" class="pbi-nav-card">
                        <div class="pbi-nav-icon">💳</div>
                        <div class="pbi-nav-text">
                            <strong>Histórico de Vendas</strong>
                            <span>Entradas, saídas e devoluções</span>
                        </div>
                    </a>

                    <a href="fornecedor.php" class="pbi-nav-card">
                        <div class="pbi-nav-icon">🏢</div>
                        <div class="pbi-nav-text">
                            <strong>Fornecedores</strong>
                            <span>Gestão de parceiros comerciais</span>
                        </div>
                    </a>
                </div>
            </div>

        </div>

        <!-- Injeção dos dados consolidados no JavaScript -->
        <script>
            const dadosBI = <?= json_encode($dadosBI, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
        </script>
        <script src="javaScript/dashboard_bi.js"></script>

    <?php else: ?>
        <!-- Conteúdo para VISITANTE NÃO LOGADO -->
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 54px; margin-bottom: 14px;">📦</div>
            <h1 style="font-size: 28px; margin-bottom: 10px;">Bem-vindo ao Sistema MVM</h1>
            <p style="color: var(--muted); font-size: 16px; max-width: 500px; margin: 0 auto 24px;">
                Plataforma de gestão comercial de estoque e inteligência de vendas estilo Power BI.
            </p>
            <a href="login.php" class="cta-button" style="padding: 14px 28px; font-size: 15px;">Acessar o Sistema (Login)</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'templates/footer.php'; ?>