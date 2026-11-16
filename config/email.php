<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

// Garante o fuso horário padrão do Brasil para data e hora corretas
if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/Sao_Paulo');
}

/**
 * Envia ao cliente o email de confirmação de compra da MVM.
 *
 * @param string      $emailCliente       Email do cliente destinatário
 * @param string      $nomeCliente        Nome do cliente
 * @param string      $nomeProduto        Nome do produto ou identificação do pedido
 * @param int         $quantidade         Quantidade total de itens comprados
 * @param float       $precoUnitario      Preço unitário (usado quando venda simples)
 * @param float|null  $valorTotal         Valor total da compra (se nulo, calcula preço unitário * quantidade)
 * @param string|null $dataValidade       Data de validade do(s) produto(s) (caso aplicável)
 * @param string|null $dataHora           Dia e horário da compra (ex: '26/10/2026 às 15:30')
 * @param array       $itensDetalhados    Lista opcional com múltiplos produtos [{nome, quantidade, precoUnitario, subtotal, validade}]
 * @return true|string                    true se o email foi enviado; caso contrário, a mensagem de erro.
 */
function enviarEmailCompra(
    $emailCliente,
    $nomeCliente,
    $nomeProduto,
    $quantidade,
    $precoUnitario = 0,
    $valorTotal = null,
    $dataValidade = null,
    $dataHora = null,
    $itensDetalhados = []
) {
    // 1. Carrega as credenciais SMTP de arquivo seguro
    if (!defined('MVM_SMTP_HOST')) {
        $arquivo = __DIR__ . '/email_credenciais.php';
        if (!file_exists($arquivo)) {
            return "Arquivo config/email_credenciais.php não encontrado.";
        }
        require_once $arquivo;
    }

    if (defined('MVM_SMTP_USUARIO') && MVM_SMTP_USUARIO === 'seu_email@gmail.com') {
        return "Configure o email e a senha em config/email_credenciais.php.";
    }

    if (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
        return "O email cadastrado do cliente é inválido.";
    }

    // 2. Trata valores, data, horário e validade
    $quantidadeTotal = max(1, (int)$quantidade);
    if ($valorTotal === null) {
        $valorTotal = round((float)$precoUnitario * $quantidadeTotal, 2);
    } else {
        $valorTotal = round((float)$valorTotal, 2);
    }

    if (empty($dataHora)) {
        $dataHora = date('d/m/Y \à\s H:i');
    }

    $produtoHtml   = htmlspecialchars($nomeProduto);
    $clienteHtml   = htmlspecialchars($nomeCliente);
    $dataHoraHtml  = htmlspecialchars($dataHora);
    $unitarioTexto = 'R$ ' . number_format((float)$precoUnitario, 2, ',', '.');
    $totalTexto    = 'R$ ' . number_format($valorTotal, 2, ',', '.');

    // Validade caso informada/necessária
    $validadeHtml = '';
    $validadeTexto = '';
    if (!empty($dataValidade) && $dataValidade !== '0000-00-00' && $dataValidade !== '-') {
        $validadeLimpa = htmlspecialchars($dataValidade);
        $validadeHtml = "
            <tr>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 13.5px;'><b>Data de Validade</b></td>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #b45309; font-weight: 700; font-size: 13.5px;'>📅 {$validadeLimpa}</td>
            </tr>";
        $validadeTexto = "Validade do Produto: {$dataValidade}\n";
    }

    // Detalhamento de itens se for carrinho de múltiplos produtos
    $tabelaItensHtml = '';
    $itensTexto = '';
    if (!empty($itensDetalhados) && is_array($itensDetalhados)) {
        $temValidadeNosItens = false;
        foreach ($itensDetalhados as $it) {
            if (!empty($it['validade'])) {
                $temValidadeNosItens = true;
                break;
            }
        }

        $linhasTabela = '';
        foreach ($itensDetalhados as $it) {
            $nItem = htmlspecialchars($it['nome'] ?? 'Item');
            $qItem = (int)($it['quantidade'] ?? 1);
            $pUnit = 'R$ ' . number_format((float)($it['precoUnitario'] ?? 0), 2, ',', '.');
            $pSub  = 'R$ ' . number_format((float)($it['subtotal'] ?? 0), 2, ',', '.');
            $vItem = !empty($it['validade']) ? htmlspecialchars($it['validade']) : '-';

            $colValidade = $temValidadeNosItens ? "<td style='padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #b45309; font-size: 12px;'>{$vItem}</td>" : "";

            $linhasTabela .= "
                <tr>
                    <td style='padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #1e293b;'><b>{$nItem}</b></td>
                    <td style='padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: center; font-size: 13px; color: #334155;'>{$qItem} un</td>
                    <td style='padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: right; font-size: 13px; color: #334155;'>{$pUnit}</td>
                    {$colValidade}
                    <td style='padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 700; font-size: 13px; color: #00704A;'>{$pSub}</td>
                </tr>";

            $itensTexto .= "- {$nItem} | Qtd: {$qItem} | Unit: {$pUnit} | Subtotal: {$pSub}" . (!empty($it['validade']) ? " | Validade: {$it['validade']}" : "") . "\n";
        }

        $thValidade = $temValidadeNosItens ? "<th style='padding: 8px 10px; text-align: center; font-size: 12px; color: #475569;'>Validade</th>" : "";

        $tabelaItensHtml = "
            <div style='margin-top: 20px;'>
                <p style='margin: 0 0 8px 0; font-weight: 700; color: #1e293b; font-size: 14px;'>📦 Itens Inclusos na Compra:</p>
                <table style='width: 100%; border-collapse: collapse; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                    <thead>
                        <tr style='background: #f8fafc; border-bottom: 1px solid #e2e8f0;'>
                            <th style='padding: 8px 10px; text-align: left; font-size: 12px; color: #475569;'>Produto</th>
                            <th style='padding: 8px 10px; text-align: center; font-size: 12px; color: #475569;'>Qtd</th>
                            <th style='padding: 8px 10px; text-align: right; font-size: 12px; color: #475569;'>Unitário</th>
                            {$thValidade}
                            <th style='padding: 8px 10px; text-align: right; font-size: 12px; color: #475569;'>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$linhasTabela}
                    </tbody>
                </table>
            </div>";
    } else {
        // Se for compra simples sem lista de carrinho
        $tabelaItensHtml = "";
        $itensTexto = "Produto: {$nomeProduto}\nQuantidade: {$quantidadeTotal}\nValor Unitário: {$unitarioTexto}\n";
    }

    // 3. Monta o Corpo em HTML com design premium e responsivo
    $corpoHtml = "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <title>MVM - Compra Realizada com Sucesso</title>
    </head>
    <body style='margin: 0; padding: 24px 10px; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
        <div style='max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
            
            <!-- Cabeçalho -->
            <div style='background: linear-gradient(135deg, #00704A 0%, #005035 100%); padding: 28px 24px; text-align: center; color: #ffffff;'>
                <h1 style='margin: 0 0 6px 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px;'>MVM ESTOQUE & VENDAS</h1>
                <div style='display: inline-block; background: rgba(255,255,255,0.2); padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase;'>
                    ✓ Compra Realizada com Sucesso
                </div>
            </div>

            <!-- Conteúdo Principal -->
            <div style='padding: 24px;'>
                <p style='margin: 0 0 12px 0; font-size: 16px; color: #1e293b;'>Olá, <b>{$clienteHtml}</b>!</p>
                <p style='margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.5;'>
                    Sua compra na <b>MVM</b> foi processada com êxito. Agradecemos pela preferência! Confira abaixo todos os dados e comprovante da transação:
                </p>

                <!-- Card de Resumo Principal -->
                <div style='background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; margin-bottom: 20px;'>
                    <table style='border-collapse: collapse; width: 100%;'>
                        <tr>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 13.5px; width: 45%;'><b>Data e Horário</b></td>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-weight: 600; font-size: 13.5px;'>🕒 {$dataHoraHtml}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 13.5px;'><b>Identificação / Produto</b></td>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 13.5px;'>{$produtoHtml}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 13.5px;'><b>Quantidade Total de Itens</b></td>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-weight: 700; font-size: 13.5px;'>📦 {$quantidadeTotal} unidade(s)</td>
                        </tr>
                        " . (empty($itensDetalhados) && (float)$precoUnitario > 0 ? "
                        <tr>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 13.5px;'><b>Valor Unitário</b></td>
                            <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 13.5px;'>{$unitarioTexto}</td>
                        </tr>" : "") . "
                        {$validadeHtml}
                        <tr style='background: #ecfdf5;'>
                            <td style='padding: 12px 14px; color: #065f46; font-size: 15px; font-weight: 700;'>Valor Total da Compra</td>
                            <td style='padding: 12px 14px; color: #047857; font-size: 18px; font-weight: 800;'>{$totalTexto}</td>
                        </tr>
                    </table>
                </div>

                {$tabelaItensHtml}

                <div style='margin-top: 24px; padding: 14px; background: #f0fdf4; border: 1px dashed #86efac; border-radius: 8px; text-align: center;'>
                    <span style='font-size: 13px; color: #166534;'>
                        ✨ Seus produtos foram devidamente expedidos e registrados em nosso controle de estoque.
                    </span>
                </div>
            </div>

            <!-- Rodapé -->
            <div style='background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px; text-align: center; color: #94a3b8; font-size: 12px;'>
                <p style='margin: 0 0 4px 0; color: #64748b; font-weight: 600;'>MVM - Sistema de Controle de Estoque & Vendas</p>
                <p style='margin: 0;'>Este é um email automático de confirmação de compra. Por favor, não responda diretamente a esta mensagem.</p>
            </div>
        </div>
    </body>
    </html>";

    // 4. Versão em Texto Puro (Fallback)
    $corpoTexto = "Olá, {$nomeCliente}!\n\n"
        . "Sua compra na MVM foi realizada com sucesso!\n\n"
        . "RESUMO DA TRANSAÇÃO:\n"
        . "--------------------------------------\n"
        . "Data e Horário: {$dataHora}\n"
        . "Identificação: {$nomeProduto}\n"
        . "Quantidade Total: {$quantidadeTotal} unidade(s)\n"
        . (!empty($itensTexto) ? "\nITENS:\n{$itensTexto}\n" : "")
        . (!empty($validadeTexto) ? "{$validadeTexto}" : "")
        . "VALOR TOTAL: {$totalTexto}\n"
        . "--------------------------------------\n\n"
        . "Obrigado por comprar na MVM!\n"
        . "MVM - Central de Estoque";

    // 5. Envio com PHPMailer
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

        $mail->setFrom(MVM_SMTP_USUARIO, 'MVM Central de Estoque');
        $mail->addAddress($emailCliente, $nomeCliente);

        $mail->isHTML(true);
        $mail->Subject = 'MVM - Compra realizada com sucesso! (' . $totalTexto . ')';
        $mail->Body    = $corpoHtml;
        $mail->AltBody = $corpoTexto;

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}

/**
 * Envia ao usuário o código e o link de recuperação de senha ("Esqueci Minha Senha").
 *
 * @param string $emailDestino E-mail do usuário
 * @param string $nomeUsuario  Nome do usuário
 * @param string $codigo       Código numérico de 6 dígitos
 * @param string $linkDireto   URL direta para redefinição da senha
 * @return true|string         true em caso de sucesso ou mensagem de erro
 */
function enviarEmailRecuperacaoSenha($emailDestino, $nomeUsuario, $codigo, $linkDireto) {
    if (!defined('MVM_SMTP_HOST')) {
        $arquivo = __DIR__ . '/email_credenciais.php';
        if (!file_exists($arquivo)) {
            return "Arquivo config/email_credenciais.php não encontrado.";
        }
        require_once $arquivo;
    }

    if (defined('MVM_SMTP_USUARIO') && MVM_SMTP_USUARIO === 'seu_email@gmail.com') {
        return "Configure o email e a senha em config/email_credenciais.php.";
    }

    if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
        return "O e-mail cadastrado do usuário é inválido.";
    }

    $nomeHtml = htmlspecialchars($nomeUsuario);
    $codigoHtml = htmlspecialchars($codigo);
    $linkHtml = htmlspecialchars($linkDireto);
    $dataHora = date('d/m/Y \à\s H:i');

    $corpoHtml = "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <title>MVM - Recuperação de Senha</title>
    </head>
    <body style='margin: 0; padding: 24px 10px; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
        <div style='max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
            <div style='background: linear-gradient(135deg, #00704A 0%, #005035 100%); padding: 26px 24px; text-align: center; color: #ffffff;'>
                <h1 style='margin: 0 0 6px 0; font-size: 21px; font-weight: 800;'>MVM ESTOQUE & LOGÍSTICA</h1>
                <div style='display: inline-block; background: rgba(255,255,255,0.2); padding: 4px 14px; border-radius: 20px; font-size: 11.5px; font-weight: 700; text-transform: uppercase;'>
                    🔐 Recuperação de Senha de Acesso
                </div>
            </div>

            <div style='padding: 26px 24px;'>
                <p style='margin: 0 0 12px 0; font-size: 15px; color: #1e293b;'>Olá, <b>{$nomeHtml}</b>!</p>
                <p style='margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.5;'>
                    Recebemos uma solicitação para redefinir a senha da sua conta no <b>Sistema MVM</b> em {$dataHora}. Utilize o código de verificação de 6 dígitos abaixo (válido por <b>30 minutos</b>):
                </p>

                <div style='background: #f8fafc; border: 2px dashed #00704A; border-radius: 10px; padding: 18px; text-align: center; margin-bottom: 22px;'>
                    <div style='font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 1px; margin-bottom: 6px;'>Código de Segurança</div>
                    <div style='font-size: 32px; font-weight: 900; letter-spacing: 6px; color: #00704A; font-family: monospace;'>{$codigoHtml}</div>
                </div>

                <div style='text-align: center; margin-bottom: 22px;'>
                    <a href='{$linkHtml}' style='display: inline-block; background: #635bff; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 14px; padding: 12px 24px; border-radius: 8px;'>
                        🔑 Redefinir Minha Senha Agora
                    </a>
                </div>

                <p style='margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; text-align: center;'>
                    Se você não solicitou a alteração de senha, ignore este e-mail com segurança — sua senha atual permanecerá inalterada.
                </p>
            </div>

            <div style='background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px; text-align: center; color: #94a3b8; font-size: 11.5px;'>
                MVM Gestão de Estoque • Segurança & Controle de Acesso
            </div>
        </div>
    </body>
    </html>";

    $corpoTexto = "Olá, {$nomeUsuario}!\n\n"
        . "Seu código de recuperação de senha do Sistema MVM é: {$codigo}\n"
        . "(Válido por 30 minutos)\n\n"
        . "Ou acesse diretamente o link para criar uma nova senha:\n{$linkDireto}\n\n"
        . "Se você não fez esta solicitação, ignore esta mensagem.";

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

        $mail->setFrom(MVM_SMTP_USUARIO, 'MVM Central de Estoque');
        $mail->addAddress($emailDestino, $nomeUsuario);

        $mail->isHTML(true);
        $mail->Subject = "🔐 MVM Estoque - Código de Recuperação de Senha: {$codigo}";
        $mail->Body    = $corpoHtml;
        $mail->AltBody = $corpoTexto;

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}
