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

$idProduto = isset($_GET['idProduto']) ? (int)$_GET['idProduto'] : 0;
$dataInicio = !empty($_GET['dataInicio']) ? $_GET['dataInicio'] : '';
$dataFim = !empty($_GET['dataFim']) ? $_GET['dataFim'] : '';

// Busca dados do produto (se filtrado)
$produtoFiltro = null;
if ($idProduto > 0) {
    $stmtP = mysqli_prepare($conexao, "SELECT * FROM produto WHERE idProduto = ? AND idUsuario = ?");
    mysqli_stmt_bind_param($stmtP, "ii", $idProduto, $idUsuario);
    mysqli_stmt_execute($stmtP);
    $produtoFiltro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP));
    mysqli_stmt_close($stmtP);
}

// Monta query do histórico
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
        p.nomeProduto, p.categoria, p.preco,
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

// Cálculos de totais
$totalEntradasQtd = 0;
$totalSaidasQtd = 0;
$totalValorEntradas = 0;
$totalValorSaidas = 0;

$linhasTabela = '';
$saldoAcumulado = 0;

foreach ($movimentos as $m) {
    $qtd = (int)$m['quantidade'];
    $val = (float)$m['valorTotal'];
    $dataMov = date('d/m/Y H:i', strtotime($m['dataMovimento']));
    $tipo = $m['tipoMovimento'];
    $isDevolvido = !empty($m['dataDevolucao']);
    $doc = htmlspecialchars($m['codigoPedido'] ?: ('MOV-' . $m['idMovimento']));
    $prodNome = htmlspecialchars($m['nomeProduto']);
    $parceiro = htmlspecialchars($m['nomeCliente'] ?: ($m['nomeFornecedor'] ?: 'Interno'));
    $obs = htmlspecialchars($m['observacao'] ?: '-');

    if ($tipo === 'ENTRADA') {
        $totalEntradasQtd += $qtd;
        $totalValorEntradas += $val;
        $saldoAcumulado += $qtd;
        $tipoHtml = "<span style='color: #047857; font-weight: bold;'>ENTRADA</span>";
        $qtdCol = "<span style='color: #047857;'>+{$qtd}</span>";
    } else {
        if (!$isDevolvido) {
            $totalSaidasQtd += $qtd;
            $totalValorSaidas += $val;
            $saldoAcumulado -= $qtd;
        }
        $tipoHtml = $isDevolvido 
            ? "<span style='color: #b45309; text-decoration: line-through;'>SAÍDA (Devolvida)</span>" 
            : "<span style='color: #b91c1c; font-weight: bold;'>SAÍDA</span>";
        $qtdCol = "<span style='color: #b91c1c;'>-{$qtd}</span>";
    }

    $linhasTabela .= "
        <tr>
            <td style='font-size: 9px; text-align: center;'>{$dataMov}</td>
            <td style='font-size: 9px; font-weight: bold;'>{$doc}</td>
            <td style='font-size: 9px;'>{$prodNome}</td>
            <td style='font-size: 9px; text-align: center;'>{$tipoHtml}</td>
            <td style='font-size: 9px;'>{$parceiro}</td>
            <td style='font-size: 9px; text-align: right; font-weight: bold;'>{$qtdCol}</td>
            <td style='font-size: 9px; text-align: right;'>R$ " . number_format($val, 2, ',', '.') . "</td>
            <td style='font-size: 9px; text-align: right; font-weight: bold; background: #f8fafc;'>{$saldoAcumulado}</td>
            <td style='font-size: 8.5px; color: #475569;'>{$obs}</td>
        </tr>";
}

$emissaoData = date('d/m/Y \à\s H:i:s');
$filtroTexto = $produtoFiltro ? "Produto: " . htmlspecialchars($produtoFiltro['nomeProduto']) : "Todos os Produtos da Empresa";
$periodoTexto = ($dataInicio || $dataFim) ? ("De " . ($dataInicio ? date('d/m/Y', strtotime($dataInicio)) : 'Início') . " até " . ($dataFim ? date('d/m/Y', strtotime($dataFim)) : 'Atual')) : "Todo o Histórico";

$html = "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
<meta charset='UTF-8'>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; }
    .header-box { border-bottom: 2px solid #00704A; padding-bottom: 8px; margin-bottom: 12px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .company-title { font-size: 16px; font-weight: bold; color: #00704A; margin: 0; }
    .doc-title { font-size: 13px; font-weight: bold; color: #0f172a; margin: 3px 0 0 0; text-transform: uppercase; }
    .meta-text { font-size: 9px; color: #64748b; }
    
    .filter-box { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; margin-bottom: 12px; font-size: 9px; }
    
    table.data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
    table.data-table th { background-color: #00704A; color: #ffffff; font-size: 9px; text-transform: uppercase; padding: 6px 4px; border: 1px solid #005035; text-align: left; }
    table.data-table td { padding: 4px 5px; border: 1px solid #e2e8f0; }
    table.data-table tr:nth-child(even) { background-color: #f8fafc; }
    
    .summary-box { width: 100%; margin-top: 14px; border-collapse: collapse; }
    .summary-box td { padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 10px; }
    .footer { position: fixed; bottom: 0px; left: 0px; right: 0px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
</style>
</head>
<body>

<div class='header-box'>
    <table class='header-table'>
        <tr>
            <td style='width: 70%;'>
                <div class='company-title'>MVM CENTRAL DE DISTRIBUIÇÃO & ESTOQUE</div>
                <div class='doc-title'>LIVRO OFICIAL DE REGISTRO KARDEX</div>
                <div class='meta-text'>CNPJ: 12.345.678/0001-90 | Controle Contábil e Físico de Movimentações</div>
            </td>
            <td style='width: 30%; text-align: right;'>
                <div class='meta-text'><b>Emissão:</b> {$emissaoData}</div>
                <div class='meta-text'><b>Operador:</b> " . htmlspecialchars($_SESSION['usuarioLogado']) . "</div>
                <div class='meta-text'><b>Autenticação:</b> " . strtoupper(substr(md5($emissaoData . $idUsuario), 0, 12)) . "</div>
            </td>
        </tr>
    </table>
</div>

<div class='filter-box'>
    <b>Parâmetros do Livro:</b> {$filtroTexto} &nbsp;|&nbsp; <b>Período:</b> {$periodoTexto} &nbsp;|&nbsp; <b>Total Registros:</b> " . count($movimentos) . "
</div>

<table class='data-table'>
    <thead>
        <tr>
            <th style='width: 11%; text-align: center;'>Data/Hora</th>
            <th style='width: 12%;'>Documento</th>
            <th style='width: 17%;'>Produto</th>
            <th style='width: 9%; text-align: center;'>Tipo</th>
            <th style='width: 14%;'>Origem/Destino</th>
            <th style='width: 8%; text-align: right;'>Qtd</th>
            <th style='width: 11%; text-align: right;'>Valor Total</th>
            <th style='width: 8%; text-align: right;'>Saldo</th>
            <th style='width: 10%;'>Observação</th>
        </tr>
    </thead>
    <tbody>
        {$linhasTabela}
    </tbody>
</table>

<table class='summary-box'>
    <tr style='background: #f1f5f9; font-weight: bold;'>
        <td style='width: 25%; color: #047857;'>Total Entradas: {$totalEntradasQtd} un</td>
        <td style='width: 25%; color: #047857;'>Valor Entradas: R$ " . number_format($totalValorEntradas, 2, ',', '.') . "</td>
        <td style='width: 25%; color: #b91c1c;'>Total Saídas: {$totalSaidasQtd} un</td>
        <td style='width: 25%; color: #b91c1c;'>Receita Saídas: R$ " . number_format($totalValorSaidas, 2, ',', '.') . "</td>
    </tr>
</table>

<div class='footer'>
    MVM Estoque - Documento emitido eletronicamente para fins de controle e auditoria fiscal interna.
</div>

</body>
</html>";

mysqli_close($conexao);

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$nomeArquivo = "livro_kardex_" . date('Ymd_His') . ".pdf";
$dompdf->stream($nomeArquivo, ["Attachment" => false]);
