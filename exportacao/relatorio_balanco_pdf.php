<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

// Mês selecionado (formato YYYY-MM)
$mesRef = !empty($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$inicioMes = $mesRef . "-01 00:00:00";
$fimMes = date("Y-m-t 23:59:59", strtotime($inicioMes));
$nomeMesExtenso = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
$partesMes = explode('-', $mesRef);
$rotuloMes = ($nomeMesExtenso[$partesMes[1]] ?? $partesMes[1]) . " de " . $partesMes[0];

// 1. Métricas gerais do mês
$stmtMetricas = mysqli_prepare($conexao, "
    SELECT 
        SUM(CASE WHEN tipoMovimento = 'ENTRADA' THEN quantidade ELSE 0 END) AS qtdEntradas,
        SUM(CASE WHEN tipoMovimento = 'ENTRADA' THEN valorTotal ELSE 0 END) AS valEntradas,
        SUM(CASE WHEN tipoMovimento = 'SAIDA' AND dataDevolucao IS NULL THEN quantidade ELSE 0 END) AS qtdSaidas,
        SUM(CASE WHEN tipoMovimento = 'SAIDA' AND dataDevolucao IS NULL THEN valorTotal ELSE 0 END) AS valSaidas,
        COUNT(DISTINCT CASE WHEN codigoPedido IS NOT NULL THEN codigoPedido END) AS totalPedidos,
        COUNT(*) AS totalMovimentos
    FROM movimento
    WHERE idUsuario = ? AND dataMovimento BETWEEN ? AND ?
");
mysqli_stmt_bind_param($stmtMetricas, "iss", $idUsuario, $inicioMes, $fimMes);
mysqli_stmt_execute($stmtMetricas);
$metricas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMetricas));
mysqli_stmt_close($stmtMetricas);

$qtdEntradas = (int)($metricas['qtdEntradas'] ?? 0);
$valEntradas = (float)($metricas['valEntradas'] ?? 0);
$qtdSaidas   = (int)($metricas['qtdSaidas'] ?? 0);
$valSaidas   = (float)($metricas['valSaidas'] ?? 0);
$lucroBruto  = max(0, $valSaidas - $valEntradas);
$totalPedidos = (int)($metricas['totalPedidos'] ?? 0);

// 2. Top 5 Produtos mais vendidos do mês
$stmtTop = mysqli_prepare($conexao, "
    SELECT p.nomeProduto, p.categoria, SUM(m.quantidade) as totalVendido, SUM(m.valorTotal) as totalFaturado
    FROM movimento m
    INNER JOIN produto p ON p.idProduto = m.idProduto
    WHERE m.idUsuario = ? AND m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL
      AND m.dataMovimento BETWEEN ? AND ?
    GROUP BY p.idProduto, p.nomeProduto, p.categoria
    ORDER BY totalVendido DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmtTop, "iss", $idUsuario, $inicioMes, $fimMes);
mysqli_stmt_execute($stmtTop);
$topProdutos = mysqli_fetch_all(mysqli_stmt_get_result($stmtTop), MYSQLI_ASSOC);
mysqli_stmt_close($stmtTop);

// 3. Vendas por Categoria
$stmtCat = mysqli_prepare($conexao, "
    SELECT COALESCE(NULLIF(p.categoria, ''), 'Sem Categoria') as categoria, 
           SUM(m.quantidade) as qtdTotal, SUM(m.valorTotal) as valorTotal
    FROM movimento m
    INNER JOIN produto p ON p.idProduto = m.idProduto
    WHERE m.idUsuario = ? AND m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL
      AND m.dataMovimento BETWEEN ? AND ?
    GROUP BY categoria
    ORDER BY valorTotal DESC
");
mysqli_stmt_bind_param($stmtCat, "iss", $idUsuario, $inicioMes, $fimMes);
mysqli_stmt_execute($stmtCat);
$vendasPorCat = mysqli_fetch_all(mysqli_stmt_get_result($stmtCat), MYSQLI_ASSOC);
mysqli_stmt_close($stmtCat);

mysqli_close($conexao);

// Monta linhas de produtos
$linhasTop = '';
if (!empty($topProdutos)) {
    foreach ($topProdutos as $tp) {
        $linhasTop .= "
            <tr>
                <td style='font-size: 9.5px; padding: 5px 8px;'><b>" . htmlspecialchars($tp['nomeProduto']) . "</b></td>
                <td style='font-size: 9px; padding: 5px 8px; color: #475569;'>" . htmlspecialchars($tp['categoria']) . "</td>
                <td style='font-size: 9.5px; padding: 5px 8px; text-align: right; font-weight: bold;'>" . (int)$tp['totalVendido'] . " un</td>
                <td style='font-size: 9.5px; padding: 5px 8px; text-align: right; font-weight: bold; color: #047857;'>R$ " . number_format((float)$tp['totalFaturado'], 2, ',', '.') . "</td>
            </tr>";
    }
} else {
    $linhasTop = "<tr><td colspan='4' style='text-align: center; padding: 12px; color: #64748b;'>Nenhuma venda registrada no período.</td></tr>";
}

// Monta linhas de categorias
$linhasCat = '';
if (!empty($vendasPorCat)) {
    foreach ($vendasPorCat as $vc) {
        $linhasCat .= "
            <tr>
                <td style='font-size: 9.5px; padding: 5px 8px;'><b>" . htmlspecialchars($vc['categoria']) . "</b></td>
                <td style='font-size: 9.5px; padding: 5px 8px; text-align: right;'>" . (int)$vc['qtdTotal'] . " un</td>
                <td style='font-size: 9.5px; padding: 5px 8px; text-align: right; font-weight: bold; color: #047857;'>R$ " . number_format((float)$vc['valorTotal'], 2, ',', '.') . "</td>
            </tr>";
    }
} else {
    $linhasCat = "<tr><td colspan='3' style='text-align: center; padding: 12px; color: #64748b;'>Sem movimentação por categoria no período.</td></tr>";
}

$emissaoData = date('d/m/Y \à\s H:i');

$html = "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
<meta charset='UTF-8'>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #0f172a; margin: 0; padding: 0; }
    .header-box { border-bottom: 2px solid #00704A; padding-bottom: 8px; margin-bottom: 12px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .company-title { font-size: 16px; font-weight: bold; color: #00704A; margin: 0; }
    .doc-title { font-size: 13px; font-weight: bold; color: #0f172a; margin: 2px 0 0 0; }
    .meta-text { font-size: 9px; color: #64748b; }
    
    .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .kpi-card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; text-align: center; background: #f8fafc; }
    .kpi-title { font-size: 9px; font-weight: bold; color: #475569; text-transform: uppercase; }
    .kpi-value { font-size: 18px; font-weight: 800; margin-top: 4px; }
    .kpi-sub { font-size: 8px; color: #64748b; margin-top: 2px; }
    
    .section-title { font-size: 11px; font-weight: bold; color: #1e293b; margin: 14px 0 6px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.data-table th { background-color: #00704A; color: #ffffff; font-size: 9px; padding: 6px 5px; border: 1px solid #005035; text-align: left; }
    table.data-table td { border: 1px solid #e2e8f0; }
    table.data-table tr:nth-child(even) { background-color: #f8fafc; }
</style>
</head>
<body>

<div class='header-box'>
    <table class='header-table'>
        <tr>
            <td style='width: 65%;'>
                <div class='company-title'>MVM CENTRAL DE DISTRIBUIÇÃO & ESTOQUE</div>
                <div class='doc-title'>BALANÇO MENSAL FINANCEIRO & OPERACIONAL</div>
                <div class='meta-text'>Mês de Referência: <b>{$rotuloMes}</b> | Demonstrativo Consolidado</div>
            </td>
            <td style='width: 35%; text-align: right;'>
                <div class='meta-text'><b>Data de Emissão:</b> {$emissaoData}</div>
                <div class='meta-text'><b>Emissor:</b> " . htmlspecialchars($_SESSION['usuarioLogado']) . "</div>
                <div class='meta-text'><b>Total de Pedidos Expedidos:</b> {$totalPedidos}</div>
            </td>
        </tr>
    </table>
</div>

<!-- Cards de Indicadores Financeiros do Mês -->
<table class='kpi-table'>
    <tr>
        <td style='width: 25%; padding-right: 5px;'>
            <div class='kpi-card'>
                <div class='kpi-title'>Faturamento de Vendas</div>
                <div class='kpi-value' style='color: #047857;'>R$ " . number_format($valSaidas, 2, ',', '.') . "</div>
                <div class='kpi-sub'>{$qtdSaidas} unidades saídas</div>
            </div>
        </td>
        <td style='width: 25%; padding: 0 3px;'>
            <div class='kpi-card'>
                <div class='kpi-title'>Entradas / Compras</div>
                <div class='kpi-value' style='color: #0284c7;'>R$ " . number_format($valEntradas, 2, ',', '.') . "</div>
                <div class='kpi-sub'>{$qtdEntradas} unidades repostas</div>
            </div>
        </td>
        <td style='width: 25%; padding: 0 3px;'>
            <div class='kpi-card'>
                <div class='kpi-title'>Saldo Operacional</div>
                <div class='kpi-value' style='color: #00704A;'>R$ " . number_format($lucroBruto, 2, ',', '.') . "</div>
                <div class='kpi-sub'>Receita líquida aproximada</div>
            </div>
        </td>
        <td style='width: 25%; padding-left: 5px;'>
            <div class='kpi-card'>
                <div class='kpi-title'>Total de Transações</div>
                <div class='kpi-value' style='color: #475569;'>" . (int)($metricas['totalMovimentos'] ?? 0) . "</div>
                <div class='kpi-sub'>Entradas + saídas registradas</div>
            </div>
        </td>
    </tr>
</table>

<!-- Seção: Top Produtos -->
<div class='section-title'>🏆 Top 5 Produtos Mais Vendidos em {$rotuloMes}</div>
<table class='data-table'>
    <thead>
        <tr>
            <th style='width: 45%;'>Produto</th>
            <th style='width: 25%;'>Categoria</th>
            <th style='width: 15%; text-align: right;'>Qtd Vendida</th>
            <th style='width: 15%; text-align: right;'>Faturamento</th>
        </tr>
    </thead>
    <tbody>
        {$linhasTop}
    </tbody>
</table>

<!-- Seção: Vendas por Categoria -->
<div class='section-title' style='margin-top: 18px;'>📊 Faturamento Consolidado por Categoria</div>
<table class='data-table'>
    <thead>
        <tr>
            <th style='width: 50%;'>Categoria</th>
            <th style='width: 25%; text-align: right;'>Volume de Itens</th>
            <th style='width: 25%; text-align: right;'>Valor Total (R$)</th>
        </tr>
    </thead>
    <tbody>
        {$linhasCat}
    </tbody>
</table>

</body>
</html>";

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nomeArquivo = "balanco_mensal_" . str_replace('-', '_', $mesRef) . ".pdf";
$dompdf->stream($nomeArquivo, ["Attachment" => false]);
