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
        $localizacao = trim($_POST['localizacao'] ?? 'A-01-01');
        if ($localizacao === '') {
            $localizacao = 'A-01-01';
        }
        $imagem = salvarImagemProduto();

        if ($nomeProduto !== '') {
            $conexao = conectar();
            $sql = "INSERT INTO produto (idUsuario, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, descricao, nomeFornecedor, lote, dataValidade, localizacao, imagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "issddiissssss", $idUsuario, $nomeProduto, $categoria, $preco, $precoCusto, $quantidade, $estoqueMinimo, $descricao, $nomeFornecedor, $lote, $dataValidade, $localizacao, $imagem);
            
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
                                            <div style="margin-top: 4px;">
                                                <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 2px 7px; background: rgba(99, 102, 241, 0.1); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-weight: 700;" title="Endereço no armazém (WMS)">
                                                    📍 <?= htmlspecialchars($p['localizacao'] ?? 'A-01-01') ?>
                                                </span>
                                            </div>
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
                <!-- CONSULTA INTELIGENTE VIA API EXTERNA (EAN-13 / GTIN) -->
                <div class="card-api-ean" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: var(--radius-sm); padding: 14px 16px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                            <span>⚡</span> Preenchimento Rápido via API (EAN-13)
                        </span>
                        <span style="font-size: 10px; background: rgba(99, 102, 241, 0.15); color: var(--primary); padding: 2px 7px; border-radius: 4px; font-weight: 700;">Open Food Facts Global</span>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="input-ean-api" placeholder="Digite ou bipe o código de barras (Ex: 7894900011517)..." style="flex: 1; padding: 9px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 13px; font-family: 'JetBrains Mono', monospace;" maxlength="14" onkeydown="if(event.key==='Enter'){event.preventDefault(); consultarEanAPI();}">
                        <button type="button" id="btn-consultar-ean" onclick="consultarEanAPI()" class="btn-primary" style="padding: 9px 14px; font-size: 12.5px; white-space: nowrap; gap: 6px;">
                            <span>🔍</span> Consultar API
                        </button>
                    </div>
                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 8px; font-size: 11px;">
                        <span style="color: var(--text-muted); font-weight: 600;">Exemplos rápidos:</span>
                        <button type="button" onclick="testarEan('7894900011517')" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-main);">🥤 Coca-Cola 2L</button>
                        <button type="button" onclick="testarEan('7891000100103')" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-main);">🥛 Leite Moça</button>
                        <button type="button" onclick="testarEan('7892840222949')" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-main);">🍟 Ruffles</button>
                    </div>
                    <div id="ean-status-msg" style="display:none; margin-top: 8px; font-size: 12px; padding: 7px 10px; border-radius: 4px;"></div>
                </div>

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
                    <label for="localizacao">Endereço no Armazém (WMS) *</label>
                    <input type="text" id="localizacao" name="localizacao" value="A-01-01" placeholder="Ex: A-01-02 (Corredor A · Prat. 01 · Nível 2)" required title="Código de localização no almoxarifado para separação e picking">
                    <small style="color: var(--text-muted); font-size: 11.5px; margin-top: 3px; display: block;">Identificação da prateleira/gôndola usada para otimizar rotas de coleta (Picking List).</small>
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

<!-- Script de Integração com a API de Códigos de Barras EAN -->
<script>
    function testarEan(codigo) {
        document.getElementById('input-ean-api').value = codigo;
        consultarEanAPI();
    }

    async function consultarEanAPI() {
        const input = document.getElementById('input-ean-api');
        const btn = document.getElementById('btn-consultar-ean');
        const msg = document.getElementById('ean-status-msg');
        const ean = input.value.replace(/\D/g, '').trim();

        if (!ean || ean.length < 7) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239, 68, 68, 0.1)';
            msg.style.color = '#ef4444';
            msg.style.border = '1px solid rgba(239, 68, 68, 0.2)';
            msg.textContent = 'Por favor, informe um código de barras EAN válido (mínimo 7 dígitos).';
            return;
        }

        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<span>⏳</span> Consultando...';
        btn.disabled = true;
        msg.style.display = 'none';

        try {
            const response = await fetch(`api/consulta_ean.php?ean=${ean}`);
            const data = await response.json();

            if (data.sucesso) {
                if (data.nome) document.getElementById('nomeProduto').value = data.nome;
                if (data.categoria) document.getElementById('categoria').value = data.categoria;
                if (data.descricao) document.getElementById('descricao').value = data.descricao;
                
                // Fornecedor / Fabricante
                const fornSelect = document.getElementById('nomeFornecedor');
                if (data.marca && fornSelect) {
                    let encontrou = false;
                    for (let opt of fornSelect.options) {
                        if (opt.value.toLowerCase().includes(data.marca.toLowerCase())) {
                            opt.selected = true;
                            encontrou = true;
                            break;
                        }
                    }
                    if (!encontrou) {
                        const desc = document.getElementById('descricao');
                        desc.value += (desc.value ? '\n' : '') + 'Fabricante/Marca: ' + data.marca;
                    }
                }

                // Código do Lote sugerido como o próprio EAN
                const loteInp = document.getElementById('lote');
                if (loteInp && !loteInp.value) {
                    loteInp.value = 'EAN-' + ean;
                }

                msg.style.display = 'block';
                msg.style.background = 'rgba(16, 185, 129, 0.12)';
                msg.style.color = '#10b981';
                msg.style.border = '1px solid rgba(16, 185, 129, 0.25)';
                msg.innerHTML = `✅ <strong>Produto localizado na API Global!</strong> Dados de "<em>${data.nome}</em>" preenchidos automaticamente.`;

                document.getElementById('preco').focus();
            } else {
                msg.style.display = 'block';
                msg.style.background = 'rgba(245, 158, 11, 0.12)';
                msg.style.color = '#f59e0b';
                msg.style.border = '1px solid rgba(245, 158, 11, 0.25)';
                msg.textContent = data.mensagem || 'Produto não encontrado na base de dados global.';
            }
        } catch(err) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239, 68, 68, 0.1)';
            msg.style.color = '#ef4444';
            msg.style.border = '1px solid rgba(239, 68, 68, 0.2)';
            msg.textContent = 'Falha ao conectar com o serviço de consulta da API.';
        } finally {
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    }
</script>

<?php require_once "templates/footer.php"; ?>