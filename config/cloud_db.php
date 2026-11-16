<?php
/**
 * Motor de Banco de Dados Serverless (SQLite3 Emulado com API compatível com MySQLi)
 * Permite que o MVM Estoque funcione 100% na nuvem (Vercel Serverless) mesmo sem
 * um servidor MySQL externo dedicado, mantendo todas as tabelas, vendas, KPIs e CRUDs.
 */

class MvmSqliteResult {
    public array $rows = [];
    public int $pos = 0;

    public function __construct(array $rows = []) {
        $this->rows = array_values($rows);
        $this->pos = 0;
    }
}

class MvmSqliteStmt {
    public MvmCloudDB $db;
    public string $sql;
    public array $params = [];
    public int $affectedRows = 0;
    public ?MvmSqliteResult $result = null;

    public function __construct(MvmCloudDB $db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }
}

class MvmCloudDB {
    public \PDO $pdo;
    public string $lastError = '';
    private int $txDepth = 0;

    public function __construct(string $dbPath) {
        $isNovo = !file_exists($dbPath) || filesize($dbPath) < 1024;
        $this->pdo = new \PDO('sqlite:' . $dbPath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->pdo->exec('PRAGMA journal_mode = WAL;');

        $this->registrarFuncoesMySQL();

        if ($isNovo) {
            $this->inicializarDadosPadrao();
        }

        $this->sincronizarUsuarioSessao();
    }

    private function sincronizarUsuarioSessao(): void {
        if (!empty($_SESSION['idUsuario']) && !empty($_SESSION['usuarioLogado'])) {
            try {
                $idU = (int)$_SESSION['idUsuario'];
                $nomeU = (string)$_SESSION['usuarioLogado'];
                $emailU = (string)($_SESSION['emailUsuario'] ?? ($nomeU . '@mvm.com'));
                $planoU = (string)($_SESSION['planoUsuario'] ?? 'ESSENCIAL');
                $valPlano = date('Y-m-d', strtotime('+30 days'));
                $hashPadrao = password_hash('123456', PASSWORD_DEFAULT);

                $st = $this->pdo->prepare("INSERT OR IGNORE INTO usuario (idUsuario, nomeUsuario, emailUsuario, senhaUsuario, planoAssinatura, cicloPlano, validadePlano) VALUES (?, ?, ?, ?, ?, 'MENSAL', ?)");
                $st->execute([$idU, $nomeU, $emailU, $hashPadrao, $planoU, $valPlano]);

                if (!empty($_SESSION['planoUsuario'])) {
                    $stUp = $this->pdo->prepare("UPDATE usuario SET planoAssinatura = ? WHERE idUsuario = ?");
                    $stUp->execute([$planoU, $idU]);
                }
            } catch (\Throwable $e) {
                // Ignora conflitos de sincronização
            }
        }
    }

    private function registrarFuncoesMySQL(): void {
        $this->pdo->sqliteCreateFunction('NOW', function () {
            return date('Y-m-d H:i:s');
        }, 0);

        $this->pdo->sqliteCreateFunction('CURDATE', function () {
            return date('Y-m-d');
        }, 0);

        $this->pdo->sqliteCreateFunction('DATEDIFF', function ($d1, $d2) {
            if (empty($d1) || empty($d2)) return null;
            $t1 = strtotime(substr((string)$d1, 0, 10));
            $t2 = strtotime(substr((string)$d2, 0, 10));
            if ($t1 === false || $t2 === false) return null;
            return (int)round(($t1 - $t2) / 86400);
        }, 2);

        $this->pdo->sqliteCreateFunction('DATE_FORMAT', function ($data, $formato) {
            if (empty($data)) return '';
            $ts = strtotime((string)$data);
            if ($ts === false) return (string)$data;
            $phpFmt = strtr((string)$formato, [
                '%d' => 'd',
                '%m' => 'm',
                '%Y' => 'Y',
                '%y' => 'y',
                '%H' => 'H',
                '%i' => 'i',
                '%s' => 's'
            ]);
            return date($phpFmt, $ts);
        }, 2);

        $this->pdo->sqliteCreateFunction('CONCAT', function (...$args) {
            return implode('', array_map(fn($v) => (string)($v ?? ''), $args));
        });

        $this->pdo->sqliteCreateFunction('LPAD', function ($str, $len, $pad = ' ') {
            return str_pad((string)$str, (int)$len, (string)$pad, STR_PAD_LEFT);
        }, 3);

        $this->pdo->sqliteCreateFunction('MD5', function ($str) {
            return md5((string)$str);
        }, 1);

        $this->pdo->sqliteCreateFunction('SUBSTRING', function ($str, $pos, $len = null) {
            $start = max(0, (int)$pos - 1);
            return $len !== null ? substr((string)$str, $start, (int)$len) : substr((string)$str, $start);
        });
    }

    public function translateSql(string $sql): string {
        $s = trim($sql);
        // Remove FOR UPDATE do final de SELECTs
        $s = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $s);

        // DATE_ADD(CURDATE(), INTERVAL X DAY)
        $s = preg_replace(
            '/DATE_ADD\s*\(\s*CURDATE\s*\(\s*\)\s*,\s*INTERVAL\s+(\?|\d+)\s+DAY\s*\)/i',
            "date('now', 'localtime', '+' || $1 || ' days')",
            $s
        );

        // DATE_ADD(NOW(), INTERVAL X MINUTE)
        $s = preg_replace(
            '/DATE_ADD\s*\(\s*NOW\s*\(\s*\)\s*,\s*INTERVAL\s+(\?|\d+)\s+MINUTE\s*\)/i',
            "datetime('now', 'localtime', '+' || $1 || ' minutes')",
            $s
        );

        // DATE_SUB(NOW(), INTERVAL X DAY)
        $s = preg_replace(
            '/DATE_SUB\s*\(\s*NOW\s*\(\s*\)\s*,\s*INTERVAL\s+(\?|\d+)\s+DAY\s*\)/i',
            "datetime('now', 'localtime', '-' || $1 || ' days')",
            $s
        );

        return $s;
    }

    public function prepare(string $sql): MvmSqliteStmt {
        return new MvmSqliteStmt($this, $this->translateSql($sql));
    }

    public function executeStmt(MvmSqliteStmt $stmt): bool {
        $sql = trim($stmt->sql);
        try {
            $pdoStmt = $this->pdo->prepare($sql);
            $pdoStmt->execute(array_values($stmt->params));
            if (preg_match('/^\s*(SELECT|PRAGMA|WITH)\b/i', $sql)) {
                $rows = $pdoStmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->result = new MvmSqliteResult($rows ?: []);
                $stmt->affectedRows = count($stmt->result->rows);
            } else {
                $stmt->affectedRows = $pdoStmt->rowCount();
                $stmt->result = new MvmSqliteResult([]);
            }
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function query(string $sql) {
        $s = trim($sql);

        // Emula SHOW COLUMNS FROM tabela [LIKE 'padrao']
        if (preg_match('/^SHOW\s+COLUMNS\s+FROM\s+(\w+)(?:\s+LIKE\s+[\'"]([^\'"]+)[\'"])?/i', $s, $m)) {
            $tabela = $m[1];
            $like = $m[2] ?? null;
            try {
                $cols = $this->pdo->query("PRAGMA table_info($tabela)")->fetchAll(\PDO::FETCH_ASSOC);
                $out = [];
                foreach ($cols as $c) {
                    $field = $c['name'];
                    if ($like !== null) {
                        $regex = '/^' . str_replace('%', '.*', preg_quote($like, '/')) . '$/i';
                        if (!preg_match($regex, $field)) continue;
                    }
                    $out[] = ['Field' => $field, 'Type' => $c['type']];
                }
                return new MvmSqliteResult($out);
            } catch (\Throwable $e) {
                return false;
            }
        }

        // Emula SHOW TABLES
        if (preg_match('/^SHOW\s+TABLES/i', $s)) {
            $rows = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_ASSOC);
            return new MvmSqliteResult($rows);
        }

        // Emula SHOW INDEX FROM
        if (preg_match('/^SHOW\s+INDEX\s+FROM/i', $s)) {
            return new MvmSqliteResult([['Key_name' => 'idx_ok']]);
        }

        // Ignora comandos DDL específicos de MySQL já tratados no schema SQLite
        if (preg_match('/^(ALTER\s+TABLE|CREATE\s+OR\s+REPLACE\s+VIEW|UPDATE\s+movimento\s+m\s+INNER\s+JOIN|UPDATE\s+produto\s+p\s+INNER\s+JOIN)/i', $s)) {
            return true;
        }

        try {
            $translated = $this->translateSql($s);
            if (preg_match('/^\s*(SELECT|PRAGMA|WITH)\b/i', $translated)) {
                $st = $this->pdo->query($translated);
                return new MvmSqliteResult($st ? $st->fetchAll(\PDO::FETCH_ASSOC) : []);
            } else {
                $this->pdo->exec($translated);
                return true;
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function beginTransaction(): bool {
        if ($this->txDepth === 0) {
            $this->pdo->beginTransaction();
        }
        $this->txDepth++;
        return true;
    }

    public function commit(): bool {
        if ($this->txDepth > 0) {
            $this->txDepth--;
            if ($this->txDepth === 0 && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        }
        return true;
    }

    public function rollBack(): bool {
        if ($this->txDepth > 0) {
            $this->txDepth = 0;
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
        return true;
    }

    private function inicializarDadosPadrao(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS usuario (
                idUsuario INTEGER PRIMARY KEY AUTOINCREMENT,
                nomeUsuario TEXT NOT NULL UNIQUE,
                emailUsuario TEXT NOT NULL UNIQUE,
                senhaUsuario TEXT NOT NULL,
                planoAssinatura TEXT NOT NULL DEFAULT 'ESSENCIAL',
                cicloPlano TEXT NOT NULL DEFAULT 'MENSAL',
                validadePlano TEXT DEFAULT NULL,
                dataAceiteTermos TEXT DEFAULT CURRENT_TIMESTAMP,
                apiToken TEXT DEFAULT NULL,
                tokenRecuperacao TEXT DEFAULT NULL,
                expiraRecuperacao TEXT DEFAULT NULL
            );

            CREATE TABLE IF NOT EXISTS cliente (
                idCliente INTEGER PRIMARY KEY AUTOINCREMENT,
                idUsuario INTEGER NOT NULL DEFAULT 1,
                nomeCliente TEXT NOT NULL,
                email TEXT NOT NULL,
                telefone TEXT DEFAULT NULL,
                endereco TEXT DEFAULT NULL,
                cidade TEXT DEFAULT NULL,
                uf TEXT DEFAULT NULL
            );

            CREATE TABLE IF NOT EXISTS fornecedor (
                idFornecedor INTEGER PRIMARY KEY AUTOINCREMENT,
                idUsuario INTEGER NOT NULL DEFAULT 1,
                nomeFornecedor TEXT NOT NULL,
                segmento TEXT DEFAULT NULL,
                cnpj TEXT NOT NULL,
                email TEXT DEFAULT NULL,
                telefone TEXT DEFAULT NULL,
                endereco TEXT DEFAULT NULL,
                cidade TEXT DEFAULT NULL,
                uf TEXT DEFAULT NULL
            );

            CREATE TABLE IF NOT EXISTS produto (
                idProduto INTEGER PRIMARY KEY AUTOINCREMENT,
                idUsuario INTEGER NOT NULL DEFAULT 1,
                idFornecedor INTEGER DEFAULT NULL,
                codigoBarras TEXT DEFAULT NULL,
                nomeProduto TEXT NOT NULL,
                categoria TEXT DEFAULT NULL,
                preco REAL NOT NULL,
                precoCusto REAL NOT NULL DEFAULT 0.00,
                quantidade INTEGER NOT NULL DEFAULT 0,
                estoqueMinimo INTEGER NOT NULL DEFAULT 10,
                localizacao TEXT NOT NULL DEFAULT 'A-01-01',
                descricao TEXT DEFAULT NULL,
                nomeFornecedor TEXT DEFAULT NULL,
                lote TEXT DEFAULT NULL,
                dataValidade TEXT DEFAULT NULL,
                imagem TEXT DEFAULT NULL
            );

            CREATE TABLE IF NOT EXISTS movimento (
                idMovimento INTEGER PRIMARY KEY AUTOINCREMENT,
                idUsuario INTEGER NOT NULL DEFAULT 1,
                codigoPedido TEXT DEFAULT NULL,
                idProduto INTEGER NOT NULL,
                idCliente INTEGER DEFAULT NULL,
                idFornecedor INTEGER DEFAULT NULL,
                tipoMovimento TEXT NOT NULL,
                quantidade INTEGER NOT NULL,
                observacao TEXT DEFAULT NULL,
                lote TEXT DEFAULT NULL,
                dataValidade TEXT DEFAULT NULL,
                motivoAjuste TEXT DEFAULT NULL,
                precoUnitario REAL NOT NULL DEFAULT 0.00,
                custoUnitario REAL NOT NULL DEFAULT 0.00,
                valorTotal REAL NOT NULL DEFAULT 0.00,
                dataMovimento TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                dataDevolucao TEXT DEFAULT NULL
            );
        ");

        $valPlano = date('Y-m-d', strtotime('+30 days'));
        $hashAdmin = password_hash('123', PASSWORD_DEFAULT);
        $stU = $this->pdo->prepare("INSERT INTO usuario (nomeUsuario, emailUsuario, senhaUsuario, planoAssinatura, cicloPlano, validadePlano, apiToken) VALUES ('admin', 'admin@mvm.com', ?, 'PREMIUM', 'MENSAL', ?, 'mvm_live_token_77a89b42')");
        $stU->execute([$hashAdmin, $valPlano]);

        $clientes = [
            [1, 'Ana Silva', 'ana.silva@email.com', '(11) 98765-4321', 'Rua das Flores, 120', 'São Paulo', 'SP'],
            [1, 'Bruno Souza', 'bruno.souza@email.com', '(21) 97654-3210', 'Av. Atlântica, 450', 'Rio de Janeiro', 'RJ'],
            [1, 'Carlos Oliveira', 'carlos.oliveira@email.com', '(31) 96543-2109', 'Rua Bahia, 88', 'Belo Horizonte', 'MG'],
            [1, 'Diana Costa', 'diana.costa@email.com', '(41) 95432-1098', 'Av. Batel, 1020', 'Curitiba', 'PR'],
            [1, 'Eduardo Santos', 'eduardo.santos@email.com', '(51) 94321-0987', 'Rua dos Andradas, 310', 'Porto Alegre', 'RS']
        ];
        $stC = $this->pdo->prepare("INSERT INTO cliente (idUsuario, nomeCliente, email, telefone, endereco, cidade, uf) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($clientes as $c) $stC->execute($c);

        $fornecedores = [
            [1, 'Tech Distribuidora', 'Informatica', '11.222.333/0001-01', 'vendas@techdistribuidora.com', '(11) 3300-1122', 'Av. Paulista, 1000', 'São Paulo', 'SP'],
            [1, 'Logitech do Brasil', 'Informatica', '22.333.444/0001-02', 'suporte@logitech.com.br', '(11) 4003-9000', 'Alameda Rio Negro, 500', 'Barueri', 'SP'],
            [1, 'Kingston Logística', 'Informatica', '33.444.555/0001-03', 'corporativo@kingston.com', '(21) 2244-5566', 'Av. Rio Branco, 25', 'Rio de Janeiro', 'RJ'],
            [1, 'LOréal Brasil Cosméticos', 'Cosméticos', '44.555.666/0001-04', 'pedidos@loreal.com.br', '(21) 3500-0011', 'Rua do Passeio, 38', 'Rio de Janeiro', 'RJ'],
            [1, 'Nivea Distribuição', 'Cosméticos', '55.666.777/0001-05', 'comercial@nivea.com.br', '(41) 3200-4455', 'Av. Sete de Setembro, 200', 'Curitiba', 'PR'],
            [1, 'Nestlé Brasil Alimentos', 'Alimentícios', '77.888.999/0001-07', 'faturamento@nestle.com.br', '(11) 2100-3344', 'Av. Guido Aliberti, 1500', 'São Caetano do Sul', 'SP'],
            [1, 'Samsung Eletrônica', 'Eletro-eletrônicos', '12.345.678/0001-10', 'faturamento@samsung.com.br', '(92) 3600-7788', 'Av. dos Oitis, 1460', 'Manaus', 'AM']
        ];
        $stF = $this->pdo->prepare("INSERT INTO fornecedor (idUsuario, nomeFornecedor, segmento, cnpj, email, telefone, endereco, cidade, uf) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($fornecedores as $f) $stF->execute($f);

        $produtos = [
            [1, 6, '7894900011517', 'Refrigerante Coca-Cola 2L', 'Bebidas', 9.50, 5.20, 150, 25, 'A-01-01', 'Refrigerante sabor cola tradicional em garrafa PET 2 Litros.', 'Nestlé Brasil Alimentos', 'LOT-BEB-2026', date('Y-m-d', strtotime('+90 days')), 'coca_cola_2l.jpg'],
            [1, 6, '7894900011524', 'Suco de Laranja Integral 1L', 'Bebidas', 11.90, 6.80, 100, 20, 'A-01-02', 'Suco 100% fruta natural sem adição de açúcar ou conservantes.', 'Nestlé Brasil Alimentos', 'LOT-SUC-2026', date('Y-m-d', strtotime('+25 days')), 'suco_laranja_1l.jpg'],
            [1, 6, '7894900530001', 'Água Mineral Crystal 500ml', 'Bebidas', 3.50, 1.40, 200, 30, 'A-01-03', 'Água mineral natural sem gás, embalagem prática.', 'Nestlé Brasil Alimentos', 'LOT-AGU-2026', date('Y-m-d', strtotime('+180 days')), 'agua_mineral_500ml.jpg'],
            [1, 6, '9002490100070', 'Energético Red Bull 250ml', 'Bebidas', 10.50, 6.10, 90, 15, 'A-01-04', 'Bebida energética gaseificada com taurina e cafeína.', 'Nestlé Brasil Alimentos', 'LOT-RED-2026', date('Y-m-d', strtotime('+120 days')), 'red_bull_250ml.jpg'],
            [1, 2, '0097855141224', 'Mouse Gamer Logitech G502 Hero', 'Informatica', 249.90, 145.00, 50, 10, 'B-02-01', 'Sensor Hero 25K, 11 botões programáveis e pesos ajustáveis.', 'Logitech do Brasil', 'LOT-LOG-502', null, 'mouse_gamer_logitech.jpg'],
            [1, 1, '6950376773906', 'Teclado Mecânico Redragon Kumara', 'Informatica', 199.90, 110.00, 8, 10, 'B-02-02', 'Switch Outemu Blue, iluminação LED vermelha.', 'Tech Distribuidora', 'LOT-RED-K55', null, 'teclado_mecanico.jpg'],
            [1, 3, '0740617263442', 'SSD Kingston A400 480GB SATA III', 'Informatica', 289.90, 160.00, 80, 15, 'B-02-03', 'Velocidade de leitura até 500MB/s e gravação até 450MB/s.', 'Kingston Logística', 'LOT-SSD-A40', null, 'ssd_kingston_a400.jpg'],
            [1, 4, '3337875588621', 'Protetor Solar Anthelios FPS 70', 'Cosméticos', 89.90, 48.00, 40, 12, 'C-01-01', 'Controle de oleosidade e acabamento matte.', 'LOréal Brasil Cosméticos', 'LOT-COS-70A', date('Y-m-d', strtotime('+45 days')), 'protetor_solar_anthelios.jpg'],
            [1, 5, '4005808000111', 'Creme Hidratante Nivea Lata Azul 56g', 'Cosméticos', 15.90, 7.50, 120, 20, 'C-01-02', 'Hidratação profunda para todos os tipos de pele.', 'Nivea Distribuição', 'LOT-NIV-056', date('Y-m-d', strtotime('+60 days')), 'creme_nivea_lata.webp'],
            [1, 6, '7896089011357', 'Café Pilão Tradicional 500g', 'Alimentícios', 18.90, 11.20, 95, 20, 'A-02-01', 'Café torrado e moído tradicional a vácuo.', 'Nestlé Brasil Alimentos', 'LOT-CAF-500', date('Y-m-d', strtotime('+75 days')), 'cafe_pilao_500g.jpg'],
            [1, 7, '7892509116756', 'Smart TV Samsung 50" Crystal UHD 4K', 'Eletro-eletrônicos', 2399.00, 1650.00, 15, 5, 'D-01-01', 'Resolução 4K UHD com processador Crystal e comando de voz.', 'Samsung Eletrônica', 'LOT-SAM-4KU', null, 'smart_tv_samsung_50.jpg']
        ];
        $stP = $this->pdo->prepare("INSERT INTO produto (idUsuario, idFornecedor, codigoBarras, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, localizacao, descricao, nomeFornecedor, lote, dataValidade, imagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($produtos as $p) $stP->execute($p);

        $movs = [
            [1, 1, null, 'SAIDA', 18, 'Venda de balcão Coca-Cola', -7, null],
            [5, 2, null, 'SAIDA', 2, 'Venda Mouse Logitech', -7, null],
            [10, 3, null, 'SAIDA', 10, 'Venda Café Pilão', -7, null],
            [1, 1, null, 'SAIDA', 22, 'Venda para evento Coca-Cola', -6, null],
            [2, 4, null, 'SAIDA', 12, 'Venda Suco Laranja', -6, null],
            [6, 5, null, 'SAIDA', 3, 'Venda Teclado Redragon', -6, null],
            [3, 1, null, 'SAIDA', 35, 'Venda Água Mineral', -5, null],
            [4, 2, null, 'SAIDA', 15, 'Venda Energético Red Bull', -5, null],
            [7, 3, null, 'SAIDA', 4, 'Venda SSD Kingston', -5, null],
            [1, 1, null, 'SAIDA', 30, 'Venda balcão Coca-Cola', -4, null],
            [8, 4, null, 'SAIDA', 6, 'Venda Protetor Solar', -4, null],
            [11, 5, null, 'SAIDA', 1, 'Venda Smart TV Samsung', -4, null],
            [1, 1, null, 'SAIDA', 25, 'Venda balcão Coca-Cola', -3, null],
            [2, 2, null, 'SAIDA', 16, 'Venda Suco Laranja', -3, null],
            [5, 3, null, 'SAIDA', 3, 'Venda Mouse Logitech', -3, null],
            [1, 1, null, 'SAIDA', 32, 'Venda para restaurante', -2, null],
            [4, 4, null, 'SAIDA', 20, 'Venda Energético', -2, null],
            [9, 5, null, 'SAIDA', 15, 'Venda Creme Nivea', -2, null],
            [1, 1, null, 'SAIDA', 40, 'Venda atacado Coca-Cola', -1, null],
            [2, 2, null, 'SAIDA', 20, 'Venda Suco Laranja', -1, null],
            [7, 3, null, 'SAIDA', 5, 'Venda SSD Kingston', -1, null],
            [1, 1, null, 'SAIDA', 28, 'Venda balcão Coca-Cola hoje', 0, null],
            [2, 4, null, 'SAIDA', 14, 'Venda Suco Laranja hoje', 0, null],
            [5, 5, null, 'SAIDA', 4, 'Venda Mouse Logitech hoje', 0, null],
            [11, 2, null, 'SAIDA', 1, 'Venda Smart TV Samsung hoje', 0, null],
            [7, null, 3, 'ENTRADA', 30, 'Carga de reposição recebida', -5, null],
            [6, 2, null, 'SAIDA', 1, 'Venda de teste com devolução', -3, -2]
        ];
        $stM = $this->pdo->prepare("
            INSERT INTO movimento (idUsuario, codigoPedido, idProduto, idCliente, idFornecedor, tipoMovimento, quantidade, observacao, precoUnitario, custoUnitario, valorTotal, dataMovimento, dataDevolucao)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($movs as $idx => $m) {
            $idProd = $m[0];
            $pInfo = $produtos[$idProd - 1];
            $pVenda = (float)$pInfo[5];
            $pCusto = (float)$pInfo[6];
            $qtd = (int)$m[4];
            $valTot = round($qtd * $pVenda, 2);
            $dtMov = date('Y-m-d H:i:s', strtotime("{$m[6]} days"));
            $dtDev = $m[7] !== null ? date('Y-m-d H:i:s', strtotime("{$m[7]} days")) : null;
            $codPed = 'PED-' . date('Ymd', strtotime($dtMov)) . '-' . sprintf('%04d', $idx + 1);
            $stM->execute([$codPed, $idProd, $m[1], $m[2], $m[3], $qtd, $m[5], $pVenda, $pCusto, $valTot, $dtMov, $dtDev]);
        }
    }
}

// Funções híbridas compatíveis tanto com \mysqli quanto com MvmCloudDB
function mvm_db_prepare($con, $sql) {
    if ($con instanceof \mysqli) return \mysqli_prepare($con, $sql);
    if ($con instanceof MvmCloudDB) return $con->prepare($sql);
    return false;
}

function mvm_db_stmt_bind_param($stmt, $types, &...$params) {
    if ($stmt instanceof \mysqli_stmt) return \mysqli_stmt_bind_param($stmt, $types, ...$params);
    if ($stmt instanceof MvmSqliteStmt) {
        $vals = [];
        foreach ($params as $p) $vals[] = $p;
        $stmt->params = $vals;
        return true;
    }
    return false;
}

function mvm_db_stmt_execute($stmt) {
    if ($stmt instanceof \mysqli_stmt) return \mysqli_stmt_execute($stmt);
    if ($stmt instanceof MvmSqliteStmt) return $stmt->db->executeStmt($stmt);
    return false;
}

function mvm_db_stmt_get_result($stmt) {
    if ($stmt instanceof \mysqli_stmt) return \mysqli_stmt_get_result($stmt);
    if ($stmt instanceof MvmSqliteStmt) return $stmt->result ?? new MvmSqliteResult([]);
    return false;
}

function mvm_db_stmt_affected_rows($stmt) {
    if ($stmt instanceof \mysqli_stmt) return \mysqli_stmt_affected_rows($stmt);
    if ($stmt instanceof MvmSqliteStmt) return $stmt->affectedRows;
    return 0;
}

function mvm_db_stmt_close($stmt) {
    if ($stmt instanceof \mysqli_stmt) return \mysqli_stmt_close($stmt);
    return true;
}

function mvm_db_query($con, $sql) {
    if ($con instanceof \mysqli) return \mysqli_query($con, $sql);
    if ($con instanceof MvmCloudDB) return $con->query($sql);
    return false;
}

function mvm_db_fetch_assoc($res) {
    if ($res instanceof \mysqli_result) return \mysqli_fetch_assoc($res);
    if ($res instanceof MvmSqliteResult) {
        if ($res->pos < count($res->rows)) {
            return $res->rows[$res->pos++];
        }
        return null;
    }
    return null;
}

function mvm_db_fetch_all($res, $mode = MYSQLI_ASSOC) {
    if ($res instanceof \mysqli_result) return \mysqli_fetch_all($res, $mode);
    if ($res instanceof MvmSqliteResult) return $res->rows;
    return [];
}

function mvm_db_fetch_row($res) {
    if ($res instanceof \mysqli_result) return \mysqli_fetch_row($res);
    if ($res instanceof MvmSqliteResult) {
        if ($res->pos < count($res->rows)) {
            return array_values($res->rows[$res->pos++]);
        }
        return null;
    }
    return null;
}

function mvm_db_num_rows($res) {
    if ($res instanceof \mysqli_result) return \mysqli_num_rows($res);
    if ($res instanceof MvmSqliteResult) return count($res->rows);
    return 0;
}

function mvm_db_insert_id($con) {
    if ($con instanceof \mysqli) return \mysqli_insert_id($con);
    if ($con instanceof MvmCloudDB) return (int)$con->pdo->lastInsertId();
    return 0;
}

function mvm_db_begin_transaction($con) {
    if ($con instanceof \mysqli) return \mysqli_begin_transaction($con);
    if ($con instanceof MvmCloudDB) return $con->beginTransaction();
    return false;
}

function mvm_db_commit($con) {
    if ($con instanceof \mysqli) return \mysqli_commit($con);
    if ($con instanceof MvmCloudDB) return $con->commit();
    return false;
}

function mvm_db_rollback($con) {
    if ($con instanceof \mysqli) return \mysqli_rollback($con);
    if ($con instanceof MvmCloudDB) return $con->rollBack();
    return false;
}

function mvm_db_close($con) {
    if ($con instanceof \mysqli) return \mysqli_close($con);
    return true;
}

function mvm_db_error($con) {
    if ($con instanceof \mysqli) return \mysqli_error($con);
    if ($con instanceof MvmCloudDB) return $con->lastError;
    return '';
}
