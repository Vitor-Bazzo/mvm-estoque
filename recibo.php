<?php
require_once "config/conexao.php";

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = obterIdUsuarioLogado();
$codigoPedido = isset($_GET['pedido']) ? trim($_GET['pedido']) : '';
$idMovimento = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idMovimento <= 0 && empty($codigoPedido)) {
    die("Identificador de pedido ou movimentação não informado.");
}

$conexao = conectar();
$itens = [];

if (!empty($codigoPedido)) {
    // Busca por código de pedido consolidado
    $sql = "
        SELECT 
            m.idMovimento, m.codigoPedido, m.tipoMovimento, m.quantidade, m.observacao, m.dataMovimento, m.dataDevolucao, m.lote, m.dataValidade, m.valorTotal,
            p.idProduto, p.nomeProduto, p.categoria, p.preco,
            c.nomeCliente, c.email AS emailCliente, c.telefone AS telCliente, c.cidade AS cidadeCliente, c.uf AS ufCliente,
            f.nomeFornecedor, f.cnpj AS cnpjFornecedor, f.telefone AS telFornecedor
        FROM movimento m
        INNER JOIN produto p ON p.idProduto = m.idProduto
        LEFT JOIN cliente c ON c.idCliente = m.idCliente
        LEFT JOIN fornecedor f ON f.idFornecedor = m.idFornecedor
        WHERE m.codigoPedido = ? AND m.idUsuario = ?
        ORDER BY m.idMovimento ASC
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
            p.idProduto, p.nomeProduto, p.categoria, p.preco,
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
        // Se a movimentação pertencer a um pedido multi-itens, busca os outros itens do pedido
        if (!empty($movIndividual['codigoPedido'])) {
            $stmtMulti = mysqli_prepare($conexao, "
                SELECT 
                    m.idMovimento, m.codigoPedido, m.tipoMovimento, m.quantidade, m.observacao, m.dataMovimento, m.dataDevolucao, m.lote, m.dataValidade, m.valorTotal,
                    p.idProduto, p.nomeProduto, p.categoria, p.preco,
                    c.nomeCliente, c.email AS emailCliente, c.telefone AS telCliente, c.cidade AS cidadeCliente, c.uf AS ufCliente,
                    f.nomeFornecedor, f.cnpj AS cnpjFornecedor, f.telefone AS telFornecedor
                FROM movimento m
                INNER JOIN produto p ON p.idProduto = m.idProduto
                LEFT JOIN cliente c ON c.idCliente = m.idCliente
                LEFT JOIN fornecedor f ON f.idFornecedor = m.idFornecedor
                WHERE m.codigoPedido = ? AND m.idUsuario = ?
                ORDER BY m.idMovimento ASC
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
    <title>Comprovante de Expedição <?= htmlspecialchars($identificadorDoc) ?> - MVM Estoque</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            color: #1e293b;
            padding: 30px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .actions-bar {
            width: 100%;
            max-width: 440px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-print {
            background: #00704A;
            color: #fff;
        }
        .btn-print:hover { background: #005638; }
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

        /* TICKET TÉRMICO / EXPEDIÇÃO */
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

        /* MODO FORMATO A4 / EXPANDIDO */
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

        @media print {
            body { background: #fff; padding: 0; }
            .actions-bar { display: none; }
            .thermal-ticket {
                box-shadow: none;
                border: none;
                max-width: 100%;
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <div class="actions-bar">
        <button type="button" onclick="window.print()" class="btn btn-print">
            <span>🖨️</span> Imprimir
        </button>
        <button type="button" onclick="alternarLarguraRecibo()" class="btn btn-mode" title="Alternar entre formato Bobina Térmica e A4 Completo">
            <span>📄</span> Formato: <span id="label-modo">Térmico</span>
        </button>
        <button type="button" onclick="window.close()" class="btn btn-close">
            ✕ Fechar
        </button>
    </div>

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

        <!-- LISTA DE ITENS DA EXPEDIÇÃO (MÚLTIPLOS ITENS SUPORTADOS) -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>ITEM / DESCRIÇÃO</th>
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
                            <div style="font-size: 10px; color: #64748b;">
                                <?= htmlspecialchars($item['categoria'] ?: 'Geral') ?>
                                <?= !empty($item['lote']) ? ' &bull; Lote: ' . htmlspecialchars($item['lote']) : '' ?>
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
            <svg id="barcode"></svg>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">
                <?= htmlspecialchars($identificadorDoc) ?>
            </div>
        </div>
    </div>

    <!-- Script de geração do código de barras real via JsBarcode -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        function alternarLarguraRecibo() {
            document.body.classList.toggle('formato-a4');
            const isA4 = document.body.classList.contains('formato-a4');
            document.getElementById('label-modo').textContent = isA4 ? 'A4 Expandido' : 'Térmico';
        }

        document.addEventListener('DOMContentLoaded', function() {
            try {
                const cod = <?= json_encode(preg_replace('/[^a-zA-Z0-9]/', '', $identificadorDoc)) ?>;
                JsBarcode("#barcode", cod || "MVM000000", {
                    format: "CODE128",
                    lineColor: "#1e293b",
                    width: 2,
                    height: 40,
                    displayValue: false
                });
            } catch(e) {
                console.error("Erro ao gerar código de barras:", e);
            }
        });
    </script>
</body>
</html>
