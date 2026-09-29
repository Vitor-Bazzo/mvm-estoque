<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

require_once "config/conexao.php";

$idUsuario = obterIdUsuarioLogado();

// Processamento da atualização de Fornecedor (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Sessão expirada ou token inválido.'];
        header("Location: fornecedor.php");
        exit;
    }

    if (isset($_POST['idFornecedor'], $_POST['nomeFornecedor'], $_POST['cnpj'], $_POST['acao']) && $_POST['acao'] === 'salvar') {
        $idFornecedor = (int)$_POST['idFornecedor'];
        $nomeFornecedor = trim($_POST['nomeFornecedor']);
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $uf = strtoupper(trim($_POST['uf'] ?? ''));
        $cnpj = trim($_POST['cnpj']);
        $segmento = trim($_POST['segmento'] ?? '');

        if ($idFornecedor > 0 && $nomeFornecedor !== '' && $cnpj !== '') {
            $conexao = conectar();
            $sql = "UPDATE fornecedor SET nomeFornecedor = ?, email = ?, telefone = ?, endereco = ?, cidade = ?, uf = ?, cnpj = ?, segmento = ? WHERE idFornecedor = ? AND idUsuario = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssii", $nomeFornecedor, $email, $telefone, $endereco, $cidade, $uf, $cnpj, $segmento, $idFornecedor, $idUsuario);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => "Fornecedor '{$nomeFornecedor}' atualizado com sucesso!"];
            } else {
                error_log("Erro ao atualizar fornecedor: " . mysqli_error($conexao));
                $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível atualizar o fornecedor. Tente novamente.'];
            }
            mysqli_close($conexao);
        } else {
            $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Nome do Fornecedor e CNPJ são obrigatórios.'];
        }

        header("Location: fornecedor.php");
        exit;
    }
}

// Busca fornecedor pelo ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$fornecedor = null;

if ($id > 0) {
    $conexao = conectar();
    $sql = "SELECT * FROM fornecedor WHERE idFornecedor = ? AND idUsuario = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $fornecedor = mysqli_fetch_assoc($resultado);
    mysqli_close($conexao);
}

if (!$fornecedor) {
    $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Fornecedor não encontrado.'];
    header('Location: fornecedor.php');
    exit;
}

$titulo_pagina = "MVM - Editar Fornecedor #" . $fornecedor['idFornecedor'];
require_once "templates/header.php";
?>

<div class="page-container" style="max-width: 720px;">
    
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Editar Fornecedor</h1>
            <p>Atualize a razão social, CNPJ e canais de contato da distribuidora parceira</p>
        </div>
        <div class="page-header-actions">
            <a href="fornecedor.php" class="btn-secondary">
                <span>←</span> Voltar para Fornecedores
            </a>
        </div>
    </div>

    <div class="table-card" style="padding: 28px;">
        <form action="fornecedor_editar.php" method="POST">
            <?= campoCSRF() ?>
            <input type="hidden" name="idFornecedor" value="<?= (int)$fornecedor['idFornecedor'] ?>" required>

            <div class="form-group">
                <label for="nomeFornecedor">Razão Social / Nome Fantasia *</label>
                <input type="text" id="nomeFornecedor" name="nomeFornecedor" value="<?= htmlspecialchars($fornecedor['nomeFornecedor']) ?>" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="cnpj">CNPJ *</label>
                    <input type="text" id="cnpj" name="cnpj" value="<?= htmlspecialchars($fornecedor['cnpj']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="segmento">Segmento / Ramo</label>
                    <input type="text" id="segmento" name="segmento" value="<?= htmlspecialchars($fornecedor['segmento'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="email">E-mail Comercial</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($fornecedor['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($fornecedor['telefone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" value="<?= htmlspecialchars($fornecedor['cidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="uf">Estado (UF)</label>
                    <input type="text" id="uf" name="uf" maxlength="2" value="<?= htmlspecialchars($fornecedor['uf'] ?? '') ?>" style="text-transform: uppercase;">
                </div>
            </div>

            <div class="form-group">
                <label for="endereco">Endereço Completo</label>
                <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($fornecedor['endereco'] ?? '') ?>">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border);">
                <a href="fornecedor.php" class="btn-secondary">Cancelar</a>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>

</div>

<?php require_once "templates/footer.php"; ?>