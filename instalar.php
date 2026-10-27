<?php
/**
 * Assistente de Instalação e Diagnóstico Automático do Banco de Dados
 * Sistema MVM Estoque - Suporte InfinityFree e Localhost
 */

header('Content-Type: text/html; charset=UTF-8');

$host = 'sql311.infinityfree.com';
$user = 'if0_42798246';
$pass = 'DA3oa2pkLHi';

// Detecta se está rodando localmente no XAMPP ou na nuvem
$hostServidor = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = empty($hostServidor) || in_array(explode(':', $hostServidor)[0], ['localhost', '127.0.0.1']);

if ($isLocal) {
    $host = '127.0.0.1';
    $user = 'root';
    $pass = '';
}

// 1. Tenta conectar ao servidor MySQL sem especificar banco para listar os bancos existentes
$conexaoInicial = null;
$erroConexao = '';
try {
    $conexaoInicial = @mysqli_connect($host, $user, $pass, null, 3306);
} catch (Throwable $e) {
    $erroConexao = $e->getMessage();
}

// 2. Se falhar, tenta com o host local ou verifica
$bancosDisponiveis = [];
if ($conexaoInicial) {
    $res = @mysqli_query($conexaoInicial, "SHOW DATABASES");
    if ($res) {
        while ($row = mysqli_fetch_row($res)) {
            // Ignora bancos de sistema
            if (!in_array($row[0], ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
                $bancosDisponiveis[] = $row[0];
            }
        }
    }
}

// 3. Processa Ação de Instalação Automática
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$bancoSelecionado = $_POST['banco'] ?? $_GET['banco'] ?? ($bancosDisponiveis[0] ?? '');
$relatorioInstalacao = [];
$sucessoTotal = false;

if ($acao === 'instalar' && !empty($bancoSelecionado) && $conexaoInicial) {
    if (@mysqli_select_db($conexaoInicial, $bancoSelecionado)) {
        mysqli_set_charset($conexaoInicial, 'utf8mb4');
        
        $caminhoSql = __DIR__ . '/sql/bdmvm_hospedagem_nuvem.sql';
        if (file_exists($caminhoSql)) {
            $conteudoSql = file_get_contents($caminhoSql);
            
            // Desativa temporariamente chaves estrangeiras para importação limpa
            @mysqli_query($conexaoInicial, "SET FOREIGN_KEY_CHECKS = 0");
            
            // Divide as queries por delimitador ;
            // Remove comentários de linha e divide
            $queries = [];
            $linhas = explode("\n", $conteudoSql);
            $buffer = '';
            foreach ($linhas as $linha) {
                $linhaTrim = trim($linha);
                if (empty($linhaTrim) || strpos($linhaTrim, '--') === 0 || strpos($linhaTrim, '/*') === 0) {
                    continue;
                }
                $buffer .= $linha . "\n";
                if (substr($linhaTrim, -1) === ';') {
                    $queries[] = $buffer;
                    $buffer = '';
                }
            }
            if (!empty(trim($buffer))) {
                $queries[] = $buffer;
            }

            $executadas = 0;
            $erros = 0;
            foreach ($queries as $q) {
                $q = trim($q);
                if (empty($q)) continue;
                try {
                    if (@mysqli_query($conexaoInicial, $q)) {
                        $executadas++;
                    } else {
                        $erros++;
                    }
                } catch (Throwable $e) {
                    $erros++;
                }
            }
            
            @mysqli_query($conexaoInicial, "SET FOREIGN_KEY_CHECKS = 1");
            
            // Atualiza conexao_producao.php se estiver na nuvem
            if (!$isLocal) {
                $novoConteudo = "<?php\n/**\n * Configuração de Conexão com o Banco de Dados do InfinityFree\n */\ndefine('DB_HOST', '$host');\ndefine('DB_USER', '$user');\ndefine('DB_PASS', '$pass');\ndefine('DB_NAME', '$bancoSelecionado');\ndefine('DB_PORT', 3306);\n";
                @file_put_contents(__DIR__ . '/config/conexao_producao.php', $novoConteudo);
            }

            // Confere tabelas criadas
            $tabelasCriadas = [];
            $resT = @mysqli_query($conexaoInicial, "SHOW TABLES");
            if ($resT) {
                while ($r = mysqli_fetch_row($resT)) {
                    $tabelasCriadas[] = $r[0];
                }
            }

            $relatorioInstalacao = [
                'banco' => $bancoSelecionado,
                'queries_executadas' => $executadas,
                'tabelas' => $tabelasCriadas
            ];

            if (in_array('usuario', $tabelasCriadas) && in_array('produto', $tabelasCriadas)) {
                $sucessoTotal = true;
            }
        } else {
            $erroInstalacao = "Arquivo sql/bdmvm_hospedagem_nuvem.sql não foi encontrado no servidor.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador & Diagnóstico de Banco de Dados • MVM Estoque</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090d16;
            --card: #111827;
            --border: rgba(255, 255, 255, 0.1);
            --text: #f3f4f6;
            --muted: #9ca3af;
            --primary: #6366f1;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 640px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 800;
            margin-top: 10px;
            background: linear-gradient(135deg, #a5b4fc, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header p {
            color: var(--muted);
            font-size: 13.5px;
            margin-top: 4px;
        }
        .status-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 13.5px;
        }
        .status-row:last-child { border-bottom: none; }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .btn-action {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }
        .instructions {
            background: rgba(245, 158, 11, 0.08);
            border: 1px dashed rgba(245, 158, 11, 0.35);
            border-radius: 10px;
            padding: 16px;
            margin-top: 18px;
            font-size: 13px;
            color: #fbbf24;
            line-height: 1.6;
        }
        .instructions ol { margin-left: 20px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="font-size: 40px;">⚡</div>
            <h1>Configuração do Banco de Dados</h1>
            <p>Diagnóstico e Instalação Automática das Tabelas</p>
        </div>

        <?php if ($sucessoTotal): ?>
            <div style="text-align: center; padding: 20px 0;">
                <div style="font-size: 48px; margin-bottom: 12px;">🎉</div>
                <h2 style="color: var(--success); font-size: 20px; margin-bottom: 8px;">Banco de Dados Instalado com Sucesso!</h2>
                <p style="color: var(--muted); font-size: 14px; margin-bottom: 20px;">
                    Todas as <strong><?= count($relatorioInstalacao['tabelas']) ?> tabelas</strong> foram criadas no banco <strong><?= htmlspecialchars($relatorioInstalacao['banco']) ?></strong> e o usuário administrador padrão já está pronto para uso.
                </p>
                <div class="status-card" style="text-align: left;">
                    <div class="status-row"><span>Tabelas Criadas</span><strong><?= implode(', ', $relatorioInstalacao['tabelas']) ?></strong></div>
                    <div class="status-row"><span>Usuário Padrão</span><strong>admin</strong></div>
                    <div class="status-row"><span>Senha Padrão</span><strong>123</strong></div>
                </div>
                <a href="index.php" class="btn-action">Acessar o Sistema MVM Estoque &rarr;</a>
            </div>

        <?php else: ?>

            <div class="status-card">
                <div class="status-row">
                    <span>Servidor MySQL Host</span>
                    <strong style="font-family: monospace;"><?= htmlspecialchars($host) ?></strong>
                </div>
                <div class="status-row">
                    <span>Usuário do Banco</span>
                    <strong style="font-family: monospace;"><?= htmlspecialchars($user) ?></strong>
                </div>
                <div class="status-row">
                    <span>Status da Conexão</span>
                    <?php if ($conexaoInicial): ?>
                        <span class="badge badge-success">Conectado com Sucesso</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Falha na Conexão</span>
                    <?php endif; ?>
                </div>
                <div class="status-row">
                    <span>Bancos Criados na Conta</span>
                    <?php if (!empty($bancosDisponiveis)): ?>
                        <span class="badge badge-success"><?= count($bancosDisponiveis) ?> banco(s) encontrado(s)</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Nenhum banco criado ainda</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$conexaoInicial): ?>
                <div class="instructions" style="background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); color: #fca5a5;">
                    <strong>Erro de Conexão com o Servidor MySQL:</strong><br>
                    <?= htmlspecialchars($erroConexao ?: 'Servidor não respondeu. Verifique se o host e senha do MySQL no painel do InfinityFree estão corretos.') ?>
                </div>

            <?php elseif (empty($bancosDisponiveis)): ?>
                <div class="instructions">
                    <strong>⚠️ Você ainda precisa criar o banco de dados no painel do InfinityFree:</strong>
                    <ol>
                        <li>Acesse o seu painel: <a href="https://dash.infinityfree.com/accounts/if0_42798246" target="_blank" style="color: #60a5fa;">Painel da Conta</a>.</li>
                        <li>Clique em <strong>Control Panel</strong> e depois em <strong>MySQL Databases</strong>.</li>
                        <li>No campo <em>Create New Database</em>, digite <strong>bdmvm</strong> e clique em <strong>Create Database</strong>.</li>
                        <li>Após clicar em criar, volte aqui e <a href="instalar.php" style="color: #60a5fa; font-weight: bold;">Recarregue esta Página</a>!</li>
                    </ol>
                </div>

            <?php else: ?>
                <form action="instalar.php" method="POST">
                    <input type="hidden" name="acao" value="instalar">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 8px; color: var(--muted);">Selecione o banco de dados para instalar as tabelas:</label>
                        <select name="banco" style="width: 100%; padding: 12px; background: rgba(255,255,255,0.06); border: 1px solid var(--border); border-radius: 8px; color: #fff; font-size: 14px; font-family: monospace;">
                            <?php foreach ($bancosDisponiveis as $bd): ?>
                                <option value="<?= htmlspecialchars($bd) ?>"><?= htmlspecialchars($bd) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-action">
                        🚀 Instalar Tabelas e Dados Automaticamente
                    </button>
                </form>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>
