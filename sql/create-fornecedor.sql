-- Garante que o banco de dados seja criado se não existir e o seleciona.
CREATE DATABASE IF NOT EXISTS mvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mvm;

-- Apaga a tabela se ela já existir, para evitar erros ao rodar o script novamente.
DROP TABLE IF EXISTS fornecedor;

-- Cria a tabela de fornecedores.
CREATE TABLE fornecedor (
    idFornecedor INT AUTO_INCREMENT PRIMARY KEY,
    nomeFornecedor VARCHAR(255) NOT NULL,
    segmento VARCHAR(100),
    cnpj VARCHAR(18) NOT NULL,
    email VARCHAR(255),
    telefone VARCHAR(20),
    cidade VARCHAR(100),
    uf VARCHAR(2)
);

-- Insere os fornecedores divididos por segmento de mercado (combinando com as categorias de produtos).
INSERT INTO fornecedor (nomeFornecedor, segmento, cnpj, email, telefone, cidade, uf) VALUES
-- Segmento: Informatica
('Tech Distribuidora de Componentes', 'Informatica', '11.222.333/0001-01', 'vendas@techdistribuidora.com', '(11) 3300-1122', 'São Paulo', 'SP'),
('Logitech do Brasil Comercio Ltda', 'Informatica', '22.333.444/0001-02', 'suporte@logitech.com.br', '(11) 4003-9000', 'Barueri', 'SP'),
('Kingston Importação e Logística', 'Informatica', '33.444.555/0001-03', 'corporativo@kingston.com', '(21) 2244-5566', 'Rio de Janeiro', 'RJ'),

-- Segmento: Cosméticos
('LOréal Brasil Comercial de Cosméticos', 'Cosméticos', '44.555.666/0001-04', 'pedidos@loreal.com.br', '(21) 3500-0011', 'Rio de Janeiro', 'RJ'),
('Nivea Distribuição Sul-Sudeste', 'Cosméticos', '55.666.777/0001-05', 'comercial@nivea.com.br', '(41) 3200-4455', 'Curitiba', 'PR'),
('Boca Rosa Comércio de Maquiagens', 'Cosméticos', '66.777.888/0001-06', 'atendimento@bocarosabeauty.com', '(11) 5566-7788', 'São Paulo', 'SP'),

-- Segmento: Alimentícios
('Nestlé Brasil Alimentos S.A.', 'Alimentícios', '77.888.999/0001-07', 'faturamento@nestle.com.br', '(11) 2100-3344', 'São Caetano do Sul', 'SP'),
('Camil Alimentos Distribuidora', 'Alimentícios', '88.999.000/0001-08', 'vendas@camil.com.br', '(14) 3400-8899', 'Ourinhos', 'SP'),
('Ambev Companhia de Bebidas', 'Alimentícios', '99.000.111/0001-09', 'teletransacoes@ambev.com.br', '(19) 3700-1122', 'Campinas', 'SP'),

-- Segmento: Eletro-eletrônicos
('Samsung Eletrônica da Amazônia', 'Eletro-eletrônicos', '12.345.678/0001-10', 'faturamento@samsung.com.br', '(92) 3600-7788', 'Manaus', 'AM'),
('Mondial Eletrodomésticos S.A.', 'Eletro-eletrônicos', '23.456.789/0001-20', 'comercial@mondial.com.br', '(11) 4004-3322', 'Barueri', 'SP'),
('WAP Ferramentas e Equipamentos', 'Eletro-eletrônicos', '34.567.890/0001-30', 'vendas@wap.ind.br', '(41) 3511-9000', 'Pinhais', 'PR');