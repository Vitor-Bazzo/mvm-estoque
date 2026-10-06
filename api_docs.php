<?php
$titulo_pagina = "Documentação & Teste de APIs - MVM Estoque";
require_once "templates/header.php";
require_once "config/conexao.php";

$idUsuario = obterIdUsuarioLogado();
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
$tokenExemplo = 'mvm_live_token_77a89b42';
?>

<style>
    .api-container {
        max-width: 1120px;
        margin: 0 auto;
        padding-bottom: 50px;
    }
    .api-hero {
        background: linear-gradient(135deg, rgba(0, 112, 74, 0.08) 0%, rgba(99, 102, 241, 0.12) 100%);
        border: 1px solid rgba(99, 102, 241, 0.25);
        border-radius: var(--radius);
        padding: 26px 30px;
        margin-bottom: 26px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    .api-hero-title h1 {
        font-size: 22px;
        font-weight: 800;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .api-hero-title p {
        color: var(--text-muted);
        font-size: 13.5px;
        margin-top: 5px;
    }

    .api-cred-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 12px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }
    .api-key-code {
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        font-weight: 700;
        background: var(--bg-subtle);
        padding: 5px 10px;
        border-radius: 4px;
        border: 1px solid var(--border);
        color: var(--primary);
    }

    .api-endpoint-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .endpoint-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .endpoint-badge-route {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .http-badge {
        font-size: 12px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 4px;
        font-family: 'JetBrains Mono', monospace;
    }
    .http-get { background: #dbeafe; color: #1e40af; }
    .http-post { background: #dcfce7; color: #166534; }

    .route-path {
        font-family: 'JetBrains Mono', monospace;
        font-size: 15px;
        font-weight: 700;
        color: var(--text-main);
    }

    .code-box {
        background: #0f172a;
        color: #f8fafc;
        border-radius: 6px;
        padding: 16px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 12.5px;
        line-height: 1.5;
        overflow-x: auto;
        position: relative;
        margin: 12px 0;
    }

    .json-output-viewer {
        background: #090d16;
        color: #38bdf8;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 16px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        max-height: 320px;
        overflow-y: auto;
        white-space: pre-wrap;
        margin-top: 12px;
        display: none;
    }

    .btn-exec-api {
        background: var(--primary);
        color: #fff;
        font-weight: 700;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12.5px;
        transition: all 0.2s ease;
    }
    .btn-exec-api:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }

    .table-params {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
        margin: 12px 0;
    }
    .table-params th {
        background: var(--bg-subtle);
        padding: 8px 12px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        color: var(--text-muted);
        font-size: 11px;
        text-transform: uppercase;
    }
    .table-params td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-subtle);
    }
</style>

<div class="page-container api-container">

    <!-- HERO DO PORTAL DE API -->
    <div class="api-hero">
        <div class="api-hero-title">
            <h1><span>⚡</span> Portal de APIs &amp; Integrações RESTful</h1>
            <p>Conecte o MVM Estoque a Lojas Virtuais, Coletores de Dados (Android/Zebra) e ERPs corporativos via JSON</p>
        </div>
        <div class="api-cred-card">
            <div>
                <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; text-transform: uppercase;">Chave de API (X-API-KEY):</span>
                <span class="api-key-code" id="token-display"><?= $tokenExemplo ?></span>
            </div>
            <button type="button" class="btn-secondary" onclick="copiarToken()" style="padding: 6px 10px; font-size: 12px;" title="Copiar Token">📋 Copiar</button>
        </div>
    </div>

    <!-- 1. ENDPOINT: GET /api/v1/estoque.php -->
    <div class="api-endpoint-card">
        <div class="endpoint-header">
            <div class="endpoint-badge-route">
                <span class="http-badge http-get">GET</span>
                <span class="route-path">/api/v1/estoque.php</span>
            </div>
            <button type="button" class="btn-exec-api" onclick="testarGetEstoque()">
                <span>🚀</span> Testar Endpoint ao Vivo
            </button>
        </div>

        <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5;">
            Retorna a lista completa de produtos do armazém com saldos atuais, preços, lote, validade FEFO e o <strong>endereço logístico WMS</strong> de cada produto.
        </p>

        <h4 style="font-size: 12.5px; text-transform: uppercase; margin-top: 14px; color: var(--text-main);">Parâmetros Opcionais (Query String):</h4>
        <table class="table-params">
            <thead>
                <tr>
                    <th style="width: 140px;">Parâmetro</th>
                    <th style="width: 100px;">Tipo</th>
                    <th>Descrição</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>busca</code></td>
                    <td>string</td>
                    <td>Filtra produtos por nome, categoria ou endereço físico (Ex: <code>?busca=Coca</code> ou <code>?busca=A-01</code>)</td>
                </tr>
                <tr>
                    <td><code>status</code></td>
                    <td>string</td>
                    <td>Filtra itens críticos por situação de estoque: <code>NORMAL</code>, <code>BAIXO</code> ou <code>ESGOTADO</code></td>
                </tr>
            </tbody>
        </table>

        <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); margin-top: 12px;">Exemplo de Chamada via cURL:</div>
        <div class="code-box">curl -X GET "<?= $baseUrl ?>/api/v1/estoque.php" \<br>     -H "X-API-KEY: <?= $tokenExemplo ?>"</div>

        <!-- Área de Saída Interativa JSON -->
        <div id="output-get-estoque" class="json-output-viewer"></div>
    </div>

    <!-- 2. ENDPOINT: POST /api/v1/estoque.php -->
    <div class="api-endpoint-card">
        <div class="endpoint-header">
            <div class="endpoint-badge-route">
                <span class="http-badge http-post">POST</span>
                <span class="route-path">/api/v1/estoque.php</span>
            </div>
            <button type="button" class="btn-exec-api" onclick="testarPostMovimento()">
                <span>⚡</span> Simular Baixa via API
            </button>
        </div>

        <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5;">
            Registra movimentações automáticas no estoque (como saídas após aprovação de venda no e-commerce ou entradas por leitor coletor). Atualiza o saldo atômico com isolamento de transação (ACID).
        </p>

        <h4 style="font-size: 12.5px; text-transform: uppercase; margin-top: 14px; color: var(--text-main);">Payload JSON (Body):</h4>
        <div class="code-box">{
  "idProduto": 1,
  "tipoMovimento": "SAIDA",
  "quantidade": 1,
  "observacao": "Baixa automatizada via API de E-commerce"
}</div>

        <!-- Área de Saída Interativa JSON -->
        <div id="output-post-movimento" class="json-output-viewer"></div>
    </div>

    <!-- 3. API EXTERNA: CONSULTA DE EAN-13 (OPEN FOOD FACTS) -->
    <div class="api-endpoint-card">
        <div class="endpoint-header">
            <div class="endpoint-badge-route">
                <span class="http-badge http-get">GET</span>
                <span class="route-path">/api/consulta_ean.php?ean={codigo}</span>
            </div>
            <a href="produto.php" class="btn-primary" style="text-decoration:none; font-size:12px; padding: 7px 12px;">
                <span>📦</span> Ver no Cadastro de Produtos
            </a>
        </div>

        <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5;">
            Integração com a base de dados global <strong>Open Food Facts API</strong> para busca instantânea de produtos por código de barras (EAN-13 / GTIN). Puxa automaticamente nome, marca, ingredientes e foto oficial da embalagem para eliminar a digitação manual de novos cadastros.
        </p>
    </div>

</div>

<script>
    function copiarToken() {
        const token = document.getElementById('token-display').textContent;
        navigator.clipboard.writeText(token).then(() => {
            alert('Chave de API copiada para a área de transferência!');
        });
    }

    async function testarGetEstoque() {
        const out = document.getElementById('output-get-estoque');
        out.style.display = 'block';
        out.textContent = '// Enviando requisição GET para /api/v1/estoque.php...';

        try {
            const resp = await fetch('api/v1/estoque.php?token=mvm_demo');
            const data = await resp.json();
            out.textContent = JSON.stringify(data, null, 2);
        } catch (err) {
            out.textContent = '// Erro na requisição: ' + err.message;
        }
    }

    async function testarPostMovimento() {
        const out = document.getElementById('output-post-movimento');
        out.style.display = 'block';
        out.textContent = '// Enviando requisição POST para registrar baixa de 1 unidade...';

        try {
            const resp = await fetch('api/v1/estoque.php?token=mvm_demo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    idProduto: 1,
                    tipoMovimento: 'SAIDA',
                    quantidade: 1,
                    observacao: 'Baixa de demonstração disparada pelo Portal de APIs'
                })
            });
            const data = await resp.json();
            out.textContent = `// Resposta HTTP ${resp.status}:\n` + JSON.stringify(data, null, 2);
        } catch (err) {
            out.textContent = '// Erro na requisição: ' + err.message;
        }
    }
</script>

<?php require_once "templates/footer.php"; ?>
