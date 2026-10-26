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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

$idProduto = isset($_GET['idProduto']) ? (int)$_GET['idProduto'] : 0;
$dataInicio = !empty($_GET['dataInicio']) ? $_GET['dataInicio'] : '';
$dataFim = !empty($_GET['dataFim']) ? $_GET['dataFim'] : '';

// Monta query
$params = [$idUsuario];
$types = "i";
$where = "m.idUsuario = ?";

if ($idProduto > 0) {
    $where .= " AND m.idProduto = ?";
    $params[] = $idProduto;
    $types .= "i";
}
if (!empty($dataInicio)) {
    $where .= " AND m.dataMovimento >= ?";
    $params[] = $dataInicio . " 00:00:00";
    $types .= "s";
}
if (!empty($dataFim)) {
    $where .= " AND m.dataMovimento <= ?";
    $params[] = $dataFim . " 23:59:59";
    $types .= "s";
}

$sql = "
    SELECT 
        m.idMovimento, m.codigoPedido, m.tipoMovimento, m.quantidade, m.observacao,
        m.dataMovimento, m.dataDevolucao, m.valorTotal, m.lote, m.dataValidade,
        p.nomeProduto, p.categoria,
        c.nomeCliente, f.nomeFornecedor
    FROM movimento m
    INNER JOIN produto p ON p.idProduto = m.idProduto
    LEFT JOIN cliente c ON c.idCliente = m.idCliente
    LEFT JOIN fornecedor f ON f.idFornecedor = m.idFornecedor
    WHERE {$where}
    ORDER BY m.dataMovimento ASC, m.idMovimento ASC
";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$movimentos = mysqli_fetch_all($res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
mysqli_close($conexao);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Livro Kardex MVM');

// Cabeçalho da Empresa
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', 'MVM CENTRAL DE DISTRIBUIÇÃO & ESTOQUE LTDA');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('00704A');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$sheet->mergeCells('A2:I2');
$sheet->setCellValue('A2', 'LIVRO OFICIAL KARDEX - CONTROLE DE MOVIMENTAÇÃO DE ESTOQUE | Emitido em: ' . date('d/m/Y H:i'));
$sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('475569');

// Linha de Cabeçalhos da Tabela
$headers = [
    'A4' => 'Data / Hora',
    'B4' => 'Documento / Pedido',
    'C4' => 'Produto',
    'D4' => 'Tipo',
    'E4' => 'Cliente / Fornecedor',
    'F4' => 'Qtd Movimentada',
    'G4' => 'Valor Total (R$)',
    'H4' => 'Saldo Físico',
    'I4' => 'Observações'
];

foreach ($headers as $cell => $text) {
    $sheet->setCellValue($cell, $text);
}

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00704A']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '005035']]]
];
$sheet->getStyle('A4:I4')->applyFromArray($headerStyle);
$sheet->getRowDimension(4)->setRowHeight(24);

$row = 5;
$saldo = 0;

foreach ($movimentos as $m) {
    $qtd = (int)$m['quantidade'];
    $val = (float)$m['valorTotal'];
    $tipo = $m['tipoMovimento'];
    $isDev = !empty($m['dataDevolucao']);
    $dataMov = date('d/m/Y H:i', strtotime($m['dataMovimento']));
    $doc = $m['codigoPedido'] ?: ('MOV-' . $m['idMovimento']);
    $parceiro = $m['nomeCliente'] ?: ($m['nomeFornecedor'] ?: 'Interno');

    if ($tipo === 'ENTRADA') {
        $saldo += $qtd;
        $qtdFormatada = $qtd;
    } else {
        if (!$isDev) {
            $saldo -= $qtd;
        }
        $qtdFormatada = -$qtd;
    }

    $sheet->setCellValue('A' . $row, $dataMov);
    $sheet->setCellValue('B' . $row, $doc);
    $sheet->setCellValue('C' . $row, $m['nomeProduto']);
    $sheet->setCellValue('D' . $row, $isDev ? 'SAÍDA (Devolvida)' : $tipo);
    $sheet->setCellValue('E' . $row, $parceiro);
    $sheet->setCellValue('F' . $row, $qtdFormatada);
    $sheet->setCellValue('G' . $row, $val);
    $sheet->setCellValue('H' . $row, $saldo);
    $sheet->setCellValue('I' . $row, $m['observacao'] ?: '-');

    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('R$ #,##0.00');
    $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0');

    // Linhas zebradas
    if ($row % 2 === 0) {
        $sheet->getStyle('A' . $row . ':I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
    }

    $row++;
}

// Borda nas células preenchidas
$sheet->getStyle('A4:I' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

// Linha de Totais
$sheet->setCellValue('E' . $row, 'TOTAL FINANCEIRO:');
$sheet->getStyle('E' . $row)->getFont()->setBold(true);
$sheet->setCellValue('G' . $row, "=SUM(G5:G" . ($row - 1) . ")");
$sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('R$ #,##0.00');
$sheet->getStyle('G' . $row)->getFont()->setBold(true)->getColor()->setRGB('00704A');

// Ajuste automático de largura de colunas
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$nomeArquivo = "livro_kardex_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
