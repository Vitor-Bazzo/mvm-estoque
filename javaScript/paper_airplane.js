/**
 * Sistema de Animação do Aviãozinho de Papel para Vendas / Saídas
 * Sistema MVM
 */

window.dispararAnimacaoAviao = function (dados) {
    // Respeita a preferência do usuário de desativação de animações
    if (typeof window.animacoesHabilitadas === 'function' && !window.animacoesHabilitadas()) {
        return;
    }

    // Remove qualquer overlay anterior
    const existente = document.querySelector('.paper-plane-overlay');
    if (existente) existente.remove();

    const produtoNome = dados?.produto || 'Produto';
    const quantidade = dados?.quantidade || 1;
    const total = dados?.total ? Number(dados.total).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '';

    const overlay = document.createElement('div');
    overlay.className = 'paper-plane-overlay';
    overlay.innerHTML = `
        <div class="plane-notification" onclick="this.parentElement.remove()">
            <div class="plane-notif-icon">✈️</div>
            <div class="plane-notif-content">
                <strong>Venda / Saída Registrada com Sucesso!</strong>
                <span>${quantidade}x ${produtoNome} ${total ? '&bull; ' + total : ''}</span>
            </div>
        </div>
        <div class="airplane-flight-stage">
            <svg class="airplane-wind-trail" viewBox="0 0 1200 800" preserveAspectRatio="none">
                <path class="trail-path" d="M -50 720 Q 350 300, 600 420 T 1150 -50" />
            </svg>
            <div class="airplane-wrapper">
                <svg viewBox="0 0 100 100" class="airplane-svg">
                    <defs>
                        <linearGradient id="planeGradLeft" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff" />
                            <stop offset="100%" stop-color="#e2e8f0" />
                        </linearGradient>
                        <linearGradient id="planeGradRight" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#cbd5e1" />
                            <stop offset="100%" stop-color="#94a3b8" />
                        </linearGradient>
                        <linearGradient id="planeGradUnder" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#64748b" />
                            <stop offset="100%" stop-color="#475569" />
                        </linearGradient>
                    </defs>
                    <g>
                        <!-- Dobra inferior da fuselagem -->
                        <polygon points="40,65 52,90 54,69" fill="url(#planeGradUnder)" />
                        <!-- Asa esquerda superior -->
                        <polygon points="8,48 94,12 40,65" fill="url(#planeGradLeft)" />
                        <!-- Asa direita superior -->
                        <polygon points="94,12 52,90 40,65" fill="url(#planeGradRight)" />
                        <!-- Vinco central -->
                        <polygon points="8,48 94,12 44,56" fill="rgba(255,255,255,0.7)" />
                    </g>
                </svg>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Efeito sonoro sutil gerado via Web Audio API (sem arquivos externos)
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(520, audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.35);
        gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.6);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.65);
    } catch (e) {
        // Ignora silenciosamente se o navegador bloquear áudio automático
    }

    // Criação de partículas festivas ao longo da tela
    const stage = overlay.querySelector('.airplane-flight-stage');
    const cores = ['#f2c811', '#10b981', '#118dff', '#635bff', '#ec4899', '#f97316'];
    for (let i = 0; i < 28; i++) {
        const part = document.createElement('div');
        part.className = 'confetti-particle';
        const top = Math.random() * 60 + 20; // 20% a 80% da altura
        const left = Math.random() * 80 + 10; // 10% a 90% da largura
        part.style.top = top + '%';
        part.style.left = left + '%';
        part.style.backgroundColor = cores[Math.floor(Math.random() * cores.length)];
        part.style.setProperty('--tx', (Math.random() * 80 - 40) + 'px');
        part.style.setProperty('--ty', (Math.random() * -80 - 20) + 'px');
        part.style.animationDelay = (Math.random() * 1.5 + 0.5) + 's';
        stage.appendChild(part);
    }

    // Limpeza automática após o voo
    setTimeout(() => {
        if (overlay && overlay.parentNode) {
            overlay.remove();
        }
    }, 4200);
};

// Verifica na inicialização se há venda recém-registrada na sessão
document.addEventListener('DOMContentLoaded', function () {
    const triggerEl = document.querySelector('#trigger-animacao-venda');
    if (triggerEl) {
        if (typeof window.animacoesHabilitadas === 'function' && !window.animacoesHabilitadas()) {
            return;
        }
        try {
            const dadosVenda = JSON.parse(triggerEl.getAttribute('data-venda'));
            setTimeout(() => {
                window.dispararAnimacaoAviao(dadosVenda);
            }, 300);
        } catch (e) {
            console.error('Erro ao ler dados da animação de venda:', e);
        }
    }
});
