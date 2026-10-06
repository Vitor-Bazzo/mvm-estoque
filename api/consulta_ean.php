<?php
/**
 * API Proxy de Consulta de Produtos por Código de Barras (EAN-13 / GTIN)
 * Integração com Open Food Facts API (Base global de dados de produtos)
 * Sistema MVM Estoque & Logística
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Permite requisições apenas se o usuário estiver autenticado no sistema
if (!isset($_SESSION['usuarioLogado'])) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não autorizado. Faça login no sistema para consultar a API.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ean = isset($_GET['ean']) ? preg_replace('/\D/', '', trim($_GET['ean'])) : '';

if (empty($ean) || strlen($ean) < 7 || strlen($ean) > 14) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Código de barras (EAN/GTIN) inválido. Informe entre 7 e 14 dígitos numéricos.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Consulta à API oficial do Open Food Facts
$url = "https://world.openfoodfacts.org/api/v2/product/{$ean}.json";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_USERAGENT      => 'MVM-Estoque-Logistica-TCC/1.0 (https://github.com/Vitor-Bazzo/mvm-estoque; vitor.bazzo@mvm.com.br)',
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || !empty($curlError)) {
    http_response_code(502);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Falha de comunicação temporária com a base de dados de produtos externa. Tente novamente.',
        'detalhe' => $curlError
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$dados = json_decode($response, true);

if (!isset($dados['status']) || (int)$dados['status'] !== 1 || empty($dados['product'])) {
    http_response_code(404);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => "Produto com código EAN '{$ean}' não foi localizado no catálogo global. Você pode preencher os dados manualmente.",
        'ean' => $ean
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$prod = $dados['product'];

// Tratamento inteligente dos campos retornados
$nome = '';
if (!empty($prod['product_name_pt'])) {
    $nome = $prod['product_name_pt'];
} elseif (!empty($prod['product_name'])) {
    $nome = $prod['product_name'];
} elseif (!empty($prod['generic_name_pt'])) {
    $nome = $prod['generic_name_pt'];
} else {
    $nome = $prod['generic_name'] ?? 'Produto Identificado';
}

// Marca / Fornecedor
$marca = '';
if (!empty($prod['brands'])) {
    $marca = trim(explode(',', $prod['brands'])[0]);
}

// Categoria
$categoria = 'Geral';
if (!empty($prod['categories'])) {
    $partes = array_map('trim', explode(',', $prod['categories']));
    // Pega a última ou penúltima categoria que costuma ser mais específica em português
    $catEscolhida = end($partes);
    if (!empty($catEscolhida)) {
        $categoria = mb_convert_case($catEscolhida, MB_CASE_TITLE, 'UTF-8');
    }
}

// Descrição / Quantidade
$quantidadeEmbalagem = $prod['quantity'] ?? '';
$descricao = '';
if (!empty($quantidadeEmbalagem)) {
    $descricao .= "Embalagem/Conteúdo: {$quantidadeEmbalagem}. ";
}
if (!empty($prod['ingredients_text_pt'])) {
    $descricao .= "Ingredientes: " . mb_strimwidth($prod['ingredients_text_pt'], 0, 160, '...');
} elseif (!empty($prod['ingredients_text'])) {
    $descricao .= "Ingredientes: " . mb_strimwidth($prod['ingredients_text'], 0, 160, '...');
}

// Imagem frontal
$imagemUrl = '';
if (!empty($prod['image_front_url'])) {
    $imagemUrl = $prod['image_front_url'];
} elseif (!empty($prod['image_url'])) {
    $imagemUrl = $prod['image_url'];
} elseif (!empty($prod['selected_images']['front']['display']['pt'])) {
    $imagemUrl = $prod['selected_images']['front']['display']['pt'];
}

echo json_encode([
    'sucesso'    => true,
    'origem'     => 'Open Food Facts Global API',
    'ean'        => $ean,
    'nome'       => trim($nome),
    'marca'      => trim($marca),
    'categoria'  => trim($categoria),
    'descricao'  => trim($descricao),
    'imagem_url' => $imagemUrl
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
