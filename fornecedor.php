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

// Inclusão de Fornecedor
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Sessão expirada ou token inválido.'];
        header("Location: fornecedor.php");
        exit;
    }

    if (isset($_POST['nomeFornecedor'], $_POST['cnpj'], $_POST['acao']) && $_POST['acao'] === 'salvar') {
        $nomeFornecedor = trim($_POST['nomeFornecedor']);
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $uf = strtoupper(trim($_POST['uf'] ?? ''));
        $cnpj = trim($_POST['cnpj']);
        $segmento = trim($_POST['segmento'] ?? '');

        if ($nomeFornecedor !== '' && $cnpj !== '') {
            $conexao = conectar();
            $sql = "INSERT INTO fornecedor (idUsuario, nomeFornecedor, email, telefone, endereco, cidade, uf, cnpj, segmento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "issssssss", $idUsuario, $nomeFornecedor, $email, $telefone, $endereco, $cidade, $uf, $cnpj, $segmento);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => "Fornecedor '{$nomeFornecedor}' cadastrado com sucesso!"];
            } else {
                error_log("Erro ao cadastrar fornecedor: " . mysqli_error($conexao));
                $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível cadastrar o fornecedor. Verifique os dados e tente novamente.'];
            }
            mysqli_close($conexao);
        } else {
            $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Nome do Fornecedor e CNPJ são campos obrigatórios.'];
        }

        header("Location: fornecedor.php");
        exit;
    }
}

$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$conexao = conectar();
$fornecedors = [];

if ($busca !== '') {
    $sql = "SELECT * FROM fornecedor WHERE idUsuario = ? AND (nomeFornecedor LIKE ? OR cnpj LIKE ? OR segmento LIKE ? OR cidade LIKE ?) ORDER BY nomeFornecedor ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    $termoBusca = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, 'issss', $idUsuario, $termoBusca, $termoBusca, $termoBusca, $termoBusca);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {    
    $sql = "SELECT * FROM fornecedor WHERE idUsuario = ? ORDER BY nomeFornecedor ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt); 
}

if ($resultado && mysqli_num_rows($resultado) > 0) {
    $fornecedors = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}
mysqli_close($conexao);

$titulo_pagina = "MVM - Fornecedores";
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

    <!-- Cabeçalho do Módulo de Fornecedores -->
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Gestão de Fornecedores</h1>
            <p>Controle de distribuidoras, segmentos industriais e canais de reposição de estoque</p>
        </div>
        <div class="page-header-actions">
            <button type="button" class="btn-primary" data-open-modal="modal-novo-fornecedor">
                <span>➕</span> Novo Fornecedor
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
            <input type="text" placeholder="Filtrar por empresa, CNPJ, segmento ou cidade..." data-table-search="#tabela-fornecedores" value="<?= htmlspecialchars($busca) ?>">
        </div>

        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;"><?= count($fornecedors) ?></span> parceiros
        </div>
    </div>

    <!-- DATA TABLE STRIPE-STYLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-fornecedores">
                <thead>
                    <tr>
                        <th style="width: 28%;">Fornecedor &amp; CNPJ</th>
                        <th style="width: 16%;">Segmento</th>
                        <th style="width: 22%;">Contato &amp; E-mail</th>
                        <th style="width: 24%;">Localização &amp; Endereço</th>
                        <th style="width: 10%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($fornecedors) > 0): ?>
                        <?php foreach ($fornecedors as $f): ?>
                            <tr>
                                <td>
                                    <div class="cell-product">
                                        <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                                            🏢
                                        </div>
                                        <div class="cell-product-info">
                                            <strong><?= htmlspecialchars($f['nomeFornecedor']) ?></strong>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px;">
                                                CNPJ: <?= htmlspecialchars($f['cnpj']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill info">
                                        <?= htmlspecialchars($f['segmento'] ?: 'Geral') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <?php if (!empty($f['telefone'])): ?>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text);">
                                                📞 <?= htmlspecialchars($f['telefone']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($f['email'])): ?>
                                            <span style="font-size: 11px; color: var(--text-muted);">
                                                ✉️ <?= htmlspecialchars($f['email']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 12px;">
                                        <strong><?= htmlspecialchars($f['cidade'] ?: 'Cidade não inf.') ?><?= !empty($f['uf']) ? ' - ' . htmlspecialchars($f['uf']) : '' ?></strong>
                                        <?php if (!empty($f['endereco'])): ?>
                                            <div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">
                                                <?= htmlspecialchars($f['endereco']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <?php 
                                        $telLimpo = preg_replace('/\D/', '', $f['telefone'] ?? '');
                                        if (!empty($telLimpo)): 
                                            if (strlen($telLimpo) <= 11) {
                                                $telLimpo = '55' . $telLimpo;
                                            }
                                            $msgZap = urlencode("Olá " . $f['nomeFornecedor'] . "! Aqui é da MVM Estoque. Gostaríamos de solicitar uma cotação e previsão de reposição de itens. Poderia nos atender?");
                                        ?>
                                            <a href="https://wa.me/<?= $telLimpo ?>?text=<?= $msgZap ?>" target="_blank" rel="noopener noreferrer" class="btn-icon" style="color: #25D366; background: rgba(37, 211, 102, 0.12);" title="Solicitar Cotação/Reposição no WhatsApp">
                                                💬
                                            </a>
                                        <?php endif; ?>
                                        <a href="fornecedor_editar.php?id=<?= (int)$f['idFornecedor'] ?>" class="btn-icon" title="Editar Fornecedor">
                                            ✏️
                                        </a>
                                        <form action="fornecedor_excluir.php" method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir este fornecedor?');">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id" value="<?= (int)$f['idFornecedor'] ?>">
                                            <button type="submit" class="btn-icon delete" style="border:none; cursor:pointer;" title="Excluir Fornecedor">
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
                                <div style="font-size: 32px; margin-bottom: 8px;">🏢</div>
                                <strong>Nenhum fornecedor cadastrado</strong>
                                <p style="font-size: 12px; margin-top: 4px;">Clique em "+ Novo Fornecedor" para cadastrar parceiros comerciais.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Rede de fornecimento e distribuição comercial</span>
            <span>Exibindo <?= count($fornecedors) ?> parceiros</span>
        </div>
    </div>
</div>

<!-- GAVETA LATERAL FLUIDA PARA CADASTRO DE FORNECEDOR -->
<div id="modal-novo-fornecedor" class="modal-container">
    <div class="drawer-content">
        <div class="drawer-header">
            <div>
                <h2>Cadastrar Novo Fornecedor</h2>
                <p>Preencha os dados da distribuidora parceira</p>
            </div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>

        <form action="fornecedor.php" method="POST">
            <?= campoCSRF() ?>
            <div class="drawer-body">
                <div class="form-group">
                    <label for="nomeFornecedor">Razão Social / Nome Fantasia *</label>
                    <input type="text" id="nomeFornecedor" name="nomeFornecedor" placeholder="Ex: Tech Distribuidora Ltda" required>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="cnpj">CNPJ *</label>
                        <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0001-00" required>
                    </div>
                    <div class="form-group">
                        <label for="segmento">Segmento / Ramo</label>
                        <input type="text" id="segmento" name="segmento" placeholder="Ex: Bebidas, Informatica">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="email">E-mail Comercial</label>
                        <input type="email" id="email" name="email" placeholder="pedidos@empresa.com">
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" id="telefone" name="telefone" placeholder="(11) 3300-1122">
                    </div>
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
                    <label for="endereco">Endereço Comercial</label>
                    <input type="text" id="endereco" name="endereco" placeholder="Av, Rua, Galpão...">
                </div>
            </div>

            <div class="drawer-footer">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Fornecedor</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts de Exportação Client-side -->
<script>
    const fornecedoresData = <?= json_encode($fornecedors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="javaScript/exportarexcel.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="javaScript/exportarPDF.js"></script>

<?php require_once "templates/footer.php"; ?>