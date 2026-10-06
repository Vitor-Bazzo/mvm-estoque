<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

require_once "config/conexao.php";

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

// 1. Lista de produtos para o seletor
$listaProdutos = [];
$stmtProds = mysqli_prepare($conexao, "SELECT idProduto, nomeProduto, categoria, preco, quantidade, localizacao FROM produto WHERE idUsuario = ? ORDER BY nomeProduto ASC");
if ($stmtProds) {
    mysqli_stmt_bind_param($stmtProds, "i", $idUsuario);
    mysqli_stmt_execute($stmtProds);
    $resProds = mysqli_stmt_get_result($stmtProds);
    if ($resProds) {
        $listaProdutos = mysqli_fetch_all($resProds, MYSQLI_ASSOC);
    }
}

// 2. Produto selecionado
$idProdutoSelecionado = isset($_GET['idProduto']) ? (int)$_GET['idProduto'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($idProdutoSelecionado === 0 && !empty($listaProdutos)) {
    $idProdutoSelecionado = (int)$listaProdutos[0]['idProduto'];
}

$produtoAtual = null;
foreach ($listaProdutos as $p) {
    if ((int)$p['idProduto'] === $idProdutoSelecionado) {
        $produtoAtual = $p;
        break;
    }
}

// 3. Filtros de data
$dataInicio = !empty($_GET['dataInicio']) ? $_GET['dataInicio'] : '';
$dataFim = !empty($_GET['dataFim']) ? $_GET['dataFim'] : '';

// 4. Busca histórico e calcula saldos contábeis exatos
$todosMovimentos = [];
$historicoFiltrado = [];
$totalEntradas = 0;
$totalSaidas = 0;

if ($idProdutoSelecionado > 0 && $produtoAtual) {
    $stmtTodos = mysqli_prepare($conexao, "
        SELECT 
            m.idMovimento, m.codigoPedido, m.tipoMovimento, m.quantidade, m.observacao,
            m.dataMovimento, m.dataDevolucao, m.lote, m.dataValidade,
            c.nomeCliente, f.nomeFornecedor
        FROM movimento m
        LEFT JOIN cliente c ON c.idCliente = m.idCliente
        LEFT JOIN fornecedor f ON f.idFornecedor = m.idFornecedor
        WHERE m.idUsuario = ? AND m.idProduto = ?
        ORDER BY m.dataMovimento ASC, m.idMovimento ASC
    ");
    
    if ($stmtTodos) {
        mysqli_stmt_bind_param($stmtTodos, "ii", $idUsuario, $idProdutoSelecionado);
        mysqli_stmt_execute($stmtTodos);
        $resTodos = mysqli_stmt_get_result($stmtTodos);
        if ($resTodos) {
            $todosMovimentos = mysqli_fetch_all($resTodos, MYSQLI_ASSOC);
        }
    }

    $estoqueAtualFisico = (int)$produtoAtual['quantidade'];
    $saldoLiquidoTotal = 0;
    foreach ($todosMovimentos as $m) {
        $qtd = (int)$m['quantidade'];
        $isDevolvido = !empty($m['dataDevolucao']);
        if ($m['tipoMovimento'] === 'ENTRADA') {
            $saldoLiquidoTotal += $qtd;
        } elseif ($m['tipoMovimento'] === 'SAIDA' && !$isDevolvido) {
            $saldoLiquidoTotal -= $qtd;
        }
    }

    $saldoBaseInicial = max(0, $estoqueAtualFisico - $saldoLiquidoTotal);
    $saldoAcumulado = $saldoBaseInicial;

    foreach ($todosMovimentos as $m) {
        $qtd = (int)$m['quantidade'];
        $isDevolvido = !empty($m['dataDevolucao']);
        $dataMovYmd = substr($m['dataMovimento'], 0, 10);

        if ($m['tipoMovimento'] === 'ENTRADA') {
            $saldoAcumulado += $qtd;
            $m['sinal'] = '+';
            $m['classeSinal'] = 'entrada';
        } elseif ($m['tipoMovimento'] === 'SAIDA') {
            if (!$isDevolvido) {
                $saldoAcumulado -= $qtd;
                $m['sinal'] = '-';
                $m['classeSinal'] = 'saida';
            } else {
                $m['sinal'] = '↩';
                $m['classeSinal'] = 'devolucao';
            }
        }
        $m['saldoApos'] = $saldoAcumulado;

        if (!empty($dataInicio) && $dataMovYmd < $dataInicio) {
            continue;
        }
        if (!empty($dataFim) && $dataMovYmd > $dataFim) {
            continue;
        }

        if ($m['tipoMovimento'] === 'ENTRADA') {
            $totalEntradas += $qtd;
        } elseif ($m['tipoMovimento'] === 'SAIDA' && !$isDevolvido) {
            $totalSaidas += $qtd;
        }

        $historicoFiltrado[] = $m;
    }

    $historicoExibicao = array_reverse($historicoFiltrado);
} else {
    $historicoExibicao = [];
}

mysqli_close($conexao);

$titulo_pagina = "Ficha Kardex - MVM";
require_once "templates/header.php";
?>

<style>
    /* ESTILOS LIMPOS E MINIMALISTAS DA FICHA KARDEX */
    .kardex-bar {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 20px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
        justify-content: space-between;
    }
    .kardex-bar-select {
        flex: 1;
        min-width: 280px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .kardex-bar-select select {
        width: 100%;
        padding: 9px 12px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        background: var(--bg-subtle);
        color: var(--text);
        font-size: 13.5px;
        font-weight: 600;
        outline: none;
    }
    .kardex-bar-dates {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .kardex-bar-dates input[type="date"] {
        padding: 8px 10px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        background: var(--bg-subtle);
        color: var(--text);
        font-size: 12.5px;
    }
    .kardex-bar-dates button {
        padding: 8px 14px;
        font-size: 12.5px;
    }

    /* CARDS DE RESUMO DIRETOS E ELEGANTES */
    .kardex-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }
    .k-stat-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px 20px;
    }
    .k-stat-label {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        display: block;
        margin-bottom: 6px;
    }
    .k-stat-value {
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
        font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        display: block;
    }
    .k-stat-sub {
        font-size: 11.5px;
        color: var(--text-muted);
        margin-top: 4px;
        display: block;
    }

    /* SALDO RESULTANTE */
    .pill-saldo {
        display: inline-block;
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        background: var(--bg-subtle);
        border: 1px solid var(--border);
        color: var(--text);
    }
    .btn-recibo-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg-subtle);
        color: var(--text);
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .btn-recibo-link:hover {
        background: var(--primary);
        color: #ffffff;
        border-color: var(--primary);
    }

    @media print {
        .no-print, .header-container, .page-header-actions, .kardex-bar {
            display: none !important;
        }
        body { background: #fff !important; color: #000 !important; }
        .table-card { border: none !important; box-shadow: none !important; }
    }
</style>

<div class="page-container">
    
    <!-- Cabeçalho Limpo -->
    <div class="page-header-row no-print">
        <div class="page-header-titles">
            <h1>Ficha Kardex</h1>
            <p>Histórico de movimentações e saldo em estoque do produto</p>
        </div>
        <div class="page-header-actions">
            <button type="button" onclick="window.print()" class="btn-secondary" title="Imprimir">
                <span>🖨️</span> Imprimir
            </button>
            <a href="movimento.php" class="btn-primary" title="Registrar movimentação">
                <span>➕</span> Nova Movimentação
            </a>
        </div>
    </div>

    <!-- Barra de Filtros Direta (Em 1 Linha) -->
    <div class="kardex-bar no-print">
        <form method="GET" action="kardex.php" style="display: contents;">
            <div class="kardex-bar-select">
                <label for="idProduto" style="font-size: 13px; font-weight: 700; color: var(--text); white-space: nowrap;">
                    Produto:
                </label>
                <select name="idProduto" id="idProduto" onchange="this.form.submit()">
                    <?php foreach ($listaProdutos as $item): ?>
                        <option value="<?= $item['idProduto'] ?>" <?= ((int)$item['idProduto'] === $idProdutoSelecionado) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['nomeProduto']) ?> (Estoque: <?= $item['quantidade'] ?> un)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="kardex-bar-dates">
                <input type="date" name="dataInicio" value="<?= htmlspecialchars($dataInicio) ?>" title="Data Inicial">
                <span style="color: var(--text-muted); font-size: 12px;">até</span>
                <input type="date" name="dataFim" value="<?= htmlspecialchars($dataFim) ?>" title="Data Final">
                <button type="submit" class="btn-primary">Filtrar</button>
                <?php if (!empty($dataInicio) || !empty($dataFim)): ?>
                    <a href="kardex.php?idProduto=<?= $idProdutoSelecionado ?>" class="btn-secondary" style="padding: 8px 12px; text-decoration: none;" title="Limpar datas">
                        ✕
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($produtoAtual): ?>
        
        <!-- 4 Indicadores Diretos e Claros -->
        <div class="kardex-stats-grid">
            <div class="k-stat-card">
                <span class="k-stat-label">Estoque Físico Atual</span>
                <strong class="k-stat-value" style="color: #10b981;">
                    <?= (int)$produtoAtual['quantidade'] ?> <small style="font-size: 14px; font-weight: 500; color: var(--text-muted);">un</small>
                </strong>
                <span class="k-stat-sub">Saldo em prateleira</span>
            </div>

            <div class="k-stat-card">
                <span class="k-stat-label">Entradas no Período</span>
                <strong class="k-stat-value" style="color: #3b82f6;">
                    +<?= $totalEntradas ?> <small style="font-size: 14px; font-weight: 500; color: var(--text-muted);">un</small>
                </strong>
                <span class="k-stat-sub">Reposições recebidas</span>
            </div>

            <div class="k-stat-card">
                <span class="k-stat-label">Saídas / Vendas</span>
                <strong class="k-stat-value" style="color: #ef4444;">
                    -<?= $totalSaidas ?> <small style="font-size: 14px; font-weight: 500; color: var(--text-muted);">un</small>
                </strong>
                <span class="k-stat-sub">Vendas a clientes</span>
            </div>

            <div class="k-stat-card">
                <span class="k-stat-label">Valor em Estoque</span>
                <strong class="k-stat-value">
                    R$ <?= number_format(((int)$produtoAtual['quantidade'] * (float)$produtoAtual['preco']), 2, ',', '.') ?>
                </strong>
                <span class="k-stat-sub">Preço unit: R$ <?= number_format((float)$produtoAtual['preco'], 2, ',', '.') ?></span>
            </div>
        </div>

        <!-- Tabela de Movimentações Limpa -->
        <div class="table-card">
            <div class="table-header-custom" style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 15px; color: var(--text);">
                        Extrato: <strong><?= htmlspecialchars($produtoAtual['nomeProduto']) ?></strong>
                    </h3>
                    <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 3px 8px; background: rgba(99, 102, 241, 0.1); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-weight: 700;" title="Localização no armazém (WMS)">
                        📍 Armazém: <?= htmlspecialchars($produtoAtual['localizacao'] ?? 'A-01-01') ?>
                    </span>
                </div>
                <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                    <?= count($historicoExibicao) ?> lançamentos
                </span>
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
                        <?php if (count($historicoExibicao) > 0): ?>
                            <?php foreach ($historicoExibicao as $item): ?>
                                <?php 
                                    $isEntrada = $item['tipoMovimento'] === 'ENTRADA';
                                    $isDevolvido = !empty($item['dataDevolucao']);

                                    $badgeClass = $isEntrada ? 'success' : 'info';
                                    $badgeTexto = $isEntrada ? 'Entrada' : 'Saída';

                                    if ($isDevolvido) {
                                        $badgeClass = 'neutral';
                                        $badgeTexto = 'Estorno';
                                    }
                                ?>
                                <tr>
                                    <!-- Data e Hora -->
                                    <td>
                                        <span style="font-weight: 600; color: var(--text); font-size: 12.5px;">
                                            <?= date('d/m/Y', strtotime($item['dataMovimento'])) ?>
                                        </span>
                                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); margin-left: 4px;">
                                            <?= date('H:i', strtotime($item['dataMovimento'])) ?>
                                        </span>
                                    </td>

                                    <!-- Documento / Pedido -->
                                    <td>
                                        <?php if (!empty($item['codigoPedido'])): ?>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; font-weight: 700; color: var(--primary);">
                                                <?= htmlspecialchars($item['codigoPedido']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted);">
                                                MOV-#<?= sprintf('%05d', $item['idMovimento']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Operação -->
                                    <td>
                                        <span class="status-pill <?= $badgeClass ?>">
                                            <?= $badgeTexto ?>
                                        </span>
                                    </td>

                                    <!-- Origem / Destino -->
                                    <td>
                                        <?php if (!empty($item['nomeCliente'])): ?>
                                            <span style="font-weight: 600; font-size: 12.5px; color: var(--text);">
                                                <?= htmlspecialchars($item['nomeCliente']) ?>
                                            </span>
                                        <?php elseif (!empty($item['nomeFornecedor'])): ?>
                                            <span style="font-weight: 600; font-size: 12.5px; color: var(--text);">
                                                <?= htmlspecialchars($item['nomeFornecedor']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 12px;">Balcão</span>
                                        <?php endif; ?>

                                        <?php if (!empty($item['observacao'])): ?>
                                            <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 1px;">
                                                <?= htmlspecialchars(mb_strimwidth($item['observacao'], 0, 35, '...')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Movimentação (+/-) -->
                                    <td style="text-align: center;">
                                        <strong style="font-family: 'JetBrains Mono', monospace; font-size: 13px; color: <?= $item['classeSinal'] === 'entrada' ? '#10b981' : ($item['classeSinal'] === 'saida' ? '#ef4444' : '#64748b') ?>;">
                                            <?= $item['sinal'] ?><?= (int)$item['quantidade'] ?> un
                                        </strong>
                                    </td>

                                    <!-- Saldo em Estoque -->
                                    <td style="text-align: center;">
                                        <span class="pill-saldo">
                                            <?= (int)$item['saldoApos'] ?> un
                                        </span>
                                    </td>

                                    <!-- Ação / Recibo -->
                                    <td style="text-align: right;">
                                        <?php if (!empty($item['codigoPedido'])): ?>
                                            <a href="recibo.php?pedido=<?= urlencode($item['codigoPedido']) ?>" target="_blank" class="btn-recibo-link" title="Ver Recibo">
                                                🧾
                                            </a>
                                        <?php else: ?>
                                            <a href="recibo.php?id=<?= $item['idMovimento'] ?>" target="_blank" class="btn-recibo-link" title="Ver Recibo">
                                                🧾
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <p style="margin: 0; font-size: 13px;">Nenhuma movimentação registrada para este produto no período.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <div class="table-card" style="padding: 40px; text-align: center; color: var(--text-muted);">
            <p>Nenhum produto cadastrado para exibir.</p>
        </div>
    <?php endif; ?>

</div>

<?php require_once "templates/footer.php"; ?>
