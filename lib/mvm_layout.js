function esc(val) {
  if (val === null || val === undefined) return '';
  return String(val)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function renderLayout(paginaTitulo, paginaAtual, session, bodyHtml) {
  const logado = Boolean(session && session.usuarioLogado);
  const planoBadge = String((session && session.planoUsuario) || 'PREMIUM').toUpperCase();
  const iconePlano = planoBadge === 'PREMIUM' ? '👑' : (planoBadge === 'PADRAO' ? '⭐' : '📦');
  const corPlano = planoBadge === 'PREMIUM' ? '#f59e0b' : (planoBadge === 'PADRAO' ? '#38bdf8' : '#94a3b8');
  const roleUsuario = (session && session.roleUsuario) || (logado && String(session.usuarioLogado).toLowerCase() === 'admin' ? 'Administrador' : 'Operador');

  let triggerAviao = '';
  if (session && session.animacao_aviao_venda) {
    triggerAviao = `<div id="trigger-animacao-venda" style="display:none;" data-venda='${esc(JSON.stringify(session.animacao_aviao_venda))}'></div>`;
    delete session.animacao_aviao_venda;
  }

  let triggerCaminhao = '';
  if (session && session.animacao_caminhao_estoque) {
    triggerCaminhao = `<div id="trigger-animacao-caminhao" style="display:none;" data-caminhao='${esc(JSON.stringify(session.animacao_caminhao_estoque))}'></div>`;
    delete session.animacao_caminhao_estoque;
  }

  return `<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${esc(paginaTitulo || 'MVM - Gestão de Estoque')}</title>
    <link rel="icon" type="image/svg+xml" href="/img/mascote_mvm.svg">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#00704A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MVM Estoque">
    <link rel="apple-touch-icon" href="/img/mascote_mvm.svg">
    <script src="/javaScript/theme.js"></script>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/powerbi.css">
    <link rel="stylesheet" href="/css/paper_airplane.css">
    <link rel="stylesheet" href="/css/truck_animation.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="/javaScript/paper_airplane.js"></script>
    <script src="/javaScript/truck_animation.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {});
            });
        }
    </script>
</head>
<body>
    ${triggerAviao}
    ${triggerCaminhao}
    <aside class="main-header">
        <div class="sidebar-top">
            <a href="/index.php" class="logo-brand" title="MVM Estoque">
                <img src="/img/mascote_mvm.svg?v=5" alt="MVM Mascote" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 8px rgba(0, 112, 74, 0.4); flex-shrink: 0;">
                <div class="logo-text">MVM Estoque</div>
            </a>
            <button type="button" id="btn-collapse-sidebar" class="btn-collapse-sidebar" title="Recolher / Expandir menu lateral">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
        </div>

        <nav>
            <span class="nav-label">Visão Geral</span>
            <a href="/index.php" class="${['index.php', 'dashboard.php'].includes(paginaAtual) ? 'active' : ''}">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard BI</span>
            </a>

            ${logado ? `
                <span class="nav-label">Operações</span>
                <a href="/modulos/produtos/produto.php" class="${['produto.php', 'produto_editar.php'].includes(paginaAtual) ? 'active' : ''}">
                    <span class="nav-icon">📦</span>
                    <span class="nav-text">Catálogo de Produtos</span>
                </a>
                <a href="/modulos/movimentos/movimento.php" class="${['movimento.php', 'movimento_devolucao.php'].includes(paginaAtual) ? 'active' : ''}">
                    <span class="nav-icon">↔️</span>
                    <span class="nav-text">Vendas &amp; Movimento</span>
                </a>
                <a href="/modulos/relatorios/kardex.php" class="${paginaAtual === 'kardex.php' ? 'active' : ''}">
                    <span class="nav-icon">📜</span>
                    <span class="nav-text">Ficha Kardex</span>
                </a>
                <a href="/modulos/relatorios/etiquetas.php" class="${paginaAtual === 'etiquetas.php' ? 'active' : ''}">
                    <span class="nav-icon">🏷️</span>
                    <span class="nav-text">Etiquetas &amp; Barras</span>
                </a>

                <span class="nav-label">Contatos</span>
                <a href="/modulos/clientes/cliente.php" class="${['cliente.php', 'cliente_editar.php'].includes(paginaAtual) ? 'active' : ''}">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Carteira de Clientes</span>
                </a>
                <a href="/modulos/fornecedores/fornecedor.php" class="${['fornecedor.php', 'fornecedor_editar.php'].includes(paginaAtual) ? 'active' : ''}">
                    <span class="nav-icon">🏢</span>
                    <span class="nav-text">Fornecedores</span>
                </a>

                <span class="nav-label">Institucional</span>
                <a href="/modulos/planos/planos.php" class="${paginaAtual === 'planos.php' ? 'active' : ''}">
                    <span class="nav-icon">💎</span>
                    <span class="nav-text">Planos &amp; Assinatura</span>
                </a>
                <a href="/docs/mascote.php" class="${paginaAtual === 'mascote.php' ? 'active' : ''}">
                    <span class="nav-icon">🐜</span>
                    <span class="nav-text">Mascote Oficial</span>
                </a>
            ` : `
                <span class="nav-label">Acesso</span>
                <a href="/modulos/auth/login.php" class="${paginaAtual === 'login.php' ? 'active' : ''}">
                    <span class="nav-icon">🔐</span>
                    <span class="nav-text">Entrar no Sistema</span>
                </a>
                <a href="/modulos/planos/planos.php" class="${paginaAtual === 'planos.php' ? 'active' : ''}">
                    <span class="nav-icon">💎</span>
                    <span class="nav-text">Planos &amp; Preços</span>
                </a>
                <a href="/docs/mascote.php" class="${paginaAtual === 'mascote.php' ? 'active' : ''}">
                    <span class="nav-icon">🐜</span>
                    <span class="nav-text">Mascote do Projeto</span>
                </a>
            `}
        </nav>

        <div class="sidebar-footer">
            ${logado ? `
                <div class="user-profile-card">
                    <div class="user-info">
                        <div class="user-avatar" title="${esc(session.usuarioLogado)}">
                            ${esc(String(session.usuarioLogado).slice(0, 1).toUpperCase())}
                        </div>
                        <div class="user-names">
                            <div class="user-name-title" title="${esc(session.emailUsuario || '')}">${esc(session.usuarioLogado)}</div>
                            <div class="user-role" style="display: flex; align-items: center; gap: 5px;">
                                <span>${esc(roleUsuario)}</span>
                                <a href="/modulos/planos/planos.php" style="color: ${corPlano}; font-weight: 800; font-size: 9.5px; text-decoration: none;" title="Plano Ativo (Clique para gerenciar)">
                                    &bull; ${iconePlano} ${esc(planoBadge)}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="theme-toggle-row">
                    <button type="button" class="btn-toggle-theme" title="Alternar entre tema Claro e Escuro">
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
                <a href="/modulos/auth/logout.php" class="btn-sidebar-logout" title="Sair do sistema">
                    <span>🚪</span>
                    <span class="nav-text">Desconectar</span>
                </a>
            ` : `
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
            `}
        </div>
    </aside>

    <main>
        ${bodyHtml}
    </main>
    <footer class="main-footer">
        <div>
            <strong>MVM Gestão de Estoque</strong> &bull; Sistema de Controle de Armazém &amp; Inteligência Comercial
        </div>
        <div>
            Desenvolvido por: Vitor A., Murilo B. e Murilo D. &bull; ${new Date().getFullYear()}
        </div>
    </footer>
</body>
</html>`;
}

module.exports = { esc, renderLayout };
