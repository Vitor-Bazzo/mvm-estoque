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
require_once "config/upload.php";

$idUsuario = obterIdUsuarioLogado();

// Processamento do formulário de inclusão (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Sessão expirada ou token de segurança inválido.'];
        header("Location: produto.php");
        exit;
    }

    if (isset($_POST['nomeProduto'], $_POST['preco'], $_POST['acao']) && $_POST['acao'] === 'salvar') {
        $nomeProduto = trim($_POST['nomeProduto']);
        $categoria = trim($_POST['categoria'] ?? '');
        $preco = (float) ($_POST['preco'] ?? 0);
        $precoCusto = (float) ($_POST['precoCusto'] ?? 0);
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $estoqueMinimo = (int) ($_POST['estoqueMinimo'] ?? 10);
        $descricao = trim($_POST['descricao'] ?? '');
        $nomeFornecedor = trim($_POST['nomeFornecedor'] ?? '');
        $lote = trim($_POST['lote'] ?? '');
        $dataValidade = !empty($_POST['dataValidade']) ? $_POST['dataValidade'] : null;
        $imagem = salvarImagemProduto();

        if ($nomeProduto !== '') {
            $conexao = conectar();
            $sql = "INSERT INTO produto (idUsuario, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, descricao, nomeFornecedor, lote, dataValidade, imagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "issddiisssss", $idUsuario, $nomeProduto, $categoria, $preco, $precoCusto, $quantidade, $estoqueMinimo, $descricao, $nomeFornecedor, $lote, $dataValidade, $imagem);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_mensagem'] = ['tipo' => 'sucesso', 'texto' => "Produto '{$nomeProduto}' cadastrado com sucesso!"];
            } else {
                error_log("Erro ao cadastrar produto: " . mysqli_error($conexao));
                $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'Não foi possível cadastrar o produto no momento. Tente novamente.'];
            }
            mysqli_close($conexao);
        } else {
            $_SESSION['flash_mensagem'] = ['tipo' => 'erro', 'texto' => 'O nome do produto é obrigatório.'];
        }

        header("Location: produto.php");
        exit;
    }
}

// Consulta de fornecedores do próprio usuário
$conexao = conectar();
$listaFornecedores = [];
$stmtForn = mysqli_prepare($conexao, "SELECT nomeFornecedor FROM fornecedor WHERE idUsuario = ? ORDER BY nomeFornecedor ASC");
if ($stmtForn) {
    mysqli_stmt_bind_param($stmtForn, "i", $idUsuario);
    mysqli_stmt_execute($stmtForn);
    $resFornecedores = mysqli_stmt_get_result($stmtForn);
    if ($resFornecedores && mysqli_num_rows($resFornecedores) > 0) {
        $listaFornecedores = mysqli_fetch_all($resFornecedores, MYSQLI_ASSOC);
    }
}

// Consulta de produtos do próprio usuário
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$campoValido = in_array($_GET['campo'] ?? '', ['nomeProduto', 'categoria', 'nomeFornecedor']) ? $_GET['campo'] : 'nomeProduto';
$produtos = [];

if ($busca !== '') {
    $sql = "SELECT * FROM produto WHERE idUsuario = ? AND $campoValido LIKE ? ORDER BY nomeProduto ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    $termoBusca = "%" . $busca . "%";
    mysqli_stmt_bind_param($stmt, 'is', $idUsuario, $termoBusca);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT * FROM produto WHERE idUsuario = ? ORDER BY nomeProduto ASC";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $idUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
}

if ($resultado && mysqli_num_rows($resultado) > 0) {
    $produtos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}
mysqli_close($conexao);

$titulo_pagina = "MVM - Catálogo de Produtos";
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

    <!-- Cabeçalho do Módulo de Produtos -->
    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>Catálogo de Produtos</h1>
            <p>Gerencie o inventário físico, precificação e níveis de reposição de estoque</p>
        </div>
        <div class="page-header-actions">
            <!-- Botão de Abertura da Gaveta Lateral -->
            <button type="button" class="btn-primary" data-open-modal="modal-novo-produto">
                <span>➕</span> Novo Produto
            </button>
            <button type="button" id="btnExportarExcel" class="btn-secondary" title="Exportar tabela atual para planilha Excel">
                <span>📊</span> Excel
            </button>
            <button type="button" id="btnExportarPDF" class="btn-secondary" title="Exportar relatório em PDF">
                <span>📄</span> PDF
            </button>
        </div>
    </div>

    <!-- Barra de Filtro e Busca Rápida (Live Search) -->
    <div class="table-filter-bar">
        <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Filtrar produtos instantaneamente..." data-table-search="#tabela-produtos" value="<?= htmlspecialchars($busca) ?>">
        </div>

        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
            Total: <span data-item-counter style="color: var(--primary); font-weight: 700;"><?= count($produtos) ?></span> itens
        </div>
    </div>

    <!-- DATA TABLE STRIPE-STYLE -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-produtos">
                <thead>
                    <tr>
                        <th style="width: 32%;">Produto & Detalhes</th>
                        <th style="width: 15%;">Categoria</th>
                        <th style="width: 13%;">Preço (R$)</th>
                        <th style="width: 18%;">Status do Estoque</th>
                        <th style="width: 14%;">Fornecedor</th>
                        <th style="width: 8%; text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($produtos) > 0): ?>
                        <?php foreach ($produtos as $p): ?>
                            <?php 
                                $qtd = (int)$p['quantidade'];
                                $statusClass = 'success';
                                $statusTexto = 'Normal: ' . $qtd . ' un';
                                if ($qtd <= 0) {
                                    $statusClass = 'danger';
                                    $statusTexto = 'Esgotado (0 un)';
                                } elseif ($qtd <= 20) {
                                    $statusClass = 'warning';
                                    $statusTexto = 'Baixo: ' . $qtd . ' un';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="cell-product">
                                        <?php if (!empty($p['imagem'])): ?>
                                            <img src="<?= CAMINHO_UPLOAD_PRODUTOS . htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nomeProduto']) ?>" class="product-thumb">
                                        <?php else: ?>
                                            <div class="product-thumb" title="Sem foto">📦</div>
                                        <?php endif; ?>
                                        <div class="cell-product-info">
                                            <strong><?= htmlspecialchars($p['nomeProduto']) ?></strong>
                                            <span><?= htmlspecialchars(mb_strimwidth($p['descricao'] ?? 'Sem descrição', 0, 45, '...')) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-pill neutral">
                                        <?= htmlspecialchars($p['categoria'] ?: 'Geral') ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="font-family: 'JetBrains Mono', monospace; font-size: 13px;">
                                        R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="status-pill <?= $statusClass ?>">
                                        <?= $statusTexto ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: var(--text-muted); font-size: 12px;">
                                        <?= htmlspecialchars($p['nomeFornecedor'] ?: 'Não vinculado') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="kardex.php?idProduto=<?= (int)$p['idProduto'] ?>" class="btn-icon" title="Ver Ficha Kardex (Extrato)">
                                            📜
                                        </a>
                                        <a href="produto_editar.php?id=<?= (int)$p['idProduto'] ?>" class="btn-icon" title="Editar Produto">
                                            ✏️
                                        </a>
                                        <form action="produto_excluir.php" method="POST" style="display:inline;" onsubmit="return confirm('Deseja realmente remover este produto permanentemente?');">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id" value="<?= (int)$p['idProduto'] ?>">
                                            <button type="submit" class="btn-icon delete" style="border:none; cursor:pointer;" title="Excluir Produto">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">📦</div>
                                <strong>Nenhum produto cadastrado</strong>
                                <p style="font-size: 12px; margin-top: 4px;">Clique em "+ Novo Produto" para adicionar itens ao estoque.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Listagem sincronizada em tempo real com o banco de dados</span>
            <span>Exibindo <?= count($produtos) ?> registros</span>
        </div>
    </div>
</div>

<!-- GAVETA LATERAL FLUIDA PARA CADASTRO (SLIDE-OVER DRAWER) -->
<div id="modal-novo-produto" class="modal-container">
    <div class="drawer-content">
        <div class="drawer-header">
            <div>
                <h2>Cadastrar Novo Produto</h2>
                <p>Preencha as informações para registrar o item no catálogo</p>
            </div>
            <button type="button" class="btn-close-drawer" data-close-modal title="Fechar janela">✕</button>
        </div>

        <form action="produto.php" method="POST" enctype="multipart/form-data">
            <?= campoCSRF() ?>
            <div class="drawer-body">
                <div class="form-group">
                    <label for="nomeProduto">Nome do Produto *</label>
                    <input type="text" id="nomeProduto" name="nomeProduto" placeholder="Ex: Mouse Gamer Logitech G502" required>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="categoria">Categoria</label>
                        <input type="text" id="categoria" name="categoria" placeholder="Ex: Bebidas, Informatica...">
                    </div>
                    <div class="form-group">
                        <label for="preco">Preço de Venda (R$) *</label>
                        <input type="number" step="0.01" min="0" id="preco" name="preco" placeholder="0,00" required>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="precoCusto">Preço de Custo (R$)</label>
                        <input type="number" step="0.01" min="0" id="precoCusto" name="precoCusto" placeholder="0,00" title="Custo de aquisição junto ao fornecedor">
                    </div>
                    <div class="form-group">
                        <label for="quantidade">Estoque Atual</label>
                        <input type="number" min="0" name="quantidade" id="quantidade" value="0">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="estoqueMinimo">Estoque Mínimo (Alerta de Reposição)</label>
                        <input type="number" min="1" name="estoqueMinimo" id="estoqueMinimo" value="10" title="Quantidade limite para disparar sugestão de compra">
                    </div>
                    <div class="form-group">
                        <label for="nomeFornecedor">Fornecedor Parceiro</label>
                        <select id="nomeFornecedor" name="nomeFornecedor">
                            <option value="">-- Opcional --</option>
                            <?php foreach ($listaFornecedores as $forn): ?>
                                <option value="<?= htmlspecialchars($forn['nomeFornecedor']) ?>">
                                    <?= htmlspecialchars($forn['nomeFornecedor']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="lote">Lote de Fabricação (Opcional)</label>
                        <input type="text" id="lote" name="lote" placeholder="Ex: LOT-2026-A1">
                    </div>
                    <div class="form-group">
                        <label for="dataValidade">Data de Validade (Logística FEFO)</label>
                        <input type="date" id="dataValidade" name="dataValidade">
                    </div>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição Completa</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="Detalhes, especificações e observações do item..."></textarea>
                </div>

                <div class="form-group">
                    <label for="imagem">Foto do Produto</label>
                    <input type="file" id="imagem" name="imagem" accept="image/*">
                </div>
            </div>

            <div class="drawer-footer">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="submit" name="acao" value="salvar" class="btn-primary">Salvar Produto</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts de Exportação Client-side -->
<script>
    const produtosData = <?= json_encode($produtos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="javaScript/exportarexcel.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="javaScript/exportarPDF.js"></script>

<?php require_once "templates/footer.php"; ?>