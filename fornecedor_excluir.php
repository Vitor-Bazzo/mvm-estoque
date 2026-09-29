<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/conexao.php";

// Verificação de autenticação e proteção contra CSRF
if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Ação de exclusão permitida apenas via formulário seguro (POST).'];
    header('Location: fornecedor.php');
    exit;
}

if (!validarTokenCSRF()) {
    $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Ação não autorizada ou token inválido.'];
    header('Location: fornecedor.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$idUsuario = obterIdUsuarioLogado();

if ($id > 0) {
    $conexao = conectar();
    $sql = "DELETE FROM fornecedor WHERE idFornecedor = ? AND idUsuario = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $idUsuario);
    
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Fornecedor excluído com sucesso!'];
    } else {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível excluir o fornecedor ou permissão negada.'];
    }
    mysqli_close($conexao);
}

header('Location: fornecedor.php');
exit;
?>