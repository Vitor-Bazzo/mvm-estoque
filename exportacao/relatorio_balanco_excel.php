<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: ' . url('modulos/auth/login.php'));
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

$mesRef = !empty($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$inicioMes = $mesRef . "-01 00:00:00";
$fimMes = date("Y-m-t 23:59:59", strtotime($inicioMes));

// 1. Resumo financeiro do mês
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

// 2. Ranking de produtos vendidos
$stmtTop = mysqli_prepare($conexao, "
    SELECT p.nomeProduto, p.categoria, SUM(m.quantidade) as totalVendido, SUM(m.valorTotal) as totalFaturado
    FROM movimento m
    INNER JOIN produto p ON p.idProduto = m.idProduto
    WHERE m.idUsuario = ? AND m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL
      AND m.dataMovimento BETWEEN ? AND ?
    GROUP BY p.idProduto, p.nomeProduto, p.categoria
    ORDER BY totalVendido DESC
");
mysqli_stmt_bind_param($stmtTop, "iss", $idUsuario, $inicioMes, $fimMes);
mysqli_stmt_execute($stmtTop);
$topProdutos = mysqli_fetch_all(mysqli_stmt_get_result($stmtTop), MYSQLI_ASSOC);
mysqli_stmt_close($stmtTop);

mysqli_close($conexao);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Balanço Mensal');

// Cabeçalho da Empresa
$sheet->mergeCells('A1:E1');
$sheet->setCellValue('A1', 'MVM CENTRAL DE DISTRIBUIÇÃO - BALANÇO MENSAL FINANCEIRO & OPERACIONAL');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('00704A');

$sheet->mergeCells('A2:E2');
$sheet->setCellValue('A2', 'Mês de Referência: ' . $mesRef . ' | Emitido em: ' . date('d/m/Y H:i') . ' | Operador: ' . $_SESSION['usuarioLogado']);
$sheet->getStyle('A2')->getFont()->setSize(9.5)->getColor()->setRGB('475569');

// Tabela de Métricas Consolidada
$sheet->setCellValue('A4', 'INDICADOR DE DESEMPENHO');
$sheet->setCellValue('B4', 'VALOR / QUANTIDADE');
$sheet->getStyle('A4:B4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00704A');

$sheet->setCellValue('A5', 'Faturamento Total de Vendas (Saídas):');
$sheet->setCellValue('B5', (float)($metricas['valSaidas'] ?? 0));
$sheet->getStyle('B5')->getNumberFormat()->setFormatCode('R$ #,##0.00');

$sheet->setCellValue('A6', 'Quantidade Total de Itens Vendidos:');
$sheet->setCellValue('B6', (int)($metricas['qtdSaidas'] ?? 0));
$sheet->getStyle('B6')->getNumberFormat()->setFormatCode('#,##0');

$sheet->setCellValue('A7', 'Compras / Entradas de Estoque (R$):');
$sheet->setCellValue('B7', (float)($metricas['valEntradas'] ?? 0));
$sheet->getStyle('B7')->getNumberFormat()->setFormatCode('R$ #,##0.00');

$sheet->setCellValue('A8', 'Quantidade Total de Itens Repostos:');
$sheet->setCellValue('B8', (int)($metricas['qtdEntradas'] ?? 0));
$sheet->getStyle('B8')->getNumberFormat()->setFormatCode('#,##0');

$sheet->setCellValue('A9', 'Saldo Operacional Estimado (Vendas - Compras):');
$sheet->setCellValue('B9', '=B5-B7');
$sheet->getStyle('B9')->getNumberFormat()->setFormatCode('R$ #,##0.00');
$sheet->getStyle('A9:B9')->getFont()->setBold(true)->getColor()->setRGB('00704A');

$sheet->setCellValue('A10', 'Total de Pedidos Comerciais Expedidos:');
$sheet->setCellValue('B10', (int)($metricas['totalPedidos'] ?? 0));

$sheet->getStyle('A4:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

// Seção: Ranking de Produtos Vendidos
$sheet->setCellValue('A13', 'RANKING DE PRODUTOS VENDIDOS NO MÊS');
$sheet->getStyle('A13')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('00704A');

$headersRank = ['A14' => 'Produto', 'B14' => 'Categoria', 'C14' => 'Qtd Vendida', 'D14' => 'Faturamento Bruto (R$)'];
foreach ($headersRank as $cell => $text) {
    $sheet->setCellValue($cell, $text);
}
$sheet->getStyle('A14:D14')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A14:D14')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00704A');

$r = 15;
foreach ($topProdutos as $tp) {
    $sheet->setCellValue('A' . $r, $tp['nomeProduto']);
    $sheet->setCellValue('B' . $r, $tp['categoria'] ?: 'Geral');
    $sheet->setCellValue('C' . $r, (int)$tp['totalVendido']);
    $sheet->setCellValue('D' . $r, (float)$tp['totalFaturado']);

    $sheet->getStyle('C' . $r)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('R$ #,##0.00');

    if ($r % 2 === 0) {
        $sheet->getStyle('A' . $r . ':D' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
    }
    $r++;
}

if ($r > 15) {
    $sheet->getStyle('A14:D' . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
    $sheet->setCellValue('B' . $r, 'TOTAL VENDAS:');
    $sheet->getStyle('B' . $r)->getFont()->setBold(true);
    $sheet->setCellValue('C' . $r, '=SUM(C15:C' . ($r - 1) . ')');
    $sheet->setCellValue('D' . $r, '=SUM(D15:D' . ($r - 1) . ')');
    $sheet->getStyle('C' . $r)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('R$ #,##0.00');
    $sheet->getStyle('C' . $r . ':D' . $r)->getFont()->setBold(true)->getColor()->setRGB('00704A');
}

foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$nomeArquivo = "balanco_mensal_" . str_replace('-', '_', $mesRef) . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
