<?php
/**
 * Conexão com o Banco de Dados MySQL + Fallback Cloud Serverless (Vercel SQLite3)
 * Sistema MVM - Suporta Ambiente Local (XAMPP), Vercel Serverless e Hospedagem em Nuvem
 */

require_once __DIR__ . '/cloud_db.php';

function mvm_is_vercel_env(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    return defined('MVM_VERCEL_ROUTER')
        || getenv('VERCEL') === '1'
        || getenv('VERCEL_ENV') !== false
        || stripos($host, '.vercel.app') !== false;
}

function conectar() {
    $hostServidor = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $hostLimpo = explode(':', $hostServidor)[0];
    $isLocal = empty($hostLimpo) || in_array($hostLimpo, ['localhost', '127.0.0.1']);
    $isVercel = mvm_is_vercel_env();

    // Se estiver na Vercel sem DB_HOST externo configurado, usa imediatamente o motor Cloud Serverless (SQLite3 em /tmp)
    if ($isVercel && !getenv('DB_HOST') && !defined('DB_HOST')) {
        try {
            return new MvmCloudDB(sys_get_temp_dir() . '/bdmvm_cloud.sqlite');
        } catch (Throwable $e) {
            return "Erro ao iniciar banco em nuvem: " . $e->getMessage();
        }
    }

    // 1. Arquivo opcional de configuração em produção (carrega automaticamente fora do localhost e fora da Vercel sem DB externo)
    $arquivoProducao = __DIR__ . '/conexao_producao.php';
    if (!$isLocal && !$isVercel && file_exists($arquivoProducao)) {
        require_once $arquivoProducao;
    }

    if (!$isLocal) {
        // Credenciais de produção devem ser definidas em config/conexao_producao.php ou variáveis de ambiente
        $hostPadrao = 'localhost';
        $userPadrao = 'root';
        $passPadrao = '';
        $bdPadrao   = 'bdmvm';
    } else {
        // Credenciais locais XAMPP
        $hostPadrao = '127.0.0.1';
        $userPadrao = 'root';
        $passPadrao = '';
        $bdPadrao   = 'bdmvm';
    }

    // 2. Lê constantes ou variáveis de ambiente (com fallback para produção/local)
    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: $hostPadrao);
    $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: $userPadrao);
    $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: $passPadrao);
    $bd   = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: $bdPadrao);
    $port = defined('DB_PORT') ? (int)DB_PORT : (getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);

    // Se for localhost/127.0.0.1, tenta ambos caso o MySQL esteja em socket ou IP
    $hostsParaTentar = ($host === '127.0.0.1' || $host === 'localhost') ? ['127.0.0.1', 'localhost'] : [$host];

    // Fallback inteligente para nomes de banco comuns do projeto no XAMPP e InfinityFree
    $bancosParaTentar = [$bd];
    if ($bd === 'bdmvm') {
        $bancosParaTentar[] = 'mvm';
    } elseif ($bd === 'mvm') {
        $bancosParaTentar[] = 'bdmvm';
    }

    // Suporte flexível para bancos criados no InfinityFree (ex: if0_42798246_bdmvm ou if0_42798246)
    if (strpos($bd, 'if0_') === 0 || strpos($user, 'if0_') === 0) {
        $prefix = preg_replace('/_.*$/', '', $user);
        $bancosParaTentar[] = $bd;
        $bancosParaTentar[] = $prefix . '_bdmvm';
        $bancosParaTentar[] = $prefix;
        $bancosParaTentar[] = $prefix . '_mvm';
        $bancosParaTentar = array_values(array_unique($bancosParaTentar));
    }

    $ultimoErro = '';
    foreach ($hostsParaTentar as $h) {
        foreach ($bancosParaTentar as $bancoAtual) {
            try {
                $conexao = @mysqli_connect($h, $user, $pass, $bancoAtual, $port);
                if ($conexao && !mysqli_connect_errno()) {
                    mysqli_set_charset($conexao, "utf8mb4");
                    verificarEAtualizarColunas($conexao);
                    return $conexao;
                }
            } catch (Throwable $e) {
                $ultimoErro = $e->getMessage();
            }
        }
    }

    // Fallback automático para MvmCloudDB (SQLite3) caso o MySQL não esteja acessível na nuvem
    if (extension_loaded('pdo_sqlite')) {
        try {
            return new MvmCloudDB(sys_get_temp_dir() . '/bdmvm_cloud.sqlite');
        } catch (Throwable $e) {
            $ultimoErro = $e->getMessage();
        }
    }

    return "Erro ao conectar com o BD: " . ($ultimoErro ?: "Host ou banco inacessível.");
}

/**
 * Garante automaticamente colunas essenciais para novos recursos (Lotes, Validade, Kardex, Pedidos)
 */
function verificarEAtualizarColunas($conexao) {
    static $executado = false;
    if ($executado || !is_object($conexao)) {
        return;
    }
    $executado = true;

    $colunasProduto = [
        'idUsuario'      => "ALTER TABLE produto ADD COLUMN idUsuario INT NOT NULL DEFAULT 1",
        'idFornecedor'   => "ALTER TABLE produto ADD COLUMN idFornecedor INT NULL",
        'codigoBarras'   => "ALTER TABLE produto ADD COLUMN codigoBarras VARCHAR(20) NULL",
        'precoCusto'     => "ALTER TABLE produto ADD COLUMN precoCusto DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'estoqueMinimo'  => "ALTER TABLE produto ADD COLUMN estoqueMinimo INT NOT NULL DEFAULT 10",
        'lote'           => "ALTER TABLE produto ADD COLUMN lote VARCHAR(50) NULL",
        'dataValidade'   => "ALTER TABLE produto ADD COLUMN dataValidade DATE NULL",
        'localizacao'    => "ALTER TABLE produto ADD COLUMN localizacao VARCHAR(60) NOT NULL DEFAULT 'A-01-01'"
    ];

    $colunasMovimento = [
        'idUsuario'      => "ALTER TABLE movimento ADD COLUMN idUsuario INT NOT NULL DEFAULT 1",
        'codigoPedido'   => "ALTER TABLE movimento ADD COLUMN codigoPedido VARCHAR(50) NULL",
        'lote'           => "ALTER TABLE movimento ADD COLUMN lote VARCHAR(50) NULL",
        'dataValidade'   => "ALTER TABLE movimento ADD COLUMN dataValidade DATE NULL",
        'precoUnitario'  => "ALTER TABLE movimento ADD COLUMN precoUnitario DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'custoUnitario'  => "ALTER TABLE movimento ADD COLUMN custoUnitario DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'valorTotal'     => "ALTER TABLE movimento ADD COLUMN valorTotal DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'motivoAjuste'   => "ALTER TABLE movimento ADD COLUMN motivoAjuste VARCHAR(100) NULL"
    ];

    try {
        $res = @mysqli_query($conexao, "SHOW COLUMNS FROM produto");
        if ($res) {
            $existentes = [];
            while ($col = mysqli_fetch_assoc($res)) {
                $existentes[strtolower($col['Field'])] = true;
            }
            foreach ($colunasProduto as $col => $sqlAlter) {
                if (!isset($existentes[strtolower($col)])) {
                    @mysqli_query($conexao, $sqlAlter);
                }
            }
        }
    } catch (Throwable $e) {
        // Tabela ainda não criada ou sem permissão
    }

    try {
        $resMov = @mysqli_query($conexao, "SHOW COLUMNS FROM movimento");
        if ($resMov) {
            $existentesMov = [];
            while ($col = mysqli_fetch_assoc($resMov)) {
                $existentesMov[strtolower($col['Field'])] = true;
            }
            foreach ($colunasMovimento as $col => $sqlAlter) {
                if (!isset($existentesMov[strtolower($col)])) {
                    @mysqli_query($conexao, $sqlAlter);
                }
            }
        }
        // Sincroniza precoUnitario, custoUnitario e valorTotal em movimentações antigas
        @mysqli_query($conexao, "
            UPDATE movimento m
            INNER JOIN produto p ON p.idProduto = m.idProduto
            SET m.precoUnitario = CASE WHEN m.precoUnitario <= 0 THEN p.preco ELSE m.precoUnitario END,
                m.custoUnitario = CASE WHEN m.custoUnitario <= 0 THEN p.precoCusto ELSE m.custoUnitario END,
                m.valorTotal    = CASE WHEN m.valorTotal <= 0 THEN ROUND(m.quantidade * p.preco, 2) ELSE m.valorTotal END
            WHERE m.valorTotal <= 0 OR m.precoUnitario <= 0
        ");

        // Sincroniza chave estrangeira idFornecedor nos produtos com base no nomeFornecedor
        @mysqli_query($conexao, "
            UPDATE produto p
            INNER JOIN fornecedor f ON f.nomeFornecedor = p.nomeFornecedor AND f.idUsuario = p.idUsuario
            SET p.idFornecedor = f.idFornecedor
            WHERE p.idFornecedor IS NULL AND p.nomeFornecedor IS NOT NULL AND p.nomeFornecedor != ''
        ");

        // Preenche códigos EAN-13 padrão nos produtos caso ainda estejam vazios
        @mysqli_query($conexao, "
            UPDATE produto
            SET codigoBarras = CONCAT('7894900', LPAD(idProduto, 6, '0'))
            WHERE codigoBarras IS NULL OR codigoBarras = ''
        ");

        // Garante índices compostos de alta performance (BI, Kardex, FEFO e EAN)
        $indicesDesejados = [
            ['tabela' => 'produto',   'nome' => 'idx_prod_fefo',  'ddl' => 'CREATE INDEX idx_prod_fefo ON produto (idUsuario, dataValidade)'],
            ['tabela' => 'produto',   'nome' => 'idx_prod_ean',   'ddl' => 'CREATE INDEX idx_prod_ean ON produto (idUsuario, codigoBarras)'],
            ['tabela' => 'produto',   'nome' => 'idx_prod_cat',   'ddl' => 'CREATE INDEX idx_prod_cat ON produto (idUsuario, categoria)'],
            ['tabela' => 'movimento', 'nome' => 'idx_mov_bi',     'ddl' => 'CREATE INDEX idx_mov_bi ON movimento (idUsuario, tipoMovimento, dataMovimento)'],
            ['tabela' => 'movimento', 'nome' => 'idx_mov_kardex', 'ddl' => 'CREATE INDEX idx_mov_kardex ON movimento (idUsuario, idProduto, dataMovimento)']
        ];
        foreach ($indicesDesejados as $idx) {
            $resIdx = @mysqli_query($conexao, "SHOW INDEX FROM {$idx['tabela']} WHERE Key_name = '{$idx['nome']}'");
            if ($resIdx && mysqli_num_rows($resIdx) === 0) {
                @mysqli_query($conexao, $idx['ddl']);
            }
        }

        // Garante Views analíticas no banco de dados
        @mysqli_query($conexao, "
            CREATE OR REPLACE VIEW vw_estoque_critico AS
            SELECT 
                p.idProduto, p.idUsuario, p.codigoBarras, p.nomeProduto, p.categoria,
                p.quantidade AS estoqueAtual, p.estoqueMinimo, p.localizacao,
                p.preco, p.precoCusto, p.lote, p.dataValidade,
                DATEDIFF(p.dataValidade, CURDATE()) AS diasParaVencer,
                COALESCE(f.nomeFornecedor, p.nomeFornecedor, 'Não vinculado') AS fornecedor,
                f.telefone AS telefoneFornecedor
            FROM produto p
            LEFT JOIN fornecedor f ON f.idFornecedor = p.idFornecedor
            WHERE p.quantidade <= p.estoqueMinimo
               OR (p.dataValidade IS NOT NULL AND p.dataValidade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        ");

        @mysqli_query($conexao, "
            CREATE OR REPLACE VIEW vw_resumo_financeiro_produto AS
            SELECT 
                p.idProduto, p.idUsuario, p.codigoBarras, p.nomeProduto, p.categoria, p.localizacao,
                p.quantidade AS saldoEstoque, p.preco AS precoVendaAtual, p.precoCusto AS custoUnitarioAtual,
                ROUND(p.quantidade * p.preco, 2) AS valorVendaEstoque,
                ROUND(p.quantidade * p.precoCusto, 2) AS valorCustoEstoque,
                COALESCE(SUM(CASE WHEN m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL THEN m.quantidade ELSE 0 END), 0) AS totalUnidadesVendidas,
                COALESCE(SUM(CASE WHEN m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL THEN m.valorTotal ELSE 0 END), 0) AS receitaAcumulada
            FROM produto p
            LEFT JOIN movimento m ON m.idProduto = p.idProduto AND m.idUsuario = p.idUsuario
            GROUP BY p.idProduto, p.idUsuario, p.codigoBarras, p.nomeProduto, p.categoria, p.localizacao, p.quantidade, p.preco, p.precoCusto
        ");

        // Garante colunas apiToken, tokenRecuperacao e expiraRecuperacao na tabela usuario
        $resUserCols = @mysqli_query($conexao, "SHOW COLUMNS FROM usuario");
        if ($resUserCols) {
            $colsU = [];
            while ($cu = mysqli_fetch_assoc($resUserCols)) {
                $colsU[strtolower($cu['Field'])] = true;
            }
            if (!isset($colsU['apitoken'])) {
                @mysqli_query($conexao, "ALTER TABLE usuario ADD COLUMN apiToken VARCHAR(80) NULL");
            }
            if (!isset($colsU['tokenrecuperacao'])) {
                @mysqli_query($conexao, "ALTER TABLE usuario ADD COLUMN tokenRecuperacao VARCHAR(100) NULL DEFAULT NULL");
            }
            if (!isset($colsU['expirarecuperacao'])) {
                @mysqli_query($conexao, "ALTER TABLE usuario ADD COLUMN expiraRecuperacao DATETIME NULL DEFAULT NULL");
            }
            if (!isset($colsU['planoassinatura'])) {
                @mysqli_query($conexao, "ALTER TABLE usuario ADD COLUMN planoAssinatura VARCHAR(30) NOT NULL DEFAULT 'ESSENCIAL'");
                @mysqli_query($conexao, "UPDATE usuario SET planoAssinatura = 'PREMIUM' WHERE idUsuario = 1");
            }
            if (!isset($colsU['cicloplano'])) {
                @mysqli_query($conexao, "ALTER TABLE usuario ADD COLUMN cicloPlano VARCHAR(15) NOT NULL DEFAULT 'MENSAL'");
            }
            if (!isset($colsU['validadeplano'])) {
                @mysqli_query($conexao, "ALTER TABLE usuario ADD COLUMN validadePlano DATE NULL DEFAULT NULL");
                @mysqli_query($conexao, "UPDATE usuario SET validadePlano = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE validadePlano IS NULL");
            }
            if (!isset($colsU['dataaceitetermos'])) {
                @mysqli_query($conexao, "ALTER TABLE usuario ADD COLUMN dataAceiteTermos DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
            }
            @mysqli_query($conexao, "UPDATE usuario SET apiToken = 'mvm_live_token_77a89b42' WHERE idUsuario = 1 AND (apiToken IS NULL OR apiToken = '')");
            @mysqli_query($conexao, "UPDATE usuario SET apiToken = CONCAT('mvm_live_', SUBSTRING(MD5(CONCAT(idUsuario, nomeUsuario, 'mvm_salt')), 1, 16)) WHERE apiToken IS NULL OR apiToken = ''");
        }
    } catch (Throwable $e) {
        // Tabela ainda não criada ou sem permissão
    }
}

/**
 * Retorna o ID do usuário logado na sessão ou busca pelo identificador salvo
 */
function obterIdUsuarioLogado() {
    iniciarSessaoSegura();
    if (!empty($_SESSION['idUsuario'])) {
        return (int)$_SESSION['idUsuario'];
    }
    if (!empty($_SESSION['usuarioLogado'])) {
        $conexao = conectar();
        if (!is_string($conexao)) {
            $stmt = mvm_db_prepare($conexao, "SELECT idUsuario FROM usuario WHERE nomeUsuario = ? OR emailUsuario = ?");
            if ($stmt) {
                mvm_db_stmt_bind_param($stmt, "ss", $_SESSION['usuarioLogado'], $_SESSION['usuarioLogado']);
                mvm_db_stmt_execute($stmt);
                $res = mvm_db_stmt_get_result($stmt);
                if ($res && $row = mvm_db_fetch_assoc($res)) {
                    $_SESSION['idUsuario'] = (int)$row['idUsuario'];
                    mvm_db_close($conexao);
                    return (int)$row['idUsuario'];
                }
            }
            mvm_db_close($conexao);
        }
    }
    return 0;
}

/**
 * Chave HMAC para assinatura do cookie de sessão stateless (Vercel Serverless)
 */
function mvm_obter_chave_sessao(): string {
    return getenv('APP_KEY') ?: 'mvm_estoque_serverless_hmac_secret_2026_v1';
}

/**
 * Sincroniza dados essenciais da $_SESSION em cookie assinado (HMAC-SHA256) para persistência em ambientes Serverless
 */
function mvm_sincronizar_cookie_sessao(): void {
    if (headers_sent()) {
        return;
    }
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    if (empty($_SESSION['usuarioLogado']) && empty($_SESSION['csrf_token'])) {
        if (!empty($_COOKIE['mvm_vercel_sess'])) {
            @setcookie('mvm_vercel_sess', '', [
                'expires'  => time() - 42000,
                'path'     => '/',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        return;
    }

    $chavesPersistentes = [
        'usuarioLogado', 'idUsuario', 'emailUsuario', 'planoUsuario',
        'roleUsuario', 'csrf_token', 'flash_dashboard', 'flash_plano', 'flash_movimento'
    ];
    $payload = [];
    foreach ($chavesPersistentes as $k) {
        if (isset($_SESSION[$k])) {
            $payload[$k] = $_SESSION[$k];
        }
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, mvm_obter_chave_sessao());
    $valorCookie = $b64 . '.' . $sig;

    if (($_COOKIE['mvm_vercel_sess'] ?? '') !== $valorCookie) {
        @setcookie('mvm_vercel_sess', $valorCookie, [
            'expires'  => time() + 86400 * 7,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Inicialização segura de sessão com proteção de cookies e fallback stateless para Serverless
 */
function iniciarSessaoSegura() {
    static $callbackRegistrado = false;
    if (session_status() === PHP_SESSION_NONE) {
        $savePath = session_save_path();
        if (empty($savePath) || !@is_dir($savePath) || !@is_writable($savePath)) {
            $tempDir = sys_get_temp_dir();
            if (@is_dir($tempDir) && @is_writable($tempDir)) {
                @session_save_path($tempDir);
            }
        }
        if (!headers_sent()) {
            $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        @session_start();
    }

    // Restaura sessão do cookie assinado caso a instância Serverless tenha trocado de container
    if (empty($_SESSION['usuarioLogado']) && !empty($_COOKIE['mvm_vercel_sess'])) {
        $partes = explode('.', (string)$_COOKIE['mvm_vercel_sess'], 2);
        if (count($partes) === 2) {
            [$b64, $sig] = $partes;
            $sigEsperada = hash_hmac('sha256', $b64, mvm_obter_chave_sessao());
            if (hash_equals($sigEsperada, $sig)) {
                $json = base64_decode(strtr($b64, '-_', '+/'), true);
                $dados = $json ? json_decode($json, true) : null;
                if (is_array($dados)) {
                    foreach ($dados as $k => $v) {
                        if (!isset($_SESSION[$k])) {
                            $_SESSION[$k] = $v;
                        }
                    }
                }
            }
        }
    }

    if (!$callbackRegistrado && function_exists('header_register_callback')) {
        $callbackRegistrado = true;
        @header_register_callback('mvm_sincronizar_cookie_sessao');
    }
}

/**
 * Obtém ou gera o token CSRF da sessão atual
 */
function obterTokenCSRF() {
    iniciarSessaoSegura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna o campo HTML oculto com o token CSRF
 */
function campoCSRF() {
    $token = htmlspecialchars(obterTokenCSRF(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Valida o token CSRF enviado na requisição
 */
function validarTokenCSRF($token = null) {
    iniciarSessaoSegura();
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Envia cabeçalhos HTTP essenciais de proteção do navegador
 */
function enviarHeadersSeguranca() {
    if (!headers_sent()) {
        header("X-Frame-Options: SAMEORIGIN");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }
}

/**
 * Retorna a URL base do projeto (compatível com XAMPP em subpastas, Vercel e servidores de produção)
 */
function obterBaseUrl() {
    static $baseUrl = null;
    if ($baseUrl !== null) {
        return $baseUrl;
    }

    if (mvm_is_vercel_env()) {
        $baseUrl = '';
        return $baseUrl;
    }

    $dirRaiz = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';

    if (!empty($docRoot) && strpos($dirRaiz, $docRoot) === 0) {
        $baseUrl = substr($dirRaiz, strlen($docRoot));
    } else {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('#^(.*?)/(modulos/[^/]+|scripts|docs|exportacao|api(?:/v\d+)?)#', $scriptDir, $matches)) {
            $baseUrl = $matches[1];
        } else {
            $baseUrl = $scriptDir;
        }
    }

    $baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/');
    return $baseUrl;
}

/**
 * Monta a URL completa para qualquer asset ou rota interna a partir da raiz do projeto
 */
function url($caminho = '') {
    $base = obterBaseUrl();
    $caminho = ltrim($caminho, '/');
    if ($caminho === '') {
        return $base ?: '/';
    }
    return ($base ? $base . '/' : '/') . $caminho;
}

// Inicializa a sessão segura e os headers globais
iniciarSessaoSegura();
enviarHeadersSeguranca();
?>