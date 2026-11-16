CREATE DATABASE IF NOT EXISTS bdmvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bdmvm;

DROP TABLE IF EXISTS fornecedor;

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

INSERT INTO fornecedor (idUsuario, nomeFornecedor, segmento, cnpj, email, telefone, endereco, cidade, uf) VALUES
(1, 'Tech Distribuidora', 'Informatica', '11.222.333/0001-01', 'vendas@techdistribuidora.com', '(11) 3300-1122', 'Av. Paulista, 1000', 'São Paulo', 'SP'),
(1, 'Logitech do Brasil', 'Informatica', '22.333.444/0001-02', 'suporte@logitech.com.br', '(11) 4003-9000', 'Alameda Rio Negro, 500', 'Barueri', 'SP'),
(1, 'Kingston Logística', 'Informatica', '33.444.555/0001-03', 'corporativo@kingston.com', '(21) 2244-5566', 'Av. Rio Branco, 25', 'Rio de Janeiro', 'RJ'),
(1, 'LOréal Brasil Cosméticos', 'Cosméticos', '44.555.666/0001-04', 'pedidos@loreal.com.br', '(21) 3500-0011', 'Rua do Passeio, 38', 'Rio de Janeiro', 'RJ'),
(1, 'Nivea Distribuição', 'Cosméticos', '55.666.777/0001-05', 'comercial@nivea.com.br', '(41) 3200-4455', 'Av. Sete de Setembro, 200', 'Curitiba', 'PR'),
(1, 'Nestlé Brasil Alimentos', 'Alimentícios', '77.888.999/0001-07', 'faturamento@nestle.com.br', '(11) 2100-3344', 'Av. Guido Aliberti, 1500', 'São Caetano do Sul', 'SP'),
(1, 'Samsung Eletrônica', 'Eletro-eletrônicos', '12.345.678/0001-10', 'faturamento@samsung.com.br', '(92) 3600-7788', 'Av. dos Oitis, 1460', 'Manaus', 'AM');
