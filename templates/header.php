<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexao.php';
$paginaTitulo = $titulo_pagina ?? $titulopagina ?? 'MVM - Gestão de Estoque';
$paginaAtual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($paginaTitulo) ?></title>
    
    <!-- Favicon Oficial com Mascote MVM -->
    <link rel="icon" type="image/svg+xml" href="img/mascote_mvm.svg">

    <!-- Configurações PWA (Instalável no Celular/Desktop) -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#00704A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MVM Estoque">
    <link rel="apple-touch-icon" href="img/mascote_mvm.svg">

    <!-- Script de Inicialização Instantânea do Tema -->
    <script src="javaScript/theme.js"></script>

    <!-- Folhas de Estilo -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/powerbi.css">
    <link rel="stylesheet" href="css/paper_airplane.css">
    <link rel="stylesheet" href="css/truck_animation.css">

    <!-- ApexCharts e Animações -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="javaScript/paper_airplane.js"></script>
    <script src="javaScript/truck_animation.js"></script>

    <!-- Registro do Service Worker PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch(err => {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</head>
<body>
    <!-- Trigger de Animação do Aviãozinho para Vendas Registradas -->
    <?php if (!empty($_SESSION['animacao_aviao_venda'])): ?>
        <div id="trigger-animacao-venda" style="display:none;" data-venda='<?= json_encode($_SESSION['animacao_aviao_venda'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'></div>
        <?php unset($_SESSION['animacao_aviao_venda']); ?>
    <?php endif; ?>

    <!-- Trigger de Animação do Mini-Caminhão para Estoque Carregado (Devolução / Venda Excluída) -->
    <?php if (!empty($_SESSION['animacao_caminhao_estoque'])): ?>
        <div id="trigger-animacao-caminhao" style="display:none;" data-caminhao='<?= json_encode($_SESSION['animacao_caminhao_estoque'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'></div>
        <?php unset($_SESSION['animacao_caminhao_estoque']); ?>
    <?php endif; ?>
    <!-- SIDEBAR MINIMALISTA INTELIGENTE (ESTILO LINEAR) -->
    <aside class="main-header">
        <div class="sidebar-top">
            <a href="index.php" class="logo-brand" title="MVM Estoque">
                <img src="img/mascote_mvm.svg?v=3" alt="MVM Mascote" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 8px rgba(0, 112, 74, 0.4); flex-shrink: 0;">
                <div class="logo-text">
                    MVM Estoque
                </div>
            </a>
            <button type="button" id="btn-collapse-sidebar" class="btn-collapse-sidebar" title="Recolher / Expandir menu lateral">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
        </div>

        <nav>
            <span class="nav-label">Visão Geral</span>
            <a href="index.php" class="<?= in_array($paginaAtual, ['index.php', 'dashboard.php']) ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard BI</span>
            </a>

            <?php if (isset($_SESSION['usuarioLogado'])): ?>
                <span class="nav-label">Operações</span>
                <a href="produto.php" class="<?= in_array($paginaAtual, ['produto.php', 'produto_editar.php']) ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span class="nav-text">Catálogo de Produtos</span>
                </a>
                <a href="movimento.php" class="<?= in_array($paginaAtual, ['movimento.php', 'movimento_devolucao.php']) ? 'active' : '' ?>">
                    <span class="nav-icon">↔️</span>
                    <span class="nav-text">Vendas &amp; Movimento</span>
                </a>
                <a href="kardex.php" class="<?= $paginaAtual === 'kardex.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📜</span>
                    <span class="nav-text">Ficha Kardex</span>
                </a>
                <a href="etiquetas.php" class="<?= $paginaAtual === 'etiquetas.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🏷️</span>
                    <span class="nav-text">Etiquetas &amp; Barras</span>
                </a>

                <span class="nav-label">Contatos</span>
                <a href="cliente.php" class="<?= in_array($paginaAtual, ['cliente.php', 'cliente_editar.php']) ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Carteira de Clientes</span>
                </a>
                <a href="fornecedor.php" class="<?= in_array($paginaAtual, ['fornecedor.php', 'fornecedor_editar.php']) ? 'active' : '' ?>">
                    <span class="nav-icon">🏢</span>
                    <span class="nav-text">Fornecedores</span>
                </a>

                <span class="nav-label">Integrações</span>
                <a href="api_docs.php" class="<?= $paginaAtual === 'api_docs.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚡</span>
                    <span class="nav-text">API REST &amp; WMS</span>
                </a>

                <span class="nav-label">Institucional</span>
                <a href="mascote.php" class="<?= $paginaAtual === 'mascote.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🐜</span>
                    <span class="nav-text">Mascote Oficial</span>
                </a>
            <?php else: ?>
                <span class="nav-label">Acesso</span>
                <a href="login.php" class="<?= $paginaAtual === 'login.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🔐</span>
                    <span class="nav-text">Entrar no Sistema</span>
                </a>
                <a href="mascote.php" class="<?= $paginaAtual === 'mascote.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🐜</span>
                    <span class="nav-text">Mascote do Projeto</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- RODAPÉ DA SIDEBAR COM PERFIL E TEMA CLARO/ESCURO -->
        <div class="sidebar-footer">
            <?php if (isset($_SESSION['usuarioLogado'])): ?>
                <div class="user-profile-card">
                    <div class="user-info">
                        <div class="user-avatar" title="<?= htmlspecialchars($_SESSION['usuarioLogado']) ?>">
                            <?= strtoupper(substr($_SESSION['usuarioLogado'], 0, 1)) ?>
                        </div>
                        <div class="user-names">
                            <div class="user-name-title" title="<?= htmlspecialchars($_SESSION['emailUsuario'] ?? '') ?>"><?= htmlspecialchars($_SESSION['usuarioLogado']) ?></div>
                            <div class="user-role"><?= htmlspecialchars($_SESSION['roleUsuario'] ?? ((strtolower($_SESSION['usuarioLogado']) === 'admin') ? 'Administrador' : 'Operador')) ?></div>
                        </div>
                    </div>
                </div>

                <div class="theme-toggle-row">
                    <button type="button" class="btn-toggle-theme" title="Alternar entre tema Claro (Stripe) e Escuro (Linear)">
                        <span class="theme-icon">🌓</span>
                        <span class="theme-toggle-text">Alternar Tema</span>
                    </button>
                </div>

                <div class="anim-toggle-row">
                    <button type="button" class="btn-toggle-animations is-active" title="Ativar ou desativar animações visuais">
                        <div class="anim-toggle-info">
                            <span class="anim-icon">✨</span>
                            <span class="anim-toggle-text">Animações: <strong class="anim-status-label">Ativas</strong></span>
                        </div>
                        <span class="anim-switch"><span class="anim-knob"></span></span>
                    </button>
                </div>

                <a href="logout.php" class="btn-sidebar-logout" title="Sair do sistema">
                    <span>🚪</span>
                    <span class="nav-text">Desconectar</span>
                </a>
            <?php else: ?>
                <div class="theme-toggle-row">
                    <button type="button" class="btn-toggle-theme" title="Alternar Tema">
                        <span>🌓</span>
                        <span class="theme-toggle-text">Alternar Tema</span>
                    </button>
                </div>

                <div class="anim-toggle-row">
                    <button type="button" class="btn-toggle-animations is-active" title="Ativar ou desativar animações visuais">
                        <div class="anim-toggle-info">
                            <span class="anim-icon">✨</span>
                            <span class="anim-toggle-text">Animações: <strong class="anim-status-label">Ativas</strong></span>
                        </div>
                        <span class="anim-switch"><span class="anim-knob"></span></span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <main>