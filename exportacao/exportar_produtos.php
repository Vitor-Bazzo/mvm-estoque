<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/conexao.php";

// Verificação de autenticação
if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: ../login.php');
    exit;
}

$conexao = conectar();

$idUsuario = obterIdUsuarioLogado();

// Whitelist de segurança contra SQL Injection
$camposValidos = ['nomeProduto', 'categoria', 'descricao', 'nomeFornecedor'];
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$campo = isset($_GET['campo']) && in_array($_GET['campo'], $camposValidos) ? $_GET['campo'] : 'nomeProduto'; 

if ($busca !== '') {
    $sql = "SELECT * FROM produto WHERE idUsuario = ? AND $campo LIKE ? ORDER BY nomeProduto ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    $termo_busca = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, 'is', $idUsuario, $termo_busca);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {    
    $sql = "SELECT * FROM produto WHERE idUsuario = ? ORDER BY nomeProduto ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
}

$nome_arquivo = "relatorio_produtos_" . date('Y-m-d_H-i') . ".xls";

// Força o navegador a baixar como planilha
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nome_arquivo\"");
header("Cache-Control: max-age=0");
echo "\xEF\xBB\xBF"; // UTF-8 BOM para garantir acentos corretos no Excel
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
        th { background-color: #217346; color: #ffffff; padding: 10px; border: 1px solid #105230; font-size: 13px; }
        td { padding: 8px; border: 1px solid #d1d5db; font-size: 12px; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .text-center { text-align: center; }
        .number-format { text-align: right; mso-number-format: "\#\,\#\#0\.00"; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th class="text-center">Código/ID</th>
                <th>Nome do Produto</th>
                <th>Categoria</th>
                <th>Fornecedor</th>
                <th style="text-align: right;">Preço (R$)</th>
                <th class="text-center">Estoque</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && mysqli_num_rows($resultado) > 0): ?>
                <?php while ($produto = mysqli_fetch_assoc($resultado)): ?>
                    <tr>
                        <td class="text-center"><?= htmlspecialchars((string)($produto['idProduto'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($produto['nomeProduto'] ?? '') ?></td>
                        <td><?= htmlspecialchars($produto['categoria'] ?? '') ?></td>
                        <td><?= htmlspecialchars($produto['nomeFornecedor'] ?? 'Não informado') ?></td>
                        <td class="number-format"><?= number_format((float)($produto['preco'] ?? 0), 2, ',', '.') ?></td>
                        <td class="text-center"><?= (int)($produto['quantidade'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($produto['descricao'] ?? '') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #777; padding: 15px;">Nenhum produto encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
mysqli_close($conexao);
exit;
?>