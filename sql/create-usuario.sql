CREATE DATABASE IF NOT EXISTS bdmvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bdmvm;

DROP TABLE IF EXISTS usuario;

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

INSERT INTO usuario (nomeUsuario, emailUsuario, senhaUsuario, planoAssinatura, cicloPlano, validadePlano, apiToken)
VALUES ('admin', 'admin@mvm.com', '123', 'PREMIUM', 'MENSAL', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'mvm_live_token_77a89b42');
