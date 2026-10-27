<?php
/**
 * Script para Execução Automática Diária / Semanal de Alertas de Estoque
 *
 * Como rodar:
 * 1. Via Linha de Comando (CLI):
 *    php cron_alertas.php
 *
 * 2. Via Agendador de Tarefas do Windows / Cron Job:
 *    php c:\xampp\htdocs\mvm-estoque-main\mvm-estoque-main\cron_alertas.php
 *
 * 3. Via Navegador / Webhook (com token de segurança):
 *    http://localhost/mvm/cron_alertas.php?token=mvm_estoque_alerta_2026
 */

require_once __DIR__ . "/../config/conexao.php";
require_once __DIR__ . "/../config/alertas_estoque.php";

$isCli = (php_sapi_name() === 'cli');

// Validação de segurança se for acessado via web
if (!$isCli) {
    $tokenInformado = $_GET['token'] ?? '';
    $tokenCorreto = 'mvm_estoque_alerta_2026';

    if ($tokenInformado !== $tokenCorreto) {
        http_response_code(403);
        die("Acesso negado: Token de automação inválido.");
    }
}

$conexao = conectar();
if (is_string($conexao)) {
    die("Erro ao conectar no banco: {$conexao}\n");
}

// Busca todos os gestores/usuários ativos cadastrados
$sql = "SELECT idUsuario, nomeUsuario, emailUsuario FROM usuario WHERE emailUsuario IS NOT NULL AND emailUsuario != ''";
$res = mysqli_query($conexao, $sql);

$relatoriosEnviados = 0;
$log = [];

if ($res) {
    while ($usr = mysqli_fetch_assoc($res)) {
        $id = (int)$usr['idUsuario'];
        $email = $usr['emailUsuario'];
        $nome = $usr['nomeUsuario'];

        $ret = enviarRelatorioAlertasEstoque($id, $email, false);
        $log[] = "[{$nome} - {$email}]: " . ($ret['sucesso'] ? "Sucesso ({$ret['mensagem']})" : "Erro ({$ret['mensagem']})");
        if ($ret['sucesso']) {
            $relatoriosEnviados++;
        }
    }
}

mysqli_close($conexao);

$msgFinal = "Disparo de alertas concluído. Processados: " . count($log) . ", Enviados: {$relatoriosEnviados}.\n" . implode("\n", $log) . "\n";

if ($isCli) {
    echo $msgFinal;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $msgFinal;
}
