<?php
$titulo_pagina = "Mascote Oficial - MVM Estoque";
require_once "templates/header.php";
?>

<div class="page-container" style="max-width: 1000px; margin: 0 auto; padding: 30px 20px;">
    
    <!-- CABEÇALHO DO SHOWCASE -->
    <div style="text-align: center; margin-bottom: 40px;">
        <span style="display: inline-block; padding: 6px 16px; background: rgba(0, 112, 74, 0.15); color: #00704A; border-radius: 50px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; border: 1px solid rgba(0, 112, 74, 0.3);">
            Identidade Visual &amp; Branding
        </span>
        <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 12px;">Mascote Oficial: A Formiga Operadora MVM</h1>
        <p style="color: var(--text-muted); font-size: 16px; max-width: 650px; margin: 0 auto;">
            Emblema circular corporativo inspirado no design icônico e simétrico da Starbucks, combinando a força e cooperação da formiga com os elementos essenciais de armazenagem, logística e controle de estoque.
        </p>
    </div>

    <!-- CARD PRINCIPAL DE APRESENTAÇÃO DO MASCOTE -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 40px; box-shadow: var(--shadow-md); margin-bottom: 35px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center;">
        
        <!-- VISUALIZAÇÃO DO MASCOTE (CENTRO EM DESTAQUE) -->
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: radial-gradient(circle at center, rgba(0, 112, 74, 0.12) 0%, transparent 70%); padding: 30px; border-radius: var(--radius-md);">
            <div style="width: 320px; height: 320px; filter: drop-shadow(0 20px 30px rgba(0, 77, 52, 0.35)); transition: transform 0.4s ease;" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                <img src="img/mascote_mvm.svg?v=3" alt="Mascote Oficial Formiga MVM Estoque" style="width: 100%; height: 100%; display: block;">
            </div>
            <div style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                <a href="img/mascote_mvm.svg?v=3" download="mascote_mvm_estoque.svg" class="btn-primary" style="padding: 10px 18px; font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <span>📥</span> Baixar Vetor SVG
                </a>
                <button type="button" onclick="baixarPNG(1024)" class="btn-secondary" style="padding: 10px 18px; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border-radius: var(--radius-sm);">
                    <span>🖼️</span> Baixar PNG (1024px)
                </button>
            </div>
        </div>

        <!-- DETALHES CONCEITUAIS E ELEMENTOS INTEGRADOS -->
        <div>
            <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 20px; color: var(--text-main);">
                Conceito &amp; Elementos de Estoque
            </h2>

            <div style="display: flex; flex-direction: column; gap: 18px;">
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(0, 112, 74, 0.15); color: #00704A; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🐜
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">A Formiga como Símbolo</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            Representa capacidade de carga, organização metódica, disciplina e trabalho em equipe incansável — os maiores valores de uma operação de estoque eficiente.
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(0, 112, 74, 0.15); color: #00704A; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        ⛑️
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">Capacete Industrial MVM</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            Substitui a coroa da sereia do logo Starbucks por um capacete de segurança de operador logístico, ostentando o emblema "M" e as antenas sinuosas.
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(0, 112, 74, 0.15); color: #00704A; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        📦
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">Caixa de Encomenda Central</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            A formiga carrega no peito uma caixa com fita de vedação, código de barras e símbolos de manuseio ("Este Lado Para Cima" e selo de conferência).
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(0, 112, 74, 0.15); color: #00704A; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🏅
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">Estilo Circular Starbucks</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            Emblema em medalhão simétrico com verde floresta e branco em espaço negativo, tipografia circular clássica e caixas isométricas no lugar das estrelas laterais.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- SEÇÃO DE APLICAÇÕES PRÁTICAS DO MASCOTE -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <!-- Card 1: Fundo Escuro -->
        <div style="background: #0f172a; border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid #1e293b;">
            <div style="font-size: 13px; color: #94a3b8; font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Aplicação em Fundo Escuro</div>
            <div style="width: 120px; height: 120px; margin: 0 auto;">
                <img src="img/mascote_mvm.svg?v=3" alt="Versão Fundo Escuro" style="width: 100%; height: 100%;">
            </div>
            <p style="color: #cbd5e1; font-size: 13px; margin-top: 15px;">Perfeito para Modo Noturno, dashboards BI e telas de sistema.</p>
        </div>

        <!-- Card 2: Fundo Claro -->
        <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid #e2e8f0;">
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Aplicação em Fundo Claro</div>
            <div style="width: 120px; height: 120px; margin: 0 auto;">
                <img src="img/mascote_mvm.svg?v=3" alt="Versão Fundo Claro" style="width: 100%; height: 100%;">
            </div>
            <p style="color: #475569; font-size: 13px; margin-top: 15px;">Ideal para cabeçalho, relatórios em papel timbrado e notas fiscais.</p>
        </div>

        <!-- Card 3: Formato Ícone / Favicon -->
        <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid var(--border);">
            <div style="font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Tamanhos Reduzidos</div>
            <div style="display: flex; align-items: center; justify-content: center; gap: 20px; height: 120px;">
                <img src="img/mascote_mvm.svg?v=3" alt="64px" style="width: 64px; height: 64px;">
                <img src="img/mascote_mvm.svg?v=3" alt="40px" style="width: 40px; height: 40px;">
                <img src="img/mascote_mvm.svg?v=3" alt="24px" style="width: 24px; height: 24px;">
            </div>
            <p style="color: var(--text-muted); font-size: 13px;">Legibilidade preservada mesmo em favicons e badges pequenos.</p>
        </div>
    </div>

</div>

<script>
function baixarPNG(tamanho = 1024) {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function() {
        const canvas = document.createElement('canvas');
        canvas.width = tamanho;
        canvas.height = tamanho;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, tamanho, tamanho);
        
        const a = document.createElement('a');
        a.download = `mascote_mvm_estoque_${tamanho}x${tamanho}.png`;
        a.href = canvas.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };
    img.src = 'img/mascote_mvm.svg?v=3';
}
</script>

<?php require_once "templates/footer.php"; ?>
