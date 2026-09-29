<?php
$titulo_pagina = "Gerador de Etiquetas de Estoque - MVM";
require_once "templates/header.php";
require_once "config/conexao.php";

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: login.php');
    exit;
}

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

// Busca os produtos do usuário
$produtos = [];
$stmt = mysqli_prepare($conexao, "SELECT idProduto, nomeProduto, categoria, preco, quantidade, nomeFornecedor FROM produto WHERE idUsuario = ? ORDER BY nomeProduto ASC");
mysqli_stmt_bind_param($stmt, "i", $idUsuario);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) {
    $produtos = mysqli_fetch_all($res, MYSQLI_ASSOC);
}
mysqli_close($conexao);
?>

<div class="page-container">
    
    <!-- Cabeçalho da Página -->
    <div class="page-header-row no-print">
        <div class="page-header-titles">
            <h1>Gerador de Etiquetas de Prateleira &amp; Código de Barras</h1>
            <p>Selecione os produtos para gerar etiquetas padronizadas prontas para impressão e gôndolas</p>
        </div>
        <div class="page-header-actions">
            <button type="button" onclick="imprimirEtiquetas()" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                <span>🖨️</span> Imprimir Etiquetas
            </button>
        </div>
    </div>

    <!-- Barra de Filtros e Seleção -->
    <div class="table-card no-print" style="margin-bottom: 24px; padding: 18px 24px;">
        <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 12px; align-items: center;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer; user-select: none;">
                    <input type="checkbox" id="check-todos" checked onchange="alternarTodos(this)" style="width: 17px; height: 17px; cursor: pointer;">
                    Selecionar Todos (<span id="count-selecionados"><?= count($produtos) ?></span> selecionados)
                </label>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <label for="formato-grid" style="font-size: 13px; color: var(--text-muted); font-weight: 600;">Tamanho da Etiqueta:</label>
                <select id="formato-grid" onchange="alterarGrid(this.value)" style="padding: 7px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 13px;">
                    <option value="padrao">Padrão Gôndola (3 por linha)</option>
                    <option value="grande">Grande / Caixa Master (2 por linha)</option>
                    <option value="compacta">Compacta / Almoxarifado (4 por linha)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ÁREA DE ETIQUETAS RENDERIZADAS -->
    <div id="grid-etiquetas" class="grid-etiquetas-padrao">
        <?php if (!empty($produtos)): ?>
            <?php foreach ($produtos as $p): ?>
                <div class="etiqueta-card" id="etiqueta-<?= (int)$p['idProduto'] ?>" data-prod-id="<?= (int)$p['idProduto'] ?>">
                    <!-- Checkbox de inclusão na impressão (visível apenas na tela) -->
                    <div class="etiqueta-check no-print">
                        <input type="checkbox" class="prod-check" value="<?= (int)$p['idProduto'] ?>" checked onchange="atualizarContagem()">
                    </div>

                    <!-- Cabeçalho da Etiqueta -->
                    <div class="etiqueta-header">
                        <div class="etiqueta-brand">
                            <img src="img/mascote_mvm.svg?v=3" alt="MVM" class="etiqueta-mini-logo">
                            <span>MVM ESTOQUE</span>
                        </div>
                        <span class="etiqueta-cat"><?= htmlspecialchars($p['categoria'] ?: 'Geral') ?></span>
                    </div>

                    <!-- Nome do Produto -->
                    <div class="etiqueta-nome" title="<?= htmlspecialchars($p['nomeProduto']) ?>">
                        <?= htmlspecialchars($p['nomeProduto']) ?>
                    </div>

                    <!-- Preço em destaque -->
                    <div class="etiqueta-preco-box">
                        <span class="etiqueta-moeda">R$</span>
                        <span class="etiqueta-valor"><?= number_format((float)$p['preco'], 2, ',', '.') ?></span>
                    </div>

                    <!-- Código de Barras Real Renderizado -->
                    <div class="etiqueta-barcode-box">
                        <svg class="barcode-svg" data-code="MVM<?= sprintf('%06d', $p['idProduto']) ?>"></svg>
                        <div class="etiqueta-code-text">CÓD: <?= sprintf('%06d', $p['idProduto']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-card no-print" style="text-align: center; padding: 50px 20px;">
                <div style="font-size: 38px; margin-bottom: 10px;">🏷️</div>
                <h3>Nenhum produto cadastrado para gerar etiquetas</h3>
                <p style="color: var(--text-muted); font-size: 13.5px; margin-top: 6px;">Cadastre produtos no catálogo para gerar as etiquetas de código de barras.</p>
                <a href="produto.php" class="btn-primary" style="margin-top: 18px; display: inline-block;">Ir para Catálogo</a>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Biblioteca JsBarcode para geração vetorial e nítida de código de barras CODE128 -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<style>
/* ESTILOS DE ETIQUETAS NA TELA */
.grid-etiquetas-padrao {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}
.grid-etiquetas-grande {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}
.grid-etiquetas-compacta {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 40px;
}

.etiqueta-card {
    background: #ffffff;
    color: #0f172a;
    border: 2px dashed #94a3b8;
    border-radius: 8px;
    padding: 16px 18px;
    position: relative;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    break-inside: avoid;
}
.etiqueta-card.desmarcada {
    opacity: 0.35;
    filter: grayscale(1);
    border-color: #cbd5e1;
}

.etiqueta-check {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
}
.etiqueta-check input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.etiqueta-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1.5px solid #00704A;
    padding-bottom: 6px;
    margin-bottom: 10px;
}
.etiqueta-brand {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 800;
    color: #00704A;
    letter-spacing: 0.5px;
}
.etiqueta-mini-logo {
    width: 20px;
    height: 20px;
    border-radius: 50%;
}
.etiqueta-cat {
    font-size: 10.5px;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
}

.etiqueta-nome {
    font-size: 14px;
    font-weight: 800;
    line-height: 1.35;
    color: #0f172a;
    margin-bottom: 10px;
    height: 38px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.etiqueta-preco-box {
    display: flex;
    align-items: baseline;
    gap: 4px;
    margin-bottom: 10px;
}
.etiqueta-moeda {
    font-size: 13px;
    font-weight: 700;
    color: #00704A;
}
.etiqueta-valor {
    font-size: 24px;
    font-weight: 900;
    font-family: 'JetBrains Mono', monospace;
    color: #00704A;
    letter-spacing: -0.5px;
}

.etiqueta-barcode-box {
    text-align: center;
    background: #f8fafc;
    padding: 8px 6px 4px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
.etiqueta-barcode-box svg {
    max-width: 100%;
    height: 38px;
    display: block;
    margin: 0 auto;
}
.etiqueta-code-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9.5px;
    color: #64748b;
    font-weight: 600;
    margin-top: 3px;
}

/* REGRAS DE IMPRESSÃO (A4 E BOBINA) */
@media print {
    body {
        background: #fff !important;
        color: #000 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .no-print, .main-header, .main-footer, .sidebar-top, .sidebar-footer {
        display: none !important;
    }
    .page-container {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    #grid-etiquetas {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 12px !important;
        margin: 0 !important;
        padding: 10px !important;
    }
    .etiqueta-card {
        box-shadow: none !important;
        border: 1px solid #000 !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    .etiqueta-card.desmarcada {
        display: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Renderiza todos os códigos de barras via JsBarcode
    const barcodeElements = document.querySelectorAll('.barcode-svg');
    barcodeElements.forEach(el => {
        const code = el.getAttribute('data-code');
        try {
            JsBarcode(el, code, {
                format: "CODE128",
                lineColor: "#0f172a",
                width: 1.6,
                height: 34,
                displayValue: false,
                margin: 0
            });
        } catch(e) {
            console.error("Erro no código de barras:", e);
        }
    });
});

function alternarTodos(checkbox) {
    const checks = document.querySelectorAll('.prod-check');
    checks.forEach(c => {
        c.checked = checkbox.checked;
        const card = c.closest('.etiqueta-card');
        if (card) {
            card.classList.toggle('desmarcada', !checkbox.checked);
        }
    });
    atualizarContagem();
}

function atualizarContagem() {
    const checks = document.querySelectorAll('.prod-check:checked');
    const totalChecks = document.querySelectorAll('.prod-check');
    
    document.querySelectorAll('.prod-check').forEach(c => {
        const card = c.closest('.etiqueta-card');
        if (card) {
            card.classList.toggle('desmarcada', !c.checked);
        }
    });

    const counter = document.getElementById('count-selecionados');
    if (counter) counter.innerText = checks.length;

    const checkTodos = document.getElementById('check-todos');
    if (checkTodos) {
        checkTodos.checked = (checks.length === totalChecks.length && totalChecks.length > 0);
    }
}

function alterarGrid(formato) {
    const grid = document.getElementById('grid-etiquetas');
    grid.className = '';
    if (formato === 'grande') {
        grid.className = 'grid-etiquetas-grande';
    } else if (formato === 'compacta') {
        grid.className = 'grid-etiquetas-compacta';
    } else {
        grid.className = 'grid-etiquetas-padrao';
    }
}

function imprimirEtiquetas() {
    const selecionados = document.querySelectorAll('.prod-check:checked');
    if (selecionados.length === 0) {
        alert('Por favor, selecione ao menos uma etiqueta para impressão.');
        return;
    }
    window.print();
}
</script>

<?php require_once "templates/footer.php"; ?>
