<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/conexao.php";
require_once "config/upload.php";

// Verificação de autenticação e proteção contra CSRF
if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Ação de exclusão permitida apenas via formulário seguro (POST).'];
    header('Location: produto.php');
    exit;
}

if (!validarTokenCSRF()) {
    $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Ação não autorizada ou token inválido.'];
    header('Location: produto.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$idUsuario = obterIdUsuarioLogado();

if ($id > 0) {
    $conexao = conectar();

    $sqlBusca = "SELECT imagem FROM produto WHERE idProduto = ? AND idUsuario = ?";
    $stmtBusca = mysqli_prepare($conexao, $sqlBusca);
    mysqli_stmt_bind_param($stmtBusca, "ii", $id, $idUsuario);
    mysqli_stmt_execute($stmtBusca);
    $resultadoBusca = mysqli_stmt_get_result($stmtBusca);
    $produtoAtual = mysqli_fetch_assoc($resultadoBusca);

    if ($produtoAtual) {
        $sql = "DELETE FROM produto WHERE idProduto = ? AND idUsuario = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $id, $idUsuario);
        
        if (mysqli_stmt_execute($stmt)) {
            if (!empty($produtoAtual['imagem'])) {
                excluirImagemProduto($produtoAtual['imagem']);
            }
            $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => 'Produto excluído com sucesso!'];
        } else {
            $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível excluir o produto (pode haver movimentos vinculados a ele).'];
        }
    } else {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Produto não encontrado ou permissão negada.'];
    }
    
    mysqli_close($conexao);
}

header('Location: produto.php');
exit;
?>