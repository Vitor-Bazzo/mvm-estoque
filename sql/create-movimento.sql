-- Seleciona o banco de dados já existente.
USE mvm;

-- Apaga as tabelas se já existirem, respeitando a ordem de restrição de chave estrangeira.
DROP TABLE IF EXISTS movimento;
DROP TABLE IF EXISTS cliente;

-- Cria a tabela de clientes.
CREATE TABLE cliente (
    idCliente INT AUTO_INCREMENT PRIMARY KEY,
    nomeCliente VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL
);

-- Insere alguns clientes para testes de movimentação.
INSERT INTO cliente (nomeCliente, email) VALUES
('Ana Silva', 'ana.silva@email.com'),
('Bruno Souza', 'bruno.souza@email.com'),
('Carlos Oliveira', 'carlos.oliveira@email.com'),
('Diana Costa', 'diana.costa@email.com'),
('Eduardo Santos', 'eduardo.santos@email.com');

-- Cria a tabela de movimentos (histórico de entradas, saídas e devoluções).
CREATE TABLE movimento (
    idMovimento INT AUTO_INCREMENT PRIMARY KEY,
    idProduto INT NOT NULL,
    idCliente INT NOT NULL,
    tipoMovimento ENUM('ENTRADA', 'SAIDA') NOT NULL,
    quantidade INT NOT NULL,
    observacao VARCHAR(255) DEFAULT NULL,
    dataMovimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dataDevolucao DATETIME DEFAULT NULL,
    FOREIGN KEY (idProduto) REFERENCES produto(idProduto) ON DELETE CASCADE,
    FOREIGN KEY (idCliente) REFERENCES cliente(idCliente) ON DELETE CASCADE
);

-- Insere movimentos iniciais para testes no sistema.
INSERT INTO movimento (idProduto, idCliente, tipoMovimento, quantidade, observacao, dataMovimento, dataDevolucao) VALUES
-- Cenário 1: Saída comum (Ainda não devolvida, exibirá o botão "Devolver" no PHP)
(1, 1, 'SAIDA', 2, 'Venda balcão realizada via pdv', '2026-06-01 10:30:00', NULL),

-- Cenário 2: Saída que JÁ FOI devolvida (dataDevolucao preenchida, não exibirá o botão no PHP)
(2, 2, 'SAIDA', 1, 'Produto apresentou defeito na embalagem', '2026-06-02 14:15:00', '2026-06-03 09:00:00'),

-- Cenário 3: Entrada / Reposição de Estoque
(11, 3, 'ENTRADA', 50, 'Carga recebida do fornecedor principal', '2026-06-04 11:00:00', NULL),

-- Cenário 4: Outra saída pendente de devolução
(31, 4, 'SAIDA', 1, 'Retirada para demonstração ao cliente', '2026-06-05 16:45:00', NULL);