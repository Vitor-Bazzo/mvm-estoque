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

// Inclusão de Cliente
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Sessão expirada ou token inválido.'];
        header("Location: cliente.php");
        exit;
    }

    if (isset($_POST['nomeCliente'], $_POST['email'], $_POST['acao']) && $_POST['acao'] === 'salvar') {
        $nomeCliente = trim($_POST['nomeCliente']);
        $email = trim($_POST['email']);
        $telefone = trim($_POST['telefone'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $uf = strtoupper(trim($_POST['uf'] ?? ''));

        if ($nomeCliente !== '' && $email !== '') {
            $conexao = conectar();
            $sql = "INSERT INTO cliente (idUsuario, nomeCliente, email, telefone, endereco, cidade, uf) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "issssss", $idUsuario, $nomeCliente, $email, $telefone, $endereco, $cidade, $uf);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => "Cliente '{$nomeCliente}' cadastrado com sucesso!"];
            } else {
                error_log("Erro ao cadastrar cliente: " . mysqli_error($conexao));
                $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível cadastrar o cliente. Tente novamente.'];
            }
            mysqli_close($conexao);
        } else {
            $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Nome e Email são campos obrigatórios.'];
        }

        header("Location: cliente.php");
        exit;
    }
}

$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$conexao = conectar();
$clientes = [];

if ($busca !== '') {
    $sql = "SELECT * FROM cliente WHERE idUsuario = ? AND (nomeCliente LIKE ? OR email LIKE ? OR cidade LIKE ?) ORDER BY nomeCliente ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    $termoBusca = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, 'isss', $idUsuario, $termoBusca, $termoBusca, $termoBusca);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {    
    $sql = "SELECT * FROM cliente WHERE idUsuario = ? ORDER BY nomeCliente ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
}

if ($resultado && mysqli_num_rows($resultado) > 0) {
    $clientes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}
mysqli_close($conexao);

$titulo_pagina = "MVM - Carteira de Clientes";
require_once "templates/header.php";
?>

<div class="page-container">
    
    <!-- Alertas Flash -->
    <?php if (!empty($_SESSION['flash_mensagem'])): ?>
        <div class="alert-box <?= $_SESSION['flash_mensagem']['tipo'] === 'sucesso' ? 'success' : 'error' ?>">
            <span><?= $_SESSION['flash_mensagem']['tipo'] === 'sucesso' ? '✅' : '⚠️' ?></span>
            <p><?= htmlspecialchars($_SESSION['flash_mensagem']['texto']) ?></p>
        </div>
        <?php unset($_SESSION['flash_mensagem']); ?>
    <?php endif; ?>

    <!-- Cabeçalho do Módulo de Clientes -->
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Carteira de Clientes</h1>
            <p>Gerencie contatos, histórico de compras e canais de comunicação direta</p>
        </div>
        <div class="page-header-actions">
            <button type="button" class="btn-primary" data-open-modal="modal-novo-cliente">
                <span>➕</span> Novo Cliente
            </button>
            <button type="button" id="btnExportarExcel" class="btn-secondary">
                <span>📊</span> Excel
            </button>
            <button type="button" id="btnExportarPDF" class="btn-secondary">
                <span>📄</span> PDF
            </button>
        </div>
    </div>

    <!-- Barra de Filtro e Busca Rápida -->
    <div class="table-filter-bar">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Filtrar por nome, email ou cidade..." data-table-search="#tabela-clientes" value="<?= htmlspecialchars($busca) ?>">
        </div>

        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;"><?= count($clientes) ?></span> clientes
        </div>
    </div>

    <!-- DATA TABLE STRIPE-STYLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-clientes">
                <thead>
                    <tr>
                        <th style="width: 30%;">Cliente</th>
                        <th style="width: 20%;">Telefone / Contato</th>
                        <th style="width: 20%;">Localização</th>
                        <th style="width: 20%;">Endereço</th>
                        <th style="width: 10%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td>
                                    <div class="cell-product">
                                        <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px; background: linear-gradient(135deg, #635bff 0%, #4f46e5 100%);">
                                            <?= strtoupper(substr($c['nomeCliente'], 0, 1)) ?>
                                        </div>
                                        <div class="cell-product-info">
                                            <strong><?= htmlspecialchars($c['nomeCliente']) ?></strong>
                                            <span><?= htmlspecialchars($c['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($c['telefone'])): ?>
                                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text);">
                                            📞 <?= htmlspecialchars($c['telefone']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 12px;">Não cadastrado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['cidade'])): ?>
                                        <span class="status-pill neutral">
                                            📍 <?= htmlspecialchars($c['cidade']) ?><?= !empty($c['uf']) ? ' - ' . htmlspecialchars($c['uf']) : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-size: 12px;">Não informado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color: var(--text-muted); font-size: 12px;">
                                        <?= htmlspecialchars($c['endereco'] ?: 'Endereço não informado') ?>
                                    </span>
                                </td>
                                <td>
                                                         <a href="cliente_editar.php?id=<?= (int)$c['idCliente'] ?>" class="btn-icon" title="Editar Cliente">
                                            ✏️
                                         </a>
                                         <form action="cliente_excluir.php" method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir este cliente?');">
                                             <?= campoCSRF() ?>
                                             <input type="hidden" name="id" value="<?= (int)$c['idCliente'] ?>">
                                             <button type="submit" class="btn-icon delete" style="border:none; cursor:pointer;" title="Excluir Cliente">
                                                 🗑️
                                             </button>
                                         </form>
                                     </div>
                                 </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">👥</div>
                                <strong>Nenhum cliente cadastrado</strong>
                                <p style="font-size: 12px; margin-top: 4px;">Clique em "+ Novo Cliente" para iniciar sua carteira.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Base sincronizada com módulo de emissão de vendas</span>
            <span>Exibindo <?= count($clientes) ?> clientes</span>
        </div>
    </div>
</div>

<!-- GAVETA LATERAL FLUIDA PARA CADASTRO DE CLIENTE -->
<div id="modal-novo-cliente" class="modal-container">
    <div class="drawer-content">
        <div class="drawer-header">
            <div>
                <h2>Cadastrar Novo Cliente</h2>
                <p>Insira os dados do comprador para registro e emissão de compras</p>
            </div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>

        <form action="cliente.php" method="POST">
            <?= campoCSRF() ?>
            <div class="drawer-body">
                <div class="form-group">
                    <label for="nomeCliente">Nome Completo *</label>
                    <input type="text" id="nomeCliente" name="nomeCliente" placeholder="Ex: Ana Maria Silva" required>
                </div>

                <div class="form-group">
                    <label for="email">E-mail Comercial / Pessoal *</label>
                    <input type="email" id="email" name="email" placeholder="cliente@exemplo.com" required>
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone / Celular</label>
                    <input type="text" id="telefone" name="telefone" placeholder="(11) 98765-4321">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="cidade">Cidade</label>
                        <input type="text" id="cidade" name="cidade" placeholder="Ex: São Paulo">
                    </div>
                    <div class="form-group">
                        <label for="uf">Estado (UF)</label>
                        <input type="text" id="uf" name="uf" maxlength="2" placeholder="SP" style="text-transform: uppercase;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="endereco">Endereço Residencial / Comercial</label>
                    <input type="text" id="endereco" name="endereco" placeholder="Rua, número, complemento...">
                </div>
            </div>

            <div class="drawer-footer">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Cliente</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts de Exportação Client-side -->
<script>
    const clientesData = <?= json_encode($clientes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="javaScript/exportarexcel.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="javaScript/exportarPDF.js"></script>

<?php require_once "templates/footer.php"; ?>