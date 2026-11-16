<?php
/**
 * Front Controller Serverless para Vercel (vercel-php)
 * Roteia todas as páginas e módulos PHP através de uma única Serverless Function,
 * evitando o download de arquivos .php estáticos e respeitando o limite do plano Hobby.
 */

define('MVM_VERCEL_ROUTER', true);

$projectRoot = realpath(__DIR__ . '/..');
$mirrorRoot  = rtrim(sys_get_temp_dir(), '/\\') . '/mvm_vercel_runtime_v2';

/**
 * Prepara o espelho de execução em /tmp convertendo chamadas mysqli_* para o adaptador híbrido mvm_db_*
 */
function mvm_preparar_ambiente_serverless(string $srcRoot, string $dstRoot): void {
    $markerFile = $dstRoot . '/.mvm_ready_' . md5((string)@filemtime($srcRoot . '/config/conexao.php') . '_' . (string)@filemtime($srcRoot . '/config/cloud_db.php'));
    if (file_exists($markerFile)) {
        return;
    }

    $mapaFuncoes = [
        '/\bmysqli_prepare\s*\(/'            => 'mvm_db_prepare(',
        '/\bmysqli_stmt_bind_param\s*\(/'    => 'mvm_db_stmt_bind_param(',
        '/\bmysqli_stmt_execute\s*\(/'       => 'mvm_db_stmt_execute(',
        '/\bmysqli_stmt_get_result\s*\(/'    => 'mvm_db_stmt_get_result(',
        '/\bmysqli_stmt_affected_rows\s*\(/' => 'mvm_db_stmt_affected_rows(',
        '/\bmysqli_stmt_close\s*\(/'         => 'mvm_db_stmt_close(',
        '/\bmysqli_query\s*\(/'              => 'mvm_db_query(',
        '/\bmysqli_fetch_assoc\s*\(/'        => 'mvm_db_fetch_assoc(',
        '/\bmysqli_fetch_all\s*\(/'          => 'mvm_db_fetch_all(',
        '/\bmysqli_fetch_row\s*\(/'          => 'mvm_db_fetch_row(',
        '/\bmysqli_num_rows\s*\(/'           => 'mvm_db_num_rows(',
        '/\bmysqli_insert_id\s*\(/'          => 'mvm_db_insert_id(',
        '/\bmysqli_begin_transaction\s*\(/'  => 'mvm_db_begin_transaction(',
        '/\bmysqli_commit\s*\(/'             => 'mvm_db_commit(',
        '/\bmysqli_rollback\s*\(/'           => 'mvm_db_rollback(',
        '/\bmysqli_close\s*\(/'              => 'mvm_db_close(',
        '/\bmysqli_error\s*\(/'              => 'mvm_db_error(',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relPath = str_replace('\\', '/', substr($item->getPathname(), strlen($srcRoot) + 1));
        if (strpos($relPath, '.git/') === 0 || $relPath === '.git') {
            continue;
        }

        $targetPath = $dstRoot . '/' . $relPath;
        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                @mkdir($targetPath, 0777, true);
            }
            continue;
        }

        $parentDir = dirname($targetPath);
        if (!is_dir($parentDir)) {
            @mkdir($parentDir, 0777, true);
        }

        $ext = strtolower($item->getExtension());
        if ($ext === 'php') {
            $codigo = file_get_contents($item->getPathname());
            // Não reescreve cloud_db.php nem api/index.php
            if ($relPath !== 'config/cloud_db.php' && $relPath !== 'api/index.php') {
                $codigo = preg_replace(array_keys($mapaFuncoes), array_values($mapaFuncoes), $codigo);
            }
            file_put_contents($targetPath, $codigo);
        } else {
            // Copia arquivos auxiliares leves (sql, uploads existentes, etc.) se ainda não existirem
            if (!file_exists($targetPath)) {
                @copy($item->getPathname(), $targetPath);
            }
        }
    }

    @file_put_contents($markerFile, date('c'));
}

mvm_preparar_ambiente_serverless($projectRoot, $mirrorRoot);

// Carrega a conexão e inicializa a sessão segura a partir do espelho
require_once $mirrorRoot . '/config/conexao.php';

// Mapa de rotas curtas (.htaccess) para estrutura modular
$rotasCurtas = [
    'cliente.php'            => 'modulos/clientes/cliente.php',
    'cliente_editar.php'     => 'modulos/clientes/cliente_editar.php',
    'cliente_excluir.php'    => 'modulos/clientes/cliente_excluir.php',
    'fornecedor.php'         => 'modulos/fornecedores/fornecedor.php',
    'fornecedor_editar.php'  => 'modulos/fornecedores/fornecedor_editar.php',
    'fornecedor_excluir.php' => 'modulos/fornecedores/fornecedor_excluir.php',
    'produto.php'            => 'modulos/produtos/produto.php',
    'produto_editar.php'     => 'modulos/produtos/produto_editar.php',
    'produto_excluir.php'    => 'modulos/produtos/produto_excluir.php',
    'movimento.php'          => 'modulos/movimentos/movimento.php',
    'movimento_devolucao.php'=> 'modulos/movimentos/movimento_devolucao.php',
    'movimento_excluir.php'  => 'modulos/movimentos/movimento_excluir.php',
    'recibo.php'             => 'modulos/movimentos/recibo.php',
    'kardex.php'             => 'modulos/relatorios/kardex.php',
    'etiquetas.php'          => 'modulos/relatorios/etiquetas.php',
    'planos.php'             => 'modulos/planos/planos.php',
    'login.php'              => 'modulos/auth/login.php',
    'logout.php'             => 'modulos/auth/logout.php',
    'criar_conta.php'        => 'modulos/auth/criar_conta.php',
    'esqueci_senha.php'      => 'modulos/auth/esqueci_senha.php',
    'redefinir_senha.php'    => 'modulos/auth/redefinir_senha.php',
    'cron_alertas.php'       => 'scripts/cron_alertas.php',
    'disparar_alerta.php'    => 'scripts/disparar_alerta.php',
    'api_docs.php'           => 'docs/api_docs.php',
    'mascote.php'            => 'docs/mascote.php',
];

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath    = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$relPath    = ltrim(str_replace('\\', '/', $uriPath), '/');

// Normaliza rota raiz
if ($relPath === '' || $relPath === 'api/index.php' || $relPath === 'api') {
    $relPath = 'index.php';
}

// Suporte a URLs amigáveis sem extensão .php (ex: /login -> login.php)
if (!pathinfo($relPath, PATHINFO_EXTENSION)) {
    $candidatoPhp = rtrim($relPath, '/') . '.php';
    if (isset($rotasCurtas[$candidatoPhp]) || file_exists($mirrorRoot . '/' . $candidatoPhp)) {
        $relPath = $candidatoPhp;
    }
}

// Resolve aliases curtos do .htaccess
if (isset($rotasCurtas[$relPath])) {
    $relPath = $rotasCurtas[$relPath];
}

// Bloqueia acesso a arquivos sensíveis
if (preg_match('/(\.(sql|env|bak|log|lock|md)$|email_credenciais\.php$)/i', $relPath)) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

// Se for um arquivo estático de upload salvo em /tmp (ex: imagem de produto recém-cadastrada)
$extArquivo = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
if ($extArquivo !== 'php') {
    $staticCandidate = file_exists($mirrorRoot . '/' . $relPath)
        ? $mirrorRoot . '/' . $relPath
        : $projectRoot . '/' . $relPath;
    if (is_file($staticCandidate)) {
        $mimes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'json'  => 'application/json; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'webp'  => 'image/webp',
            'gif'   => 'image/gif',
            'ico'   => 'image/x-icon',
            'woff2' => 'font/woff2',
        ];
        if (isset($mimes[$extArquivo]) && !headers_sent()) {
            header('Content-Type: ' . $mimes[$extArquivo]);
        }
        readfile($staticCandidate);
        exit;
    }
}

$targetFile = $mirrorRoot . '/' . $relPath;
if (!is_file($targetFile)) {
    http_response_code(404);
    echo "<!DOCTYPE html><html lang='pt-br'><head><meta charset='UTF-8'><title>404 - Página não encontrada</title></head><body style='font-family:sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;'><div style='text-align:center;'><h1>404</h1><p>Página não encontrada: " . htmlspecialchars($relPath) . "</p><a href='/index.php' style='color:#10b981;'>Voltar ao Dashboard MVM</a></div></body></html>";
    exit;
}

// Configura variáveis globais de ambiente esperadas pelos scripts PHP
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
$_SERVER['DOCUMENT_ROOT']   = $mirrorRoot;
$_SERVER['SCRIPT_FILENAME'] = $targetFile;
$_SERVER['SCRIPT_NAME']     = '/' . $relPath;
$_SERVER['PHP_SELF']        = '/' . $relPath;

chdir(dirname($targetFile));
require $targetFile;
