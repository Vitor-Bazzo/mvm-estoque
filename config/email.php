<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Envia ao cliente o email de confirmação de compra da MVM.
 *
 * @return true|string  true se o email foi enviado; caso contrário, a mensagem de erro.
 */
function enviarEmailCompra($emailCliente, $nomeCliente, $nomeProduto, $quantidade, $precoUnitario) {
    // As credenciais SMTP ficam em um arquivo separado (não vai para o GitHub).
    if (!defined('MVM_SMTP_HOST')) {
        $arquivo = __DIR__ . '/email_credenciais.php';
        if (!file_exists($arquivo)) {
            return "Arquivo config/email_credenciais.php não encontrado.";
        }
        require_once $arquivo;
    }

    if (MVM_SMTP_USUARIO === 'seu_email@gmail.com') {
        return "Configure o email e a senha em config/email_credenciais.php.";
    }

    if (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
        return "O email cadastrado do cliente é inválido.";
    }

    $valorTotal = round($precoUnitario * $quantidade, 2);

    $produtoHtml   = htmlspecialchars($nomeProduto);
    $clienteHtml   = htmlspecialchars($nomeCliente);
    $unitarioTexto = 'R$ ' . number_format($precoUnitario, 2, ',', '.');
    $totalTexto    = 'R$ ' . number_format($valorTotal, 2, ',', '.');

    $corpoHtml = "
    <div style='font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; color: #222;'>
        <h2 style='color: #16a34a;'>Compra realizada com sucesso!</h2>
        <p>Olá, <b>{$clienteHtml}</b>!</p>
        <p>Sua compra na <b>MVM</b> foi realizada com sucesso. Confira os detalhes:</p>
        <table style='border-collapse: collapse; width: 100%;'>
            <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><b>Produto</b></td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$produtoHtml}</td></tr>
            <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><b>Quantidade</b></td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$quantidade}</td></tr>
            <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><b>Valor unitário</b></td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$unitarioTexto}</td></tr>
            <tr><td style='padding: 8px;'><b>Valor total</b></td>
                <td style='padding: 8px;'><b>{$totalTexto}</b></td></tr>
        </table>
        <p style='margin-top: 24px;'>Obrigado por comprar na MVM!</p>
        <p style='color: #888; font-size: 12px;'>MVM</p>
    </div>";

    $corpoTexto = "Olá, {$nomeCliente}!\n\n"
        . "Sua compra na MVM foi realizada com sucesso.\n\n"
        . "Produto: {$nomeProduto}\n"
        . "Quantidade: {$quantidade}\n"
        . "Valor unitário: {$unitarioTexto}\n"
        . "Valor total: {$totalTexto}\n\n"
        . "Obrigado por comprar na MVM!";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MVM_SMTP_HOST;
        $mail->Port       = MVM_SMTP_PORTA;
        $mail->SMTPAuth   = true;
        $mail->Username   = trim(MVM_SMTP_USUARIO);
        $mail->Password   = str_replace(' ', '', MVM_SMTP_SENHA);
        $mail->SMTPSecure = MVM_SMTP_SEGURANCA;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(MVM_SMTP_USUARIO, 'MVM');
        $mail->addAddress($emailCliente, $nomeCliente);

        $mail->isHTML(true);
        $mail->Subject = 'MVM - Compra realizada com sucesso';
        $mail->Body    = $corpoHtml;
        $mail->AltBody = $corpoTexto;

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}
?>
