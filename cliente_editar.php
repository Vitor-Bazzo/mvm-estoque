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

// Processamento da atualização de Cliente (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Sessão expirada ou token inválido.'];
        header("Location: cliente.php");
        exit;
    }

    if (isset($_POST['idCliente'], $_POST['nomeCliente'], $_POST['email'], $_POST['acao']) && $_POST['acao'] === 'salvar') {
        $idCliente = (int)$_POST['idCliente'];
        $nomeCliente = trim($_POST['nomeCliente']);
        $email = trim($_POST['email']);
        $telefone = trim($_POST['telefone'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $uf = strtoupper(trim($_POST['uf'] ?? ''));

        if ($idCliente > 0 && $nomeCliente !== '' && $email !== '') {
            $conexao = conectar();
            $sql = "UPDATE cliente SET nomeCliente = ?, email = ?, telefone = ?, endereco = ?, cidade = ?, uf = ? WHERE idCliente = ? AND idUsuario = ?";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssii", $nomeCliente, $email, $telefone, $endereco, $cidade, $uf, $idCliente, $idUsuario);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => "Cliente '{$nomeCliente}' atualizado com sucesso!"];
            } else {
                error_log("Erro ao atualizar cliente: " . mysqli_error($conexao));
                $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível atualizar os dados do cliente. Tente novamente.'];
            }
            mysqli_close($conexao);
        } else {
            $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Nome e Email são campos obrigatórios.'];
        }

        header("Location: cliente.php");
        exit;
    }
}

// Busca o cliente pelo ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cliente = null;

if ($id > 0) {
    $conexao = conectar();
    $sql = "SELECT * FROM cliente WHERE idCliente = ? AND idUsuario = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $cliente = mysqli_fetch_assoc($resultado);
    mysqli_close($conexao);
}

if (!$cliente) {
    $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Cliente não encontrado.'];
    header('Location: cliente.php');
    exit;
}

$titulo_pagina = "MVM - Editar Cliente #" . $cliente['idCliente'];
require_once "templates/header.php";
?>

<div class="page-container" style="max-width: 680px;">
    
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Editar Cliente</h1>
            <p>Atualize as informações de contato e localização do comprador</p>
        </div>
        <div class="page-header-actions">
            <a href="cliente.php" class="btn-secondary">
                <span>←</span> Voltar para Clientes
            </a>
        </div>
    </div>

    <div class="table-card" style="padding: 28px;">
        <form action="cliente_editar.php" method="POST">
            <?= campoCSRF() ?>
            <input type="hidden" name="idCliente" value="<?= (int)$cliente['idCliente'] ?>" required>

            <div class="form-group">
                <label for="nomeCliente">Nome do Cliente *</label>
                <input type="text" id="nomeCliente" name="nomeCliente" value="<?= htmlspecialchars($cliente['nomeCliente']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail de Contato *</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="telefone">Telefone / Celular</label>
                <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="uf">Estado (UF)</label>
                    <input type="text" id="uf" name="uf" maxlength="2" value="<?= htmlspecialchars($cliente['uf'] ?? '') ?>" style="text-transform: uppercase;">
                </div>
            </div>

            <div class="form-group">
                <label for="endereco">Endereço Completo</label>
                <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border);">
                <a href="cliente.php" class="btn-secondary">Cancelar</a>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>

</div>

<?php require_once "templates/footer.php"; ?>
