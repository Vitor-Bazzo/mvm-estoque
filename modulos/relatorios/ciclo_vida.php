<?php
require_once __DIR__ . "/../../config/conexao.php";

if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: ' . url('modulos/auth/login.php'));
    exit;
}

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

// Ação inteligente: Aplicar desconto promocional (-15%) para acelerar giro de estoque parado/próximo do vencimento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aplicar_promocao') {
    if (validarTokenCSRF()) {
        $idProd = (int)($_POST['idProduto'] ?? 0);
        $desconto = min(50, max(5, (float)($_POST['desconto'] ?? 15)));
        if ($idProd > 0 && is_object($conexao)) {
            $stmtP = mysqli_prepare($conexao, "SELECT nomeProduto, preco FROM produto WHERE idProduto = ? AND idUsuario = ?");
            mysqli_stmt_bind_param($stmtP, "ii", $idProd, $idUsuario);
            mysqli_stmt_execute($stmtP);
            $prodRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP));
            if ($prodRow) {
                $precoAntigo = (float)$prodRow['preco'];
                $novoPreco = round($precoAntigo * (1 - ($desconto / 100)), 2);
                $stmtUpd = mysqli_prepare($conexao, "UPDATE produto SET preco = ? WHERE idProduto = ? AND idUsuario = ?");
                mysqli_stmt_bind_param($stmtUpd, "dii", $novoPreco, $idProd, $idUsuario);
                mysqli_stmt_execute($stmtUpd);
                $_SESSION['flash_plm'] = [
                    'tipo' => 'sucesso',
                    'texto' => "🔥 Ação inteligente aplicada! Preço de <strong>" . htmlspecialchars($prodRow['nomeProduto']) . "</strong> reduzido em {$desconto}% (para <strong>R$ " . number_format($novoPreco, 2, ',', '.') . "</strong>)."
                ];
            }
        }
    }
    if (is_object($conexao)) mysqli_close($conexao);
    header('Location: ciclo_vida.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'importar_sugestao') {
    if (validarTokenCSRF() && is_object($conexao)) {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome !== '') {
            $cat = trim($_POST['categoria'] ?? 'Geral');
            $ean = trim($_POST['ean'] ?? '');
            $preco = (float)($_POST['preco'] ?? 49.90);
            $custo = (float)($_POST['precoCusto'] ?? round($preco * 0.62, 2));
            $stmtIns = mysqli_prepare($conexao, "INSERT INTO produto (idUsuario, codigoBarras, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, localizacao, descricao) VALUES (?, ?, ?, ?, ?, ?, 10, 5, 'A-01-01', 'Produto importado via Sugestão Inteligente de Mercado MVM.')");
            mysqli_stmt_bind_param($stmtIns, "isssdd", $idUsuario, $ean, $nome, $cat, $preco, $custo);
            mysqli_stmt_execute($stmtIns);
            $_SESSION['flash_plm'] = [
                'tipo' => 'sucesso',
                'texto' => "🚀 Produto <strong>" . htmlspecialchars($nome) . "</strong> importado para o seu catálogo com sucesso!"
            ];
        }
    }
    if (is_object($conexao)) mysqli_close($conexao);
    header('Location: ciclo_vida.php');
    exit;
}

$produtos = [];
$movimentosPorProd = [];
if (is_object($conexao)) {
    $stmt = mysqli_prepare($conexao, "SELECT * FROM produto WHERE idUsuario = ? ORDER BY nomeProduto ASC");
    mysqli_stmt_bind_param($stmt, "i", $idUsuario);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) $produtos = mysqli_fetch_all($res, MYSQLI_ASSOC);

    $stmtM = mysqli_prepare($conexao, "SELECT idProduto, tipoMovimento, SUM(quantidade) AS qtd, COUNT(*) AS ops FROM movimento WHERE idUsuario = ? AND dataDevolucao IS NULL GROUP BY idProduto, tipoMovimento");
    mysqli_stmt_bind_param($stmtM, "i", $idUsuario);
    mysqli_stmt_execute($stmtM);
    $resM = mysqli_stmt_get_result($stmtM);
    if ($resM) {
        while ($rm = mysqli_fetch_assoc($resM)) {
            $pid = (int)$rm['idProduto'];
            if (!isset($movimentosPorProd[$pid])) $movimentosPorProd[$pid] = ['SAIDA' => 0, 'ENTRADA' => 0, 'OPS' => 0];
            $movimentosPorProd[$pid][$rm['tipoMovimento']] = (int)$rm['qtd'];
            $movimentosPorProd[$pid]['OPS'] += (int)$rm['ops'];
        }
    }
    mysqli_close($conexao);
}

$resumo = [
    'LANCAMENTO'  => ['label' => 'Lançamento',       'icone' => '🚀', 'cor' => '#3b82f6', 'qtd' => 0, 'capital' => 0.0],
    'CRESCIMENTO' => ['label' => 'Crescimento',      'icone' => '📈', 'cor' => '#10b981', 'qtd' => 0, 'capital' => 0.0],
    'MATURIDADE'  => ['label' => 'Maturidade',       'icone' => '⚖️', 'cor' => '#f59e0b', 'qtd' => 0, 'capital' => 0.0],
    'DECLINIO'    => ['label' => 'Declínio / Risco', 'icone' => '📉', 'cor' => '#ef4444', 'qtd' => 0, 'capital' => 0.0],
];

$itens = [];
$hojeTs = strtotime(date('Y-m-d'));
foreach ($produtos as $p) {
    $pid = (int)$p['idProduto'];
    $est = (int)$p['quantidade'];
    $min = max(1, (int)($p['estoqueMinimo'] ?? 10));
    $preco = (float)$p['preco'];
    $custo = (float)$p['precoCusto'] > 0 ? (float)$p['precoCusto'] : ($preco * 0.6);
    $capital = round($est * $custo, 2);
    $margem = $preco > 0 ? round((($preco - $custo) / $preco) * 100, 1) : 0;

    $totalVendido = $movimentosPorProd[$pid]['SAIDA'] ?? 0;
    $totalOps = $movimentosPorProd[$pid]['OPS'] ?? 0;
    $mediaDia = $totalVendido / 30;
    $diasCobertura = $mediaDia > 0 ? (int)round($est / $mediaDia) : ($est > 0 ? 999 : 0);

    $diasVal = null;
    if (!empty($p['dataValidade'])) {
        $diasVal = (int)round((strtotime($p['dataValidade']) - $hojeTs) / 86400);
    }

    $fase = 'MATURIDADE';
    $motivo = 'Demanda estável e estoque equilibrado';
    $acao = 'Manter reposição padrão';
    $podePromocao = false;

    if (($diasVal !== null && $diasVal <= 30 && $est > 0) || ($totalVendido === 0 && $est > $min) || $diasCobertura > 120) {
        $fase = 'DECLINIO';
        if ($diasVal !== null && $diasVal <= 0) {
            $motivo = 'Lote vencido em estoque — bloqueio imediato FEFO';
            $acao = 'Auditar e baixar perda no Kardex';
        } elseif ($diasVal !== null && $diasVal <= 30) {
            $motivo = "Validade crítica ({$diasVal} dia(s)) — risco de perda";
            $acao = 'Aplicar queima promocional (-15%)';
            $podePromocao = true;
        } else {
            $motivo = $totalVendido === 0 ? 'Sem saídas registradas (capital parado)' : "Cobertura excessiva ({$diasCobertura} dias)";
            $acao = 'Aplicar promoção de giro (-15%)';
            $podePromocao = true;
        }
    } elseif ($totalVendido >= 20 || ($totalOps >= 3 && $est <= $min * 2)) {
        $fase = 'CRESCIMENTO';
        $motivo = "Alto giro ({$totalVendido} un vendidas)";
        $acao = $est <= $min ? 'Repor estoque com urgência' : 'Garantir estoque de segurança';
    } elseif ($totalOps <= 1 && $est > 0) {
        $fase = 'LANCAMENTO';
        $motivo = 'Item recém-introduzido aguardando tração comercial';
        $acao = 'Destacar no PDV e criar combo';
    }

    $resumo[$fase]['qtd']++;
    $resumo[$fase]['capital'] += $capital;

    $p['fase'] = $fase;
    $p['faseInfo'] = $resumo[$fase];
    $p['totalVendido'] = $totalVendido;
    $p['capitalParado'] = $capital;
    $p['margemPct'] = $margem;
    $p['diasCobertura'] = $diasCobertura;
    $p['motivo'] = $motivo;
    $p['acaoSugerida'] = $acao;
    $p['podePromocao'] = $podePromocao;
    $itens[] = $p;
}

$faseFiltro = strtoupper($_GET['fase'] ?? 'TODOS');
$itensExibidos = ($faseFiltro === 'TODOS') ? $itens : array_values(array_filter($itens, fn($i) => $i['fase'] === $faseFiltro));
$totalCapital = array_sum(array_column($resumo, 'capital'));

$titulo_pagina = "Ciclo de Vida dos Produtos (PLM) - MVM";
require_once __DIR__ . "/../../templates/header.php";
?>

<div class="page-container">
    <?php if (!empty($_SESSION['flash_plm'])): ?>
        <div class="alert-box <?= $_SESSION['flash_plm']['tipo'] === 'sucesso' ? 'success' : 'error' ?>">
            <span>🔥</span>
            <p><?= $_SESSION['flash_plm']['texto'] ?></p>
        </div>
        <?php unset($_SESSION['flash_plm']); ?>
    <?php endif; ?>

    <div class="page-header-row">
        <div class="page-header-titles">
            <h1>♻️ Gestão Inteligente do Ciclo de Vida dos Produtos (PLM)</h1>
            <p>Classificação automática de maturidade comercial, cobertura de estoque, capital parado e ações de queima FEFO</p>
        </div>
        <div class="page-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= url('modulos/produtos/produto.php') ?>" class="btn-secondary"><span>📦</span> Catálogo</a>
            <a href="<?= url('modulos/movimentos/movimento.php') ?>" class="btn-primary"><span>➕</span> Registrar Saída / Reposição</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <?php foreach (['LANCAMENTO', 'CRESCIMENTO', 'MATURIDADE', 'DECLINIO'] as $chave): $info = $resumo[$chave]; $ativo = ($faseFiltro === $chave); ?>
            <a href="ciclo_vida.php?fase=<?= $ativo ? 'TODOS' : $chave ?>" style="text-decoration:none; background: var(--card); border: 2px solid <?= $ativo ? $info['cor'] : 'var(--border)' ?>; border-top: 4px solid <?= $info['cor'] ?>; border-radius: var(--radius); padding: 18px 20px; display: block;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:12px; font-weight:800; text-transform:uppercase; color:<?= $info['cor'] ?>;"><?= $info['icone'] ?> <?= $info['label'] ?></span>
                    <span style="font-size:11px; font-weight:700; color:var(--text-muted);"><?= $ativo ? 'Filtrado ✓' : 'Filtrar' ?></span>
                </div>
                <div style="font-size:28px; font-weight:900; color:var(--text);"><?= $info['qtd'] ?> <small style="font-size:13px; font-weight:600; color:var(--text-muted);">SKU(s)</small></div>
                <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Capital alocado: <strong style="color:var(--text);">R$ <?= number_format($info['capital'], 2, ',', '.') ?></strong></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="pbi-grid-2cols" style="margin-bottom: 24px;">
        <div class="pbi-visual-card">
            <div class="pbi-visual-header">
                <div class="pbi-visual-title-box">
                    <h3><span>📊</span> Distribuição do Portfólio por Estágio (PLM)</h3>
                    <p>Quantidade de SKUs em Lançamento, Crescimento, Maturidade e Declínio</p>
                </div>
            </div>
            <div id="chart-plm-fases" style="min-height: 250px;"></div>
        </div>

        <div class="pbi-visual-card">
            <div class="pbi-visual-header">
                <div class="pbi-visual-title-box">
                    <h3><span>💡</span> Diagnóstico Automático de Capital</h3>
                    <p>Visão consolidada do capital imobilizado e oportunidades de giro</p>
                </div>
            </div>
            <div style="padding: 10px 4px; display:flex; flex-direction:column; gap:12px;">
                <div style="background:var(--bg-subtle); border:1px solid var(--border); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11.5px; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Capital Total Imobilizado em Custo</div>
                    <div style="font-size:24px; font-weight:900; color:var(--primary); margin-top:2px;">R$ <?= number_format($totalCapital, 2, ',', '.') ?></div>
                </div>
                <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.28); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11.5px; color:#ef4444; text-transform:uppercase; font-weight:800;">⚠️ Atenção: Capital em Declínio / Risco FEFO</div>
                    <div style="font-size:22px; font-weight:900; color:#ef4444; margin-top:2px;">R$ <?= number_format($resumo['DECLINIO']['capital'], 2, ',', '.') ?> <small style="font-size:12px; font-weight:600;">(<?= $resumo['DECLINIO']['qtd'] ?> item(ns))</small></div>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Use a ação de promoção automática abaixo para recuperar liquidez antes do vencimento.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="data-table" id="tabela-plm">
                <thead>
                    <tr>
                        <th style="width: 24%;">Produto &amp; Preço</th>
                        <th style="width: 14%;">Estágio (PLM)</th>
                        <th style="width: 14%;">Giro &amp; Cobertura</th>
                        <th style="width: 14%;">Capital / Margem</th>
                        <th style="width: 18%;">Diagnóstico Inteligente</th>
                        <th style="width: 16%; text-align: right;">Ação Recomendada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itensExibidos)): ?>
                        <tr class="empty-row"><td colspan="6" style="text-align:center; padding:42px; color:var(--text-muted);">Nenhum produto encontrado neste estágio.</td></tr>
                    <?php else: foreach ($itensExibidos as $it): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($it['nomeProduto']) ?></strong>
                                <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;">Estoque: <strong><?= (int)$it['quantidade'] ?> un</strong> &bull; Preço: <strong>R$ <?= number_format((float)$it['preco'], 2, ',', '.') ?></strong></div>
                            </td>
                            <td>
                                <span style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:800; background:<?= $it['faseInfo']['cor'] ?>1f; color:<?= $it['faseInfo']['cor'] ?>; border:1px solid <?= $it['faseInfo']['cor'] ?>44;">
                                    <?= $it['faseInfo']['icone'] ?> <?= $it['faseInfo']['label'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size:12.5px; font-weight:700;"><?= $it['totalVendido'] ?> un vendidas</div>
                                <div style="font-size:11px; color:var(--text-muted);">Cobertura: <?= $it['diasCobertura'] >= 999 ? 'Sem giro' : $it['diasCobertura'] . ' dias' ?></div>
                            </td>
                            <td>
                                <div style="font-family:'JetBrains Mono',monospace; font-size:12.5px; font-weight:700;">R$ <?= number_format($it['capitalParado'], 2, ',', '.') ?></div>
                                <div style="font-size:11px; color:<?= $it['margemPct'] >= 20 ? '#10b981' : '#f59e0b' ?>;">Margem: <?= $it['margemPct'] ?>%</div>
                            </td>
                            <td>
                                <div style="font-size:12px; color:var(--text); font-weight:600;"><?= htmlspecialchars($it['motivo']) ?></div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Sugestão: <?= htmlspecialchars($it['acaoSugerida']) ?></div>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($it['podePromocao']): ?>
                                    <form action="ciclo_vida.php" method="POST" style="display:inline;" onsubmit="return confirm('Aplicar desconto promocional de 15% neste produto?');">
                                        <?= campoCSRF() ?>
                                        <input type="hidden" name="acao" value="aplicar_promocao">
                                        <input type="hidden" name="idProduto" value="<?= (int)$it['idProduto'] ?>">
                                        <input type="hidden" name="desconto" value="15">
                                        <button type="submit" class="btn-primary" style="padding:6px 11px; font-size:11.5px; background:#ef4444; border:none;">🔥 Queima -15%</button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?= url('modulos/relatorios/kardex.php') ?>?idProduto=<?= (int)$it['idProduto'] ?>" class="btn-secondary" style="padding:6px 11px; font-size:11.5px;">📜 Ver Kardex</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pbi-visual-card" style="margin-top: 26px;">
        <div class="pbi-visual-header" style="border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div class="pbi-visual-title-box">
                <h3><span>🌐</span> Sugestões de Produtos da Internet para Aumentar seu Faturamento</h3>
                <p id="plm-sugestoes-sub">Analisando seu perfil comercial e buscando oportunidades reais na web...</p>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="input-nicho-plm" placeholder="Ex: bebidas, informatica, cosmeticos..." style="padding:8px 12px; border-radius:8px; border:1px solid var(--border); background:var(--bg-subtle); color:var(--text); font-size:12.5px;">
                <button type="button" onclick="carregarSugestoesWeb()" class="btn-primary" style="padding:8px 14px; font-size:12.5px;">🔍 Buscar na Internet</button>
            </div>
        </div>
        <div id="grid-sugestoes-web" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap:16px;">
            <div style="grid-column:1/-1; text-align:center; padding:30px; color:var(--text-muted);">⏳ Buscando produtos compatíveis com o seu perfil na internet...</div>
        </div>
    </div>
</div>

<script>
async function carregarSugestoesWeb() {
    const grid = document.getElementById('grid-sugestoes-web');
    const sub = document.getElementById('plm-sugestoes-sub');
    const nicho = (document.getElementById('input-nicho-plm').value || '').trim();
    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:30px; color:var(--text-muted);">⏳ Pesquisando produtos na internet para o seu perfil...</div>';
    try {
        const resp = await fetch('<?= url('api/sugestoes_produtos.php') ?>' + (nicho ? '?nicho=' + encodeURIComponent(nicho) : ''));
        const data = await resp.json();
        if (!data.sucesso || !data.sugestoes || data.sugestoes.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:24px; color:var(--text-muted);">Nenhuma sugestão encontrada. Tente outro termo acima.</div>';
            return;
        }
        sub.innerHTML = 'Perfil detectado: <strong>' + data.perfil_nicho + '</strong> &bull; Fonte: <strong>' + data.fonte + '</strong>';
        grid.innerHTML = data.sugestoes.map(s => {
            const imgHtml = s.imagem
                ? '<img src="' + s.imagem + '" alt="" style="width:58px;height:58px;object-fit:contain;border-radius:8px;background:#fff;padding:4px;border:1px solid var(--border);flex-shrink:0;">'
                : '<div style="width:58px;height:58px;border-radius:8px;background:var(--bg-subtle);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">🚀</div>';
            return '<div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;flex-direction:column;justify-content:space-between;gap:12px;">'
                + '<div style="display:flex;gap:12px;align-items:flex-start;">' + imgHtml
                + '<div><strong style="font-size:13.5px;color:var(--text);display:block;line-height:1.35;">' + s.nome + '</strong>'
                + '<span style="font-size:11px;color:#10b981;font-weight:700;display:block;margin-top:4px;">💡 ' + s.motivo + '</span></div></div>'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;background:var(--card);padding:10px;border-radius:8px;border:1px solid var(--border);font-size:12px;">'
                + '<div><span style="color:var(--text-muted);display:block;font-size:10.5px;">Preço Sugerido</span><strong style="color:var(--primary);font-size:14px;">R$ ' + Number(s.preco).toFixed(2).replace('.', ',') + '</strong></div>'
                + '<div><span style="color:var(--text-muted);display:block;font-size:10.5px;">Potencial Extra/Mês</span><strong style="color:#10b981;font-size:14px;">+R$ ' + Number(s.potencialMes).toFixed(2).replace('.', ',') + '</strong></div>'
                + '</div>'
                + '<div style="display:flex;gap:8px;align-items:center;justify-content:space-between;">'
                + '<a href="' + s.link + '" target="_blank" rel="noopener noreferrer" style="font-size:11.5px;color:#eab308;font-weight:700;text-decoration:none;">🛒 Ver no Mercado Livre ↗</a>'
                + '<form action="ciclo_vida.php" method="POST" style="margin:0;">'
                + '<?= campoCSRF() ?>'
                + '<input type="hidden" name="acao" value="importar_sugestao">'
                + '<input type="hidden" name="nome" value="' + s.nome.replace(/"/g, '&quot;') + '">'
                + '<input type="hidden" name="categoria" value="' + (s.categoria || 'Geral').replace(/"/g, '&quot;') + '">'
                + '<input type="hidden" name="ean" value="' + (s.ean || '') + '">'
                + '<input type="hidden" name="preco" value="' + s.preco + '">'
                + '<input type="hidden" name="precoCusto" value="' + s.precoCusto + '">'
                + '<button type="submit" class="btn-primary" style="padding:7px 12px;font-size:11.5px;">➕ Importar para Catálogo</button>'
                + '</form></div></div>';
        }).join('');
    } catch (e) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:24px; color:#ef4444;">Erro ao consultar sugestões na internet.</div>';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    carregarSugestoesWeb();
    if (window.ApexCharts && document.querySelector('#chart-plm-fases')) {
        new ApexCharts(document.querySelector('#chart-plm-fases'), {
            chart: { type: 'donut', height: 250 },
            series: [<?= $resumo['LANCAMENTO']['qtd'] ?>, <?= $resumo['CRESCIMENTO']['qtd'] ?>, <?= $resumo['MATURIDADE']['qtd'] ?>, <?= $resumo['DECLINIO']['qtd'] ?>],
            labels: ['🚀 Lançamento', '📈 Crescimento', '⚖️ Maturidade', '📉 Declínio / Risco'],
            colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
            legend: { position: 'bottom' }
        }).render();
    }
});
</script>

<?php require_once __DIR__ . "/../../templates/footer.php"; ?>
