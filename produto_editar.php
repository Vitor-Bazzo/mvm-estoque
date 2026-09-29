<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

require_once "config/conexao.php";
require_once "config/upload.php";

$idUsuario = obterIdUsuarioLogado();

// Processamento da atualização (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Sessão expirada ou token inválido.'];
        header("Location: produto.php");
        exit;
    }

    if (isset($_POST['idProduto'], $_POST['nomeProduto'], $_POST['preco'], $_POST['acao']) && $_POST['acao'] === 'salvar') {
        $idProduto = (int)$_POST['idProduto'];
        $nomeProduto = trim($_POST['nomeProduto']);
        $categoria = trim($_POST['categoria'] ?? '');
        $preco = (float)($_POST['preco'] ?? 0);
        $precoCusto = (float)($_POST['precoCusto'] ?? 0);
        $quantidade = (int)($_POST['quantidade'] ?? 0);
        $estoqueMinimo = (int)($_POST['estoqueMinimo'] ?? 10);
        $descricao = trim($_POST['descricao'] ?? '');
        $nomeFornecedor = trim($_POST['nomeFornecedor'] ?? '');
        $lote = trim($_POST['lote'] ?? '');
        $dataValidade = !empty($_POST['dataValidade']) ? $_POST['dataValidade'] : null;
        $imagemAtual = $_POST['imagemAtual'] ?? null;

        $novaImagem = salvarImagemProduto();
        $imagem = $imagemAtual;
        if ($novaImagem !== null) {
            excluirImagemProduto($imagemAtual);
            $imagem = $novaImagem;
        }

        $conexao = conectar();
        $sql = "UPDATE produto SET nomeProduto = ?, categoria = ?, preco = ?, precoCusto = ?, quantidade = ?, estoqueMinimo = ?, descricao = ?, nomeFornecedor = ?, lote = ?, dataValidade = ?, imagem = ? WHERE idProduto = ? AND idUsuario = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "ssddiisssssii", $nomeProduto, $categoria, $preco, $precoCusto, $quantidade, $estoqueMinimo, $descricao, $nomeFornecedor, $lote, $dataValidade, $imagem, $idProduto, $idUsuario);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => "Produto '{$nomeProduto}' atualizado com sucesso!"];
        } else {
            error_log("Erro ao atualizar produto: " . mysqli_error($conexao));
            $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível atualizar o produto. Tente novamente.'];
        }
        mysqli_close($conexao);

        header("Location: produto.php");
        exit;
    }
}

// Busca o produto pelo ID (aceita tanto id quanto idProduto)
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['idProduto']) ? (int)$_GET['idProduto'] : 0);
$produto = null;
$listaFornecedores = [];

if ($id > 0) {
    $conexao = conectar();
    $stmtForn = mysqli_prepare($conexao, "SELECT nomeFornecedor FROM fornecedor WHERE idUsuario = ? ORDER BY nomeFornecedor ASC");
    if ($stmtForn) {
        mysqli_stmt_bind_param($stmtForn, "i", $idUsuario);
        mysqli_stmt_execute($stmtForn);
        $resFornecedores = mysqli_stmt_get_result($stmtForn);
        if ($resFornecedores && mysqli_num_rows($resFornecedores) > 0) {
            $listaFornecedores = mysqli_fetch_all($resFornecedores, MYSQLI_ASSOC);
        }
    }

    $sql = "SELECT * FROM produto WHERE idProduto = ? AND idUsuario = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $produto = mysqli_fetch_assoc($resultado);
    mysqli_close($conexao);
}

if (!$produto) {
    $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Produto não encontrado.'];
    header('Location: produto.php');
    exit;
}

$titulo_pagina = "MVM - Editar Produto #" . $produto['idProduto'];
require_once "templates/header.php";
?>

<div class="page-container" style="max-width: 760px;">
    
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Editar Produto</h1>
            <p>Atualize as informações, estoque e precificação do item no catálogo</p>
        </div>
        <div class="page-header-actions">
            <a href="produto.php" class="btn-secondary">
                <span>←</span> Voltar para Catálogo
            </a>
        </div>
    </div>

    <div class="table-card" style="padding: 28px;">
        <form action="produto_editar.php" method="POST" enctype="multipart/form-data">
            <?= campoCSRF() ?>
            <input type="hidden" name="idProduto" value="<?= (int)$produto['idProduto'] ?>" required>
            <input type="hidden" name="imagemAtual" value="<?= htmlspecialchars($produto['imagem'] ?? '') ?>">

            <div class="form-group">
                <label for="nomeProduto">Nome do Produto *</label>
                <input type="text" id="nomeProduto" name="nomeProduto" value="<?= htmlspecialchars($produto['nomeProduto']) ?>" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <input type="text" id="categoria" name="categoria" value="<?= htmlspecialchars($produto['categoria'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="preco">Preço de Venda (R$) *</label>
                    <input type="number" step="0.01" min="0" id="preco" name="preco" value="<?= htmlspecialchars($produto['preco']) ?>" required>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="precoCusto">Preço de Custo (R$)</label>
                    <input type="number" step="0.01" min="0" id="precoCusto" name="precoCusto" value="<?= htmlspecialchars($produto['precoCusto'] ?? '0.00') ?>" title="Custo pago ao fornecedor">
                </div>
                <div class="form-group">
                    <label for="quantidade">Estoque Atual</label>
                    <input type="number" min="0" name="quantidade" id="quantidade" value="<?= (int)$produto['quantidade'] ?>">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="estoqueMinimo">Estoque Mínimo (Ponto de Reposição)</label>
                    <input type="number" min="1" name="estoqueMinimo" id="estoqueMinimo" value="<?= (int)($produto['estoqueMinimo'] ?? 10) ?>" title="Dispara alerta de compra sugerida">
                </div>
                <div class="form-group">
                    <label for="nomeFornecedor">Fornecedor Vinculado</label>
                    <select id="nomeFornecedor" name="nomeFornecedor">
                        <option value="">-- Não informado --</option>
                        <?php foreach ($listaFornecedores as $forn): ?>
                            <option value="<?= htmlspecialchars($forn['nomeFornecedor']) ?>" <?= (isset($produto['nomeFornecedor']) && $produto['nomeFornecedor'] === $forn['nomeFornecedor']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($forn['nomeFornecedor']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="lote">Lote de Fabricação</label>
                    <input type="text" id="lote" name="lote" value="<?= htmlspecialchars($produto['lote'] ?? '') ?>" placeholder="Ex: LOT-2026-A1">
                </div>
                <div class="form-group">
                    <label for="dataValidade">Data de Validade (Logística FEFO)</label>
                    <input type="date" id="dataValidade" name="dataValidade" value="<?= htmlspecialchars($produto['dataValidade'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="descricao">Descrição Completa</label>
                <textarea id="descricao" name="descricao" rows="3"><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="imagem">Foto do Produto</label>
                <?php if (!empty($produto['imagem'])): ?>
                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 10px;">
                        <img src="<?= CAMINHO_UPLOAD_PRODUTOS . htmlspecialchars($produto['imagem']) ?>" alt="Imagem atual" style="width: 56px; height: 56px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border);">
                        <span style="font-size: 12px; color: var(--text-muted);">Foto atual cadastrada</span>
                    </div>
                <?php endif; ?>
                <input type="file" id="imagem" name="imagem" accept="image/*">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border);">
                <a href="produto.php" class="btn-secondary">Cancelar</a>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>

</div>

<?php require_once "templates/footer.php"; ?>