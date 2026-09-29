-- =====================================================================
-- SCRIPT CONSOLIDADO DO BANCO DE DADOS - SISTEMA MVM
-- Banco: bdmvm (compatível com config/conexao.php)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS bdmvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bdmvm;

-- Apaga as tabelas na ordem correta para respeitar chaves estrangeiras
DROP TABLE IF EXISTS movimento;
DROP TABLE IF EXISTS produto;
DROP TABLE IF EXISTS fornecedor;
DROP TABLE IF EXISTS cliente;
DROP TABLE IF EXISTS usuario;

-- 1. TABELA DE USUÁRIOS
CREATE TABLE usuario (
    idUsuario INT AUTO_INCREMENT PRIMARY KEY,
    nomeUsuario VARCHAR(50) NOT NULL UNIQUE,
    emailUsuario VARCHAR(255) NOT NULL UNIQUE,
    senhaUsuario VARCHAR(255) NOT NULL
);

-- Insere o usuário inicial admin (senha: 123)
INSERT INTO usuario (nomeUsuario, emailUsuario, senhaUsuario) 
VALUES ('admin', 'admin@mvm.com', '123');

-- 2. TABELA DE CLIENTES
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

-- Insere clientes de exemplo
INSERT INTO cliente (idUsuario, nomeCliente, email, telefone, endereco, cidade, uf) VALUES
(1, 'Ana Silva', 'ana.silva@email.com', '(11) 98765-4321', 'Rua das Flores, 120', 'São Paulo', 'SP'),
(1, 'Bruno Souza', 'bruno.souza@email.com', '(21) 97654-3210', 'Av. Atlântica, 450', 'Rio de Janeiro', 'RJ'),
(1, 'Carlos Oliveira', 'carlos.oliveira@email.com', '(31) 96543-2109', 'Rua Bahia, 88', 'Belo Horizonte', 'MG'),
(1, 'Diana Costa', 'diana.costa@email.com', '(41) 95432-1098', 'Av. Batel, 1020', 'Curitiba', 'PR'),
(1, 'Eduardo Santos', 'eduardo.santos@email.com', '(51) 94321-0987', 'Rua dos Andradas, 310', 'Porto Alegre', 'RS');

-- 3. TABELA DE FORNECEDORES
CREATE TABLE fornecedor (
    idFornecedor INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL DEFAULT 1,
    nomeFornecedor VARCHAR(255) NOT NULL,
    segmento VARCHAR(100) DEFAULT NULL,
    cnpj VARCHAR(18) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    endereco VARCHAR(255) DEFAULT NULL,
    cidade VARCHAR(100) DEFAULT NULL,
    uf VARCHAR(2) DEFAULT NULL,
    INDEX idx_fornecedor_usuario (idUsuario)
);

-- Insere fornecedores de exemplo
INSERT INTO fornecedor (idUsuario, nomeFornecedor, segmento, cnpj, email, telefone, endereco, cidade, uf) VALUES
(1, 'Tech Distribuidora', 'Informatica', '11.222.333/0001-01', 'vendas@techdistribuidora.com', '(11) 3300-1122', 'Av. Paulista, 1000', 'São Paulo', 'SP'),
(1, 'Logitech do Brasil', 'Informatica', '22.333.444/0001-02', 'suporte@logitech.com.br', '(11) 4003-9000', 'Alameda Rio Negro, 500', 'Barueri', 'SP'),
(1, 'Kingston Logística', 'Informatica', '33.444.555/0001-03', 'corporativo@kingston.com', '(21) 2244-5566', 'Av. Rio Branco, 25', 'Rio de Janeiro', 'RJ'),
(1, 'LOréal Brasil Cosméticos', 'Cosméticos', '44.555.666/0001-04', 'pedidos@loreal.com.br', '(21) 3500-0011', 'Rua do Passeio, 38', 'Rio de Janeiro', 'RJ'),
(1, 'Nivea Distribuição', 'Cosméticos', '55.666.777/0001-05', 'comercial@nivea.com.br', '(41) 3200-4455', 'Av. Sete de Setembro, 200', 'Curitiba', 'PR'),
(1, 'Nestlé Brasil Alimentos', 'Alimentícios', '77.888.999/0001-07', 'faturamento@nestle.com.br', '(11) 2100-3344', 'Av. Guido Aliberti, 1500', 'São Caetano do Sul', 'SP'),
(1, 'Samsung Eletrônica', 'Eletro-eletrônicos', '12.345.678/0001-10', 'faturamento@samsung.com.br', '(92) 3600-7788', 'Av. dos Oitis, 1460', 'Manaus', 'AM');

-- 4. TABELA DE PRODUTOS
CREATE TABLE produto (
    idProduto INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL DEFAULT 1,
    idFornecedor INT DEFAULT NULL,
    nomeProduto VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) DEFAULT NULL,
    preco DECIMAL(10, 2) NOT NULL,
    precoCusto DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    quantidade INT NOT NULL DEFAULT 0,
    estoqueMinimo INT NOT NULL DEFAULT 10,
    descricao TEXT DEFAULT NULL,
    nomeFornecedor VARCHAR(255) DEFAULT NULL,
    lote VARCHAR(50) DEFAULT NULL,
    dataValidade DATE DEFAULT NULL,
    imagem VARCHAR(255) DEFAULT NULL,
    INDEX idx_produto_usuario (idUsuario),
    FOREIGN KEY (idFornecedor) REFERENCES fornecedor(idFornecedor) ON DELETE SET NULL
);

-- Insere produtos de exemplo vinculados ao admin (idUsuario = 1)
INSERT INTO produto (idUsuario, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, descricao, nomeFornecedor, lote, dataValidade, imagem) VALUES
(1, 'Refrigerante Coca-Cola 2L', 'Bebidas', 9.50, 5.20, 150, 25, 'Refrigerante sabor cola tradicional em garrafa PET 2 Litros.', 'Nestlé Brasil Alimentos', 'LOT-BEB-2026', DATE_ADD(CURDATE(), INTERVAL 90 DAY), NULL),
(1, 'Suco de Laranja Integral 1L', 'Bebidas', 11.90, 6.80, 100, 20, 'Suco 100% fruta natural sem adição de açúcar ou conservantes.', 'Nestlé Brasil Alimentos', 'LOT-SUC-2026', DATE_ADD(CURDATE(), INTERVAL 25 DAY), NULL),
(1, 'Água Mineral Crystal 500ml', 'Bebidas', 3.50, 1.40, 200, 30, 'Água mineral natural sem gás, embalagem prática.', 'Nestlé Brasil Alimentos', 'LOT-AGU-2026', DATE_ADD(CURDATE(), INTERVAL 180 DAY), NULL),
(1, 'Energético Red Bull 250ml', 'Bebidas', 10.50, 6.10, 90, 15, 'Bebida energética gaseificada com taurina e cafeína.', 'Nestlé Brasil Alimentos', 'LOT-RED-2026', DATE_ADD(CURDATE(), INTERVAL 120 DAY), NULL),
(1, 'Mouse Gamer Logitech G502 Hero', 'Informatica', 249.90, 145.00, 50, 10, 'Sensor Hero 25K, 11 botões programáveis e pesos ajustáveis.', 'Logitech do Brasil', 'LOT-LOG-502', NULL, NULL),
(1, 'Teclado Mecânico Redragon Kumara', 'Informatica', 199.90, 110.00, 35, 10, 'Switch Outemu Blue, iluminação LED vermelha.', 'Tech Distribuidora', 'LOT-RED-K55', NULL, NULL),
(1, 'SSD Kingston A400 480GB SATA III', 'Informatica', 289.90, 160.00, 80, 15, 'Velocidade de leitura até 500MB/s e gravação até 450MB/s.', 'Kingston Logística', 'LOT-SSD-A40', NULL, NULL),
(1, 'Protetor Solar Anthelios FPS 70', 'Cosméticos', 89.90, 48.00, 40, 12, 'Controle de oleosidade e acabamento matte.', 'LOréal Brasil Cosméticos', 'LOT-COS-70A', DATE_ADD(CURDATE(), INTERVAL 45 DAY), NULL),
(1, 'Creme Hidratante Nivea Lata Azul 56g', 'Cosméticos', 15.90, 7.50, 120, 20, 'Hidratação profunda para todos os tipos de pele.', 'Nivea Distribuição', 'LOT-NIV-056', DATE_ADD(CURDATE(), INTERVAL 60 DAY), NULL),
(1, 'Café Pilão Tradicional 500g', 'Alimentícios', 18.90, 11.20, 95, 20, 'Café torrado e moído tradicional a vácuo.', 'Nestlé Brasil Alimentos', 'LOT-CAF-500', DATE_ADD(CURDATE(), INTERVAL 75 DAY), NULL),
(1, 'Smart TV Samsung 50" Crystal UHD 4K', 'Eletro-eletrônicos', 2399.00, 1650.00, 15, 5, 'Resolução 4K UHD com processador Crystal e comando de voz.', 'Samsung Eletrônica', 'LOT-SAM-4KU', NULL, NULL);

-- 5. TABELA DE MOVIMENTAÇÕES DE ESTOQUE
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
    valorTotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    dataMovimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dataDevolucao DATETIME DEFAULT NULL,
    INDEX idx_movimento_usuario (idUsuario),
    INDEX idx_movimento_pedido (codigoPedido),
    FOREIGN KEY (idProduto) REFERENCES produto(idProduto) ON DELETE CASCADE,
    FOREIGN KEY (idCliente) REFERENCES cliente(idCliente) ON DELETE SET NULL,
    FOREIGN KEY (idFornecedor) REFERENCES fornecedor(idFornecedor) ON DELETE SET NULL
);

-- Insere movimentações de exemplo com histórico de vendas distribuído por dias
INSERT INTO movimento (idProduto, idCliente, idFornecedor, tipoMovimento, quantidade, observacao, dataMovimento, dataDevolucao) VALUES
-- Dia -7
(1, 1, NULL, 'SAIDA', 18, 'Venda de balcão Coca-Cola', DATE_SUB(NOW(), INTERVAL 7 DAY), NULL),
(5, 2, NULL, 'SAIDA', 2, 'Venda Mouse Logitech', DATE_SUB(NOW(), INTERVAL 7 DAY), NULL),
(10, 3, NULL, 'SAIDA', 10, 'Venda Café Pilão', DATE_SUB(NOW(), INTERVAL 7 DAY), NULL),

-- Dia -6
(1, 1, NULL, 'SAIDA', 22, 'Venda para evento Coca-Cola', DATE_SUB(NOW(), INTERVAL 6 DAY), NULL),
(2, 4, NULL, 'SAIDA', 12, 'Venda Suco Laranja', DATE_SUB(NOW(), INTERVAL 6 DAY), NULL),
(6, 5, NULL, 'SAIDA', 3, 'Venda Teclado Redragon', DATE_SUB(NOW(), INTERVAL 6 DAY), NULL),

-- Dia -5
(3, 1, NULL, 'SAIDA', 35, 'Venda Água Mineral', DATE_SUB(NOW(), INTERVAL 5 DAY), NULL),
(4, 2, NULL, 'SAIDA', 15, 'Venda Energético Red Bull', DATE_SUB(NOW(), INTERVAL 5 DAY), NULL),
(7, 3, NULL, 'SAIDA', 4, 'Venda SSD Kingston', DATE_SUB(NOW(), INTERVAL 5 DAY), NULL),

-- Dia -4
(1, 1, NULL, 'SAIDA', 30, 'Venda balcão Coca-Cola', DATE_SUB(NOW(), INTERVAL 4 DAY), NULL),
(8, 4, NULL, 'SAIDA', 6, 'Venda Protetor Solar', DATE_SUB(NOW(), INTERVAL 4 DAY), NULL),
(11, 5, NULL, 'SAIDA', 1, 'Venda Smart TV Samsung', DATE_SUB(NOW(), INTERVAL 4 DAY), NULL),

-- Dia -3
(1, 1, NULL, 'SAIDA', 25, 'Venda balcão Coca-Cola', DATE_SUB(NOW(), INTERVAL 3 DAY), NULL),
(2, 2, NULL, 'SAIDA', 16, 'Venda Suco Laranja', DATE_SUB(NOW(), INTERVAL 3 DAY), NULL),
(5, 3, NULL, 'SAIDA', 3, 'Venda Mouse Logitech', DATE_SUB(NOW(), INTERVAL 3 DAY), NULL),

-- Dia -2
(1, 1, NULL, 'SAIDA', 32, 'Venda para restaurante', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL),
(4, 4, NULL, 'SAIDA', 20, 'Venda Energético', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL),
(9, 5, NULL, 'SAIDA', 15, 'Venda Creme Nivea', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL),

-- Dia -1 (Ontem)
(1, 1, NULL, 'SAIDA', 40, 'Venda atacado Coca-Cola', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),
(2, 2, NULL, 'SAIDA', 20, 'Venda Suco Laranja', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),
(7, 3, NULL, 'SAIDA', 5, 'Venda SSD Kingston', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),

-- Hoje
(1, 1, NULL, 'SAIDA', 28, 'Venda balcão Coca-Cola hoje', NOW(), NULL),
(2, 4, NULL, 'SAIDA', 14, 'Venda Suco Laranja hoje', NOW(), NULL),
(5, 5, NULL, 'SAIDA', 4, 'Venda Mouse Logitech hoje', NOW(), NULL),
(11, 2, NULL, 'SAIDA', 1, 'Venda Smart TV Samsung hoje', NOW(), NULL),

-- Entrada e Devolução para testes de regras de negócio
(7, NULL, 3, 'ENTRADA', 30, 'Carga de reposição recebida', DATE_SUB(NOW(), INTERVAL 5 DAY), NULL),
(6, 2, NULL, 'SAIDA', 1, 'Venda de teste com devolução', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));
