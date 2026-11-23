/**
 * Gerenciador de Tema (Claro / Escuro) e Navegação Estilo Linear/Stripe
 * Sistema MVM
 */

(function () {
    // Inicialização imediata do tema para evitar Flash of Unstyled Theme (FOUT)
    const temaSalvo = localStorage.getItem('mvm_theme') || 'light';
    document.documentElement.setAttribute('data-theme', temaSalvo);

    // Carregamento de estado da barra lateral (recolhida ou expandida)
    const sidebarState = localStorage.getItem('mvm_sidebar_collapsed');
    if (sidebarState === 'true') {
        document.documentElement.classList.add('sidebar-collapsed');
    }

    // Carregamento de preferência de animações visuais (padrão: ativadas)
    const animState = localStorage.getItem('mvm_animacoes_ativas');
    const isAnimActive = animState !== 'false';
    document.documentElement.setAttribute('data-animations', isAnimActive ? 'enabled' : 'disabled');
})();

// Helper global para verificar se animações estão habilitadas
window.animacoesHabilitadas = function () {
    return localStorage.getItem('mvm_animacoes_ativas') !== 'false';
};

// Helper global para alternar o estado de animações
window.alternarAnimacoes = function () {
    const atual = window.animacoesHabilitadas();
    const novoEstado = !atual;
    localStorage.setItem('mvm_animacoes_ativas', novoEstado ? 'true' : 'false');
    document.documentElement.setAttribute('data-animations', novoEstado ? 'enabled' : 'disabled');
    
    // Atualiza todos os botões/switches na tela
    if (typeof window.atualizarBotoesAnimacoes === 'function') {
        window.atualizarBotoesAnimacoes(novoEstado);
    }
    
    // Exibe toast sutil de feedback
    if (typeof window.exibirToastFeedback === 'function') {
        const msg = novoEstado ? 'Animações visuais ativadas' : 'Animações visuais desativadas';
        const icone = novoEstado ? '✨' : '💤';
        window.exibirToastFeedback(msg, icone);
    }

    // Dispara evento customizado
    window.dispatchEvent(new CustomEvent('animacoesAlteradas', { detail: { ativas: novoEstado } }));
    return novoEstado;
};

// Helper global para exibir toast de notificação rápida
window.exibirToastFeedback = function (mensagem, icone = 'ℹ️') {
    const anterior = document.querySelector('.anim-feedback-toast');
    if (anterior) anterior.remove();

    const toast = document.createElement('div');
    toast.className = 'anim-feedback-toast';
    toast.innerHTML = `<span>${icone}</span> <span>${mensagem}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        if (toast && toast.parentNode) toast.remove();
    }, 2400);
};

// Atualiza o estado visual de todos os botões de animação presentes no DOM
window.atualizarBotoesAnimacoes = function (ativas) {
    const botoes = document.querySelectorAll('.btn-toggle-animations');
    botoes.forEach(btn => {
        if (ativas) {
            btn.classList.add('is-active');
            btn.classList.remove('is-disabled');
            btn.setAttribute('title', 'Animações: Ativadas (Clique para desativar)');
            const icon = btn.querySelector('.anim-icon');
            if (icon) icon.textContent = '✨';
            const label = btn.querySelector('.anim-status-label');
            if (label) label.textContent = 'Ativas';
        } else {
            btn.classList.remove('is-active');
            btn.classList.add('is-disabled');
            btn.setAttribute('title', 'Animações: Desativadas (Clique para ativar)');
            const icon = btn.querySelector('.anim-icon');
            if (icon) icon.textContent = '💤';
            const label = btn.querySelector('.anim-status-label');
            if (label) label.textContent = 'Desativadas';
        }
    });
};

document.addEventListener('DOMContentLoaded', function () {
    const htmlEl = document.documentElement;

    // 1. Alternador de Tema Claro / Escuro
    const atualizarBotoesTema = (tema) => {
        const botoes = document.querySelectorAll('.btn-toggle-theme');
        botoes.forEach(btn => {
            const icon = btn.querySelector('.theme-icon') || btn.querySelector('span:first-child');
            const text = btn.querySelector('.theme-toggle-text');
            if (tema === 'dark') {
                if (icon) icon.textContent = '☀️';
                if (text) text.textContent = 'Modo Claro';
                btn.setAttribute('title', 'Alternar para Modo Claro (Stripe)');
            } else {
                if (icon) icon.textContent = '🌙';
                if (text) text.textContent = 'Modo Escuro';
                btn.setAttribute('title', 'Alternar para Modo Escuro (Linear)');
            }
        });
    };

    const temaInicial = htmlEl.getAttribute('data-theme') || 'light';
    atualizarBotoesTema(temaInicial);

    const botoesTema = document.querySelectorAll('.btn-toggle-theme');
    botoesTema.forEach(btn => {
        btn.addEventListener('click', function () {
            const temaAtual = htmlEl.getAttribute('data-theme') || 'light';
            const novoTema = temaAtual === 'dark' ? 'light' : 'dark';

            htmlEl.setAttribute('data-theme', novoTema);
            localStorage.setItem('mvm_theme', novoTema);
            atualizarBotoesTema(novoTema);

            // Dispara evento customizado para gráficos ou componentes ouvintes
            window.dispatchEvent(new CustomEvent('temaAlterado', { detail: { tema: novoTema } }));
        });
    });

    // 2. Alternador de Colapso da Barra Lateral (Linear Sidebar)
    const btnCollapseSidebar = document.querySelector('#btn-collapse-sidebar');
    if (btnCollapseSidebar) {
        btnCollapseSidebar.addEventListener('click', function () {
            htmlEl.classList.toggle('sidebar-collapsed');
            const isCollapsed = htmlEl.classList.contains('sidebar-collapsed');
            localStorage.setItem('mvm_sidebar_collapsed', isCollapsed ? 'true' : 'false');
            
            // Redimensiona gráficos ApexCharts se existirem na tela
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 250);
        });
    }

    // 2.5 Alternador de Ativação / Desativação de Animações
    const animAtivas = window.animacoesHabilitadas();
    window.atualizarBotoesAnimacoes(animAtivas);

    document.addEventListener('click', function (e) {
        const btnAnim = e.target.closest('.btn-toggle-animations');
        if (btnAnim) {
            e.preventDefault();
            window.alternarAnimacoes();
        }
    });

    // 3. Gerenciamento Global de Modais / Gavetas Laterais (Slide-overs)
    const openers = document.querySelectorAll('[data-open-modal]');
    openers.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-open-modal');
            const modal = document.getElementById(targetId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) setTimeout(() => firstInput.focus(), 150);
            }
        });
    });

    const closers = document.querySelectorAll('[data-close-modal], .modal-backdrop');
    closers.forEach(el => {
        el.addEventListener('click', function (e) {
            if (e.target === this) {
                const modal = this.closest('.modal-container') || this;
                if (modal && modal.classList.contains('active')) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
    });

    // Fechar modal com tecla ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-container.active');
            if (activeModal) {
                activeModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });

    // 4. Filtro Instantâneo em Tabelas (Live Search)
    const liveSearchInputs = document.querySelectorAll('[data-table-search]');
    liveSearchInputs.forEach(input => {
        const tableSelector = input.getAttribute('data-table-search');
        const table = document.querySelector(tableSelector);
        if (!table) return;

        input.addEventListener('input', function () {
            const termo = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
            let visiveis = 0;

            rows.forEach(row => {
                const textoLinha = row.textContent.toLowerCase();
                if (textoLinha.includes(termo)) {
                    row.style.display = '';
                    visiveis++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Linha de resultado vazio
            let emptyRow = table.querySelector('.empty-search-row');
            if (visiveis === 0 && termo !== '') {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.className = 'empty-search-row';
                    const colSpan = table.querySelectorAll('thead th').length || 6;
                    emptyRow.innerHTML = `<td colspan="${colSpan}" style="text-align: center; padding: 32px; color: var(--text-muted);">Nenhum resultado encontrado para "<strong>${input.value}</strong>".</td>`;
                    table.querySelector('tbody').appendChild(emptyRow);
                }
                emptyRow.style.display = '';
            } else if (emptyRow) {
                emptyRow.style.display = 'none';
            }

            // Atualiza contador de itens se houver
            const countEl = document.querySelector('[data-item-counter]');
            if (countEl) {
                countEl.textContent = visiveis;
            }
        });

        if (input.value && input.value.trim() !== '') {
            input.dispatchEvent(new Event('input'));
        }
    });

    // 5. Modal Global Estilizado (substitui confirm() e alert() nativos do navegador)
    function inferirDialogMeta(mensagem, isConfirm) {
        const txt = String(mensagem || '').toLowerCase();
        if (txt.includes('desconto') || txt.includes('promo')) {
            return { icone: '🔥', titulo: 'Aplicar Ação Promocional', tipo: 'warning', btnOk: 'Aplicar Desconto' };
        }
        if (txt.includes('devolu') || txt.includes('estorno') || txt.includes('exclu') || txt.includes('remover') || txt.includes('apagar')) {
            return { icone: '⚠️', titulo: 'Confirmar Ação', tipo: 'danger', btnOk: 'Confirmar' };
        }
        if (txt.includes('e-mail') || txt.includes('email') || txt.includes('relatório')) {
            return { icone: '📧', titulo: 'Enviar Relatório', tipo: 'info', btnOk: 'Enviar Agora' };
        }
        return {
            icone: isConfirm ? '✨' : 'ℹ️',
            titulo: isConfirm ? 'Confirmar Operação' : 'Aviso do Sistema',
            tipo: 'info',
            btnOk: isConfirm ? 'Confirmar' : 'Entendi'
        };
    }

    function abrirMvmDialog(opts) {
        return new Promise((resolve) => {
            const anterior = document.querySelector('.mvm-dialog-backdrop');
            if (anterior) anterior.remove();

            const isConfirm = Boolean(opts.isConfirm);
            const meta = inferirDialogMeta(opts.mensagem, isConfirm);
            const icone = opts.icone || meta.icone;
            const titulo = opts.titulo || meta.titulo;
            const tipo = opts.tipo || meta.tipo;
            const textoConfirmar = opts.textoConfirmar || meta.btnOk;
            const textoCancelar = opts.textoCancelar || 'Cancelar';

            const backdrop = document.createElement('div');
            backdrop.className = 'mvm-dialog-backdrop';
            backdrop.setAttribute('role', 'dialog');
            backdrop.setAttribute('aria-modal', 'true');

            const box = document.createElement('div');
            box.className = 'mvm-dialog-box';

            const iconEl = document.createElement('div');
            iconEl.className = 'mvm-dialog-icon' + (tipo !== 'info' ? ' is-' + tipo : '');
            iconEl.textContent = icone;

            const titleEl = document.createElement('h3');
            titleEl.className = 'mvm-dialog-title';
            titleEl.textContent = titulo;

            const msgEl = document.createElement('p');
            msgEl.className = 'mvm-dialog-msg';
            msgEl.textContent = String(opts.mensagem || '');

            const actionsEl = document.createElement('div');
            actionsEl.className = 'mvm-dialog-actions';

            let btnCancel = null;
            if (isConfirm) {
                btnCancel = document.createElement('button');
                btnCancel.type = 'button';
                btnCancel.className = 'mvm-dialog-btn mvm-dialog-btn-cancel';
                btnCancel.textContent = textoCancelar;
                actionsEl.appendChild(btnCancel);
            }

            const btnOk = document.createElement('button');
            btnOk.type = 'button';
            btnOk.className = 'mvm-dialog-btn mvm-dialog-btn-confirm' + (tipo === 'danger' || tipo === 'warning' ? ' is-' + tipo : '');
            btnOk.textContent = textoConfirmar;
            actionsEl.appendChild(btnOk);

            box.appendChild(iconEl);
            box.appendChild(titleEl);
            box.appendChild(msgEl);
            box.appendChild(actionsEl);
            backdrop.appendChild(box);
            document.body.appendChild(backdrop);

            requestAnimationFrame(() => backdrop.classList.add('is-open'));
            setTimeout(() => btnOk.focus(), 40);

            const fechar = (resultado) => {
                document.removeEventListener('keydown', onKey);
                backdrop.classList.remove('is-open');
                setTimeout(() => {
                    if (backdrop.parentNode) backdrop.remove();
                    resolve(resultado);
                }, 160);
            };

            const onKey = (e) => {
                if (e.key === 'Escape') fechar(false);
            };
            document.addEventListener('keydown', onKey);

            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) fechar(false);
            });
            if (btnCancel) btnCancel.addEventListener('click', () => fechar(false));
            btnOk.addEventListener('click', () => fechar(true));
        });
    }

    window.mvmConfirm = function (mensagem, opts = {}) {
        return abrirMvmDialog({ ...opts, mensagem, isConfirm: true });
    };

    window.mvmAlert = function (mensagem, opts = {}) {
        return abrirMvmDialog({ ...opts, mensagem, isConfirm: false });
    };

    // Substitui alert() nativo pelo modal bonito
    window.alert = function (mensagem) {
        window.mvmAlert(mensagem);
    };

    // Intercepta automaticamente formulários com onsubmit="return confirm('...')"
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        const attr = form.getAttribute('onsubmit') || '';
        const match = attr.match(/confirm\s*\(\s*(['"`])([\s\S]*?)\1\s*\)/);
        if (!match) return;
        const mensagem = match[2];
        form.removeAttribute('onsubmit');
        form.addEventListener('submit', async function (e) {
            if (form.dataset.mvmConfirmed === '1') return;
            e.preventDefault();
            const ok = await window.mvmConfirm(mensagem);
            if (ok) {
                form.dataset.mvmConfirmed = '1';
                form.submit();
            }
        });
    });
});
