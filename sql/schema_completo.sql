-- =====================================================================
-- SCRIPT CONSOLIDADO DO BANCO DE DADOS - SISTEMA MVM
-- Banco: bdmvm (compatível com config/conexao.php)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS bdmvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bdmvm;

-- Apaga Views e Tabelas na ordem correta para respeitar chaves estrangeiras
DROP VIEW IF EXISTS vw_resumo_financeiro_produto;
DROP VIEW IF EXISTS vw_estoque_critico;
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
    senhaUsuario VARCHAR(255) NOT NULL,
    planoAssinatura VARCHAR(30) NOT NULL DEFAULT 'ESSENCIAL',
    cicloPlano VARCHAR(15) NOT NULL DEFAULT 'MENSAL',
    validadePlano DATE DEFAULT NULL,
    dataAceiteTermos DATETIME DEFAULT CURRENT_TIMESTAMP,
    apiToken VARCHAR(80) DEFAULT NULL UNIQUE,
    tokenRecuperacao VARCHAR(100) DEFAULT NULL,
    expiraRecuperacao DATETIME DEFAULT NULL
);

-- Insere o usuário inicial admin (senha: 123)
INSERT INTO usuario (nomeUsuario, emailUsuario, senhaUsuario, planoAssinatura, cicloPlano, validadePlano, apiToken) 
VALUES ('admin', 'admin@mvm.com', '123', 'PREMIUM', 'MENSAL', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'mvm_live_token_77a89b42');

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
    INDEX idx_cliente_usuario (idUsuario),
    CONSTRAINT fk_cliente_usuario FOREIGN KEY (idUsuario) REFERENCES usuario(idUsuario) ON DELETE CASCADE
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
    INDEX idx_fornecedor_usuario (idUsuario),
    CONSTRAINT fk_fornecedor_usuario FOREIGN KEY (idUsuario) REFERENCES usuario(idUsuario) ON DELETE CASCADE
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
    CONSTRAINT chk_produto_qtd CHECK (quantidade >= 0),
    CONSTRAINT fk_produto_usuario FOREIGN KEY (idUsuario) REFERENCES usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_produto_fornecedor FOREIGN KEY (idFornecedor) REFERENCES fornecedor(idFornecedor) ON DELETE SET NULL
);

-- Insere produtos de exemplo vinculados ao admin (idUsuario = 1) e aos respectivos fornecedores
INSERT INTO produto (idUsuario, idFornecedor, codigoBarras, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, localizacao, descricao, nomeFornecedor, lote, dataValidade, imagem) VALUES
(1, 6, '7894900011517', 'Refrigerante Coca-Cola 2L', 'Bebidas', 9.50, 5.20, 150, 25, 'A-01-01', 'Refrigerante sabor cola tradicional em garrafa PET 2 Litros.', 'Nestlé Brasil Alimentos', 'LOT-BEB-2026', DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'coca_cola_2l.jpg'),
(1, 6, '7894900011524', 'Suco de Laranja Integral 1L', 'Bebidas', 11.90, 6.80, 100, 20, 'A-01-02', 'Suco 100% fruta natural sem adição de açúcar ou conservantes.', 'Nestlé Brasil Alimentos', 'LOT-SUC-2026', DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'suco_laranja_1l.jpg'),
(1, 6, '7894900530001', 'Água Mineral Crystal 500ml', 'Bebidas', 3.50, 1.40, 200, 30, 'A-01-03', 'Água mineral natural sem gás, embalagem prática.', 'Nestlé Brasil Alimentos', 'LOT-AGU-2026', DATE_ADD(CURDATE(), INTERVAL 180 DAY), 'agua_mineral_500ml.jpg'),
(1, 6, '9002490100070', 'Energético Red Bull 250ml', 'Bebidas', 10.50, 6.10, 90, 15, 'A-01-04', 'Bebida energética gaseificada com taurina e cafeína.', 'Nestlé Brasil Alimentos', 'LOT-RED-2026', DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'red_bull_250ml.jpg'),
(1, 2, '0097855141224', 'Mouse Gamer Logitech G502 Hero', 'Informatica', 249.90, 145.00, 50, 10, 'B-02-01', 'Sensor Hero 25K, 11 botões programáveis e pesos ajustáveis.', 'Logitech do Brasil', 'LOT-LOG-502', NULL, 'mouse_gamer_logitech.jpg'),
(1, 1, '6950376773906', 'Teclado Mecânico Redragon Kumara', 'Informatica', 199.90, 110.00, 35, 10, 'B-02-02', 'Switch Outemu Blue, iluminação LED vermelha.', 'Tech Distribuidora', 'LOT-RED-K55', NULL, 'teclado_mecanico.jpg'),
(1, 3, '0740617263442', 'SSD Kingston A400 480GB SATA III', 'Informatica', 289.90, 160.00, 80, 15, 'B-02-03', 'Velocidade de leitura até 500MB/s e gravação até 450MB/s.', 'Kingston Logística', 'LOT-SSD-A40', NULL, 'ssd_kingston_a400.jpg'),
(1, 4, '3337875588621', 'Protetor Solar Anthelios FPS 70', 'Cosméticos', 89.90, 48.00, 40, 12, 'C-01-01', 'Controle de oleosidade e acabamento matte.', 'LOréal Brasil Cosméticos', 'LOT-COS-70A', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 'protetor_solar_anthelios.jpg'),
(1, 5, '4005808000111', 'Creme Hidratante Nivea Lata Azul 56g', 'Cosméticos', 15.90, 7.50, 120, 20, 'C-01-02', 'Hidratação profunda para todos os tipos de pele.', 'Nivea Distribuição', 'LOT-NIV-056', DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'creme_nivea_lata.webp'),
(1, 6, '7896089011357', 'Café Pilão Tradicional 500g', 'Alimentícios', 18.90, 11.20, 95, 20, 'A-02-01', 'Café torrado e moído tradicional a vácuo.', 'Nestlé Brasil Alimentos', 'LOT-CAF-500', DATE_ADD(CURDATE(), INTERVAL 75 DAY), 'cafe_pilao_500g.jpg'),
(1, 7, '7892509116756', 'Smart TV Samsung 50" Crystal UHD 4K', 'Eletro-eletrônicos', 2399.00, 1650.00, 15, 5, 'D-01-01', 'Resolução 4K UHD com processador Crystal e comando de voz.', 'Samsung Eletrônica', 'LOT-SAM-4KU', NULL, 'smart_tv_samsung_50.jpg');

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
    precoUnitario DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    custoUnitario DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    valorTotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    dataMovimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dataDevolucao DATETIME DEFAULT NULL,
    INDEX idx_movimento_usuario (idUsuario),
    INDEX idx_movimento_pedido (codigoPedido),
    INDEX idx_mov_bi (idUsuario, tipoMovimento, dataMovimento),
    INDEX idx_mov_kardex (idUsuario, idProduto, dataMovimento),
    CONSTRAINT chk_movimento_qtd CHECK (quantidade > 0),
    CONSTRAINT fk_movimento_usuario FOREIGN KEY (idUsuario) REFERENCES usuario(idUsuario) ON DELETE CASCADE,
    CONSTRAINT fk_movimento_produto FOREIGN KEY (idProduto) REFERENCES produto(idProduto) ON DELETE CASCADE,
    CONSTRAINT fk_movimento_cliente FOREIGN KEY (idCliente) REFERENCES cliente(idCliente) ON DELETE SET NULL,
    CONSTRAINT fk_movimento_fornecedor FOREIGN KEY (idFornecedor) REFERENCES fornecedor(idFornecedor) ON DELETE SET NULL
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

-- Calcula automaticamente precoUnitario, custoUnitario e valorTotal das movimentações iniciais
UPDATE movimento m
INNER JOIN produto p ON p.idProduto = m.idProduto
SET m.precoUnitario = p.preco,
    m.custoUnitario = p.precoCusto,
    m.valorTotal    = ROUND(m.quantidade * p.preco, 2);

-- 6. VIEWS ANALÍTICAS DO BANCO DE DADOS
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
   OR (p.dataValidade IS NOT NULL AND p.dataValidade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY));

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
GROUP BY p.idProduto, p.idUsuario, p.codigoBarras, p.nomeProduto, p.categoria, p.localizacao, p.quantidade, p.preco, p.precoCusto;
