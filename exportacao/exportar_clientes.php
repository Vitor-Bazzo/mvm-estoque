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
$camposValidos = ['nomeCliente', 'email', 'cidade', 'uf'];
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$campo = isset($_GET['campo']) && in_array($_GET['campo'], $camposValidos) ? $_GET['campo'] : 'nomeCliente';

if ($busca !== '') {
    $sql = "SELECT * FROM cliente WHERE idUsuario = ? AND $campo LIKE ? ORDER BY nomeCliente ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    $termo_busca = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, 'is', $idUsuario, $termo_busca);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {    
    $sql = "SELECT * FROM cliente WHERE idUsuario = ? ORDER BY nomeCliente ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt); 
}

$nome_arquivo = "relatorio_clientes_" . date('Y-m-d_H-i') . ".xls";

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
        th { background-color: #107c41; color: #ffffff; font-weight: bold; text-align: left; padding: 10px; border: 1px solid #d9d9d9; }
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
                <th>Nome do Cliente</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Endereço</th>
                <th>Cidade</th>
                <th class="text-center">UF</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && mysqli_num_rows($resultado) > 0): ?>
                <?php while ($cliente = mysqli_fetch_assoc($resultado)): ?>
                    <tr>
                        <td class="text-center"><?= (int)$cliente['idCliente'] ?></td>
                        <td><?= htmlspecialchars($cliente['nomeCliente']) ?></td>
                        <td><?= htmlspecialchars($cliente['email'] ?? '') ?></td>
                        <td class="text-format"><?= htmlspecialchars($cliente['telefone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($cliente['endereco'] ?? '') ?></td>
                        <td><?= htmlspecialchars($cliente['cidade'] ?? '') ?></td>
                        <td class="text-center"><?= htmlspecialchars($cliente['uf'] ?? '') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #777; padding: 15px;">Nenhum cliente encontrado.</td>
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