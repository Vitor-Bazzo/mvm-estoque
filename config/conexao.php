<?php
/**
 * Conexão com o Banco de Dados MySQL
 * Sistema MVM - Suporta Ambiente Local (XAMPP) e Hospedagem em Nuvem (InfinityFree / cPanel / VPS)
 */

function conectar() {
    // 1. Arquivo opcional de configuração em produção (se você criar conexao_producao.php)
    $arquivoProducao = __DIR__ . '/conexao_producao.php';
    if (file_exists($arquivoProducao)) {
        require_once $arquivoProducao;
    }

    // 2. Lê constantes ou variáveis de ambiente (com fallback automático para XAMPP local)
    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');
    $bd   = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'bdmvm');
    $port = defined('DB_PORT') ? (int)DB_PORT : (getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);

    // Se for localhost/127.0.0.1, tenta ambos caso o MySQL esteja em socket ou IP
    $hostsParaTentar = ($host === '127.0.0.1' || $host === 'localhost') ? ['127.0.0.1', 'localhost'] : [$host];

    // Fallback inteligente para nomes de banco comuns do projeto no XAMPP local (bdmvm / mvm)
    $bancosParaTentar = [$bd];
    if ($bd === 'bdmvm') {
        $bancosParaTentar[] = 'mvm';
    } elseif ($bd === 'mvm') {
        $bancosParaTentar[] = 'bdmvm';
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
        'idFornecedor'   => "ALTER TABLE produto ADD COLUMN idFornecedor INT NULL",
        'precoCusto'     => "ALTER TABLE produto ADD COLUMN precoCusto DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'estoqueMinimo'  => "ALTER TABLE produto ADD COLUMN estoqueMinimo INT NOT NULL DEFAULT 10",
        'lote'           => "ALTER TABLE produto ADD COLUMN lote VARCHAR(50) NULL",
        'dataValidade'   => "ALTER TABLE produto ADD COLUMN dataValidade DATE NULL",
        'localizacao'    => "ALTER TABLE produto ADD COLUMN localizacao VARCHAR(60) NOT NULL DEFAULT 'A-01-01'"
    ];

    $colunasMovimento = [
        'codigoPedido'   => "ALTER TABLE movimento ADD COLUMN codigoPedido VARCHAR(50) NULL",
        'lote'           => "ALTER TABLE movimento ADD COLUMN lote VARCHAR(50) NULL",
        'dataValidade'   => "ALTER TABLE movimento ADD COLUMN dataValidade DATE NULL",
        'valorTotal'     => "ALTER TABLE movimento ADD COLUMN valorTotal DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'motivoAjuste'   => "ALTER TABLE movimento ADD COLUMN motivoAjuste VARCHAR(100) NULL"
    ];

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
            $stmt = mysqli_prepare($conexao, "SELECT idUsuario FROM usuario WHERE nomeUsuario = ? OR emailUsuario = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $_SESSION['usuarioLogado'], $_SESSION['usuarioLogado']);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                if ($res && $row = mysqli_fetch_assoc($res)) {
                    $_SESSION['idUsuario'] = (int)$row['idUsuario'];
                    mysqli_close($conexao);
                    return (int)$row['idUsuario'];
                }
            }
            mysqli_close($conexao);
        }
    }
    return 0;
}

/**
 * Inicialização segura de sessão com proteção de cookies
 */
function iniciarSessaoSegura() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_start();
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

// Inicializa a sessão segura e os headers globais
iniciarSessaoSegura();
enviarHeadersSeguranca();
?>