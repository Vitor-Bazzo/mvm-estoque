<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/conexao.php";
require_once __DIR__ . "/../config/alertas_estoque.php";

// Apenas usuários autenticados
if (!isset($_SESSION['usuarioLogado'])) {
    header('Location: ' . url('modulos/auth/login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('index.php'));
    exit;
}

if (!validarTokenCSRF()) {
    $_SESSION['flash_dashboard'] = [
        'tipo'  => 'erro',
        'texto' => 'Token de segurança inválido. Tente novamente.'
    ];
    header('Location: ' . url('index.php'));
    exit;
}

$idUsuario = obterIdUsuarioLogado();
$emailDestino = !empty($_POST['emailDestino']) ? trim($_POST['emailDestino']) : ($_SESSION['emailUsuario'] ?? null);

$resultado = enviarRelatorioAlertasEstoque($idUsuario, $emailDestino, true);

if ($resultado['sucesso']) {
    $_SESSION['flash_dashboard'] = [
        'tipo'  => 'sucesso',
        'texto' => "📧 " . $resultado['mensagem'] . " ({$resultado['totalEstoque']} produto(s) em estoque crítico e {$resultado['totalValidade']} a vencer)."
    ];
} else {
    $_SESSION['flash_dashboard'] = [
        'tipo'  => 'erro',
        'texto' => "⚠️ Não foi possível enviar os alertas: " . $resultado['mensagem']
    ];
}

$origem = !empty($_POST['origem']) ? $_POST['origem'] : url('index.php');
header("Location: {$origem}");
exit;
