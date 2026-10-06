<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificação de autenticação
if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

require_once "config/conexao.php";
require_once "config/email.php";

$idUsuario = obterIdUsuarioLogado();

// =========================================================================
// PROCESSAMENTO DO FORMULÁRIO (POST) - Executa ANTES de qualquer saída HTML
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validarTokenCSRF()) {
        $_SESSION['mensagem_movimento'] = ['erro', 'Sessão expirada ou token de segurança inválido.'];
        header("Location: movimento.php");
        exit;
    }

    $acao = $_POST['acao'] ?? '';
    $conexao = conectar();

    // -------------------------------------------------------------------------
    // AÇÃO 1: VENDA / SAÍDA COM MÚLTIPLOS ITENS (CARRINHO DE COMPRAS)
    // -------------------------------------------------------------------------
    if ($acao === 'carrinho_saida') {
        $idCliente = !empty($_POST['idClienteCarrinho']) ? (int)$_POST['idClienteCarrinho'] : null;
        $observacao = trim($_POST['observacaoCarrinho'] ?? 'Venda Multi-Itens');
        $itensJson = $_POST['itens_carrinho_json'] ?? '[]';
        $itens = json_decode($itensJson, true);

        if (empty($itens) || !is_array($itens)) {
            $_SESSION['mensagem_movimento'] = ['erro', 'O carrinho de vendas está vazio. Adicione pelo menos um produto.'];
            mysqli_close($conexao);
            header("Location: movimento.php");
            exit;
        }

        // Valida cliente contra IDOR
        $clienteSelecionado = null;
        if ($idCliente !== null) {
            $stmtCheckCli = mysqli_prepare($conexao, "SELECT idCliente, nomeCliente, email FROM cliente WHERE idCliente = ? AND idUsuario = ?");
            mysqli_stmt_bind_param($stmtCheckCli, "ii", $idCliente, $idUsuario);
            mysqli_stmt_execute($stmtCheckCli);
            $resCli = mysqli_stmt_get_result($stmtCheckCli);
            $clienteSelecionado = mysqli_fetch_assoc($resCli);
            if (!$clienteSelecionado) {
                $idCliente = null;
            }
        }

        // Gera código único de pedido comercial
        $codigoPedido = 'PED-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        // Inicia transação ACID com isolamento rigoroso
        mysqli_begin_transaction($conexao);
        $totalPedido = 0;
        $totalItensQtd = 0;
        $sucesso = true;
        $mensagemErro = '';
        $primeiroProdutoNome = '';

        foreach ($itens as $item) {
            $idProduto = (int)($item['idProduto'] ?? 0);
            $qtd = (int)($item['quantidade'] ?? 0);

            if ($idProduto <= 0 || $qtd <= 0) {
                continue;
            }

            // Busca produto para conferência de preço e estoque com bloqueio
            $stmtP = mysqli_prepare($conexao, "SELECT nomeProduto, preco, quantidade FROM produto WHERE idProduto = ? AND idUsuario = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmtP, "ii", $idProduto, $idUsuario);
            mysqli_stmt_execute($stmtP);
            $resP = mysqli_stmt_get_result($stmtP);
            $prod = mysqli_fetch_assoc($resP);

            if (!$prod || $prod['quantidade'] < $qtd) {
                $sucesso = false;
                $mensagemErro = "Estoque insuficiente para o produto '" . ($prod['nomeProduto'] ?? "ID $idProduto") . "'. Disponível: " . ($prod['quantidade'] ?? 0) . " un.";
                break;
            }

            if (empty($primeiroProdutoNome)) {
                $primeiroProdutoNome = $prod['nomeProduto'];
            }

            // Atualização atômica de saldo
            $stmtUpd = mysqli_prepare($conexao, "UPDATE produto SET quantidade = quantidade - ? WHERE idProduto = ? AND idUsuario = ? AND quantidade >= ?");
            mysqli_stmt_bind_param($stmtUpd, "iiii", $qtd, $idProduto, $idUsuario, $qtd);
            mysqli_stmt_execute($stmtUpd);

            if (mysqli_stmt_affected_rows($stmtUpd) <= 0) {
                $sucesso = false;
                $mensagemErro = "Falha de concorrência ao baixar estoque de '" . $prod['nomeProduto'] . "'. Tente novamente.";
                break;
            }

            $subtotalItem = round($qtd * (float)$prod['preco'], 2);
            $totalPedido += $subtotalItem;
            $totalItensQtd += $qtd;

            // Insere registro de movimento vinculado ao código do pedido
            $stmtMov = mysqli_prepare($conexao, "INSERT INTO movimento (idUsuario, codigoPedido, idProduto, idCliente, tipoMovimento, quantidade, observacao, valorTotal, dataMovimento) VALUES (?, ?, ?, ?, 'SAIDA', ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmtMov, "isiisds", $idUsuario, $codigoPedido, $idProduto, $idCliente, $qtd, $observacao, $subtotalItem);
            mysqli_stmt_execute($stmtMov);
        }

        if ($sucesso) {
            mysqli_commit($conexao);

            $_SESSION['mensagem_movimento'] = ['ok', "Pedido <strong>{$codigoPedido}</strong> finalizado com sucesso! {$totalItensQtd} itens expedidos no valor de R$ " . number_format($totalPedido, 2, ',', '.') . ". <a href='recibo.php?pedido={$codigoPedido}' target='_blank' style='color:#00704A; font-weight:800; text-decoration:underline;'>Ver Comprovante 🧾</a>"];

            // Animação de venda
            $_SESSION['animacao_aviao_venda'] = [
                'produto'    => (count($itens) > 1) ? "{$primeiroProdutoNome} + " . (count($itens) - 1) . " itens" : $primeiroProdutoNome,
                'quantidade' => $totalItensQtd,
                'total'      => $totalPedido
            ];

            // E-mail opcional
            if ($clienteSelecionado && !empty($clienteSelecionado['email'])) {
                enviarEmailCompra($clienteSelecionado['email'], $clienteSelecionado['nomeCliente'], "Pedido {$codigoPedido} ({$totalItensQtd} itens)", $totalItensQtd, (float)$totalPedido);
            }
        } else {
            mysqli_rollback($conexao);
            $_SESSION['mensagem_movimento'] = ['erro', $mensagemErro ?: 'Erro ao processar o carrinho de vendas.'];
        }

        mysqli_close($conexao);
        header("Location: movimento.php");
        exit;
    }

    // -------------------------------------------------------------------------
    // AÇÃO 2: MOVIMENTO RÁPIDO UNITÁRIO (ENTRADA OU SAÍDA) COM LOTE E VALIDADE
    // -------------------------------------------------------------------------
    elseif ($acao === 'registrar') {
        if (isset($_POST['idProduto'], $_POST['tipoMovimento'], $_POST['quantidade'])) {
            $idProduto = (int)$_POST['idProduto'];
            $idCliente = !empty($_POST['idCliente']) ? (int)$_POST['idCliente'] : null;
            $idFornecedor = !empty($_POST['idFornecedor']) ? (int)$_POST['idFornecedor'] : null;
            $tipoMovimento = in_array($_POST['tipoMovimento'], ['ENTRADA', 'SAIDA']) ? $_POST['tipoMovimento'] : 'SAIDA';
            $quantidade = (int)$_POST['quantidade'];
            $observacao = trim($_POST['observacao'] ?? '');
            $lote = trim($_POST['lote'] ?? '');
            $dataValidade = !empty($_POST['dataValidade']) ? $_POST['dataValidade'] : null;

            if ($quantidade <= 0) {
                $_SESSION['mensagem_movimento'] = ['erro', 'A quantidade deve ser maior que zero.'];
                mysqli_close($conexao);
                header("Location: movimento.php");
                exit;
            }

            // Inicia transação ACID
            mysqli_begin_transaction($conexao);

            // Busca produto
            $stmt = mysqli_prepare($conexao, "SELECT nomeProduto, preco, quantidade, lote, dataValidade FROM produto WHERE idProduto = ? AND idUsuario = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "ii", $idProduto, $idUsuario);
            mysqli_stmt_execute($stmt);
            $produto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$produto) {
                mysqli_rollback($conexao);
                $_SESSION['mensagem_movimento'] = ['erro', 'Produto não encontrado.'];
                mysqli_close($conexao);
                header("Location: movimento.php");
                exit;
            }

            if ($tipoMovimento === 'SAIDA' && $quantidade > $produto['quantidade']) {
                mysqli_rollback($conexao);
                $_SESSION['mensagem_movimento'] = ['erro', "Estoque insuficiente para a baixa. Disponível: {$produto['quantidade']} un."];
                mysqli_close($conexao);
                header("Location: movimento.php");
                exit;
            }

            // Atualização de estoque atômica
            if ($tipoMovimento === 'SAIDA') {
                $stmtUpd = mysqli_prepare($conexao, "UPDATE produto SET quantidade = quantidade - ? WHERE idProduto = ? AND idUsuario = ? AND quantidade >= ?");
                mysqli_stmt_bind_param($stmtUpd, "iiii", $quantidade, $idProduto, $idUsuario, $quantidade);
            } else {
                // Se informou lote/validade na entrada, atualiza também os dados cadastrais do lote
                if (!empty($lote) || !empty($dataValidade)) {
                    $stmtUpd = mysqli_prepare($conexao, "UPDATE produto SET quantidade = quantidade + ?, lote = COALESCE(NULLIF(?, ''), lote), dataValidade = COALESCE(?, dataValidade) WHERE idProduto = ? AND idUsuario = ?");
                    mysqli_stmt_bind_param($stmtUpd, "issii", $quantidade, $lote, $dataValidade, $idProduto, $idUsuario);
                } else {
                    $stmtUpd = mysqli_prepare($conexao, "UPDATE produto SET quantidade = quantidade + ? WHERE idProduto = ? AND idUsuario = ?");
                    mysqli_stmt_bind_param($stmtUpd, "iii", $quantidade, $idProduto, $idUsuario);
                }
            }

            mysqli_stmt_execute($stmtUpd);
            if (mysqli_stmt_affected_rows($stmtUpd) <= 0) {
                mysqli_rollback($conexao);
                $_SESSION['mensagem_movimento'] = ['erro', 'Falha ao atualizar estoque por concorrência. Tente novamente.'];
                mysqli_close($conexao);
                header("Location: movimento.php");
                exit;
            }

            $valorTotal = round($quantidade * (float)($produto['preco'] ?? 0), 2);
            $loteSalvar = !empty($lote) ? $lote : ($produto['lote'] ?? null);
            $validadeSalvar = !empty($dataValidade) ? $dataValidade : ($produto['dataValidade'] ?? null);

            // Inserção da movimentação
            $stmtMov = mysqli_prepare($conexao, "INSERT INTO movimento (idUsuario, idProduto, idCliente, idFornecedor, tipoMovimento, quantidade, observacao, lote, dataValidade, valorTotal, dataMovimento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmtMov, "iiiisssssd", $idUsuario, $idProduto, $idCliente, $idFornecedor, $tipoMovimento, $quantidade, $observacao, $loteSalvar, $validadeSalvar, $valorTotal);
            mysqli_stmt_execute($stmtMov);
            $idMovimentoInserido = mysqli_insert_id($conexao);

            mysqli_commit($conexao);

            $novoSaldo = ($tipoMovimento === 'SAIDA') ? ($produto['quantidade'] - $quantidade) : ($produto['quantidade'] + $quantidade);
            $_SESSION['mensagem_movimento'] = ['ok', "Movimentação registrada com sucesso. Estoque atualizado para {$novoSaldo} un."];

            // Animação de venda se foi saída
            if ($tipoMovimento === "SAIDA") {
                $_SESSION['animacao_aviao_venda'] = [
                    'produto'    => $produto['nomeProduto'] ?? 'Produto',
                    'quantidade' => $quantidade,
                    'total'      => $valorTotal
                ];

                // E-mail para cliente
                if ($idCliente !== null) {
                    $stmtCli = mysqli_prepare($conexao, "SELECT nomeCliente, email FROM cliente WHERE idCliente = ? AND idUsuario = ?");
                    mysqli_stmt_bind_param($stmtCli, "ii", $idCliente, $idUsuario);
                    mysqli_stmt_execute($stmtCli);
                    $cliRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCli));
                    if ($cliRow && !empty($cliRow['email'])) {
                        enviarEmailCompra($cliRow['email'], $cliRow['nomeCliente'], $produto['nomeProduto'], $quantidade, (float)$produto['preco']);
                    }
                }
            }

            mysqli_close($conexao);
            header("Location: movimento.php");
            exit;
        }
    }



    if (is_object($conexao)) {
        mysqli_close($conexao);
    }
    header("Location: movimento.php");
    exit;
}

// =========================================================================
// CONSULTAS PARA RENDERIZAÇÃO DA PÁGINA (GET)
// =========================================================================
$conexao = conectar();

// Lista produtos do usuário com preços, lotes e validade para suporte a FEFO e WMS
$stmtProd = mysqli_prepare($conexao, "SELECT idProduto, nomeProduto, quantidade, preco, precoCusto, categoria, lote, dataValidade, localizacao FROM produto WHERE idUsuario = ? ORDER BY nomeProduto ASC");
mysqli_stmt_bind_param($stmtProd, "i", $idUsuario);
mysqli_stmt_execute($stmtProd);
$resultado = mysqli_stmt_get_result($stmtProd);
$lista_produtos = mysqli_fetch_all($resultado, MYSQLI_ASSOC); 

// Lista clientes do usuário
$stmtCli = mysqli_prepare($conexao, "SELECT idCliente, nomeCliente, email FROM cliente WHERE idUsuario = ? ORDER BY nomeCliente ASC");
mysqli_stmt_bind_param($stmtCli, "i", $idUsuario);
mysqli_stmt_execute($stmtCli);
$resultado = mysqli_stmt_get_result($stmtCli);
$lista_clientes = mysqli_fetch_all($resultado, MYSQLI_ASSOC); 

// Lista fornecedores do usuário
$stmtForn = mysqli_prepare($conexao, "SELECT idFornecedor, nomeFornecedor, segmento FROM fornecedor WHERE idUsuario = ? ORDER BY nomeFornecedor ASC");
mysqli_stmt_bind_param($stmtForn, "i", $idUsuario);
mysqli_stmt_execute($stmtForn);
$resultado = mysqli_stmt_get_result($stmtForn);
$lista_fornecedores = mysqli_fetch_all($resultado, MYSQLI_ASSOC); 

// Lista movimentos do usuário com código de pedido, lote, validade e endereço de armazém
$sqlmovimento = "
SELECT 
    movimento.idMovimento, movimento.codigoPedido, movimento.idCliente, movimento.idFornecedor, movimento.idProduto, 
    movimento.tipoMovimento, movimento.dataMovimento, movimento.dataDevolucao, 
    movimento.observacao, movimento.lote, movimento.dataValidade, movimento.motivoAjuste, movimento.valorTotal,
    movimento.quantidade, produto.nomeProduto, produto.preco, produto.localizacao,
    cliente.nomeCliente, fornecedor.nomeFornecedor
FROM movimento
INNER JOIN produto ON produto.idProduto = movimento.idProduto
LEFT JOIN cliente ON cliente.idCliente = movimento.idCliente
LEFT JOIN fornecedor ON fornecedor.idFornecedor = movimento.idFornecedor
WHERE movimento.idUsuario = ?
ORDER BY dataMovimento DESC, idMovimento DESC
";
$stmtMovList = mysqli_prepare($conexao, $sqlmovimento);
mysqli_stmt_bind_param($stmtMovList, "i", $idUsuario);
mysqli_stmt_execute($stmtMovList);
$resultado = mysqli_stmt_get_result($stmtMovList);
$lista_movimentos = mysqli_fetch_all($resultado, MYSQLI_ASSOC); 

mysqli_close($conexao);

$titulopagina = "MVM - Vendas & Movimentação";
require_once "templates/header.php";
?>

<div class="page-container">
    
    <!-- Alertas Flash -->
    <?php if (!empty($_SESSION['mensagem_movimento'])): ?>
        <div class="alert-box <?= $_SESSION['mensagem_movimento'][0] === 'ok' ? 'success' : 'error' ?>">
            <span><?= $_SESSION['mensagem_movimento'][0] === 'ok' ? '✅' : '⚠️' ?></span>
            <p><?= $_SESSION['mensagem_movimento'][1] ?></p>
        </div>
        <?php unset($_SESSION['mensagem_movimento']); ?>
    <?php endif; ?>

    <!-- Cabeçalho do Módulo de Movimentos -->
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Vendas &amp; Movimentações de Estoque</h1>
            <p>Operações de frente de estoque, vendas com carrinho e entradas de reposição por lote</p>
        </div>
        <div class="page-header-actions">
            <button type="button" class="btn-secondary btn-toggle-animations is-active" title="Ativar ou desativar animações">
                <span class="anim-icon">✨</span>
                <span class="anim-toggle-text">Animações: <strong class="anim-status-label">Ativas</strong></span>
                <span class="anim-switch"><span class="anim-knob"></span></span>
            </button>
            <button type="button" class="btn-primary" data-open-modal="modal-novo-movimento" style="display: inline-flex; align-items: center; gap: 8px;">
                <span>➕</span> Nova Operação / Carrinho
            </button>
        </div>
    </div>

    <!-- Barra de Filtro e Busca Rápida -->
    <div class="table-filter-bar">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Filtrar por produto, pedido, cliente, lote..." data-table-search="#tabela-movimentos">
        </div>

        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;"><?= count($lista_movimentos) ?></span> registros
        </div>
    </div>

    <!-- TABELA DE MOVIMENTAÇÕES -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-movimentos">
                <thead>
                    <tr>
                        <th style="width: 14%;">Data / Hora</th>
                        <th style="width: 13%;">Tipo / Pedido</th>
                        <th style="width: 25%;">Produto</th>
                        <th style="width: 17%;">Cliente / Fornecedor</th>
                        <th style="width: 9%;">Qtd</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 12%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lista_movimentos) > 0): ?>
                        <?php foreach ($lista_movimentos as $m): ?>
                            <?php 
                                $isSaida = $m['tipoMovimento'] === 'SAIDA';
                                $isDevolvido = !empty($m['dataDevolucao']);

                                $tipoClass = $isSaida ? 'info' : 'success';
                                $tipoTexto = $isSaida ? 'Saída' : 'Entrada';
                            ?>
                            <tr>
                                <td>
                                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-muted);">
                                        <?= date('d/m/Y H:i', strtotime($m['dataMovimento'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill <?= $tipoClass ?>">
                                        <?= $tipoTexto ?>
                                    </span>
                                    <?php if (!empty($m['codigoPedido'])): ?>
                                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 700; color: var(--primary); margin-top: 3px;">
                                            <?= htmlspecialchars($m['codigoPedido']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($m['nomeProduto']) ?></strong>
                                    <div style="margin-top: 3px;">
                                        <span style="display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; padding: 1px 6px; background: rgba(99, 102, 241, 0.08); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-weight: 700;" title="Localização no armazém (WMS)">
                                            📍 <?= htmlspecialchars($m['localizacao'] ?? 'A-01-01') ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($m['lote']) || !empty($m['dataValidade'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                            <?= !empty($m['lote']) ? 'Lote: ' . htmlspecialchars($m['lote']) : '' ?>
                                            <?= (!empty($m['lote']) && !empty($m['dataValidade'])) ? ' &bull; ' : '' ?>
                                            <?= !empty($m['dataValidade']) ? 'Val: ' . date('d/m/Y', strtotime($m['dataValidade'])) : '' ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($m['observacao'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                            <?= htmlspecialchars(mb_strimwidth($m['observacao'], 0, 40, '...')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($m['nomeCliente'])): ?>
                                        <span style="font-weight: 600; color: var(--text-main);">👤 <?= htmlspecialchars($m['nomeCliente']) ?></span>
                                    <?php elseif (!empty($m['nomeFornecedor'])): ?>
                                        <span style="font-weight: 600; color: var(--text-main);">🏢 <?= htmlspecialchars($m['nomeFornecedor']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px;">Balcão / Geral</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 13px; font-family: 'JetBrains Mono', monospace;"><?= (int)$m['quantidade'] ?> un</strong>
                                </td>
                                <td>
                                    <?php if ($isDevolvido): ?>
                                        <span class="status-pill warning" title="Devolvido em <?= htmlspecialchars($m['dataDevolucao']) ?>">
                                            Estornado
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill success">
                                            Concluído
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="row-actions" style="justify-content: flex-end; gap: 6px;">
                                        <!-- Ordem de Separação / Picking List (WMS) e Recibo Comercial -->
                                        <?php if (!empty($m['codigoPedido'])): ?>
                                            <a href="recibo.php?pedido=<?= urlencode($m['codigoPedido']) ?>&modo=picking" target="_blank" class="btn-icon" style="color: #2563eb; background: rgba(37, 99, 235, 0.12);" title="Lista de Separação / Picking List (WMS)">
                                                📋
                                            </a>
                                            <a href="recibo.php?pedido=<?= urlencode($m['codigoPedido']) ?>" target="_blank" class="btn-icon" style="color: var(--primary); background: rgba(0, 112, 74, 0.12);" title="Ver Comprovante Comercial (Térmico / A4)">
                                                🧾
                                            </a>
                                        <?php else: ?>
                                            <a href="recibo.php?id=<?= $m['idMovimento'] ?>&modo=picking" target="_blank" class="btn-icon" style="color: #2563eb; background: rgba(37, 99, 235, 0.12);" title="Ordem de Coleta / Picking List (WMS)">
                                                📋
                                            </a>
                                            <a href="recibo.php?id=<?= $m['idMovimento'] ?>" target="_blank" class="btn-icon" style="color: var(--primary); background: rgba(0, 112, 74, 0.12);" title="Ver Recibo da Operação">
                                                🧾
                                            </a>
                                        <?php endif; ?>

                                        <!-- Ficha Kardex -->
                                        <a href="kardex.php?idProduto=<?= (int)$m['idProduto'] ?>" class="btn-icon" title="Ver no Extrato Kardex">
                                            📜
                                        </a>

                                        <!-- Devolução Segura (POST) -->
                                        <?php if (!$isDevolvido): ?>
                                            <form action="movimento_devolucao.php" method="POST" style="display:inline;" onsubmit="return confirm('Confirma a devolução/estorno desta movimentação?');">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="id" value="<?= $m['idMovimento'] ?>">
                                                <button type="submit" class="btn-icon" style="border:none; cursor:pointer;" title="Registrar Devolução / Estorno">
                                                    ↩️
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Exclusão Segura (POST) -->
                                        <form action="movimento_excluir.php" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir permanentemente este registro?');">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id" value="<?= $m['idMovimento'] ?>">
                                            <button type="submit" class="btn-icon delete" style="border:none; cursor:pointer;" title="Excluir Registro">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">↔️</div>
                                <strong>Nenhum movimento registrado</strong>
                                <p style="font-size: 12px; margin-top: 4px;">Utilize o botão acima para registrar vendas em carrinho ou reposições por lote.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Registro seguro e auditado com proteção contra concorrência e saldo negativo</span>
            <span>Exibindo <?= count($lista_movimentos) ?> registros</span>
        </div>
    </div>
</div>

<!-- ESTILOS ESPECÍFICOS DA CENTRAL DE OPERAÇÕES (DRAWER & CARRINHO) -->
<style>
    .drawer-operacoes {
        max-width: 620px !important;
        background: var(--card) !important;
        border-left: 1px solid var(--border) !important;
        display: flex !important;
        flex-direction: column !important;
    }

    /* SEGMENTED CONTROL / SELETOR DE ABAS */
    .tabs-segmented-wrapper {
        display: flex;
        background: var(--bg-subtle);
        padding: 5px;
        border-radius: var(--radius);
        margin: 16px 24px 0 24px;
        border: 1px solid var(--border);
        gap: 4px;
    }
    .tab-seg-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 9px 12px;
        border-radius: var(--radius-sm);
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
    }
    .tab-seg-btn:hover {
        color: var(--text);
        background: rgba(255, 255, 255, 0.04);
    }
    .tab-seg-btn.active {
        background: var(--primary);
        color: #ffffff !important;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
    }

    /* CARD DE ADIÇÃO DE PRODUTO */
    .card-add-box {
        background: var(--bg-subtle);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px 18px;
        margin-bottom: 18px;
    }
    .card-add-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--text);
        display: block;
        margin-bottom: 12px;
    }
    .row-add-inputs {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 12px;
        align-items: flex-end;
    }
    .col-input-qtd label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 5px;
    }
    .input-qtd-custom {
        width: 100% !important;
        height: 42px !important;
        background: var(--card) !important;
        border: 1px solid var(--border) !important;
        border-radius: var(--radius-sm) !important;
        color: var(--text) !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        text-align: center !important;
        outline: none !important;
        box-sizing: border-box !important;
    }
    .input-qtd-custom:focus {
        border-color: var(--border-focus) !important;
        box-shadow: 0 0 0 3px var(--primary-subtle) !important;
    }
    .btn-inserir-carrinho {
        width: 100% !important;
        height: 42px !important;
        background: var(--primary) !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: var(--radius-sm) !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        cursor: pointer !important;
        transition: all 0.18s ease !important;
        box-shadow: 0 2px 6px rgba(99, 102, 241, 0.25) !important;
        box-sizing: border-box !important;
    }
    .btn-inserir-carrinho:hover {
        background: var(--primary-hover) !important;
        transform: translateY(-1px);
    }
    .btn-inserir-carrinho:active {
        transform: scale(0.99);
    }

    /* TABELA DE ITENS NO CARRINHO */
    .carrinho-table-container {
        max-height: 180px;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--card);
        margin-bottom: 18px;
    }
    .carrinho-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }
    .carrinho-table thead th {
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border);
        padding: 8px 12px;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: left;
    }
    .carrinho-table tbody td {
        padding: 9px 12px;
        border-bottom: 1px solid var(--border-subtle);
        color: var(--text);
        vertical-align: middle;
    }
    .carrinho-empty-state {
        text-align: center;
        padding: 24px 16px !important;
        color: var(--text-muted);
        font-size: 12.5px;
    }

    /* CARD DE TOTAL DO PEDIDO */
    .total-venda-card {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(16, 185, 129, 0.15) 100%);
        border: 1px solid rgba(16, 185, 129, 0.35);
        border-radius: var(--radius);
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
    }
    .total-venda-card .label-total {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .total-venda-card .valor-total {
        font-size: 24px;
        font-weight: 800;
        color: #10b981;
        letter-spacing: -0.02em;
        font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
    }

    /* DICA FEFO */
    .fefo-alert-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.3);
        color: #f59e0b;
        font-size: 12px;
        padding: 8px 12px;
        border-radius: var(--radius-sm);
        margin-top: 10px;
        width: 100%;
        box-sizing: border-box;
    }

    /* FOOTER */
    .drawer-footer-actions {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: var(--card);
        position: sticky;
        bottom: 0;
        z-index: 10;
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.12);
    }
    .btn-acao-cancelar {
        padding: 10px 18px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        background: var(--bg-subtle);
        color: var(--text);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-acao-cancelar:hover {
        background: var(--border);
    }
    .btn-acao-confirmar {
        padding: 10px 22px;
        border-radius: var(--radius-sm);
        border: none;
        background: var(--primary);
        color: #ffffff;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
    }
    .btn-acao-confirmar:hover:not(:disabled) {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }
    .btn-acao-confirmar:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        box-shadow: none;
    }
</style>

<!-- GAVETA LATERAL MULTI-ABAS PARA OPERAÇÕES -->
<div id="modal-novo-movimento" class="modal-container">
    <div class="drawer-content drawer-operacoes">
        <div class="drawer-header">
            <div>
                <h2>Central de Operações de Estoque</h2>
                <p>Venda com múltiplos produtos ou reposição de estoque por lote</p>
            </div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>

        <!-- Seletor de Abas Estilo Segmented Control -->
        <div class="tabs-segmented-wrapper">
            <button type="button" class="tab-seg-btn active" onclick="alternarAbaOperacao('carrinho', this)">
                <span>🛒</span> Venda Multi-Itens
            </button>
            <button type="button" class="tab-seg-btn" onclick="alternarAbaOperacao('rapido', this)">
                <span>⚡</span> Movimento Rápido / Lote
            </button>
        </div>

        <!-- ABA 1: CARRINHO DE VENDAS MULTI-ITENS -->
        <div id="aba-carrinho" class="conteudo-aba">
            <form action="movimento.php" method="POST" id="form-carrinho" onsubmit="return validarEnvioCarrinho()">
                <?= campoCSRF() ?>
                <input type="hidden" name="acao" value="carrinho_saida">
                <input type="hidden" name="itens_carrinho_json" id="itens_carrinho_json" value="[]">

                <div class="drawer-body">
                    <div class="form-group">
                        <label for="idClienteCarrinho">Cliente Comprador (Opcional)</label>
                        <select name="idClienteCarrinho" id="idClienteCarrinho">
                            <option value="">-- Venda de Balcão / Consumidor Final --</option>
                            <?php foreach($lista_clientes as $c): ?>
                                <option value="<?= $c['idCliente'] ?>"><?= htmlspecialchars($c['nomeCliente']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Card de Adição de Produtos -->
                    <div class="card-add-box">
                        <span class="card-add-title">➕ Adicionar Produto ao Pedido:</span>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label for="sel-add-produto" style="font-size: 11px; text-transform: uppercase; color: var(--text-muted);">Produto em Estoque:</label>
                            <select id="sel-add-produto" onchange="atualizarInfoProdutoCarrinho()">
                                <option value="">-- Selecione o Produto --</option>
                                <?php foreach($lista_produtos as $p): ?>
                                    <option value="<?= $p['idProduto'] ?>" data-preco="<?= $p['preco'] ?>" data-estoque="<?= $p['quantidade'] ?>" data-nome="<?= htmlspecialchars($p['nomeProduto']) ?>" data-localizacao="<?= htmlspecialchars($p['localizacao'] ?? 'A-01-01') ?>" data-lote="<?= htmlspecialchars($p['lote'] ?? '') ?>" data-validade="<?= $p['dataValidade'] ? date('d/m/Y', strtotime($p['dataValidade'])) : '' ?>">
                                        <?= htmlspecialchars($p['nomeProduto']) ?> &bull; R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?> (Disp: <?= $p['quantidade'] ?> un) [📍 <?= htmlspecialchars($p['localizacao'] ?? 'A-01-01') ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Grid Perfeito: Quantidade e Botão lado a lado sem sobreposição -->
                        <div class="row-add-inputs">
                            <div class="col-input-qtd">
                                <label for="inp-add-qtd">Quantidade:</label>
                                <input type="number" id="inp-add-qtd" class="input-qtd-custom" min="1" value="1">
                            </div>
                            <div>
                                <button type="button" onclick="adicionarItemAoCarrinho()" class="btn-inserir-carrinho">
                                    <span>➕</span> Inserir no Carrinho
                                </button>
                            </div>
                        </div>

                        <div id="carrinho-fefo-hint" class="fefo-alert-pill" style="display:none;">
                            <span>⏳</span> <span>Dica FEFO: Lote sugerido: <strong id="carrinho-fefo-lote"></strong></span>
                            <span style="margin-left: 8px; border-left: 1px solid rgba(245, 158, 11, 0.3); padding-left: 8px;">📍 Armazém: <strong id="carrinho-wms-loc"></strong></span>
                        </div>
                    </div>

                    <!-- Tabela de Itens Adicionados -->
                    <div style="margin-bottom: 16px;">
                        <span style="font-size: 12.5px; font-weight: 700; color: var(--text); display: block; margin-bottom: 8px;">
                            Itens do Pedido (<span id="carrinho-total-itens" style="color: var(--primary);">0</span>):
                        </span>
                        
                        <div class="carrinho-table-container">
                            <table class="carrinho-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th style="width: 65px; text-align: center;">Qtd</th>
                                        <th style="width: 90px; text-align: right;">Subtotal</th>
                                        <th style="width: 40px; text-align: center;"></th>
                                    </tr>
                                </thead>
                                <tbody id="carrinho-itens-tbody">
                                    <tr>
                                        <td colspan="4" class="carrinho-empty-state">
                                            Nenhum item adicionado ainda ao pedido.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Resumo Financeiro da Venda -->
                    <div class="total-venda-card">
                        <span class="label-total">
                            <span>💰</span> Total do Pedido:
                        </span>
                        <strong id="carrinho-total-valor" class="valor-total">R$ 0,00</strong>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="observacaoCarrinho">Observações do Pedido</label>
                        <input type="text" name="observacaoCarrinho" id="observacaoCarrinho" placeholder="Ex: Pedido balcão, entrega agendada...">
                    </div>
                </div>

                <div class="drawer-footer-actions">
                    <button type="button" class="btn-acao-cancelar" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn-acao-confirmar" id="btn-finalizar-carrinho" disabled>Finalizar Pedido de Venda</button>
                </div>
            </form>
        </div>

        <!-- ABA 2: MOVIMENTO RÁPIDO / LOTE & VALIDADE -->
        <div id="aba-rapido" class="conteudo-aba" style="display: none;">
            <form action="movimento.php" method="POST">
                <?= campoCSRF() ?>
                <input type="hidden" name="acao" value="registrar">

                <div class="drawer-body">
                    <div class="form-group">
                        <label for="idProduto">Produto *</label>
                        <select name="idProduto" id="idProduto" required onchange="aoMudarProdutoRapido(this)">
                            <option value="">-- Selecione o Produto --</option>
                            <?php foreach($lista_produtos as $itemproduto): ?>
                                <option value="<?= $itemproduto['idProduto'] ?>" data-localizacao="<?= htmlspecialchars($itemproduto['localizacao'] ?? 'A-01-01') ?>" data-lote="<?= htmlspecialchars($itemproduto['lote'] ?? '') ?>" data-validade="<?= $itemproduto['dataValidade'] ? date('d/m/Y', strtotime($itemproduto['dataValidade'])) : '' ?>">
                                    <?= htmlspecialchars($itemproduto['nomeProduto']) ?> (Estoque: <?= $itemproduto['quantidade'] ?> un) [📍 <?= htmlspecialchars($itemproduto['localizacao'] ?? 'A-01-01') ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="tipoMovimento">Tipo de Operação *</label>
                            <select name="tipoMovimento" id="tipoMovimento" required onchange="alternarCamposTipoOperacao(this.value)">
                                <option value="SAIDA">Saída / Venda Comercial</option>
                                <option value="ENTRADA">Entrada / Reposição de Carga</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantidade">Quantidade *</label>
                            <input type="number" name="quantidade" id="quantidade" min="1" placeholder="1" required>
                        </div>
                    </div>

                    <!-- Campos de Lote & Validade (Especialmente importantes em ENTRADA para FEFO) -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="loteMov">Lote do Produto</label>
                            <input type="text" name="lote" id="loteMov" placeholder="Ex: LOT-2026-A1">
                        </div>
                        <div class="form-group">
                            <label for="dataValidadeMov">Data de Validade (FEFO)</label>
                            <input type="date" name="dataValidade" id="dataValidadeMov">
                        </div>
                    </div>

                    <div id="aviso-fefo-rapido" style="display:none;" class="fefo-alert-pill">
                        <span>⏳</span> <span><strong>Atenção FEFO:</strong> Este produto possui lote cadastrado com validade. Ao vender, verifique a etiqueta da prateleira.</span>
                    </div>

                    <div class="form-group" id="grp-cliente">
                        <label for="idCliente">Cliente Comprador</label>
                        <select name="idCliente" id="idCliente">
                            <option value="">-- Balcão / Não especificado --</option>
                            <?php foreach($lista_clientes as $c): ?>
                                <option value="<?= $c['idCliente'] ?>"><?= htmlspecialchars($c['nomeCliente']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="grp-fornecedor" style="display: none;">
                        <label for="idFornecedor">Fornecedor da Mercadoria</label>
                        <select name="idFornecedor" id="idFornecedor">
                            <option value="">-- Não especificado --</option>
                            <?php foreach($lista_fornecedores as $f): ?>
                                <option value="<?= $f['idFornecedor'] ?>"><?= htmlspecialchars($f['nomeFornecedor']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="observacao">Observações</label>
                        <textarea name="observacao" id="observacao" rows="2" placeholder="Nota fiscal ou justificativa..."></textarea>
                    </div>
                </div>

                <div class="drawer-footer-actions">
                    <button type="button" class="btn-acao-cancelar" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn-acao-confirmar">Gravar Movimentação</button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- SCRIPTS PARA GERENCIAMENTO DO CARRINHO, ABAS E FEFO -->
<script>
    let carrinhoItens = [];

    function alternarAbaOperacao(aba, btn) {
        document.querySelectorAll('.tab-seg-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.getElementById('aba-carrinho').style.display = (aba === 'carrinho') ? 'block' : 'none';
        document.getElementById('aba-rapido').style.display = (aba === 'rapido') ? 'block' : 'none';
    }

    function atualizarInfoProdutoCarrinho() {
        const sel = document.getElementById('sel-add-produto');
        const opt = sel.options[sel.selectedIndex];
        const hint = document.getElementById('carrinho-fefo-hint');
        const loteHint = document.getElementById('carrinho-fefo-lote');
        const wmsHint = document.getElementById('carrinho-wms-loc');

        if (opt && opt.value) {
            hint.style.display = 'inline-flex';
            loteHint.textContent = (opt.dataset.lote ? opt.dataset.lote : 'Padrão') + (opt.dataset.validade ? ' (Vence: ' + opt.dataset.validade + ')' : '');
            if (wmsHint) {
                wmsHint.textContent = opt.dataset.localizacao || 'A-01-01';
            }
        } else {
            hint.style.display = 'none';
        }
    }

    function adicionarItemAoCarrinho() {
        const sel = document.getElementById('sel-add-produto');
        const qtdInput = document.getElementById('inp-add-qtd');
        const idProduto = parseInt(sel.value);
        const qtd = parseInt(qtdInput.value);

        if (!idProduto || qtd <= 0 || isNaN(qtd)) {
            alert('Por favor, selecione um produto e informe uma quantidade válida.');
            return;
        }

        const opt = sel.options[sel.selectedIndex];
        const estoque = parseInt(opt.dataset.estoque || 0);
        const preco = parseFloat(opt.dataset.preco || 0);
        const nome = opt.dataset.nome || 'Produto';
        const localizacao = opt.dataset.localizacao || 'A-01-01';

        const indexExistente = carrinhoItens.findIndex(item => item.idProduto === idProduto);
        const qtdJaNoCarrinho = (indexExistente >= 0) ? carrinhoItens[indexExistente].quantidade : 0;

        if (qtdJaNoCarrinho + qtd > estoque) {
            alert(`Estoque insuficiente para "${nome}". Disponível: ${estoque} un (Já no carrinho: ${qtdJaNoCarrinho} un).`);
            return;
        }

        if (indexExistente >= 0) {
            carrinhoItens[indexExistente].quantidade += qtd;
        } else {
            carrinhoItens.push({
                idProduto: idProduto,
                nome: nome,
                preco: preco,
                quantidade: qtd,
                localizacao: localizacao
            });
        }

        qtdInput.value = 1;
        sel.value = '';
        document.getElementById('carrinho-fefo-hint').style.display = 'none';
        renderizarCarrinho();
    }

    function removerItemCarrinho(index) {
        carrinhoItens.splice(index, 1);
        renderizarCarrinho();
    }

    function renderizarCarrinho() {
        const tbody = document.getElementById('carrinho-itens-tbody');
        const totalItensSpan = document.getElementById('carrinho-total-itens');
        const totalValorStrong = document.getElementById('carrinho-total-valor');
        const inputJson = document.getElementById('itens_carrinho_json');
        const btnFinalizar = document.getElementById('btn-finalizar-carrinho');

        if (carrinhoItens.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="carrinho-empty-state">Nenhum item adicionado ainda ao pedido.</td></tr>';
            totalItensSpan.textContent = '0';
            totalValorStrong.textContent = 'R$ 0,00';
            inputJson.value = '[]';
            btnFinalizar.disabled = true;
            return;
        }

        let html = '';
        let totalQtd = 0;
        let totalValor = 0;

        carrinhoItens.forEach((item, idx) => {
            const subtotal = item.quantidade * item.preco;
            totalQtd += item.quantidade;
            totalValor += subtotal;

            html += `
                <tr>
                    <td>
                        <strong>${item.nome}</strong>
                        <div style="font-size: 11px; margin-top: 2px;">
                            <span style="color: var(--primary); font-family: 'JetBrains Mono', monospace; font-weight: 700;">📍 ${item.localizacao || 'A-01-01'}</span>
                        </div>
                    </td>
                    <td style="text-align: center; font-family: 'JetBrains Mono', monospace; font-weight: 700;">${item.quantidade} un</td>
                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace;">R$ ${subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td style="text-align: center;">
                        <button type="button" onclick="removerItemCarrinho(${idx})" style="background: none; border: none; cursor: pointer; color: #ef4444; font-size: 14px; padding: 4px;" title="Remover item">✕</button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        totalItensSpan.textContent = totalQtd;
        totalValorStrong.textContent = 'R$ ' + totalValor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        inputJson.value = JSON.stringify(carrinhoItens);
        btnFinalizar.disabled = false;
    }

    function validarEnvioCarrinho() {
        if (carrinhoItens.length === 0) {
            alert('Adicione produtos ao carrinho antes de finalizar o pedido.');
            return false;
        }
        return true;
    }

    function alternarCamposTipoOperacao(tipo) {
        document.getElementById('grp-cliente').style.display = (tipo === 'SAIDA') ? 'block' : 'none';
        document.getElementById('grp-fornecedor').style.display = (tipo === 'ENTRADA') ? 'block' : 'none';
    }

    function aoMudarProdutoRapido(sel) {
        const opt = sel.options[sel.selectedIndex];
        const aviso = document.getElementById('aviso-fefo-rapido');
        if (opt && opt.dataset.lote) {
            aviso.style.display = 'inline-flex';
            document.getElementById('loteMov').value = opt.dataset.lote;
        } else {
            aviso.style.display = 'none';
        }
    }
</script>

<?php require_once "templates/footer.php"; ?>