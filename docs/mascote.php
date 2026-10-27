<?php
require_once __DIR__ . "/../config/conexao.php";
$titulo_pagina = "Mascote Oficial - MVM Estoque";
require_once __DIR__ . "/../templates/header.php";
?>

<div class="page-container" style="max-width: 1000px; margin: 0 auto; padding: 30px 20px;">
    
    <!-- CABEÇALHO DO SHOWCASE -->
    <div style="text-align: center; margin-bottom: 40px;">
        <span style="display: inline-block; padding: 6px 16px; background: rgba(0, 112, 74, 0.15); color: #00704A; border-radius: 50px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; border: 1px solid rgba(0, 112, 74, 0.3);">
            Identidade Visual &amp; Branding
        </span>
        <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 12px;">Emblema Oficial: O Selo Circular de Logística MVM</h1>
        <p style="color: var(--text-muted); font-size: 16px; max-width: 650px; margin: 0 auto;">
            Identidade corporativa minimalista combinando a silhueta ágil da formiga, a caixa isométrica de armazenagem e as setas do ciclo contínuo de controle de estoque.
        </p>
    </div>

    <!-- CARD PRINCIPAL DE APRESENTAÇÃO DO MASCOTE -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 40px; box-shadow: var(--shadow-md); margin-bottom: 35px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center;">
        
        <!-- VISUALIZAÇÃO DO MASCOTE (CENTRO EM DESTAQUE) -->
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: radial-gradient(circle at center, rgba(16, 185, 129, 0.15) 0%, transparent 70%); padding: 30px; border-radius: var(--radius-md);">
            <div style="width: 320px; height: 320px; filter: drop-shadow(0 20px 30px rgba(0, 0, 0, 0.45)); transition: transform 0.4s ease;" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                <img src="<?= url('img/mascote_mvm.svg?v=5') ?>" alt="Emblema Oficial MVM Estoque" style="width: 100%; height: 100%; display: block; border-radius: 50%; object-fit: cover;">
            </div>
            <div style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                <a href="<?= url('img/mascote_mvm.svg?v=5') ?>" download="mascote_mvm_estoque.svg" class="btn-primary" style="padding: 10px 18px; font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <span>📥</span> Baixar Vetor SVG
                </a>
                <a href="<?= url('img/mascote_mvm.png') ?>" download="mascote_mvm_estoque_1024x1024.png" class="btn-secondary" style="padding: 10px 18px; font-size: 13.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border-radius: var(--radius-sm);">
                    <span>🖼️</span> Baixar PNG (1024px)
                </a>
            </div>
        </div>

        <!-- DETALHES CONCEITUAIS E ELEMENTOS INTEGRADOS -->
        <div>
            <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 20px; color: var(--text-main);">
                Conceito &amp; Elementos de Logística
            </h2>

            <div style="display: flex; flex-direction: column; gap: 18px;">
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🐜
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">A Formiga como Símbolo</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            Representa a superação de carga, precisão, disciplina e trabalho em equipe incansável — os maiores pilares de uma operação logística de excelência.
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🔄
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">Setas de Rotação &amp; Fluxo</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            O ciclo contínuo em volta da mercadoria simboliza o método FEFO (*First-Expired, First-Out*), a auditoria Kardex e a agilidade nas devoluções.
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        📦
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">Caixa Isométrica Central</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            A encomenda selada com fita e código de barras personifica a segurança e o controle unitário de cada item armazenado no sistema.
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🏅
                    </div>
                    <div>
                        <strong style="font-size: 15px; color: var(--text-main);">Selo Flat Minimalista</strong>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                            Design corporativo atemporal em tons de verde esmeralda, ardósia e ouro sutil, garantindo alta legibilidade em telas, crachás e recibos térmicos.
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
                <img src="<?= url('img/mascote_mvm.svg?v=5') ?>" alt="Versão Fundo Escuro" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            </div>
            <p style="color: #cbd5e1; font-size: 13px; margin-top: 15px;">Perfeito para Modo Noturno, dashboards BI e telas de sistema.</p>
        </div>

        <!-- Card 2: Fundo Claro -->
        <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid #e2e8f0;">
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Aplicação em Fundo Claro</div>
            <div style="width: 120px; height: 120px; margin: 0 auto;">
                <img src="<?= url('img/mascote_mvm.svg?v=5') ?>" alt="Versão Fundo Claro" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            </div>
            <p style="color: #475569; font-size: 13px; margin-top: 15px;">Ideal para cabeçalho, relatórios em papel timbrado e notas fiscais.</p>
        </div>

        <!-- Card 3: Formato Ícone / Favicon -->
        <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 25px; text-align: center; border: 1px solid var(--border);">
            <div style="font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 15px; text-transform: uppercase;">Tamanhos Reduzidos</div>
            <div style="display: flex; align-items: center; justify-content: center; gap: 20px; height: 120px;">
                <img src="<?= url('img/mascote_mvm.svg?v=5') ?>" alt="64px" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;">
                <img src="<?= url('img/mascote_mvm.svg?v=5') ?>" alt="40px" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                <img src="<?= url('img/mascote_mvm.svg?v=5') ?>" alt="24px" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
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
    img.src = '<?= url('img/mascote_mvm.svg?v=4') ?>';
}
</script>

<?php require_once __DIR__ . "/../templates/footer.php"; ?>
