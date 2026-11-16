CREATE DATABASE IF NOT EXISTS bdmvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bdmvm;

DROP TABLE IF EXISTS movimento;
DROP TABLE IF EXISTS cliente;

CREATE TABLE cliente (
    idCliente INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL DEFAULT 1,
    nomeCliente VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    endereco VARCHAR(255) DEFAULT NULL,
    cidade VARCHAR(100) DEFAULT NULL,
    uf VARCHAR(2) DEFAULT NULL,
    INDEX idx_cliente_usuario (idUsuario)
);

CREATE TABLE movimento (
    idMovimento INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL DEFAULT 1,
    codigoPedido VARCHAR(50) DEFAULT NULL,
    idProduto INT NOT NULL,
    idCliente INT DEFAULT NULL,
    idFornecedor INT DEFAULT NULL,
    tipoMovimento ENUM('ENTRADA', 'SAIDA') NOT NULL,
    quantidade INT NOT NULL,
    observacao VARCHAR(255) DEFAULT NULL,
    lote VARCHAR(50) DEFAULT NULL,
    dataValidade DATE DEFAULT NULL,
    motivoAjuste VARCHAR(100) DEFAULT NULL,
    precoUnitario DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    custoUnitario DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    valorTotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    dataMovimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dataDevolucao DATETIME DEFAULT NULL,
    INDEX idx_movimento_usuario (idUsuario),
    INDEX idx_movimento_pedido (codigoPedido),
    INDEX idx_mov_bi (idUsuario, tipoMovimento, dataMovimento),
    INDEX idx_mov_kardex (idUsuario, idProduto, dataMovimento),
    CONSTRAINT chk_movimento_qtd CHECK (quantidade > 0)
);
