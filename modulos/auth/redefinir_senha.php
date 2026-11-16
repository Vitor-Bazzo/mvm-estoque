<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../../config/conexao.php";

$titulopagina = "MVM - Redefinir Senha";
$mensagemErro = "";

$identificadorUrl = isset($_GET['identificador']) ? trim($_GET['identificador']) : '';
$codigoUrl = isset($_GET['codigo']) ? preg_replace('/\D/', '', trim($_GET['codigo'])) : '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCSRF()) {
        $mensagemErro = "Sessão expirada ou token inválido. Tente novamente.";
    } elseif (isset($_POST['identificador'], $_POST['codigo'], $_POST['nova_senha'], $_POST['confirmar_senha'])) {
        $identificador = trim($_POST['identificador']);
        $codigo = preg_replace('/\D/', '', trim($_POST['codigo']));
        $novaSenha = $_POST['nova_senha'];
        $confirmarSenha = $_POST['confirmar_senha'];

        $identificadorUrl = $identificador;
        $codigoUrl = $codigo;

        if (empty($identificador) || empty($codigo) || empty($novaSenha)) {
            $mensagemErro = "Preencha todos os campos obrigatórios.";
        } elseif (strlen($codigo) !== 6) {
            $mensagemErro = "O código de verificação deve conter exatamente 6 dígitos.";
        } elseif (strlen($novaSenha) < 6) {
            $mensagemErro = "A nova senha deve ter no mínimo 6 caracteres.";
        } elseif ($novaSenha !== $confirmarSenha) {
            $mensagemErro = "As senhas digitadas não coincidem.";
        } else {
            $conexao = conectar();
            if (is_string($conexao)) {
                $mensagemErro = $conexao;
            } else {
                $sql = "SELECT idUsuario, nomeUsuario, tokenRecuperacao, expiraRecuperacao, (expiraRecuperacao >= NOW()) AS dentroDoPrazo FROM usuario WHERE emailUsuario = ? OR nomeUsuario = ? LIMIT 1";
                $stmt = mysqli_prepare($conexao, $sql);
                mysqli_stmt_bind_param($stmt, "ss", $identificador, $identificador);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $user = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);

                $codigoHash = hash('sha256', $codigo);

                if (!$user || empty($user['tokenRecuperacao'])) {
                    $mensagemErro = "Nenhuma solicitação de redefinição ativa foi encontrada para este usuário.";
                    mysqli_close($conexao);
                } elseif (!hash_equals($user['tokenRecuperacao'], $codigoHash)) {
                    $mensagemErro = "Código de verificação incorreto. Confira o código de 6 dígitos enviado.";
                    mysqli_close($conexao);
                } elseif ((int)$user['dentroDoPrazo'] !== 1) {
                    $mensagemErro = "Este código de recuperação expirou (validade de 30 minutos). Solicite um novo código.";
                    mysqli_close($conexao);
                } else {
                    $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $stmtUpd = mysqli_prepare($conexao, "UPDATE usuario SET senhaUsuario = ?, tokenRecuperacao = NULL, expiraRecuperacao = NULL WHERE idUsuario = ?");
                    mysqli_stmt_bind_param($stmtUpd, "si", $novoHash, $user['idUsuario']);

                    if (mysqli_stmt_execute($stmtUpd)) {
                        mysqli_stmt_close($stmtUpd);
                        mysqli_close($conexao);

                        unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueado_ate'], $_SESSION['flash_recuperacao']);
                        $_SESSION['flash_login'] = [
                            'tipo' => 'sucesso',
                            'texto' => "Senha redefinida com sucesso para '" . $user['nomeUsuario'] . "'! Faça login com sua nova senha."
                        ];

                        header("Location: login.php");
                        exit;
                    } else {
                        $mensagemErro = "Erro ao atualizar a senha no banco de dados. Tente novamente.";
                        mysqli_stmt_close($stmtUpd);
                        mysqli_close($conexao);
                    }
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
            <h1>Criar Nova Senha</h1>
            <p>Digite o código de 6 dígitos recebido e defina sua nova senha de acesso</p>
        </div>

        <?php if (!empty($_SESSION['flash_recuperacao'])): ?>
            <div class="alert-box <?= $_SESSION['flash_recuperacao']['tipo'] === 'sucesso' ? 'success' : 'error' ?>" style="margin-bottom: 20px;">
                <span><?= $_SESSION['flash_recuperacao']['tipo'] === 'sucesso' ? '✅' : '⚠️' ?></span>
                <p><?= $_SESSION['flash_recuperacao']['texto'] ?></p>
            </div>
            <?php unset($_SESSION['flash_recuperacao']); ?>
        <?php endif; ?>

        <?php if (!empty($mensagemErro)): ?>
            <div class="alert-box error" style="margin-bottom: 20px;">
                <span>⚠️</span>
                <p><?= htmlspecialchars($mensagemErro) ?></p>
            </div>
        <?php endif; ?>

        <form action="redefinir_senha.php" method="POST" onsubmit="return validarConfirmacao(event)">
            <?= campoCSRF() ?>
            <div class="form-group">
                <label for="identificador">E-mail ou Nome de Usuário *</label>
                <input type="text" id="identificador" name="identificador" value="<?= htmlspecialchars($identificadorUrl) ?>" placeholder="Digite seu e-mail ou usuário..." required>
            </div>

            <div class="form-group">
                <label for="codigo">Código de Segurança (6 dígitos) *</label>
                <input type="text" id="codigo" name="codigo" maxlength="6" value="<?= htmlspecialchars($codigoUrl) ?>" placeholder="000000" required style="font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 800; letter-spacing: 5px; text-align: center;" autofocus>
            </div>

            <div class="form-group">
                <label for="nova_senha">Nova Senha de Acesso *</label>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo de 6 caracteres" required>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirmar Nova Senha *</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Digite a nova senha novamente" required>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; margin-top: 6px;">
                <span>🔑</span> Salvar Nova Senha
            </button>
        </form>

        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; color: var(--text-muted);">
            <a href="esqueci_senha.php" style="font-weight: 600;">Reenviar código</a>
            <a href="login.php" style="font-weight: 700;">Voltar ao Login</a>
        </div>
    </div>
</div>

<script>
    function validarConfirmacao(e) {
        const s1 = document.getElementById('nova_senha').value;
        const s2 = document.getElementById('confirmar_senha').value;
        if (s1.length < 6) {
            alert('A nova senha deve ter pelo menos 6 caracteres.');
            e.preventDefault();
            return false;
        }
        if (s1 !== s2) {
            alert('As senhas digitadas não coincidem.');
            e.preventDefault();
            return false;
        }
        return true;
    }
</script>

<?php require_once __DIR__ . "/../../templates/footer.php"; ?>
