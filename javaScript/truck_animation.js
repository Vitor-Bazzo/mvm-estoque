/**
 * Sistema de Animação do Mini-Caminhão para Estoque Carregado / Reabastecido
 * Sistema MVM - Devoluções & Exclusões de Vendas
 */

window.dispararAnimacaoCaminhao = function (dados) {
    // Respeita a preferência do usuário de desativação de animações
    if (typeof window.animacoesHabilitadas === 'function' && !window.animacoesHabilitadas()) {
        return;
    }

    // Remove qualquer overlay anterior para evitar duplicações
    const existente = document.querySelector('.truck-overlay');
    if (existente) existente.remove();

    const produtoNome = dados?.produto || 'Produto';
    const quantidade = dados?.quantidade || 1;
    const novaQtd = dados?.novaQuantidade !== undefined ? ` (Total atual: ${dados.novaQuantidade} un.)` : '';
    const subtitulo = dados?.origem === 'exclusao_venda' 
        ? `Venda cancelada • +${quantidade} un. de ${produtoNome} repostas no estoque${novaQtd}`
        : `Devolução concluída • +${quantidade} un. de ${produtoNome} recarregadas no estoque${novaQtd}`;

    const overlay = document.createElement('div');
    overlay.className = 'truck-overlay';
    overlay.innerHTML = `
        <!-- Notificação superior elegante -->
        <div class="truck-notification" onclick="this.parentElement.remove()" title="Clique para fechar">
            <div class="truck-notif-icon">🚚</div>
            <div class="truck-notif-content">
                <span class="truck-notif-badge">Estoque Carregado</span>
                <strong>Reposição Efetuada com Sucesso!</strong>
                <span>${subtitulo}</span>
            </div>
            <div class="truck-notif-close">✕</div>
        </div>

        <!-- Palco inferior: Pista e Mini-Caminhão de Carga -->
        <div class="truck-stage">
            <!-- Asfalto com faixas em movimento -->
            <div class="truck-road">
                <div class="truck-road-lines"></div>
            </div>

            <!-- Rig do Mini-Caminhão -->
            <div class="truck-rig">
                <!-- Luz do Farol Dianteiro -->
                <div class="truck-headlight-beam"></div>

                <!-- Escapamento com fumaça -->
                <div class="truck-exhaust">
                    <div class="exhaust-smoke"></div>
                    <div class="exhaust-smoke"></div>
                    <div class="exhaust-smoke"></div>
                </div>

                <!-- Corpo e Suspensão com balanço -->
                <div class="truck-body-suspension">
                    <svg viewBox="0 0 240 120" width="240" height="120" style="overflow: visible;">
                        <defs>
                            <!-- Gradiente da Cabine -->
                            <linearGradient id="truckCabGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#38bdf8" />
                                <stop offset="50%" stop-color="#0284c7" />
                                <stop offset="100%" stop-color="#0369a1" />
                            </linearGradient>

                            <!-- Gradiente da Caçamba / Baú -->
                            <linearGradient id="truckBedGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#334155" />
                                <stop offset="100%" stop-color="#1e293b" />
                            </linearGradient>

                            <!-- Gradiente das Caixas de Papelão de Estoque -->
                            <linearGradient id="boxGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#d97706" />
                                <stop offset="100%" stop-color="#b45309" />
                            </linearGradient>
                            <linearGradient id="boxGrad2" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#f59e0b" />
                                <stop offset="100%" stop-color="#d97706" />
                            </linearGradient>

                            <!-- Vidro da Cabine -->
                            <linearGradient id="windowGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#e0f2fe" />
                                <stop offset="70%" stop-color="#bae6fd" />
                                <stop offset="100%" stop-color="#7dd3fc" />
                            </linearGradient>

                            <!-- Sombra debaixo do caminhão -->
                            <radialGradient id="truckShadow" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="rgba(0,0,0,0.6)" />
                                <stop offset="100%" stop-color="rgba(0,0,0,0)" />
                            </radialGradient>
                        </defs>

                        <!-- Sombra no solo -->
                        <ellipse cx="120" cy="110" rx="100" ry="8" fill="url(#truckShadow)" />

                        <!-- ================= CAIXAS DE ESTOQUE CARREGADAS ================= -->
                        <g class="cargo-glow">
                            <!-- Caixa 1 (Fundo / Esquerda) -->
                            <rect x="25" y="42" width="34" height="34" rx="4" fill="url(#boxGrad1)" stroke="#78350f" stroke-width="1.5" />
                            <line x1="25" y1="59" x2="59" y2="59" stroke="#fbbf24" stroke-width="2.5" stroke-dasharray="3 2" />
                            <rect x="35" y="47" width="14" height="8" rx="1" fill="#ffffff" opacity="0.85" />

                            <!-- Caixa 2 (Centro) -->
                            <rect x="56" y="36" width="38" height="40" rx="4" fill="url(#boxGrad2)" stroke="#78350f" stroke-width="1.5" />
                            <line x1="75" y1="36" x2="75" y2="76" stroke="#fbbf24" stroke-width="2.5" />
                            <line x1="56" y1="56" x2="94" y2="56" stroke="#fbbf24" stroke-width="2" />
                            <rect x="68" y="42" width="14" height="9" rx="1" fill="#ffffff" opacity="0.9" />

                            <!-- Caixa 3 (Topo / Empilhada) -->
                            <rect x="38" y="16" width="32" height="26" rx="3" fill="url(#boxGrad1)" stroke="#78350f" stroke-width="1.5" />
                            <line x1="38" y1="29" x2="70" y2="29" stroke="#fbbf24" stroke-width="2" />

                            <!-- Selo Flutuante Verde de Reposição de Estoque -->
                            <g transform="translate(68, 12)">
                                <circle cx="10" cy="10" r="11" fill="#10b981" stroke="#ffffff" stroke-width="1.5" />
                                <text x="10" y="14" font-family="'Plus Jakarta Sans', sans-serif" font-weight="900" font-size="12" fill="#ffffff" text-anchor="middle">📦</text>
                            </g>
                        </g>

                        <!-- ================= GRADE / CAÇAMBA TRASEIRA ================= -->
                        <g>
                            <!-- Chassi da caçamba -->
                            <path d="M 18 76 L 120 76 L 120 90 L 18 90 Z" fill="url(#truckBedGrad)" />
                            <!-- Grades de proteção da carga -->
                            <rect x="18" y="52" width="4" height="24" fill="#64748b" rx="1" />
                            <rect x="48" y="52" width="4" height="24" fill="#64748b" rx="1" />
                            <rect x="80" y="52" width="4" height="24" fill="#64748b" rx="1" />
                            <rect x="114" y="52" width="4" height="24" fill="#64748b" rx="1" />
                            <line x1="18" y1="62" x2="118" y2="62" stroke="#64748b" stroke-width="3" />
                            
                            <!-- Para-lama Traseiro -->
                            <path d="M 40 90 Q 60 72 80 90 Z" fill="#0f172a" />
                        </g>

                        <!-- ================= CABINE DO MINICAMINHÃO ================= -->
                        <g>
                            <!-- Formato da cabine moderna estilizada -->
                            <path d="
                                M 120 90
                                L 120 44
                                C 120 38, 128 32, 138 32
                                L 175 32
                                C 190 32, 206 48, 214 62
                                L 222 76
                                C 225 80, 226 86, 222 90
                                Z"
                                fill="url(#truckCabGrad)"
                                stroke="#0284c7"
                                stroke-width="1.5"
                            />

                            <!-- Letreiro MVM Logística na porta -->
                            <rect x="128" y="68" width="46" height="15" rx="3" fill="rgba(15, 23, 42, 0.4)" />
                            <text x="151" y="79" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="8.5" font-weight="900" fill="#f8fafc" text-anchor="middle" letter-spacing="0.5">MVM LOG</text>

                            <!-- Para-brisa e janela lateral com reflexo -->
                            <path d="
                                M 140 38
                                L 174 38
                                C 184 38, 196 50, 204 62
                                L 140 62
                                Z"
                                fill="url(#windowGrad)"
                                stroke="#0369a1"
                                stroke-width="1"
                            />
                            <!-- Reflexo do vidro -->
                            <path d="M 152 40 L 164 40 L 148 60 L 142 60 Z" fill="#ffffff" opacity="0.6" />

                            <!-- Maçaneta cromada -->
                            <rect x="132" y="64" width="7" height="2" rx="1" fill="#e2e8f0" />

                            <!-- Retrovisor lateral -->
                            <rect x="178" y="52" width="6" height="10" rx="2" fill="#0284c7" stroke="#ffffff" stroke-width="0.7" />

                            <!-- Grade Frontal e Para-choque dianteiro -->
                            <rect x="216" y="78" width="10" height="12" rx="2" fill="#1e293b" />
                            <line x1="217" y1="82" x2="225" y2="82" stroke="#64748b" stroke-width="1.5" />
                            <line x1="217" y1="86" x2="225" y2="86" stroke="#64748b" stroke-width="1.5" />

                            <!-- Farol dianteiro (aceso com brilho) -->
                            <path d="M 218 66 L 226 70 L 224 76 L 217 76 Z" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                            <circle cx="221" cy="72" r="3" fill="#ffffff" />

                            <!-- Lanterna traseira vermelha -->
                            <rect x="17" y="76" width="3" height="10" rx="1" fill="#ef4444" />

                            <!-- Para-choque e Chassi inferior -->
                            <rect x="16" y="88" width="208" height="6" rx="2" fill="#0f172a" />
                        </g>

                        <!-- ================= RODAS GIRATÓRIAS ================= -->
                        <!-- Roda Traseira (Centro em x: 60, y: 94) -->
                        <g transform="translate(60, 94)">
                            <!-- Pneu -->
                            <circle cx="0" cy="0" r="17" fill="#0f172a" stroke="#334155" stroke-width="2.5" />
                            <!-- Calota / Aro esportivo que gira -->
                            <g class="truck-wheel-spin">
                                <circle cx="0" cy="0" r="10" fill="#94a3b8" />
                                <circle cx="0" cy="0" r="4" fill="#0f172a" />
                                <!-- Raios do aro -->
                                <line x1="0" y1="-10" x2="0" y2="10" stroke="#f8fafc" stroke-width="2.5" />
                                <line x1="-10" y1="0" x2="10" y2="0" stroke="#f8fafc" stroke-width="2.5" />
                                <line x1="-7" y1="-7" x2="7" y2="7" stroke="#f8fafc" stroke-width="2" />
                                <line x1="-7" y1="7" x2="7" y2="-7" stroke="#f8fafc" stroke-width="2" />
                            </g>
                        </g>

                        <!-- Roda Dianteira (Centro em x: 190, y: 94) -->
                        <g transform="translate(190, 94)">
                            <!-- Pneu -->
                            <circle cx="0" cy="0" r="17" fill="#0f172a" stroke="#334155" stroke-width="2.5" />
                            <!-- Calota / Aro esportivo que gira -->
                            <g class="truck-wheel-spin">
                                <circle cx="0" cy="0" r="10" fill="#94a3b8" />
                                <circle cx="0" cy="0" r="4" fill="#0f172a" />
                                <!-- Raios do aro -->
                                <line x1="0" y1="-10" x2="0" y2="10" stroke="#f8fafc" stroke-width="2.5" />
                                <line x1="-10" y1="0" x2="10" y2="0" stroke="#f8fafc" stroke-width="2.5" />
                                <line x1="-7" y1="-7" x2="7" y2="7" stroke="#f8fafc" stroke-width="2" />
                                <line x1="-7" y1="7" x2="7" y2="-7" stroke="#f8fafc" stroke-width="2" />
                            </g>
                        </g>
                    </svg>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Efeito sonoro do minicaminhão: bipe duplo suave ("bip bip") + acorde positivo
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }

        const playBeep = (freq, startTime, duration) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq, startTime);
            gain.gain.setValueAtTime(0.04, startTime);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(startTime);
            osc.stop(startTime + duration);
        };

        const now = audioCtx.currentTime;
        // Bipe alegre do caminhão chegando com a carga
        playBeep(440, now + 0.1, 0.12);
        playBeep(554.37, now + 0.28, 0.18);
        playBeep(659.25, now + 0.48, 0.28);
    } catch (e) {
        // Ignora silenciosamente caso o navegador bloqueie áudio automático
    }

    // Limpeza automática ao término da corrida do caminhão
    setTimeout(() => {
        if (overlay && overlay.parentNode) {
            overlay.remove();
        }
    }, 4500);
};

// Auto-disparo caso exista gatilho na sessão ao carregar a página
document.addEventListener('DOMContentLoaded', function () {
    const triggerTruck = document.querySelector('#trigger-animacao-caminhao');
    if (triggerTruck) {
        if (typeof window.animacoesHabilitadas === 'function' && !window.animacoesHabilitadas()) {
            return;
        }
        try {
            const dadosCaminhao = JSON.parse(triggerTruck.getAttribute('data-caminhao'));
            setTimeout(() => {
                window.dispararAnimacaoCaminhao(dadosCaminhao);
            }, 300);
        } catch (e) {
            console.error('Erro ao ler dados da animação de caminhão:', e);
        }
    }
});
