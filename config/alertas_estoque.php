<?php
/**
 * Módulo de Alertas Automáticos de Estoque Baixo e Validade
 * Sistema MVM - Controle de Estoque
 */

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/conexao.php';

if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/Sao_Paulo');
}

/**
 * Busca os produtos em estado crítico (estoque baixo ou validade próxima)
 *
 * @param mysqli $conexao
 * @param int $idUsuario
 * @return array
 */
function obterRelatorioAlertas($conexao, $idUsuario) {
    $alertasEstoque = [];
    $alertasValidade = [];

    // 1. Produtos com estoque crítico (abaixo ou igual ao estoque mínimo)
    $stmtEstoque = mysqli_prepare($conexao, "
        SELECT idProduto, nomeProduto, categoria, preco, quantidade, estoqueMinimo, localizacao, nomeFornecedor
        FROM produto
        WHERE idUsuario = ? AND quantidade <= estoqueMinimo
        ORDER BY (quantidade - estoqueMinimo) ASC, quantidade ASC
    ");
    if ($stmtEstoque) {
        mysqli_stmt_bind_param($stmtEstoque, "i", $idUsuario);
        mysqli_stmt_execute($stmtEstoque);
        $res = mysqli_stmt_get_result($stmtEstoque);
        if ($res) {
            $alertasEstoque = mysqli_fetch_all($res, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($stmtEstoque);
    }

    // 2. Produtos vencidos ou que vencem nos próximos 30 dias
    $stmtValidade = mysqli_prepare($conexao, "
        SELECT idProduto, nomeProduto, categoria, lote, dataValidade, quantidade, localizacao,
               DATEDIFF(dataValidade, CURDATE()) AS diasRestantes
        FROM produto
        WHERE idUsuario = ? 
          AND dataValidade IS NOT NULL 
          AND dataValidade != '0000-00-00'
          AND dataValidade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY dataValidade ASC
    ");
    if ($stmtValidade) {
        mysqli_stmt_bind_param($stmtValidade, "i", $idUsuario);
        mysqli_stmt_execute($stmtValidade);
        $res = mysqli_stmt_get_result($stmtValidade);
        if ($res) {
            $alertasValidade = mysqli_fetch_all($res, MYSQLI_ASSOC);
        }
        mysqli_stmt_close($stmtValidade);
    }

    return [
        'estoqueBaixo' => $alertasEstoque,
        'validade'     => $alertasValidade,
        'totalAlertas' => count($alertasEstoque) + count($alertasValidade)
    ];
}

/**
 * Envia o e-mail diário/semanal de alerta para o gestor
 *
 * @param int         $idUsuario
 * @param string|null $emailDestinatario Se nulo, busca o e-mail do gestor no banco
 * @param bool        $forcarEnvio       Se verdadeiro, envia mesmo sem pendências
 * @return array                         ['sucesso' => bool, 'mensagem' => string, 'totalEstoque' => int, 'totalValidade' => int]
 */
function enviarRelatorioAlertasEstoque($idUsuario = 1, $emailDestinatario = null, $forcarEnvio = false) {
    $conexao = conectar();
    if (is_string($conexao)) {
        return ['sucesso' => false, 'mensagem' => "Erro de conexão: " . $conexao];
    }

    // Identifica e-mail e nome do gestor
    $nomeGestor = "Gestor MVM";
    if (empty($emailDestinatario)) {
        $stmtUser = mysqli_prepare($conexao, "SELECT nomeUsuario, emailUsuario FROM usuario WHERE idUsuario = ? LIMIT 1");
        if ($stmtUser) {
            mysqli_stmt_bind_param($stmtUser, "i", $idUsuario);
            mysqli_stmt_execute($stmtUser);
            $resUser = mysqli_stmt_get_result($stmtUser);
            if ($row = mysqli_fetch_assoc($resUser)) {
                $emailDestinatario = $row['emailUsuario'];
                $nomeGestor = $row['nomeUsuario'];
            }
            mysqli_stmt_close($stmtUser);
        }
    }

    if (empty($emailDestinatario) || !filter_var($emailDestinatario, FILTER_VALIDATE_EMAIL)) {
        mysqli_close($conexao);
        return ['sucesso' => false, 'mensagem' => "Nenhum e-mail de gestor válido configurado."];
    }

    // Obtém dados de estoque e validade
    $dados = obterRelatorioAlertas($conexao, $idUsuario);
    $estoqueBaixo = $dados['estoqueBaixo'];
    $validade = $dados['validade'];
    $totalAlertas = $dados['totalAlertas'];

    mysqli_close($conexao);

    // Se não há alertas e não foi forçado, não precisa disparar
    if ($totalAlertas === 0 && !$forcarEnvio) {
        return [
            'sucesso'       => true,
            'mensagem'      => 'Estoque em perfeito estado! Nenhum item com estoque baixo ou validade crítica.',
            'totalEstoque'  => 0,
            'totalValidade' => 0
        ];
    }

    // Carrega credenciais SMTP
    if (!defined('MVM_SMTP_HOST')) {
        $arquivoCred = __DIR__ . '/email_credenciais.php';
        if (file_exists($arquivoCred)) {
            require_once $arquivoCred;
        } else {
            return ['sucesso' => false, 'mensagem' => 'Arquivo config/email_credenciais.php não encontrado.'];
        }
    }

    $dataHora = date('d/m/Y \à\s H:i');

    // 1. Tabela de Estoque Baixo
    $linhasEstoque = '';
    if (!empty($estoqueBaixo)) {
        foreach ($estoqueBaixo as $item) {
            $nome = htmlspecialchars($item['nomeProduto']);
            $cat = htmlspecialchars($item['categoria'] ?? 'Geral');
            $forn = htmlspecialchars($item['nomeFornecedor'] ?? 'Não informado');
            $qtd = (int)$item['quantidade'];
            $min = (int)$item['estoqueMinimo'];

            $badge = ($qtd <= 0) 
                ? "<span style='background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:12px; font-weight:700; font-size:11px;'>ESGOTADO (0)</span>"
                : "<span style='background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:12px; font-weight:700; font-size:11px;'>CRÍTICO ({$qtd}/{$min})</span>";

            $linhasEstoque .= "
                <tr>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; font-size:13px; color:#1e293b;'><b>{$nome}</b><br><small style='color:#64748b;'>{$cat}</small></td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; font-size:12px; color:#475569;'>{$forn}</td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:center;'>{$badge}</td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:right; font-weight:700; font-size:13px; color:#0f172a;'>{$qtd} un</td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:right; font-size:13px; color:#64748b;'>{$min} un</td>
                </tr>";
        }
    } else {
        $linhasEstoque = "<tr><td colspan='5' style='padding:16px; text-align:center; color:#059669; font-weight:600;'>✓ Todos os produtos estão com níveis saudáveis de estoque.</td></tr>";
    }

    // 2. Tabela de Validade
    $linhasValidade = '';
    if (!empty($validade)) {
        foreach ($validade as $item) {
            $nome = htmlspecialchars($item['nomeProduto']);
            $lote = htmlspecialchars($item['lote'] ?: 'S/L');
            $dataVal = date('d/m/Y', strtotime($item['dataValidade']));
            $dias = (int)$item['diasRestantes'];
            $qtd = (int)$item['quantidade'];

            if ($dias < 0) {
                $statusBadge = "<span style='background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:12px; font-weight:700; font-size:11px;'>VENCIDO há " . abs($dias) . " dia(s)</span>";
            } elseif ($dias === 0) {
                $statusBadge = "<span style='background:#fef2f2; color:#dc2626; padding:2px 8px; border-radius:12px; font-weight:700; font-size:11px;'>VENCE HOJE!</span>";
            } else {
                $statusBadge = "<span style='background:#fef3c7; color:#b45309; padding:2px 8px; border-radius:12px; font-weight:700; font-size:11px;'>Vence em {$dias} dia(s)</span>";
            }

            $linhasValidade .= "
                <tr>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; font-size:13px; color:#1e293b;'><b>{$nome}</b></td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:center; font-size:12px; color:#475569;'>{$lote}</td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:center; font-weight:600; font-size:13px; color:#0f172a;'>{$dataVal}</td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:center;'>{$statusBadge}</td>
                    <td style='padding:8px 10px; border-bottom:1px solid #e2e8f0; text-align:right; font-weight:700; font-size:13px; color:#0f172a;'>{$qtd} un</td>
                </tr>";
        }
    } else {
        $linhasValidade = "<tr><td colspan='5' style='padding:16px; text-align:center; color:#059669; font-weight:600;'>✓ Nenhum produto vencendo nos próximos 30 dias.</td></tr>";
    }

    $qtdEstoqueBaixo = count($estoqueBaixo);
    $qtdValidade = count($validade);

    // Monta HTML Executivo
    $corpoHtml = "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0; padding:24px 10px; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
        <div style='max-width:680px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.06); border:1px solid #cbd5e1;'>
            
            <!-- Topo -->
            <div style='background:linear-gradient(135deg, #00704A 0%, #004b32 100%); padding:28px 24px; color:#ffffff;'>
                <div style='display:flex; justify-content:space-between; align-items:center;'>
                    <div>
                        <h1 style='margin:0 0 4px 0; font-size:22px; font-weight:800; letter-spacing:-0.5px;'>MVM ESTOQUE</h1>
                        <p style='margin:0; font-size:13px; opacity:0.9;'>Relatório Gerencial de Alertas Automáticos</p>
                    </div>
                </div>
            </div>

            <!-- Dados e Métricas -->
            <div style='padding:24px;'>
                <p style='margin:0 0 16px 0; font-size:15px; color:#334155;'>
                    Olá, <b>" . htmlspecialchars($nomeGestor) . "</b>! Este é o balanço automático de itens que demandam atenção na sua central de estoque emitido em <b>{$dataHora}</b>:
                </p>

                <!-- Cards de Resumo -->
                <div style='display:table; width:100%; margin-bottom:24px;'>
                    <div style='display:table-cell; width:50%; padding-right:8px;'>
                        <div style='background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px; text-align:center;'>
                            <div style='font-size:12px; font-weight:700; color:#991b1b; text-transform:uppercase;'>Estoque Baixo / Esgotado</div>
                            <div style='font-size:26px; font-weight:900; color:#dc2626; margin-top:4px;'>{$qtdEstoqueBaixo}</div>
                            <div style='font-size:11px; color:#7f1d1d;'>Itens atingiram estoque mínimo</div>
                        </div>
                    </div>
                    <div style='display:table-cell; width:50%; padding-left:8px;'>
                        <div style='background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:14px; text-align:center;'>
                            <div style='font-size:12px; font-weight:700; color:#92400e; text-transform:uppercase;'>Validade Crítica (30 dias)</div>
                            <div style='font-size:26px; font-weight:900; color:#d97706; margin-top:4px;'>{$qtdValidade}</div>
                            <div style='font-size:11px; color:#78350f;'>Itens vencidos ou a vencer</div>
                        </div>
                    </div>
                </div>

                <!-- Seção 1: Estoque Baixo -->
                <div style='margin-bottom:28px;'>
                    <div style='display:flex; align-items:center; margin-bottom:10px;'>
                        <h2 style='margin:0; font-size:16px; color:#1e293b;'>🚨 Produtos com Estoque Crítico ({$qtdEstoqueBaixo})</h2>
                    </div>
                    <table style='width:100%; border-collapse:collapse; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;'>
                        <thead>
                            <tr style='background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left;'>
                                <th style='padding:8px 10px; font-size:12px; color:#475569;'>Produto</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569;'>Fornecedor</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569; text-align:center;'>Status</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569; text-align:right;'>Atual</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569; text-align:right;'>Mínimo</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$linhasEstoque}
                        </tbody>
                    </table>
                </div>

                <!-- Seção 2: Validade Próxima -->
                <div style='margin-bottom:24px;'>
                    <div style='display:flex; align-items:center; margin-bottom:10px;'>
                        <h2 style='margin:0; font-size:16px; color:#1e293b;'>⏳ Produtos Próximos do Vencimento ({$qtdValidade})</h2>
                    </div>
                    <table style='width:100%; border-collapse:collapse; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;'>
                        <thead>
                            <tr style='background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left;'>
                                <th style='padding:8px 10px; font-size:12px; color:#475569;'>Produto</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569; text-align:center;'>Lote</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569; text-align:center;'>Data Validade</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569; text-align:center;'>Condição</th>
                                <th style='padding:8px 10px; font-size:12px; color:#475569; text-align:right;'>Qtd em Estoque</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$linhasValidade}
                        </tbody>
                    </table>
                </div>

                <!-- Dica / Call to Action -->
                <div style='background:#f0fdf4; border:1px dashed #86efac; border-radius:8px; padding:14px; text-align:center;'>
                    <p style='margin:0 0 6px 0; font-size:13px; color:#166534; font-weight:600;'>
                        💡 Sugestão Operacional:
                    </p>
                    <p style='margin:0; font-size:12.5px; color:#15803d;'>
                        Programe novas ordens de compra junto aos fornecedores e crie promoções ou ações de saída para produtos com validade próxima para evitar perdas financeiras.
                    </p>
                </div>
            </div>

            <!-- Rodapé -->
            <div style='background:#f8fafc; border-top:1px solid #e2e8f0; padding:16px 24px; text-align:center; color:#94a3b8; font-size:12px;'>
                <p style='margin:0 0 4px 0; color:#64748b; font-weight:600;'>MVM Estoque - Alerta Automático Diário/Semanal</p>
                <p style='margin:0;'>Emitido automaticamente pelo servidor local para " . htmlspecialchars($emailDestinatario) . ".</p>
            </div>
        </div>
    </body>
    </html>";

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
        $mail->addAddress($emailDestinatario, $nomeGestor);

        $mail->isHTML(true);
        $mail->Subject = "⚠️ MVM Alerta: {$qtdEstoqueBaixo} produto(s) em estoque crítico e {$qtdValidade} a vencer";
        $mail->Body    = $corpoHtml;
        $mail->AltBody = "MVM Estoque - Alertas Automáticos ({$dataHora})\n\n"
                       . "Estoque Baixo: {$qtdEstoqueBaixo} itens\n"
                       . "Validade Próxima: {$qtdValidade} itens\n\n"
                       . "Acesse o painel MVM para gerenciar suas compras e saídas.";

        $mail->send();

        // Registra a data do último envio para evitar envios repetitivos automáticos no mesmo dia
        $_SESSION['ultimo_alerta_enviado_data'] = date('Y-m-d');

        return [
            'sucesso'       => true,
            'mensagem'      => "Relatório de alertas enviado com sucesso para {$emailDestinatario}!",
            'totalEstoque'  => $qtdEstoqueBaixo,
            'totalValidade' => $qtdValidade
        ];
    } catch (\Throwable $e) {
        return [
            'sucesso'  => false,
            'mensagem' => "Falha no envio de e-mail: " . ($mail->ErrorInfo ?: $e->getMessage())
        ];
    }
}
