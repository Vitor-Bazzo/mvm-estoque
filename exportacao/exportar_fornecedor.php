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
$camposValidos = ['nomeFornecedor', 'cidade', 'uf', 'cnpj', 'segmento'];
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$campo = isset($_GET['campo']) && in_array($_GET['campo'], $camposValidos) ? $_GET['campo'] : 'nomeFornecedor';

if ($busca !== '') {
    $sql = "SELECT * FROM fornecedor WHERE idUsuario = ? AND $campo LIKE ? ORDER BY nomeFornecedor ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    $termo_busca = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, 'is', $idUsuario, $termo_busca);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {    
    $sql = "SELECT * FROM fornecedor WHERE idUsuario = ? ORDER BY nomeFornecedor ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt); 
}

$nome_arquivo = "relatorio_fornecedores_" . date('Y-m-d_H-i') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nome_arquivo\"");
header("Cache-Control: max-age=0");
echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: 'Segoe UI', Calibri, Arial, sans-serif; font-size: 11pt; }
        th { background-color: #217346; color: #ffffff; font-weight: bold; text-align: left; padding: 10px; border: 1px solid #d9d9d9; }
        td { padding: 8px; border: 1px solid #e1e1e1; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f9fbf9; }
        .text-format { mso-number-format: "\@"; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 50px;">ID</th>
                <th>Nome do Fornecedor</th>
                <th>CNPJ</th>
                <th>Segmento</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>Cidade</th>
                <th class="text-center">UF</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && mysqli_num_rows($resultado) > 0): ?>
                <?php while ($fornecedor = mysqli_fetch_assoc($resultado)): ?>
                    <tr>
                        <td class="text-center"><?= (int)$fornecedor['idFornecedor'] ?></td>
                        <td><?= htmlspecialchars($fornecedor['nomeFornecedor']) ?></td>
                        <td class="text-format"><?= htmlspecialchars($fornecedor['cnpj'] ?? '') ?></td>
                        <td><?= htmlspecialchars($fornecedor['segmento'] ?? '') ?></td>
                        <td><?= htmlspecialchars($fornecedor['email'] ?? '') ?></td>
                        <td class="text-format"><?= htmlspecialchars($fornecedor['telefone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($fornecedor['endereco'] ?? '') ?></td>
                        <td><?= htmlspecialchars($fornecedor['cidade'] ?? '') ?></td>
                        <td class="text-center"><?= htmlspecialchars($fornecedor['uf'] ?? '') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: #777; padding: 15px;">Nenhum fornecedor encontrado.</td>
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