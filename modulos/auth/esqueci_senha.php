<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../../config/conexao.php";
require_once __DIR__ . "/../../config/email.php";

$titulopagina = "MVM - Recuperar Senha";
$mensagemErro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $mensagemErro = "Sessão expirada ou requisição inválida. Recarregue a página e tente novamente.";
    } elseif (isset($_POST['identificador'])) {
        $identificador = trim($_POST['identificador']);

        if (empty($identificador)) {
            $mensagemErro = "Por favor, informe seu e-mail cadastrado ou nome de usuário.";
        } else {
            $conexao = conectar();
            if (is_string($conexao)) {
                $mensagemErro = $conexao;
            } else {
                $sql = "SELECT idUsuario, nomeUsuario, emailUsuario FROM usuario WHERE emailUsuario = ? OR nomeUsuario = ? LIMIT 1";
                $stmt = mysqli_prepare($conexao, $sql);
                mysqli_stmt_bind_param($stmt, "ss", $identificador, $identificador);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $usuarioRow = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);

                if (!$usuarioRow) {
                    $mensagemErro = "Nenhuma conta foi encontrada com este e-mail ou nome de usuário.";
                    mysqli_close($conexao);
                } else {
                    $codigo = sprintf('%06d', random_int(100000, 999999));
                    $tokenHash = hash('sha256', $codigo);

                    $stmtUpd = mysqli_prepare($conexao, "UPDATE usuario SET tokenRecuperacao = ?, expiraRecuperacao = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE idUsuario = ?");
                    mysqli_stmt_bind_param($stmtUpd, "si", $tokenHash, $usuarioRow['idUsuario']);
                    mysqli_stmt_execute($stmtUpd);
                    mysqli_stmt_close($stmtUpd);
                    mysqli_close($conexao);

                    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                    $hostAtual = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
                    $linkDireto = $protocolo . '://' . $hostAtual . url('modulos/auth/redefinir_senha.php') . '?identificador=' . urlencode($usuarioRow['emailUsuario']) . '&codigo=' . urlencode($codigo);

                    $envio = enviarEmailRecuperacaoSenha(
                        $usuarioRow['emailUsuario'],
                        $usuarioRow['nomeUsuario'],
                        $codigo,
                        $linkDireto
                    );

                    $hostLimpo = explode(':', $hostAtual)[0];
                    $isAmbienteLocal = in_array($hostLimpo, ['localhost', '127.0.0.1']);
                    $isEmailFicticio = (stripos($usuarioRow['emailUsuario'], '@mvm.com') !== false || stripos($usuarioRow['emailUsuario'], '@email.com') !== false);

                    if ($envio === true && !$isEmailFicticio) {
                        $_SESSION['flash_recuperacao'] = [
                            'tipo' => 'sucesso',
                            'texto' => "Enviamos um código de 6 dígitos para <strong>" . htmlspecialchars($usuarioRow['emailUsuario']) . "</strong>. Digite-o abaixo para criar sua nova senha."
                        ];
                    } elseif ($isAmbienteLocal) {
                        // Em ambiente local (localhost) ou e-mail fictício de demonstração (ex: admin@mvm.com), exibe o código para facilitar testes e banca de TCC
                        $detalheEnvio = ($envio === true)
                            ? "Código enviado e também exibido abaixo por ser um e-mail de demonstração (<code>" . htmlspecialchars($usuarioRow['emailUsuario']) . "</code>):"
                            : "Ambiente Local (XAMPP): Código gerado para <strong>" . htmlspecialchars($usuarioRow['nomeUsuario']) . "</strong>:";
                        $_SESSION['flash_recuperacao'] = [
                            'tipo' => 'sucesso',
                            'texto' => "{$detalheEnvio} Seu código de verificação é <strong style='font-family: monospace; font-size: 15px; background: rgba(16,185,129,0.2); padding: 2px 8px; border-radius: 4px;'>{$codigo}</strong>"
                        ];
                    } else {
                        $_SESSION['flash_recuperacao'] = [
                            'tipo' => 'erro',
                            'texto' => "Não foi possível enviar o e-mail de recuperação: " . htmlspecialchars((string)$envio)
                        ];
                    }

                    header("Location: redefinir_senha.php?identificador=" . urlencode($usuarioRow['emailUsuario']));
                    exit;
                }
            }
        }
    }
}

require_once __DIR__ . "/../../templates/header.php";
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <a href="<?= url('docs/mascote.php') ?>" title="Conheça o Mascote Oficial MVM" style="display: inline-block; text-decoration: none;">
                <img src="<?= url('img/mascote_mvm.svg?v=5') ?>" alt="Mascote MVM Estoque" style="width: 84px; height: 84px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35); transition: transform 0.3s ease; object-fit: cover;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <h1>Esqueci Minha Senha</h1>
            <p>Informe seu e-mail ou nome de usuário para receber o código de redefinição</p>
        </div>

        <?php if (!empty($mensagemErro)): ?>
            <div class="alert-box error" style="margin-bottom: 20px;">
                <span>⚠️</span>
                <p><?= htmlspecialchars($mensagemErro) ?></p>
            </div>
        <?php endif; ?>

        <form action="esqueci_senha.php" method="POST">
            <?= campoCSRF() ?>
            <div class="form-group">
                <label for="identificador">E-mail Cadastrado ou Usuário *</label>
                <input type="text" id="identificador" name="identificador" placeholder="Ex: seu.email@empresa.com ou admin" required autofocus>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; margin-top: 6px;">
                <span>📧</span> Enviar Código de Recuperação
            </button>
        </form>

        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; color: var(--text-muted);">
            <a href="login.php" style="font-weight: 700;">← Voltar para o Login</a>
            <a href="redefinir_senha.php" style="font-weight: 600;">Já tenho um código</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../../templates/footer.php"; ?>
