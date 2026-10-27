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

use Dompdf\Dompdf;
use Dompdf\Options;

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

$categoria = !empty($_GET['categoria']) ? trim($_GET['categoria']) : '';
$ordem = !empty($_GET['ordem']) ? trim($_GET['ordem']) : 'localizacao';

$where = "WHERE idUsuario = ?";
$params = [$idUsuario];
$types = "i";

if (!empty($categoria)) {
    $where .= " AND categoria = ?";
    $params[] = $categoria;
    $types .= "s";
}

$orderBy = ($ordem === 'nome') ? 'nomeProduto ASC' : 'localizacao ASC, nomeProduto ASC';

$sql = "
    SELECT idProduto, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, localizacao, lote, dataValidade, nomeFornecedor
    FROM produto
    {$where}
    ORDER BY {$orderBy}
";

$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$produtos = mysqli_fetch_all($res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
mysqli_close($conexao);

$linhas = '';
$totalItensSistema = 0;
$totalCustoEstoque = 0;

foreach ($produtos as $p) {
    $id = (int)$p['idProduto'];
    $nome = htmlspecialchars($p['nomeProduto']);
    $cat = htmlspecialchars($p['categoria'] ?: 'Geral');
    $loc = htmlspecialchars($p['localizacao'] ?: 'A-01-01');
    $lote = htmlspecialchars($p['lote'] ?: 'S/L');
    $val = (!empty($p['dataValidade']) && $p['dataValidade'] !== '0000-00-00') ? date('d/m/Y', strtotime($p['dataValidade'])) : '-';
    $qtd = (int)$p['quantidade'];
    $custo = (float)$p['precoCusto'];
    
    $totalItensSistema += $qtd;
    $totalCustoEstoque += ($qtd * $custo);

    $linhas .= "
        <tr>
            <td style='font-size: 8.5px; text-align: center; font-weight: bold;'>#{$id}</td>
            <td style='font-size: 9px; font-weight: bold; color: #00704A; text-align: center;'>{$loc}</td>
            <td style='font-size: 9px;'><b>{$nome}</b><br><small style='color: #64748b;'>{$cat}</small></td>
            <td style='font-size: 8.5px; text-align: center;'>{$lote}</td>
            <td style='font-size: 8.5px; text-align: center;'>{$val}</td>
            <td style='font-size: 9.5px; text-align: right; font-weight: bold; background: #f8fafc;'>{$qtd}</td>
            <td style='border-left: 2px solid #94a3b8; width: 14%;'>&nbsp;</td>
            <td style='width: 14%;'>&nbsp;</td>
            <td style='font-size: 8px; color: #94a3b8;'>&nbsp;</td>
        </tr>";
}

$emissaoData = date('d/m/Y \à\s H:i');

$html = "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
<meta charset='UTF-8'>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #0f172a; margin: 0; padding: 0; }
    .header-box { border-bottom: 2px solid #00704A; padding-bottom: 8px; margin-bottom: 10px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .company-title { font-size: 15px; font-weight: bold; color: #00704A; margin: 0; }
    .doc-title { font-size: 12px; font-weight: bold; color: #0f172a; margin: 2px 0 0 0; }
    .meta-text { font-size: 8.5px; color: #64748b; }
    
    .instruction-box { background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 4px; padding: 6px 10px; margin-bottom: 10px; font-size: 8.5px; }
    
    table.inventory-table { width: 100%; border-collapse: collapse; }
    table.inventory-table th { background-color: #00704A; color: #ffffff; font-size: 8.5px; padding: 5px 3px; border: 1px solid #005035; text-align: left; }
    table.inventory-table td { padding: 4px; border: 1px solid #cbd5e1; height: 18px; }
    table.inventory-table tr:nth-child(even) td { background-color: #fbfcfd; }
    
    .signature-box { width: 100%; margin-top: 25px; border-collapse: collapse; }
    .signature-line { border-top: 1px solid #334155; text-align: center; padding-top: 4px; font-size: 8.5px; }
</style>
</head>
<body>

<div class='header-box'>
    <table class='header-table'>
        <tr>
            <td style='width: 65%;'>
                <div class='company-title'>MVM CENTRAL DE DISTRIBUIÇÃO & LOGÍSTICA</div>
                <div class='doc-title'>FOLHA OFICIAL DE INVENTÁRIO FÍSICO DE ESTOQUE</div>
                <div class='meta-text'>Auditoria de Saldos, Validades e Rastreabilidade de Lotes</div>
            </td>
            <td style='width: 35%; text-align: right;'>
                <div class='meta-text'><b>Data/Hora Emissão:</b> {$emissaoData}</div>
                <div class='meta-text'><b>Responsável:</b> " . htmlspecialchars($_SESSION['usuarioLogado']) . "</div>
                <div class='meta-text'><b>Total de Itens Cadastrados:</b> " . count($produtos) . " produtos</div>
            </td>
        </tr>
    </table>
</div>

<div class='instruction-box'>
    <b>Instruções para a Equipe de Contagem:</b> 
    1. Preencha a <b>1ª Contagem</b> com a quantidade física encontrada na prateleira. 
    2. Em caso de divergência com o Saldo do Sistema, realizar a <b>2ª Contagem</b> com um segundo conferente.
    3. Anote no campo <b>Visto / Avarias</b> qualquer produto com embalagem danificada ou validade vencida.
</div>

<table class='inventory-table'>
    <thead>
        <tr>
            <th style='width: 6%; text-align: center;'>ID</th>
            <th style='width: 10%; text-align: center;'>Endereço/Box</th>
            <th style='width: 28%;'>Descrição do Produto / Categoria</th>
            <th style='width: 9%; text-align: center;'>Lote</th>
            <th style='width: 9%; text-align: center;'>Validade</th>
            <th style='width: 8%; text-align: right;'>Sistema</th>
            <th style='width: 15%; text-align: center; background: #005035;'>1ª Contagem</th>
            <th style='width: 15%; text-align: center; background: #005035;'>2ª Contagem</th>
            <th style='width: 10%; text-align: center;'>Visto / Obs</th>
        </tr>
    </thead>
    <tbody>
        {$linhas}
    </tbody>
</table>

<table class='signature-box'>
    <tr>
        <td style='width: 30%;'>
            <div class='signature-line'>Conferente 1 (Assinatura / Matrícula)</div>
        </td>
        <td style='width: 5%;'></td>
        <td style='width: 30%;'>
            <div class='signature-line'>Conferente 2 / Revisor</div>
        </td>
        <td style='width: 5%;'></td>
        <td style='width: 30%;'>
            <div class='signature-line'>Gestor de Estoque / Visto Final</div>
        </td>
    </tr>
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

$nomeArquivo = "folha_inventario_fisico_" . date('Ymd_His') . ".pdf";
$dompdf->stream($nomeArquivo, ["Attachment" => false]);
