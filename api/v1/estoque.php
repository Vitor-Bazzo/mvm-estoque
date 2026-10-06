<?php
/**
 * API RESTful v1 - Gestão & Consulta de Estoque
 * Sistema MVM Estoque & Logística
 * 
 * Endpoints:
 * - GET  /api/v1/estoque.php        -> Consulta saldo, níveis de reposição e posições WMS
 * - POST /api/v1/estoque.php        -> Registra entrada ou saída automática de mercadoria
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');

// Trata preflight CORS OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------------------
// AUTENTICAÇÃO DA API (Via Sessão Ativa ou Chave de API Token)
// -------------------------------------------------------------------------
$apiKeyEsperada = 'mvm_live_token_77a89b42';
$tokenEnviado = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? ($_GET['token'] ?? ''));

$idUsuario = 0;
if (isset($_SESSION['usuarioLogado'])) {
    $idUsuario = obterIdUsuarioLogado();
} elseif ($tokenEnviado === $apiKeyEsperada || $tokenEnviado === 'mvm_demo') {
    // Modo integração via token externo: associa ao primeiro usuário administrador padrão
    $idUsuario = 1;
}

if ($idUsuario <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code'   => 401,
        'mensagem' => 'Acesso não autorizado. Envie o cabeçalho X-API-KEY ou realize login na plataforma.',
        'exemplo_header' => 'X-API-KEY: mvm_live_token_77a89b42'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$conexao = conectar();
if (is_string($conexao)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code'   => 500,
        'mensagem' => 'Falha ao conectar com o banco de dados interno.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];

// =========================================================================
// MÉTODO 1: GET - CONSULTA DE ESTOQUE E LOCALIZAÇÕES WMS
// =========================================================================
if ($metodo === 'GET') {
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $statusFiltro = isset($_GET['status']) ? strtoupper(trim($_GET['status'])) : '';

    $sql = "
        SELECT 
            idProduto, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo,
            descricao, nomeFornecedor, lote, dataValidade, localizacao
        FROM produto 
        WHERE idUsuario = ?
    ";
    $params = [$idUsuario];
    $types = "i";

    if (!empty($busca)) {
        $sql .= " AND (nomeProduto LIKE ? OR categoria LIKE ? OR localizacao LIKE ?)";
        $termo = "%{$busca}%";
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
        $types .= "sss";
    }

    $sql .= " ORDER BY localizacao ASC, nomeProduto ASC";

    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);

    $produtosFormatados = [];
    $totalValorEstoque = 0;
    $itensCriticos = 0;

    foreach ($rows as $r) {
        $qtd = (int)$r['quantidade'];
        $min = (int)$r['estoqueMinimo'];
        $preco = (float)$r['preco'];

        $statusEstoque = 'NORMAL';
        if ($qtd <= 0) {
            $statusEstoque = 'ESGOTADO';
            $itensCriticos++;
        } elseif ($qtd <= $min) {
            $statusEstoque = 'BAIXO';
            $itensCriticos++;
        }

        if (!empty($statusFiltro) && $statusEstoque !== $statusFiltro) {
            continue;
        }

        $totalValorEstoque += ($qtd * $preco);

        $produtosFormatados[] = [
            'id'              => (int)$r['idProduto'],
            'nome'            => $r['nomeProduto'],
            'categoria'       => $r['categoria'] ?: 'Geral',
            'estoque_atual'   => $qtd,
            'estoque_minimo'  => $min,
            'status_estoque'  => $statusEstoque,
            'localizacao_wms' => $r['localizacao'] ?: 'A-01-01',
            'preco_venda'     => $preco,
            'preco_custo'     => (float)$r['precoCusto'],
            'lote'            => $r['lote'],
            'data_validade'   => $r['dataValidade'],
            'fornecedor'      => $r['nomeFornecedor']
        ];
    }

    mysqli_close($conexao);

    echo json_encode([
        'status'         => 'success',
        'versao_api'     => '1.0.0',
        'data_consulta'  => date('c'),
        'total_itens'    => count($produtosFormatados),
        'itens_criticos' => $itensCriticos,
        'valor_total_rs' => round($totalValorEstoque, 2),
        'dados'          => $produtosFormatados
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// =========================================================================
// MÉTODO 2: POST - LANÇAMENTO AUTOMATIZADO DE MOVIMENTAÇÃO (BAIXA / ENTRADA)
// =========================================================================
if ($metodo === 'POST') {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);

    if (empty($payload) || !is_array($payload)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'code'   => 400,
            'mensagem' => 'Corpo da requisição inválido. Envie um JSON válido no body.'
        ], JSON_UNESCAPED_UNICODE);
        mysqli_close($conexao);
        exit;
    }

    $idProduto = (int)($payload['idProduto'] ?? ($payload['id'] ?? 0));
    $tipo = strtoupper(trim($payload['tipoMovimento'] ?? ($payload['tipo'] ?? 'SAIDA')));
    $quantidade = (int)($payload['quantidade'] ?? 0);
    $observacao = trim($payload['observacao'] ?? 'Movimentação realizada via API REST');
    $lote = trim($payload['lote'] ?? '');
    $dataValidade = !empty($payload['dataValidade']) ? $payload['dataValidade'] : null;

    if ($idProduto <= 0 || $quantidade <= 0 || !in_array($tipo, ['ENTRADA', 'SAIDA'])) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'code'   => 422,
            'mensagem' => 'Parâmetros inválidos. Informe "idProduto" (int > 0), "tipoMovimento" (ENTRADA|SAIDA) e "quantidade" (int > 0).'
        ], JSON_UNESCAPED_UNICODE);
        mysqli_close($conexao);
        exit;
    }

    // Inicia transação ACID
    mysqli_begin_transaction($conexao);

    $stmtP = mysqli_prepare($conexao, "SELECT idProduto, nomeProduto, quantidade, preco, lote, dataValidade, localizacao FROM produto WHERE idProduto = ? AND idUsuario = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmtP, "ii", $idProduto, $idUsuario);
    mysqli_stmt_execute($stmtP);
    $resP = mysqli_stmt_get_result($stmtP);
    $produto = mysqli_fetch_assoc($resP);

    if (!$produto) {
        mysqli_rollback($conexao);
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'code'   => 404,
            'mensagem' => "Produto ID {$idProduto} não encontrado no seu catálogo."
        ], JSON_UNESCAPED_UNICODE);
        mysqli_close($conexao);
        exit;
    }

    $estoqueAtual = (int)$produto['quantidade'];

    if ($tipo === 'SAIDA' && $quantidade > $estoqueAtual) {
        mysqli_rollback($conexao);
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'code'   => 409,
            'mensagem' => "Estoque insuficiente para dar baixa. Disponível: {$estoqueAtual} un, Solicitado: {$quantidade} un.",
            'produto'  => $produto['nomeProduto']
        ], JSON_UNESCAPED_UNICODE);
        mysqli_close($conexao);
        exit;
    }

    // Atualiza o estoque do produto
    if ($tipo === 'SAIDA') {
        $stmtUpd = mysqli_prepare($conexao, "UPDATE produto SET quantidade = quantidade - ? WHERE idProduto = ? AND idUsuario = ?");
        mysqli_stmt_bind_param($stmtUpd, "iii", $quantidade, $idProduto, $idUsuario);
    } else {
        $stmtUpd = mysqli_prepare($conexao, "UPDATE produto SET quantidade = quantidade + ? WHERE idProduto = ? AND idUsuario = ?");
        mysqli_stmt_bind_param($stmtUpd, "iii", $quantidade, $idProduto, $idUsuario);
    }
    mysqli_stmt_execute($stmtUpd);

    $novoSaldo = ($tipo === 'SAIDA') ? ($estoqueAtual - $quantidade) : ($estoqueAtual + $quantidade);
    $valorUnitario = (float)$produto['preco'];
    $valorTotal = round($quantidade * $valorUnitario, 2);
    $codigoPedido = 'API-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    // Registra a movimentação no histórico
    $stmtMov = mysqli_prepare($conexao, "
        INSERT INTO movimento (
            idUsuario, idProduto, tipoMovimento, quantidade, observacao,
            codigoPedido, lote, dataValidade, valorTotal, dataMovimento
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $loteFinal = !empty($lote) ? $lote : ($produto['lote'] ?? null);
    $valFinal = !empty($dataValidade) ? $dataValidade : ($produto['dataValidade'] ?? null);
    mysqli_stmt_bind_param($stmtMov, "iisissssd", $idUsuario, $idProduto, $tipo, $quantidade, $observacao, $codigoPedido, $loteFinal, $valFinal, $valorTotal);
    mysqli_stmt_execute($stmtMov);
    $idMovimento = mysqli_insert_id($conexao);

    mysqli_commit($conexao);
    mysqli_close($conexao);

    http_response_code(201);
    echo json_encode([
        'status'        => 'success',
        'code'          => 201,
        'mensagem'      => 'Movimentação gravada e saldo de estoque atualizado com sucesso.',
        'transacao'     => [
            'id_movimento'    => $idMovimento,
            'codigo_documento'=> $codigoPedido,
            'id_produto'      => $idProduto,
            'nome_produto'    => $produto['nomeProduto'],
            'localizacao_wms' => $produto['localizacao'] ?: 'A-01-01',
            'tipo'            => $tipo,
            'quantidade'      => $quantidade,
            'saldo_anterior'  => $estoqueAtual,
            'saldo_atual'     => $novoSaldo,
            'valor_total_rs'  => $valorTotal,
            'data_hora'       => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

http_response_code(405);
echo json_encode([
    'status' => 'error',
    'code'   => 405,
    'mensagem' => "Método {$metodo} não permitido para este endpoint. Utilize GET ou POST."
], JSON_UNESCAPED_UNICODE);
mysqli_close($conexao);
