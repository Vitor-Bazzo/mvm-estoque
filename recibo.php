<?php
require_once "config/conexao.php";

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = obterIdUsuarioLogado();
$codigoPedido = isset($_GET['pedido']) ? trim($_GET['pedido']) : '';
$idMovimento = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modoInicial = (isset($_GET['modo']) && $_GET['modo'] === 'picking') ? 'picking' : 'ticket';

if ($idMovimento <= 0 && empty($codigoPedido)) {
    die("Identificador de pedido ou movimentação não informado.");
}

$conexao = conectar();
$itens = [];

if (!empty($codigoPedido)) {
    // Busca por código de pedido consolidado - ordenado por localização no armazém (rota física de picking)
    $sql = "
        SELECT 
            m.idMovimento, m.codigoPedido, m.tipoMovimento, m.quantidade, m.observacao, m.dataMovimento, m.dataDevolucao, m.lote, m.dataValidade, m.valorTotal,
            p.idProduto, p.nomeProduto, p.categoria, p.preco, p.localizacao,
            c.nomeCliente, c.email AS emailCliente, c.telefone AS telCliente, c.cidade AS cidadeCliente, c.uf AS ufCliente,
            f.nomeFornecedor, f.cnpj AS cnpjFornecedor, f.telefone AS telFornecedor
        FROM movimento m
        INNER JOIN produto p ON p.idProduto = m.idProduto
        LEFT JOIN cliente c ON c.idCliente = m.idCliente
        LEFT JOIN fornecedor f ON f.idFornecedor = m.idFornecedor
        WHERE m.codigoPedido = ? AND m.idUsuario = ?
        ORDER BY p.localizacao ASC, m.idMovimento ASC
    ";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "si", $codigoPedido, $idUsuario);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        $itens = mysqli_fetch_all($res, MYSQLI_ASSOC);
    }
} else {
    // Busca pelo id individual
    $sql = "
        SELECT 
            m.idMovimento, m.codigoPedido, m.tipoMovimento, m.quantidade, m.observacao, m.dataMovimento, m.dataDevolucao, m.lote, m.dataValidade, m.valorTotal,
            p.idProduto, p.nomeProduto, p.categoria, p.preco, p.localizacao,
            c.nomeCliente, c.email AS emailCliente, c.telefone AS telCliente, c.cidade AS cidadeCliente, c.uf AS ufCliente,
            f.nomeFornecedor, f.cnpj AS cnpjFornecedor, f.telefone AS telFornecedor
        FROM movimento m
        INNER JOIN produto p ON p.idProduto = m.idProduto
        LEFT JOIN cliente c ON c.idCliente = m.idCliente
        LEFT JOIN fornecedor f ON f.idFornecedor = m.idFornecedor
        WHERE m.idMovimento = ? AND m.idUsuario = ?
    ";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $idMovimento, $idUsuario);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $movIndividual = mysqli_fetch_assoc($res);

    if ($movIndividual) {
        // Se a movimentação pertencer a um pedido multi-itens, busca os outros itens ordenados por endereço WMS
        if (!empty($movIndividual['codigoPedido'])) {
            $stmtMulti = mysqli_prepare($conexao, "
                SELECT 
                    m.idMovimento, m.codigoPedido, m.tipoMovimento, m.quantidade, m.observacao, m.dataMovimento, m.dataDevolucao, m.lote, m.dataValidade, m.valorTotal,
                    p.idProduto, p.nomeProduto, p.categoria, p.preco, p.localizacao,
                    c.nomeCliente, c.email AS emailCliente, c.telefone AS telCliente, c.cidade AS cidadeCliente, c.uf AS ufCliente,
                    f.nomeFornecedor, f.cnpj AS cnpjFornecedor, f.telefone AS telFornecedor
                FROM movimento m
                INNER JOIN produto p ON p.idProduto = m.idProduto
                LEFT JOIN cliente c ON c.idCliente = m.idCliente
                LEFT JOIN fornecedor f ON f.idFornecedor = m.idFornecedor
                WHERE m.codigoPedido = ? AND m.idUsuario = ?
                ORDER BY p.localizacao ASC, m.idMovimento ASC
            ");
            mysqli_stmt_bind_param($stmtMulti, "si", $movIndividual['codigoPedido'], $idUsuario);
            mysqli_stmt_execute($stmtMulti);
            $resMulti = mysqli_stmt_get_result($stmtMulti);
            $itens = mysqli_fetch_all($resMulti, MYSQLI_ASSOC);
        } else {
            $itens = [$movIndividual];
        }
    }
}

mysqli_close($conexao);

if (empty($itens)) {
    die("Registro de movimentação não encontrado ou você não tem permissão para acessá-lo.");
}

$primeiroItem = $itens[0];
$isSaida = ($primeiroItem['tipoMovimento'] === 'SAIDA');
$identificadorDoc = !empty($primeiroItem['codigoPedido']) ? $primeiroItem['codigoPedido'] : ('MOV-#' . sprintf('%06d', $primeiroItem['idMovimento']));

// Cálculos consolidados
$totalValorGeral = 0;
$totalPecasGeral = 0;
foreach ($itens as $it) {
    $qtd = (int)$it['quantidade'];
    $preco = (float)$it['preco'];
    $totalValorGeral += ($qtd * $preco);
    $totalPecasGeral += $qtd;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modoInicial === 'picking' ? 'Ordem de Separação (Picking List)' : 'Comprovante de Expedição' ?> <?= htmlspecialchars($identificadorDoc) ?> - MVM Estoque</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            color: #1e293b;
            padding: 24px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .actions-bar {
            width: 100%;
            max-width: 780px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .actions-bar-left, .actions-bar-right {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 15px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-print {
            background: #00704A;
            color: #fff;
        }
        .btn-print:hover { background: #005638; }
        .btn-picking-toggle {
            background: #2563eb;
            color: #fff;
        }
        .btn-picking-toggle:hover { background: #1d4ed8; }
        .btn-mode {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-mode:hover { background: #cbd5e1; }
        .btn-close {
            background: #e2e8f0;
            color: #475569;
        }
        .btn-close:hover { background: #cbd5e1; }

        /* =========================================================================
           1. MODO TICKET TÉRMICO / BALCÃO
           ========================================================================= */
        .thermal-ticket {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border-radius: 8px;
            padding: 26px 22px;
            position: relative;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            line-height: 1.45;
            transition: max-width 0.3s ease;
        }

        body.formato-a4 .thermal-ticket {
            max-width: 720px;
            padding: 35px 40px;
        }

        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #94a3b8;
            padding-bottom: 16px;
            margin-bottom: 16px;
        }

        .ticket-logo {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            margin: 0 auto 8px;
            display: block;
        }

        .ticket-header h1 {
            font-size: 15px;
            font-weight: 800;
            color: #00704A;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ticket-header p {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        .ticket-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            background: <?= $isSaida ? '#dcfce7; color: #166534;' : '#dbeafe; color: #1e40af;' ?>;
        }

        .ticket-section {
            margin-bottom: 14px;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 12px;
        }

        .row-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .row-info .label { color: #64748b; }
        .row-info .val { font-weight: 600; text-align: right; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
        }
        .items-table th {
            text-align: left;
            font-size: 11px;
            color: #64748b;
            border-bottom: 1px solid #94a3b8;
            padding-bottom: 6px;
        }
        .items-table td {
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            vertical-align: top;
        }
        .items-table .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }

        .total-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-box .total-title { font-weight: 700; font-size: 13.5px; }
        .total-box .total-val { font-weight: 800; font-size: 16px; color: #00704A; }

        .sign-area {
            margin-top: 32px;
            text-align: center;
        }
        .sign-line {
            border-top: 1px solid #64748b;
            width: 80%;
            margin: 0 auto 6px;
        }
        .sign-text {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
        }

        .barcode-box {
            text-align: center;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 2px dashed #94a3b8;
        }
        .barcode-box svg {
            width: 230px;
            height: 48px;
        }

        /* =========================================================================
           2. MODO LISTA DE SEPARAÇÃO (PICKING LIST WMS)
           ========================================================================= */
        .picking-sheet {
            display: none;
            width: 100%;
            max-width: 780px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border-radius: 8px;
            padding: 35px 40px;
        }

        body.view-picking .thermal-ticket { display: none; }
        body.view-picking .picking-sheet { display: block; }

        .picking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }
        .picking-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .picking-header-titles h1 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .picking-header-titles p {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .picking-header-badges {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }
        .badge-wms-route {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-order-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .picking-info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .p-info-block .p-label {
            display: block;
            font-size: 10.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
        }
        .p-info-block .p-val {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .picking-route-tip {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 22px;
            font-size: 12px;
            color: #166534;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.45;
        }

        .picking-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .picking-table thead th {
            background: #0f172a;
            color: #ffffff;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: left;
        }
        .picking-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }
        .picking-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .picking-table tbody td {
            padding: 12px;
            font-size: 13px;
            vertical-align: middle;
        }

        .check-box-square {
            width: 22px;
            height: 22px;
            border: 2px solid #94a3b8;
            border-radius: 4px;
            margin: 0 auto;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            user-select: none;
            background: #fff;
        }
        .check-box-square.checked {
            background: #16a34a;
            border-color: #16a34a;
            color: #ffffff;
        }

        .tag-wms-loc {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            font-size: 13px;
            background: #1e293b;
            color: #38bdf8;
            padding: 5px 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
        }

        .badge-qtd-coletar {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            font-size: 15px;
            color: #0f172a;
            background: #e2e8f0;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .picking-footer-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 20px;
            margin-bottom: 28px;
        }

        .picking-sign-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
        }
        .sign-col {
            text-align: center;
        }
        .sign-col-line {
            border-top: 1px solid #475569;
            margin-bottom: 6px;
        }
        .sign-col-title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .sign-col-sub {
            font-size: 10.5px;
            color: #64748b;
        }

        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .actions-bar { display: none !important; }
            .thermal-ticket, .picking-sheet {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                width: 100% !important;
                padding: 10px !important;
            }
        }
    </style>
</head>
<body class="<?= $modoInicial === 'picking' ? 'view-picking' : '' ?>">

    <!-- BARRA SUPERIOR DE AÇÕES -->
    <div class="actions-bar">
        <div class="actions-bar-left">
            <button type="button" onclick="window.print()" class="btn btn-print">
                <span>🖨️</span> Imprimir
            </button>
            <button type="button" onclick="alternarModoVisualizacao()" class="btn btn-picking-toggle" id="btn-toggle-modo">
                <?= $modoInicial === 'picking' ? '<span>🧾</span> Ver Recibo Balcão' : '<span>📋</span> Modo Picking List (WMS)' ?>
            </button>
        </div>
        <div class="actions-bar-right">
            <button type="button" onclick="alternarLarguraRecibo()" class="btn btn-mode" id="btn-formato" title="Alternar entre formato Bobina Térmica e A4 Completo">
                <span>📄</span> Formato: <span id="label-modo">Térmico</span>
            </button>
            <button type="button" onclick="window.close()" class="btn btn-close">
                ✕ Fechar
            </button>
        </div>
    </div>

    <!-- =========================================================================
         VISUALIZAÇÃO 1: TICKET TÉRMICO / COMPROVANTE COMERCIAL
         ========================================================================= -->
    <div class="thermal-ticket">
        <div class="ticket-header">
            <img src="img/mascote_mvm.svg?v=3" alt="MVM Logo" class="ticket-logo">
            <h1>MVM ESTOQUE &amp; LOGÍSTICA</h1>
            <p>Sistema Integrado de Controle de Armazém &amp; Expedição</p>
            <span class="ticket-badge">
                <?= $isSaida ? 'Comprovante de Saída / Venda' : 'Comprovante de Entrada de Carga' ?>
            </span>
        </div>

        <div class="ticket-section">
            <div class="row-info">
                <span class="label">Identificador:</span>
                <span class="val" style="color: #00704A;"><?= htmlspecialchars($identificadorDoc) ?></span>
            </div>
            <div class="row-info">
                <span class="label">Data/Hora:</span>
                <span class="val"><?= date('d/m/Y H:i', strtotime($primeiroItem['dataMovimento'])) ?></span>
            </div>
            <div class="row-info">
                <span class="label">Operador:</span>
                <span class="val"><?= htmlspecialchars($_SESSION['usuarioLogado'] ?? 'Sistema') ?></span>
            </div>
        </div>

        <div class="ticket-section">
            <?php if ($isSaida && !empty($primeiroItem['nomeCliente'])): ?>
                <div class="row-info">
                    <span class="label">Destinatário:</span>
                    <span class="val"><?= htmlspecialchars($primeiroItem['nomeCliente']) ?></span>
                </div>
                <?php if (!empty($primeiroItem['telCliente'])): ?>
                    <div class="row-info">
                        <span class="label">Telefone:</span>
                        <span class="val"><?= htmlspecialchars($primeiroItem['telCliente']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($primeiroItem['cidadeCliente'])): ?>
                    <div class="row-info">
                        <span class="label">Localidade:</span>
                        <span class="val"><?= htmlspecialchars($primeiroItem['cidadeCliente']) ?><?= !empty($primeiroItem['ufCliente']) ? '/' . htmlspecialchars($primeiroItem['ufCliente']) : '' ?></span>
                    </div>
                <?php endif; ?>
            <?php elseif (!empty($primeiroItem['nomeFornecedor'])): ?>
                <div class="row-info">
                    <span class="label">Fornecedor:</span>
                    <span class="val"><?= htmlspecialchars($primeiroItem['nomeFornecedor']) ?></span>
                </div>
                <?php if (!empty($primeiroItem['cnpjFornecedor'])): ?>
                    <div class="row-info">
                        <span class="label">CNPJ:</span>
                        <span class="val"><?= htmlspecialchars($primeiroItem['cnpjFornecedor']) ?></span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="row-info">
                    <span class="label">Destino / Origem:</span>
                    <span class="val">Balcão / Operação Interna</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- LISTA DE ITENS DA EXPEDIÇÃO -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>ITEM / LOCALIZAÇÃO</th>
                    <th class="text-center" style="width: 45px;">QTD</th>
                    <th class="text-right" style="width: 80px;">UNIT</th>
                    <th class="text-right" style="width: 85px;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <?php 
                        $precoUnit = (float)$item['preco'];
                        $qtdItem = (int)$item['quantidade'];
                        $subtotalItem = $precoUnit * $qtdItem;
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['nomeProduto']) ?></strong>
                            <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                                <span style="font-weight: 700; color: #00704A;">📍 <?= htmlspecialchars($item['localizacao'] ?? 'A-01-01') ?></span>
                                <?= !empty($item['lote']) ? ' &bull; Lote: ' . htmlspecialchars($item['lote']) : '' ?>
                                <?= !empty($item['dataValidade']) ? ' &bull; Val: ' . date('d/m/Y', strtotime($item['dataValidade'])) : '' ?>
                            </div>
                        </td>
                        <td class="text-center"><?= $qtdItem ?></td>
                        <td class="text-right">R$ <?= number_format($precoUnit, 2, ',', '.') ?></td>
                        <td class="text-right"><strong>R$ <?= number_format($subtotalItem, 2, ',', '.') ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTAL GERAL DA OPERAÇÃO -->
        <div class="total-box">
            <div>
                <span class="total-title">VALOR TOTAL:</span>
                <div style="font-size: 11px; color: #64748b; font-weight: 500;">
                    <?= count($itens) ?> item(ns) &bull; <?= $totalPecasGeral ?> peça(s) no total
                </div>
            </div>
            <span class="total-val">R$ <?= number_format($totalValorGeral, 2, ',', '.') ?></span>
        </div>

        <?php if (!empty($primeiroItem['observacao'])): ?>
            <div style="margin-top: 12px; font-size: 11px; color: #475569; background: #f8fafc; padding: 8px 10px; border-radius: 4px; border: 1px dashed #cbd5e1;">
                <strong>Obs:</strong> <?= htmlspecialchars($primeiroItem['observacao']) ?>
            </div>
        <?php endif; ?>

        <!-- ASSINATURA DE CONFERÊNCIA -->
        <div class="sign-area">
            <div class="sign-line"></div>
            <div class="sign-text">Assinatura do Recebedor / Conferente</div>
        </div>

        <!-- CÓDIGO DE BARRAS LOGÍSTICO -->
        <div class="barcode-box">
            <svg id="barcode-ticket"></svg>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">
                <?= htmlspecialchars($identificadorDoc) ?>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         VISUALIZAÇÃO 2: FOLHA DE SEPARAÇÃO LOGÍSTICA (PICKING LIST WMS)
         ========================================================================= -->
    <div class="picking-sheet">
        <div class="picking-header">
            <div class="picking-header-left">
                <img src="img/mascote_mvm.svg?v=3" alt="MVM" style="width: 48px; height: 48px;">
                <div class="picking-header-titles">
                    <h1>Ordem de Separação de Pedido (Picking List)</h1>
                    <p>MVM Estoque &amp; Logística &bull; Módulo WMS</p>
                </div>
            </div>
            <div class="picking-header-badges">
                <span class="badge-order-id"><?= htmlspecialchars($identificadorDoc) ?></span>
                <span class="badge-wms-route">
                    <span>🧭</span> Rota Sequencial de Armazém
                </span>
            </div>
        </div>

        <!-- Grid de Metadados Logísticos -->
        <div class="picking-info-grid">
            <div class="p-info-block">
                <span class="p-label">Destinatário:</span>
                <span class="p-val"><?= htmlspecialchars($primeiroItem['nomeCliente'] ?? ($primeiroItem['nomeFornecedor'] ?? 'Balcão / Uso Interno')) ?></span>
            </div>
            <div class="p-info-block">
                <span class="p-label">Data da Emissão:</span>
                <span class="p-val"><?= date('d/m/Y H:i', strtotime($primeiroItem['dataMovimento'])) ?></span>
            </div>
            <div class="p-info-block">
                <span class="p-label">Operador Responsável:</span>
                <span class="p-val"><?= htmlspecialchars($_SESSION['usuarioLogado'] ?? 'Operador') ?></span>
            </div>
            <div class="p-info-block">
                <span class="p-label">Tipo de Movimento:</span>
                <span class="p-val" style="color: <?= $isSaida ? '#16a34a' : '#2563eb' ?>;">
                    <?= $isSaida ? 'EXPEDIÇÃO / VENDA' : 'ENTRADA / REPOSIÇÃO' ?>
                </span>
            </div>
        </div>

        <!-- Banner Explicativo de Rota WMS -->
        <div class="picking-route-tip">
            <span style="font-size: 20px;">🚚</span>
            <div>
                <strong>Sequenciamento Logístico Otimizado:</strong>
                Os itens desta lista estão organizados pela localização física no armazém (Corredor &rarr; Prateleira &rarr; Nível) para eliminar voltas desnecessárias no galpão.
            </div>
        </div>

        <!-- TABELA DE ITENS COM CHECKLIST INTERATIVO -->
        <table class="picking-table">
            <thead>
                <tr>
                    <th style="width: 45px; text-align: center;">✓</th>
                    <th style="width: 140px;">Endereço WMS</th>
                    <th>Produto &amp; Rastreabilidade</th>
                    <th style="width: 110px; text-align: center;">Qtd a Coletar</th>
                    <th style="width: 110px; text-align: center;">Conferência</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $idx => $item): ?>
                    <tr>
                        <td style="text-align: center;">
                            <div class="check-box-square" onclick="this.classList.toggle('checked'); this.textContent = this.classList.contains('checked') ? '✓' : '';" title="Clique para marcar como coletado"></div>
                        </td>
                        <td>
                            <span class="tag-wms-loc">
                                <span>📍</span> <?= htmlspecialchars($item['localizacao'] ?? 'A-01-01') ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #0f172a; font-size: 14px;"><?= htmlspecialchars($item['nomeProduto']) ?></strong>
                            <div style="font-size: 11px; color: #64748b; margin-top: 3px; font-family: 'JetBrains Mono', monospace;">
                                CÓD: #<?= sprintf('%06d', $item['idProduto']) ?>
                                <?= !empty($item['lote']) ? ' &bull; Lote: <strong>' . htmlspecialchars($item['lote']) . '</strong>' : '' ?>
                                <?= !empty($item['dataValidade']) ? ' &bull; Validade FEFO: <strong>' . date('d/m/Y', strtotime($item['dataValidade'])) . '</strong>' : '' ?>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge-qtd-coletar">
                                <?= (int)$item['quantidade'] ?> un
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="border-bottom: 1px dashed #94a3b8; display: inline-block; width: 65px; height: 18px;"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Resumo de Carga -->
        <div class="picking-footer-summary">
            <div>
                <strong style="font-size: 14px; color: #0f172a;">Total de Itens / SKUs:</strong>
                <span style="font-size: 14px; font-weight: 700; color: #2563eb; margin-left: 6px;"><?= count($itens) ?> distintos</span>
            </div>
            <div>
                <strong style="font-size: 14px; color: #0f172a;">Volume Físico Total:</strong>
                <span style="font-size: 16px; font-weight: 800; color: #16a34a; margin-left: 6px; font-family: 'JetBrains Mono', monospace;"><?= $totalPecasGeral ?> unidades</span>
            </div>
        </div>

        <!-- Assinaturas e Checkpoints WMS -->
        <div class="picking-sign-grid">
            <div class="sign-col">
                <div class="sign-col-line"></div>
                <div class="sign-col-title">Separador (Picking)</div>
                <div class="sign-col-sub">Coleta física realizada nas prateleiras</div>
            </div>
            <div class="sign-col">
                <div class="sign-col-line"></div>
                <div class="sign-col-title">Conferente / Embalador (Packing)</div>
                <div class="sign-col-sub">Auditoria final antes do despacho</div>
            </div>
        </div>

        <div class="barcode-box" style="margin-top: 25px;">
            <svg id="barcode-picking"></svg>
            <div style="font-size: 10.5px; color: #64748b; margin-top: 4px; font-family: 'JetBrains Mono', monospace;">
                ORDEM WMS: <?= htmlspecialchars($identificadorDoc) ?>
            </div>
        </div>
    </div>

    <!-- Script de geração de códigos de barra e alternância de modos -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        function alternarLarguraRecibo() {
            document.body.classList.toggle('formato-a4');
            const isA4 = document.body.classList.contains('formato-a4');
            document.getElementById('label-modo').textContent = isA4 ? 'A4 Expandido' : 'Térmico';
        }

        function alternarModoVisualizacao() {
            document.body.classList.toggle('view-picking');
            const isPicking = document.body.classList.contains('view-picking');
            const btn = document.getElementById('btn-toggle-modo');
            const btnFormato = document.getElementById('btn-formato');

            if (isPicking) {
                btn.innerHTML = '<span>🧾</span> Ver Recibo Balcão';
                btnFormato.style.display = 'none';
            } else {
                btn.innerHTML = '<span>📋</span> Modo Picking List (WMS)';
                btnFormato.style.display = 'inline-flex';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            try {
                const cod = <?= json_encode(preg_replace('/[^a-zA-Z0-9]/', '', $identificadorDoc)) ?>;
                const opts = {
                    format: "CODE128",
                    lineColor: "#1e293b",
                    width: 2,
                    height: 40,
                    displayValue: false
                };
                if (document.getElementById("barcode-ticket")) {
                    JsBarcode("#barcode-ticket", cod || "MVM000000", opts);
                }
                if (document.getElementById("barcode-picking")) {
                    JsBarcode("#barcode-picking", cod || "MVM000000", opts);
                }
            } catch(e) {
                console.error("Erro ao gerar código de barras:", e);
            }
        });
    </script>
</body>
</html>
