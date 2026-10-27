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

$categoria = !empty($_GET['categoria']) ? trim($_GET['categoria']) : '';
$where = "WHERE idUsuario = ?";
$params = [$idUsuario];
$types = "i";

if (!empty($categoria)) {
    $where .= " AND categoria = ?";
    $params[] = $categoria;
    $types .= "s";
}

$sql = "
    SELECT idProduto, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, localizacao, lote, dataValidade, nomeFornecedor
    FROM produto
    {$where}
    ORDER BY localizacao ASC, nomeProduto ASC
";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$produtos = mysqli_fetch_all($res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
mysqli_close($conexao);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Inventário Físico');

// Título Oficial
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A1', 'MVM CENTRAL DE DISTRIBUIÇÃO & LOGÍSTICA - PLANILHA DE CONFERÊNCIA DE ESTOQUE');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('00704A');

$sheet->mergeCells('A2:J2');
$sheet->setCellValue('A2', 'Auditoria de Estoque Físico x Sistema | Gerado em: ' . date('d/m/Y H:i') . ' | Operador: ' . $_SESSION['usuarioLogado']);
$sheet->getStyle('A2')->getFont()->setSize(9.5)->getColor()->setRGB('475569');

$headers = [
    'A4' => 'ID',
    'B4' => 'Endereço / Box',
    'C4' => 'Nome do Produto',
    'D4' => 'Categoria',
    'E4' => 'Lote',
    'F4' => 'Validade',
    'G4' => 'Qtd Sistema',
    'H4' => '1ª Contagem Física',
    'I4' => '2ª Contagem (Revisão)',
    'J4' => 'Divergência (Física - Sistema)'
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
$sheet->getStyle('A4:J4')->applyFromArray($headerStyle);
$sheet->getRowDimension(4)->setRowHeight(24);

$row = 5;
foreach ($produtos as $p) {
    $val = (!empty($p['dataValidade']) && $p['dataValidade'] !== '0000-00-00') ? date('d/m/Y', strtotime($p['dataValidade'])) : '-';

    $sheet->setCellValue('A' . $row, $p['idProduto']);
    $sheet->setCellValue('B' . $row, $p['localizacao'] ?: 'A-01-01');
    $sheet->setCellValue('C' . $row, $p['nomeProduto']);
    $sheet->setCellValue('D' . $row, $p['categoria'] ?: 'Geral');
    $sheet->setCellValue('E' . $row, $p['lote'] ?: 'S/L');
    $sheet->setCellValue('F' . $row, $val);
    $sheet->setCellValue('G' . $row, (int)$p['quantidade']);
    $sheet->setCellValue('H' . $row, ''); // Em branco para preenchimento físico
    $sheet->setCellValue('I' . $row, ''); // Em branco para revisão
    $sheet->setCellValue('J' . $row, "=IF(H{$row}<>\"\", H{$row}-G{$row}, \"\")"); // Fórmula de divergência automática

    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode('#,##0');

    // Destaque para colunas de contagem manual
    $sheet->getStyle('H' . $row . ':I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFBEB');

    $row++;
}

$sheet->getStyle('A4:J' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

// Totais
$sheet->setCellValue('F' . $row, 'TOTAL SISTEMA:');
$sheet->getStyle('F' . $row)->getFont()->setBold(true);
$sheet->setCellValue('G' . $row, "=SUM(G5:G" . ($row - 1) . ")");
$sheet->getStyle('G' . $row)->getFont()->setBold(true)->getColor()->setRGB('00704A');

foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$nomeArquivo = "inventario_contagem_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
