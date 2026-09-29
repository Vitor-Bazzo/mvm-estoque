<?php
// Pasta onde as imagens dos produtos são salvas
define('PASTA_UPLOAD_PRODUTOS', __DIR__ . '/../uploads/produtos/');
define('CAMINHO_UPLOAD_PRODUTOS', 'uploads/produtos/');

/**
 * Salva a imagem enviada no formulário (campo "imagem") e retorna o nome
 * do arquivo salvo, ou null se nenhuma imagem válida foi enviada.
 */
function salvarImagemProduto() {
    if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // 1. Limite de tamanho máximo: 3 MB
    if ($_FILES['imagem']['size'] > 3 * 1024 * 1024) {
        return null;
    }

    // 2. Validação por extensão estrita
    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        return null;
    }

    // 3. Validação por integridade real de imagem (magic bytes)
    $infoImagem = @getimagesize($_FILES['imagem']['tmp_name']);
    if ($infoImagem === false) {
        return null;
    }

    $mimesPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($infoImagem['mime'], $mimesPermitidos, true)) {
        return null;
    }

    if (!is_dir(PASTA_UPLOAD_PRODUTOS)) {
        mkdir(PASTA_UPLOAD_PRODUTOS, 0755, true);
    }

    $nomeArquivo = uniqid('produto_', true) . '.' . $extensao;
    $destino = PASTA_UPLOAD_PRODUTOS . $nomeArquivo;

    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
        return $nomeArquivo;
    }

    return null;
}

/**
 * Remove o arquivo de imagem do produto do disco, se existir (com proteção contra Path Traversal).
 */
function excluirImagemProduto($nomeArquivo) {
    if (!$nomeArquivo) {
        return;
    }
    // basename() impede Directory / Path Traversal (ex: ../../arquivo)
    $nomeSeguro = basename($nomeArquivo);
    $caminho = PASTA_UPLOAD_PRODUTOS . $nomeSeguro;
    if (is_file($caminho)) {
        unlink($caminho);
    }
}
?>
