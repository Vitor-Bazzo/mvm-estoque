CREATE DATABASE IF NOT EXISTS bdmvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bdmvm;

DROP TABLE IF EXISTS produto;

CREATE TABLE produto (
    idProduto INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL DEFAULT 1,
    idFornecedor INT DEFAULT NULL,
    codigoBarras VARCHAR(20) DEFAULT NULL,
    nomeProduto VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) DEFAULT NULL,
    preco DECIMAL(10, 2) NOT NULL,
    precoCusto DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    quantidade INT NOT NULL DEFAULT 0,
    estoqueMinimo INT NOT NULL DEFAULT 10,
    localizacao VARCHAR(60) NOT NULL DEFAULT 'A-01-01',
    descricao TEXT DEFAULT NULL,
    nomeFornecedor VARCHAR(255) DEFAULT NULL,
    lote VARCHAR(50) DEFAULT NULL,
    dataValidade DATE DEFAULT NULL,
    imagem VARCHAR(255) DEFAULT NULL,
    INDEX idx_produto_usuario (idUsuario),
    INDEX idx_prod_fefo (idUsuario, dataValidade),
    INDEX idx_prod_ean (idUsuario, codigoBarras),
    INDEX idx_prod_cat (idUsuario, categoria),
    CONSTRAINT chk_produto_qtd CHECK (quantidade >= 0)
);
