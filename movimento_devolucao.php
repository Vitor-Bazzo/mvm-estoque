<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/conexao.php";

// Verificação de usuário logado e CSRF
if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem_movimento'] = ['erro', 'Ação permitida apenas via formulário seguro (POST).'];
    header('Location: movimento.php');
    exit;
}

if (!validarTokenCSRF()) {
    $_SESSION['mensagem_movimento'] = ['erro', 'Ação não autorizada ou token inválido.'];
    header('Location: movimento.php');
    exit;
}

$idMovimento = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($idMovimento > 0) {

    $conexao = conectar();
    $idUsuario = obterIdUsuarioLogado();
    mysqli_begin_transaction($conexao);

    // Trecho de busca e validação do movimento do usuário
    $sqlMovimento = "SELECT idProduto, quantidade, tipoMovimento, dataDevolucao FROM movimento WHERE idMovimento = ? AND idUsuario = ?";
    $stmtMovimento = mysqli_prepare($conexao, $sqlMovimento);
    mysqli_stmt_bind_param($stmtMovimento, "ii", $idMovimento, $idUsuario);
    mysqli_stmt_execute($stmtMovimento);
    $resultadoMovimento = mysqli_stmt_get_result($stmtMovimento);
    $movimento = mysqli_fetch_assoc($resultadoMovimento);

    // Só prossegue se o movimento existir e ainda não tiver sido devolvido
    if ($movimento && $movimento['dataDevolucao'] == NULL) {
        $idProduto = $movimento['idProduto'];
        $quantidadeDevolvida = $movimento['quantidade'];
        $tipoMovimento = $movimento['tipoMovimento'];

        // Trecho de busca do produto para obter o nome e o estoque atual
        $sqlProduto = "SELECT nomeProduto, quantidade FROM produto WHERE idProduto = ? AND idUsuario = ?";
        $stmtProduto = mysqli_prepare($conexao, $sqlProduto);
        mysqli_stmt_bind_param($stmtProduto, "ii", $idProduto, $idUsuario);
        mysqli_stmt_execute($stmtProduto);
        $resultadoProduto = mysqli_stmt_get_result($stmtProduto);
        $produto = mysqli_fetch_assoc($resultadoProduto);

        if ($produto) {
            $estoqueValido = true;

            // LÓGICA DE ATUALIZAÇÃO DE ESTOQUE:
            if ($tipoMovimento == 'SAIDA') {
                // Devolução de venda: Produto entra de volta no estoque (carregado!)
                $novaquantidade = $produto['quantidade'] + $quantidadeDevolvida;
            } elseif ($tipoMovimento == 'ENTRADA') {
                // Devolução de compra (Fornecedor): Produto sai do estoque
                $novaquantidade = $produto['quantidade'] - $quantidadeDevolvida;
                
                // Validação de segurança: Não permite o estoque ficar negativo
                if ($novaquantidade < 0) {
                    $estoqueValido = false;
                }
            }

            // Só atualiza o banco se a operação de estoque for permitida
            if ($estoqueValido) {
                // Atualização de estoque do produto
                $sqlUpdProduto = "UPDATE produto SET quantidade = ? WHERE idProduto = ? AND idUsuario = ?";
                $stmtUpdProduto = mysqli_prepare($conexao, $sqlUpdProduto);
                mysqli_stmt_bind_param($stmtUpdProduto, "iii", $novaquantidade, $idProduto, $idUsuario);
                mysqli_stmt_execute($stmtUpdProduto);

                // Atualização da data de devolução no movimento
                $sqlUpdMovimento = "UPDATE movimento SET dataDevolucao = NOW() WHERE idMovimento = ? AND idUsuario = ?";
                $stmtUpdMovimento = mysqli_prepare($conexao, $sqlUpdMovimento);
                mysqli_stmt_bind_param($stmtUpdMovimento, "ii", $idMovimento, $idUsuario);
                mysqli_stmt_execute($stmtUpdMovimento);

                // Dispara a animação do mini-caminhão demonstrando o estoque carregado
                $_SESSION['animacao_caminhao_estoque'] = [
                    'origem'         => 'devolucao',
                    'produto'        => $produto['nomeProduto'] ?? 'Produto',
                    'quantidade'     => $quantidadeDevolvida,
                    'novaQuantidade' => $novaquantidade,
                    'tipo'           => $tipoMovimento,
                    'mensagem'       => "+{$quantidadeDevolvida} un. recarregadas no estoque"
                ];

                $_SESSION['mensagem_movimento'] = [
                    'ok', 
                    "Devolução registrada com sucesso! {$quantidadeDevolvida} un. de '{$produto['nomeProduto']}' retornaram ao estoque (Total: {$novaquantidade} un.)."
                ];
                mysqli_commit($conexao);
            } else {
                mysqli_rollback($conexao);
                $_SESSION['mensagem_movimento'] = [
                    'erro', 
                    "Não foi possível efetuar a devolução: estoque insuficiente para baixa."
                ];
            }
        } else {
            mysqli_rollback($conexao);
        }
    } else {
        mysqli_rollback($conexao);
    }

    mysqli_close($conexao);
}

// Redireciona de volta para a listagem de movimentos
header("Location: movimento.php");
exit;
?>