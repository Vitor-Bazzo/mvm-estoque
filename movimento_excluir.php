<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/conexao.php";

// Verificação de autenticação do usuário e CSRF
if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem_movimento'] = ['erro', 'Ação de exclusão permitida apenas via formulário seguro (POST).'];
    header('Location: movimento.php');
    exit;
}

if (!validarTokenCSRF()) {
    $_SESSION['mensagem_movimento'] = ['erro', 'Ação não autorizada ou token inválido.'];
    header('Location: movimento.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$idUsuario = obterIdUsuarioLogado();

if ($id > 0) {
    $conexao = conectar();
    mysqli_begin_transaction($conexao);

    // Busca dados da movimentação e do produto do próprio usuário
    $sqlBusca = "
        SELECT m.idMovimento, m.idProduto, m.quantidade, m.tipoMovimento, m.dataDevolucao, 
               p.nomeProduto, p.quantidade AS estoqueAtual
        FROM movimento m
        INNER JOIN produto p ON p.idProduto = m.idProduto AND p.idUsuario = m.idUsuario
        WHERE m.idMovimento = ? AND m.idUsuario = ?
    ";
    $stmtBusca = mysqli_prepare($conexao, $sqlBusca);
    mysqli_stmt_bind_param($stmtBusca, "ii", $id, $idUsuario);
    mysqli_stmt_execute($stmtBusca);
    $resBusca = mysqli_stmt_get_result($stmtBusca);
    $mov = mysqli_fetch_assoc($resBusca);

    if ($mov) {
        $novaQuantidade = $mov['estoqueAtual'];

        // Se for exclusão de VENDA (SAÍDA):
        if ($mov['tipoMovimento'] === 'SAIDA') {
            // Se a venda ainda não havia sido devolvida, repõe a mercadoria no estoque
            if ($mov['dataDevolucao'] === null) {
                $novaQuantidade = $mov['estoqueAtual'] + $mov['quantidade'];
                $sqlEstoque = "UPDATE produto SET quantidade = ? WHERE idProduto = ? AND idUsuario = ?";
                $stmtEstoque = mysqli_prepare($conexao, $sqlEstoque);
                mysqli_stmt_bind_param($stmtEstoque, "iii", $novaQuantidade, $mov['idProduto'], $idUsuario);
                mysqli_stmt_execute($stmtEstoque);
            }

            // Dispara a animação do mini-caminhão demonstrando que o estoque foi carregado/reabastecido
            $_SESSION['animacao_caminhao_estoque'] = [
                'origem'         => 'exclusao_venda',
                'produto'        => $mov['nomeProduto'] ?? 'Produto',
                'quantidade'     => $mov['quantidade'],
                'novaQuantidade' => $novaQuantidade,
                'tipo'           => 'SAIDA',
                'mensagem'       => "+{$mov['quantidade']} un. repostas no estoque com a exclusão da venda"
            ];

            $_SESSION['mensagem_movimento'] = [
                'ok', 
                "Venda excluída com sucesso! {$mov['quantidade']} un. de '{$mov['nomeProduto']}' foram reabastecidas no estoque (Total: {$novaQuantidade} un.)."
            ];
        } else {
            $_SESSION['mensagem_movimento'] = [
                'ok', 
                "Registro de movimentação excluído com sucesso."
            ];
        }

        // Exclui o registro da tabela movimento
        $sqlDelete = "DELETE FROM movimento WHERE idMovimento = ? AND idUsuario = ?";
        $stmtDelete = mysqli_prepare($conexao, $sqlDelete);
        mysqli_stmt_bind_param($stmtDelete, "ii", $id, $idUsuario);
        mysqli_stmt_execute($stmtDelete);
        mysqli_commit($conexao);
    } else {
        mysqli_rollback($conexao);
    }

    mysqli_close($conexao);
}

// Redireciona de volta para a listagem de movimentos
header('Location: movimento.php');
exit;
?>