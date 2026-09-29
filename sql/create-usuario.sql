-- Garante que o banco de dados seja criado se não existir e o seleciona.
CREATE DATABASE IF NOT EXISTS mvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mvm;

-- Apaga as tabelas se elas já existirem, para evitar erros.
DROP TABLE IF EXISTS usuario;

-- Cria a tabela de usuários.
CREATE TABLE usuario (
    idUsuario INT AUTO_INCREMENT PRIMARY KEY,
    nomeUsuario VARCHAR(50) NOT NULL UNIQUE,
    senhaUsuario VARCHAR(255) NOT NULL
);

-- Insere o usuário 'admin' com a senha '123' criptografada.
-- IMPORTANTE: A string abaixo pode variar ligeiramente a cada execução, mas sempre será válida para a senha '123'.
INSERT INTO usuario (nomeUsuario, senhaUsuario) VALUES ('admin', '123');
