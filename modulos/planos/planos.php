<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/conexao.php";

$idUsuario = obterIdUsuarioLogado();
$conexao = conectar();

$planoAtual = 'ESSENCIAL';
$cicloAtual = 'MENSAL';
$validadePlano = null;

if ($idUsuario > 0 && is_object($conexao)) {
    // Processa troca/assinatura de plano via POST
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['acao']) && $_POST['acao'] === 'assinar_plano') {
        if (!validarTokenCSRF()) {
            $_SESSION['flash_plano'] = ['tipo' => 'erro', 'texto' => 'Sessão expirada ou token inválido.'];
        } else {
            $novoPlano = strtoupper(trim($_POST['plano_escolhido'] ?? 'ESSENCIAL'));
            $novoCiclo = strtoupper(trim($_POST['ciclo_escolhido'] ?? 'MENSAL'));
            $metodoPagamento = trim($_POST['metodo_pagamento'] ?? 'PIX');

            if (!in_array($novoPlano, ['ESSENCIAL', 'PADRAO', 'PREMIUM'])) {
                $novoPlano = 'ESSENCIAL';
            }
            if (!in_array($novoCiclo, ['MENSAL', 'ANUAL'])) {
                $novoCiclo = 'MENSAL';
            }

            $diasValidade = ($novoCiclo === 'ANUAL') ? 365 : 30;
            $stmtUpd = mysqli_prepare($conexao, "UPDATE usuario SET planoAssinatura = ?, cicloPlano = ?, validadePlano = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE idUsuario = ?");
            if ($stmtUpd) {
                mysqli_stmt_bind_param($stmtUpd, "ssii", $novoPlano, $novoCiclo, $diasValidade, $idUsuario);
                if (mysqli_stmt_execute($stmtUpd)) {
                    $_SESSION['planoUsuario'] = $novoPlano;
                    $nomesPlanos = [
                        'ESSENCIAL' => 'Essencial (Básico)',
                        'PADRAO'    => 'Padrão (Comercial)',
                        'PREMIUM'   => 'Premium (Enterprise 4K BI & WMS)'
                    ];
                    $rotuloPlano = $nomesPlanos[$novoPlano] ?? $novoPlano;
                    $_SESSION['flash_plano'] = [
                        'tipo'  => 'sucesso',
                        'texto' => "🎉 Assinatura confirmada via {$metodoPagamento}! Seu plano agora é <strong>{$rotuloPlano}</strong> ({$novoCiclo})."
                    ];
                }
                mysqli_stmt_close($stmtUpd);
            }
        }
        mysqli_close($conexao);
        header("Location: planos.php");
        exit;
    }

    // Busca dados atuais do plano do usuário
    $stmtUser = mysqli_prepare($conexao, "SELECT planoAssinatura, cicloPlano, validadePlano FROM usuario WHERE idUsuario = ? LIMIT 1");
    if ($stmtUser) {
        mysqli_stmt_bind_param($stmtUser, "i", $idUsuario);
        mysqli_stmt_execute($stmtUser);
        $resUser = mysqli_stmt_get_result($stmtUser);
        if ($resUser && $rowU = mysqli_fetch_assoc($resUser)) {
            $planoAtual = strtoupper($rowU['planoAssinatura'] ?: 'ESSENCIAL');
            $cicloAtual = strtoupper($rowU['cicloPlano'] ?: 'MENSAL');
            $validadePlano = $rowU['validadePlano'];
            $_SESSION['planoUsuario'] = $planoAtual;
        }
        mysqli_stmt_close($stmtUser);
    }
}

if (is_object($conexao)) {
    mysqli_close($conexao);
}

$titulo_pagina = "Planos & Assinaturas MVM - Escolha seu Nível";
require_once __DIR__ . "/../../templates/header.php";
?>

<style>
    .pricing-hero {
        text-align: center;
        max-width: 720px;
        margin: 10px auto 28px;
    }
    .pricing-badge-top {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        border-radius: 30px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        background: rgba(229, 9, 20, 0.12);
        color: #e50914;
        border: 1px solid rgba(229, 9, 20, 0.3);
        margin-bottom: 12px;
    }
    [data-theme="dark"] .pricing-badge-top {
        background: rgba(229, 9, 20, 0.2);
        color: #ff4d57;
    }
    .pricing-hero h1 {
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: var(--text);
        margin-bottom: 8px;
    }
    .pricing-hero p {
        font-size: 14.5px;
        color: var(--text-muted);
        line-height: 1.5;
    }

    /* SELETOR MENSAL / ANUAL */
    .billing-toggle-bar {
        display: inline-flex;
        align-items: center;
        background: var(--card);
        border: 1px solid var(--border);
        padding: 5px;
        border-radius: 40px;
        margin-top: 18px;
        box-shadow: var(--shadow-sm);
        gap: 4px;
    }
    .billing-btn {
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-size: 13px;
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 30px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .billing-btn.active {
        background: var(--primary);
        color: #ffffff;
        box-shadow: 0 3px 10px rgba(99, 91, 255, 0.35);
    }
    .discount-pill {
        background: #10b981;
        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 12px;
        text-transform: uppercase;
    }

    /* GRID DE 3 PLANOS ESTILO NETFLIX */
    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        align-items: stretch;
        margin-bottom: 36px;
    }
    .plan-card {
        background: var(--card);
        border: 1.5px solid var(--border);
        border-radius: 18px;
        padding: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }
    .plan-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }
    .plan-card.popular {
        border-color: #635bff;
        box-shadow: 0 12px 32px rgba(99, 91, 255, 0.16);
    }
    .plan-card.premium-tier {
        border-color: #e50914;
        box-shadow: 0 12px 34px rgba(229, 9, 20, 0.16);
    }
    .plan-card.is-current {
        outline: 3px solid #10b981;
        outline-offset: -3px;
    }

    /* CABEÇALHO GRADIENTE ESTILO NETFLIX CARDS */
    .plan-banner {
        padding: 22px 24px 18px;
        color: #ffffff;
        position: relative;
    }
    .banner-essencial {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    }
    .banner-padrao {
        background: linear-gradient(135deg, #4338ca 0%, #635bff 50%, #3b82f6 100%);
    }
    .banner-premium {
        background: linear-gradient(135deg, #7f1d1d 0%, #e50914 55%, #f59e0b 100%);
    }
    .plan-ribbon {
        position: absolute;
        top: 12px;
        right: 14px;
        background: rgba(255, 255, 255, 0.22);
        backdrop-filter: blur(6px);
        color: #ffffff;
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 3px 10px;
        border-radius: 20px;
        letter-spacing: 0.5px;
        border: 1px solid rgba(255, 255, 255, 0.35);
    }
    .plan-banner h3 {
        font-size: 20px;
        font-weight: 800;
        margin-bottom: 3px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .plan-banner span.plan-res {
        font-size: 12px;
        opacity: 0.9;
        font-weight: 600;
    }

    .plan-body {
        padding: 24px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .plan-price-box {
        margin-bottom: 20px;
        padding-bottom: 18px;
        border-bottom: 1px solid var(--border);
    }
    .plan-price-row {
        display: flex;
        align-items: baseline;
        gap: 4px;
    }
    .plan-currency {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-muted);
    }
    .plan-amount {
        font-size: 36px;
        font-weight: 900;
        color: var(--text);
        letter-spacing: -0.03em;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .plan-period {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 600;
    }
    .plan-annual-note {
        font-size: 11.5px;
        color: #10b981;
        font-weight: 700;
        margin-top: 4px;
        min-height: 18px;
    }

    .plan-features {
        list-style: none;
        padding: 0;
        margin: 0 0 24px 0;
        display: flex;
        flex-direction: column;
        gap: 11px;
        flex: 1;
    }
    .plan-features li {
        font-size: 13px;
        color: var(--text);
        display: flex;
        align-items: flex-start;
        gap: 10px;
        line-height: 1.4;
    }
    .plan-features li .feat-check {
        color: #10b981;
        font-weight: 900;
        flex-shrink: 0;
    }
    .plan-features li.disabled {
        color: var(--text-light);
        text-decoration: line-through;
        opacity: 0.65;
    }
    .plan-features li.disabled .feat-check {
        color: var(--text-light);
    }

    .btn-plan-select {
        width: 100%;
        padding: 13px 18px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 800;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-plan-essencial {
        background: var(--bg-subtle);
        color: var(--text);
        border: 1px solid var(--border);
    }
    .btn-plan-essencial:hover {
        background: var(--border);
    }
    .btn-plan-padrao {
        background: linear-gradient(135deg, #635bff 0%, #4338ca 100%);
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(99, 91, 255, 0.35);
    }
    .btn-plan-padrao:hover {
        filter: brightness(1.08);
        color: #ffffff;
    }
    .btn-plan-premium {
        background: linear-gradient(135deg, #e50914 0%, #b91c1c 100%);
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(229, 9, 20, 0.35);
    }
    .btn-plan-premium:hover {
        filter: brightness(1.08);
        color: #ffffff;
    }
    .btn-plan-current {
        background: rgba(16, 185, 129, 0.14);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.35);
        cursor: default;
    }

    /* TABELA COMPARATIVA ESTILO NETFLIX */
    .compare-table th, .compare-table td {
        padding: 13px 16px;
        border-bottom: 1px solid var(--border);
        font-size: 13px;
    }
    .compare-table th {
        background: var(--bg-subtle);
        font-weight: 800;
        text-transform: uppercase;
        font-size: 11.5px;
        color: var(--text-muted);
    }
    .compare-center {
        text-align: center;
        font-weight: 700;
    }
</style>

<div class="page-container">

    <!-- Mensagem Flash de Assinatura -->
    <?php if (!empty($_SESSION['flash_plano'])): ?>
        <div class="alert-box <?= $_SESSION['flash_plano']['tipo'] === 'sucesso' ? 'success' : 'error' ?>">
            <span><?= $_SESSION['flash_plano']['tipo'] === 'sucesso' ? '🎉' : '⚠️' ?></span>
            <p><?= $_SESSION['flash_plano']['texto'] ?></p>
        </div>
        <?php unset($_SESSION['flash_plano']); ?>
    <?php endif; ?>

    <!-- Banner do Plano Ativo (quando logado) -->
    <?php if ($idUsuario > 0): ?>
        <div style="background: var(--card); border: 1px solid var(--border); border-left: 5px solid #10b981; border-radius: var(--radius); padding: 16px 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                    <?= $planoAtual === 'PREMIUM' ? '👑' : ($planoAtual === 'PADRAO' ? '⭐' : '📦') ?>
                </div>
                <div>
                    <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Sua Assinatura Ativa</div>
                    <div style="font-size: 16px; font-weight: 800; color: var(--text);">
                        Plano MVM <?= htmlspecialchars($planoAtual) ?> <span class="status-pill success" style="margin-left: 6px;">Ativo • Ciclo <?= htmlspecialchars($cicloAtual) ?></span>
                    </div>
                </div>
            </div>
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Próxima renovação: <strong><?= $validadePlano ? date('d/m/Y', strtotime($validadePlano)) : date('d/m/Y', strtotime('+30 days')) ?></strong>
                &bull; Cancele ou altere quando quiser, sem multa.
            </div>
        </div>
    <?php endif; ?>

    <!-- HERO CABEÇALHO -->
    <div class="pricing-hero">
        <span class="pricing-badge-top">💎 MVM SaaS • Assinatura Flexível</span>
        <h1>Escolha o plano ideal para a sua operação</h1>
        <p>Assim como no streaming, você pode fazer upgrade, downgrade ou cancelar a qualquer momento. Sem fidelidade e com ativação imediata.</p>

        <div class="billing-toggle-bar">
            <button type="button" class="billing-btn active" id="btn-ciclo-mensal" onclick="alternarCiclo('MENSAL')">
                Cobrança Mensal
            </button>
            <button type="button" class="billing-btn" id="btn-ciclo-anual" onclick="alternarCiclo('ANUAL')">
                Cobrança Anual <span class="discount-pill">Economize 20%</span>
            </button>
        </div>
    </div>

    <!-- CARDS DOS 3 PLANOS -->
    <div class="pricing-grid">

        <!-- 1. PLANO ESSENCIAL -->
        <div class="plan-card <?= ($idUsuario > 0 && $planoAtual === 'ESSENCIAL') ? 'is-current' : '' ?>">
            <div>
                <div class="plan-banner banner-essencial">
                    <span class="plan-ribbon">Pequenas Empresas • 720p</span>
                    <h3>📦 Essencial</h3>
                    <span class="plan-res">Para pequenas empresas e comércios iniciando o controle</span>
                </div>
                <div class="plan-body">
                    <div class="plan-price-box">
                        <div class="plan-price-row">
                            <span class="plan-currency">R$</span>
                            <span class="plan-amount" data-mensal="149,90" data-anual="119,90">149,90</span>
                            <span class="plan-period">/mês</span>
                        </div>
                        <div class="plan-annual-note" data-note-mensal="Faturado mensalmente sem fidelidade" data-note-anual="R$ 1.438,80/ano (Economia de R$ 360,00)">
                            Faturado mensalmente sem fidelidade
                        </div>
                    </div>

                    <ul class="plan-features">
                        <li><span class="feat-check">✓</span> <span>Até <strong>100 produtos</strong> cadastrados no catálogo</span></li>
                        <li><span class="feat-check">✓</span> <span>Registro de <strong>Entradas, Saídas e Estornos</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Emissão de <strong>Recibo Térmico (80mm/A4)</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Carteira de Clientes e Fornecedores</span></li>
                        <li><span class="feat-check">✓</span> <span><strong>1 Operador</strong> conectado</span></li>
                        <li class="disabled"><span class="feat-check">✕</span> <span>Controle de Lotes, Validade (FEFO) e Kardex</span></li>
                        <li class="disabled"><span class="feat-check">✕</span> <span>Dashboard Power BI, Curva ABC e WMS</span></li>
                    </ul>
                </div>
            </div>
            <div style="padding: 0 24px 24px;">
                <?php if ($idUsuario > 0 && $planoAtual === 'ESSENCIAL'): ?>
                    <button type="button" class="btn-plan-select btn-plan-current" disabled>✓ Seu Plano Atual</button>
                <?php elseif ($idUsuario > 0): ?>
                    <button type="button" class="btn-plan-select btn-plan-essencial" onclick="abrirCheckoutPlano('ESSENCIAL', 'Essencial', '149,90', '119,90')">
                        Mudar para Essencial
                    </button>
                <?php else: ?>
                    <a href="<?= url('modulos/auth/criar_conta.php?plano=ESSENCIAL') ?>" class="btn-plan-select btn-plan-essencial">
                        Começar com Essencial
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. PLANO PADRÃO (MAIS POPULAR) -->
        <div class="plan-card popular <?= ($idUsuario > 0 && $planoAtual === 'PADRAO') ? 'is-current' : '' ?>">
            <div>
                <div class="plan-banner banner-padrao">
                    <span class="plan-ribbon">🔥 Médias Empresas • Full HD</span>
                    <h3>⭐ Padrão Comercial</h3>
                    <span class="plan-res">Para médias empresas, lojas e distribuidoras em expansão</span>
                </div>
                <div class="plan-body">
                    <div class="plan-price-box">
                        <div class="plan-price-row">
                            <span class="plan-currency">R$</span>
                            <span class="plan-amount" data-mensal="349,90" data-anual="279,90">349,90</span>
                            <span class="plan-period">/mês</span>
                        </div>
                        <div class="plan-annual-note" data-note-mensal="Faturado mensalmente sem fidelidade" data-note-anual="R$ 3.358,80/ano (Economia de R$ 840,00)">
                            Faturado mensalmente sem fidelidade
                        </div>
                    </div>

                    <ul class="plan-features">
                        <li><span class="feat-check">✓</span> <span>Até <strong>1.000 produtos</strong> com fotos</span></li>
                        <li><span class="feat-check">✓</span> <span>Controle de <strong>Lotes e Validade (FEFO)</strong></span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Ficha Kardex Contábil</strong> (CMP) + Etiquetas EAN</span></li>
                        <li><span class="feat-check">✓</span> <span>Exportação oficial em <strong>Excel (.xlsx) e PDF</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Alertas automáticos de estoque por <strong>E-mail</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Até <strong>3 Operadores</strong> simultâneos</span></li>
                        <li class="disabled"><span class="feat-check">✕</span> <span>Roteirização WMS + API RESTful Ilimitada</span></li>
                    </ul>
                </div>
            </div>
            <div style="padding: 0 24px 24px;">
                <?php if ($idUsuario > 0 && $planoAtual === 'PADRAO'): ?>
                    <button type="button" class="btn-plan-select btn-plan-current" disabled>✓ Seu Plano Atual</button>
                <?php elseif ($idUsuario > 0): ?>
                    <button type="button" class="btn-plan-select btn-plan-padrao" onclick="abrirCheckoutPlano('PADRAO', 'Padrão Comercial', '349,90', '279,90')">
                        <span>⚡</span> Assinar Plano Padrão
                    </button>
                <?php else: ?>
                    <a href="<?= url('modulos/auth/criar_conta.php?plano=PADRAO') ?>" class="btn-plan-select btn-plan-padrao">
                        <span>⚡</span> Começar com Padrão
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. PLANO PREMIUM (ENTERPRISE 4K + HDR) -->
        <div class="plan-card premium-tier <?= ($idUsuario > 0 && $planoAtual === 'PREMIUM') ? 'is-current' : '' ?>">
            <div>
                <div class="plan-banner banner-premium">
                    <span class="plan-ribbon">👑 Grandes Empresas • BI + WMS</span>
                    <h3>👑 Premium Enterprise</h3>
                    <span class="plan-res">Para grandes empresas: BI Executivo, WMS de Galpão e API</span>
                </div>
                <div class="plan-body">
                    <div class="plan-price-box">
                        <div class="plan-price-row">
                            <span class="plan-amount" data-mensal="Preços a consultar" data-anual="Preços a consultar" style="font-size: 26px; line-height: 1.2;">Preços a consultar</span>
                        </div>
                        <div class="plan-annual-note" data-note-mensal="Valor sob medida conforme o porte da empresa" data-note-anual="Condições especiais no contrato anual sob medida">
                            Valor sob medida conforme o porte da empresa
                        </div>
                    </div>

                    <ul class="plan-features">
                        <li><span class="feat-check">✓</span> <span>Produtos, Clientes e Vendas <strong>ILIMITADOS</strong></span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Dashboard Power BI</strong> + Curva ABC (Pareto 80/20)</span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Endereçamento WMS</strong> + Picking List de Galpão</span></li>
                        <li><span class="feat-check">✓</span> <span>Cotação inteligente via <strong>WhatsApp Direto</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Consulta Global <strong>EAN-13</strong> + API RESTful v1</span></li>
                        <li><span class="feat-check">✓</span> <span>Balanço Mensal Financeiro + Auditoria Cega</span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Operadores Ilimitados</strong> + Suporte VIP 24/7</span></li>
                    </ul>
                </div>
            </div>
            <div style="padding: 0 24px 24px;">
                <?php if ($idUsuario > 0 && $planoAtual === 'PREMIUM'): ?>
                    <button type="button" class="btn-plan-select btn-plan-current" disabled>👑 Seu Plano Atual (Máximo)</button>
                <?php elseif ($idUsuario > 0): ?>
                    <a href="mailto:mvm.centralestoque@gmail.com?subject=Proposta%20Plano%20Premium%20Enterprise%20-%20MVM%20Estoque" class="btn-plan-select btn-plan-premium">
                        <span>👑</span> Consultar Valores
                    </a>
                <?php else: ?>
                    <a href="mailto:mvm.centralestoque@gmail.com?subject=Proposta%20Plano%20Premium%20Enterprise%20-%20MVM%20Estoque" class="btn-plan-select btn-plan-premium">
                        <span>👑</span> Consultar Valores
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- TABELA COMPARATIVA DETALHADA (ESTILO NETFLIX) -->
    <div class="table-card" style="margin-bottom: 30px;">
        <div style="padding: 18px 22px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--text);">Comparativo Completo de Recursos por Plano</h3>
            <p style="font-size: 12.5px; color: var(--text-muted);">Confira em detalhes o que cada assinatura libera para sua empresa</p>
        </div>
        <div class="table-responsive">
            <table class="data-table compare-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Recurso / Capacidade</th>
                        <th class="compare-center" style="width: 20%;">📦 Essencial</th>
                        <th class="compare-center" style="width: 20%; color: var(--primary);">⭐ Padrão</th>
                        <th class="compare-center" style="width: 20%; color: #e50914;">👑 Premium</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Limite de Produtos (SKUs)</strong></td>
                        <td class="compare-center">Até 100 itens</td>
                        <td class="compare-center">Até 1.000 itens</td>
                        <td class="compare-center" style="color: #10b981;">Ilimitado</td>
                    </tr>
                    <tr>
                        <td><strong>Operadores Simultâneos</strong></td>
                        <td class="compare-center">1 usuário</td>
                        <td class="compare-center">Até 3 usuários</td>
                        <td class="compare-center" style="color: #10b981;">Ilimitados</td>
                    </tr>
                    <tr>
                        <td><strong>PDV com Carrinho Multi-Itens &amp; Recibo Térmico</strong></td>
                        <td class="compare-center">✅</td>
                        <td class="compare-center">✅</td>
                        <td class="compare-center">✅</td>
                    </tr>
                    <tr>
                        <td><strong>Ficha Kardex (CMP) &amp; Controle de Validade FEFO</strong></td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">✅</td>
                        <td class="compare-center">✅</td>
                    </tr>
                    <tr>
                        <td><strong>Gerador de Etiquetas de Gôndola (Código de Barras)</strong></td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">✅</td>
                        <td class="compare-center">✅</td>
                    </tr>
                    <tr>
                        <td><strong>Alertas Automáticos de Estoque Baixo por E-mail</strong></td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">✅</td>
                        <td class="compare-center">✅</td>
                    </tr>
                    <tr>
                        <td><strong>Dashboard Executivo Power BI &amp; Curva ABC (Pareto)</strong></td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">✅</td>
                    </tr>
                    <tr>
                        <td><strong>Endereçamento Logístico WMS &amp; Picking List</strong></td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">✅</td>
                    </tr>
                    <tr>
                        <td><strong>API RESTful v1 + Cadastro Rápido EAN-13 Global</strong></td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">—</td>
                        <td class="compare-center">✅</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL DE CHECKOUT INTERATIVO (PIX / CARTÃO) -->
<div id="modal-checkout-plano" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card" style="max-width: 480px; text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
            <div>
                <h3 style="margin: 0; font-size: 18px;">Finalizar Assinatura MVM</h3>
                <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">Ativação instantânea na sua conta</p>
            </div>
            <button type="button" class="btn-close-drawer" onclick="fecharCheckoutPlano()">✕</button>
        </div>

        <form action="planos.php" method="POST">
            <?= campoCSRF() ?>
            <input type="hidden" name="acao" value="assinar_plano">
            <input type="hidden" name="plano_escolhido" id="input-plano-escolhido" value="PREMIUM">
            <input type="hidden" name="ciclo_escolhido" id="input-ciclo-escolhido" value="MENSAL">

            <div style="background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Plano Selecionado</div>
                    <strong id="checkout-nome-plano" style="font-size: 16px; color: var(--text);">Premium Enterprise</strong>
                    <div id="checkout-ciclo-label" style="font-size: 11.5px; color: #10b981; font-weight: 700;">Ciclo Mensal</div>
                </div>
                <div style="text-align: right;">
                    <strong id="checkout-valor-plano" style="font-size: 22px; font-weight: 900; color: var(--primary);">R$ 449,90</strong>
                    <div style="font-size: 11px; color: var(--text-muted);">/mês</div>
                </div>
            </div>

            <div class="form-group">
                <label>Forma de Pagamento *</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1.5px solid var(--primary); border-radius: 8px; cursor: pointer; background: var(--primary-subtle);" id="lbl-metodo-pix">
                        <input type="radio" name="metodo_pagamento" value="PIX Instantâneo" checked onchange="alternarMetodoPagamento('PIX')" style="width: auto;">
                        <span style="font-weight: 700; font-size: 12.5px;">⚡ PIX (Imediato)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; cursor: pointer;" id="lbl-metodo-cartao">
                        <input type="radio" name="metodo_pagamento" value="Cartão de Crédito" onchange="alternarMetodoPagamento('CARTAO')" style="width: auto;">
                        <span style="font-weight: 700; font-size: 12.5px;">💳 Cartão Crédito</span>
                    </label>
                </div>
            </div>

            <!-- Box PIX -->
            <div id="box-pagamento-pix" style="text-align: center; background: var(--bg-subtle); border: 1px dashed var(--border); border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                <div style="font-size: 12px; font-weight: 700; color: var(--text); margin-bottom: 6px;">Chave PIX Copia e Cola (CNPJ MVM SaaS):</div>
                <code style="display: block; padding: 8px; background: var(--card); border: 1px solid var(--border); border-radius: 6px; font-size: 11px; color: var(--primary); word-break: break-all;">00020126580014BR.GOV.BCB.PIX0136mvm.centralestoque@gmail.com5204000053039865802BR5913MVM ESTOQUE6009SAO PAULO62070503***6304ABCD</code>
                <span style="display: block; font-size: 11px; color: var(--text-muted); margin-top: 6px;">Liberação automática após confirmação</span>
            </div>

            <!-- Box Cartão -->
            <div id="box-pagamento-cartao" style="display: none; margin-bottom: 18px;">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label>Número do Cartão</label>
                    <input type="text" placeholder="0000 0000 0000 0000" maxlength="19" value="4532 •••• •••• 8891" style="font-family: 'JetBrains Mono', monospace;">
                </div>
                <div class="form-row-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Validade</label>
                        <input type="text" placeholder="MM/AA" maxlength="5" value="12/29" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>CVV</label>
                        <input type="text" placeholder="123" maxlength="4" value="999" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                </div>
            </div>

            <div class="confirm-dialog-actions">
                <button type="button" class="btn-secondary" onclick="fecharCheckoutPlano()">Cancelar</button>
                <button type="submit" class="btn-primary" style="justify-content: center;">
                    <span>🚀</span> Confirmar e Ativar Plano
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let cicloSelecionado = 'MENSAL';

    function alternarCiclo(ciclo) {
        cicloSelecionado = ciclo;
        document.getElementById('btn-ciclo-mensal').classList.toggle('active', ciclo === 'MENSAL');
        document.getElementById('btn-ciclo-anual').classList.toggle('active', ciclo === 'ANUAL');

        document.querySelectorAll('.plan-amount').forEach(el => {
            el.textContent = ciclo === 'ANUAL' ? el.getAttribute('data-anual') : el.getAttribute('data-mensal');
        });
        document.querySelectorAll('.plan-annual-note').forEach(el => {
            el.textContent = ciclo === 'ANUAL' ? el.getAttribute('data-note-anual') : el.getAttribute('data-note-mensal');
        });
    }

    function abrirCheckoutPlano(codigo, nome, valorMensal, valorAnual) {
        document.getElementById('input-plano-escolhido').value = codigo;
        document.getElementById('input-ciclo-escolhido').value = cicloSelecionado;
        document.getElementById('checkout-nome-plano').textContent = 'Plano ' + nome;
        document.getElementById('checkout-ciclo-label').textContent = cicloSelecionado === 'ANUAL' ? 'Ciclo Anual (20% de desconto aplicado)' : 'Ciclo Mensal (Sem fidelidade)';
        document.getElementById('checkout-valor-plano').textContent = 'R$ ' + (cicloSelecionado === 'ANUAL' ? valorAnual : valorMensal);

        const modal = document.getElementById('modal-checkout-plano');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function fecharCheckoutPlano() {
        const modal = document.getElementById('modal-checkout-plano');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function alternarMetodoPagamento(metodo) {
        const isPix = metodo === 'PIX';
        document.getElementById('box-pagamento-pix').style.display = isPix ? 'block' : 'none';
        document.getElementById('box-pagamento-cartao').style.display = isPix ? 'none' : 'block';
        document.getElementById('lbl-metodo-pix').style.borderColor = isPix ? 'var(--primary)' : 'var(--border)';
        document.getElementById('lbl-metodo-pix').style.background = isPix ? 'var(--primary-subtle)' : 'transparent';
        document.getElementById('lbl-metodo-cartao').style.borderColor = !isPix ? 'var(--primary)' : 'var(--border)';
        document.getElementById('lbl-metodo-cartao').style.background = !isPix ? 'var(--primary-subtle)' : 'transparent';
    }
</script>

<?php require_once __DIR__ . "/../../templates/footer.php"; ?>
