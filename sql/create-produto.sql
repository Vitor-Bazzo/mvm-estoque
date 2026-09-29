-- Garante que o banco de dados seja criado se não existir e o seleciona.
CREATE DATABASE IF NOT EXISTS mvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mvm;

-- Apaga a tabela se ela já existir, para evitar erros ao rodar o script novamente.
DROP TABLE IF EXISTS produto;

-- Cria a tabela de produtos.
CREATE TABLE produto (
    idProduto INT AUTO_INCREMENT PRIMARY KEY,
    nomeProduto VARCHAR(255) NOT NULL,
    categoria VARCHAR(100),
    preco DECIMAL(10, 2) NOT NULL,
    quantidade INT NOT NULL
);

-- Insere os 40 produtos, 10 por categoria.
INSERT INTO produto (nomeProduto, categoria, preco, quantidade) VALUES
-- Categoria: Informatica
('Mouse Gamer Logitech G502 Hero', 'Informatica', 249.90, 155),
('Teclado Mecânico Redragon Kumara K552', 'Informatica', 199.90, 180),
('SSD Kingston A400 480GB SATA III', 'Informatica', 289.90, 200),
('Memória RAM Corsair Vengeance LPX 8GB DDR4', 'Informatica', 219.90, 170),
('Webcam Logitech C920s Pro Full HD', 'Informatica', 399.90, 120),
('Headset Gamer HyperX Cloud II', 'Informatica', 499.90, 110),
('Monitor Dell UltraSharp 24" U2422H', 'Informatica', 1899.00, 105),
('Roteador TP-Link Archer C6 Wi-Fi AC1200', 'Informatica', 259.90, 195),
('Placa de Vídeo NVIDIA GeForce RTX 3060 12GB', 'Informatica', 2599.90, 100),
('Processador AMD Ryzen 5 5600X', 'Informatica', 1299.90, 115),

-- Categoria: Cosméticos
('Protetor Solar La Roche-Posay Anthelios Airlicium FPS 70', 'Cosméticos', 89.90, 188),
('Base Líquida MAC Studio Fix Fluid FPS 15', 'Cosméticos', 249.00, 140),
('Shampoo Kérastase Résistance Bain Force Architecte 250ml', 'Cosméticos', 179.90, 160),
('Creme Hidratante Nivea Lata Azul 56g', 'Cosméticos', 15.90, 200),
('Perfume Chanel Nº 5 Eau de Parfum 50ml', 'Cosméticos', 799.00, 100),
('Máscara de Cílios Maybelline Sky High', 'Cosméticos', 75.50, 190),
('Sabonete Líquido Dove Nutrição Profunda 250ml', 'Cosméticos', 12.90, 198),
('Batom Líquido Matte Boca Rosa Beauty', 'Cosméticos', 39.90, 175),
('Sérum Facial Vichy Mineral 89 50ml', 'Cosméticos', 199.90, 130),
('Água Micelar Bioderma Sensibio H2O 500ml', 'Cosméticos', 85.00, 150),

-- Categoria: Alimentícios
('Café Pilão Tradicional a Vácuo 500g', 'Alimentícios', 18.90, 199),
('Arroz Agulhinha Tipo 1 Tio João 5kg', 'Alimentícios', 29.90, 180),
('Feijão Carioca Tipo 1 Camil 1kg', 'Alimentícios', 8.99, 200),
('Azeite de Oliva Extra Virgem Gallo 500ml', 'Alimentícios', 35.90, 165),
('Leite Condensado Moça Lata 395g', 'Alimentícios', 6.50, 195),
('Macarrão Barilla Spaghettoni nº 7 500g', 'Alimentícios', 9.90, 185),
('Molho de Tomate Heinz Peneirada 300g', 'Alimentícios', 4.50, 190),
('Biscoito Recheado Oreo Original 90g', 'Alimentícios', 3.99, 200),
('Refrigerante Coca-Cola Original 2L', 'Alimentícios', 8.00, 177),
('Barra de Chocolate Lacta Ao Leite 90g', 'Alimentícios', 5.99, 192),

-- Categoria: Eletro-eletrônicos
('Smart TV Samsung 50" Crystal UHD 4K', 'Eletro-eletrônicos', 2399.00, 120),
('Air Fryer Mondial Family 4L', 'Eletro-eletrônicos', 349.90, 160),
('Liquidificador Oster Clássico Osterizer', 'Eletro-eletrônicos', 299.00, 140),
('Aspirador de Pó Vertical WAP Power Speed', 'Eletro-eletrônicos', 279.90, 130),
('Caixa de Som Bluetooth JBL Go 3', 'Eletro-eletrônicos', 229.00, 180),
('Smart Speaker Amazon Echo Dot 4ª Geração', 'Eletro-eletrônicos', 379.00, 150),
('Carregador Portátil Power Bank Anker 10000mAh', 'Eletro-eletrônicos', 199.90, 170),
('Máquina de Café Nespresso Essenza Mini', 'Eletro-eletrônicos', 449.00, 110),
('Fones de Ouvido Bluetooth Sony WH-1000XM4', 'Eletro-eletrônicos', 1799.00, 100),
('Micro-ondas Electrolux 20L MTD30', 'Eletro-eletrônicos', 599.00, 125);
