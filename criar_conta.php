<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/config/conexao.php";

$titulopagina = "MVM - Criar Conta";
$mensagemerro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validarTokenCSRF()) {
        $mensagemerro = "Sessão expirada ou requisição inválida. Por favor, tente novamente.";
    } elseif (isset($_POST['usuario'], $_POST['email'], $_POST['senha'])) {
        $usuario = trim($_POST['usuario']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        if (strlen($senha) < 6) {
            $mensagemerro = "A senha deve conter no mínimo 6 caracteres.";
        } elseif (!empty($usuario) && !empty($email) && !empty($senha)) {
            $conexao = conectar(); 
            if (is_string($conexao)) {
                $mensagemerro = $conexao;
            } else {
                $sqlCheck = "SELECT idUsuario FROM usuario WHERE nomeUsuario = ? OR emailUsuario = ?";
                $stmtCheck = mysqli_prepare($conexao, $sqlCheck);
                mysqli_stmt_bind_param($stmtCheck, "ss", $usuario, $email);
                mysqli_stmt_execute($stmtCheck);
                $resultadoCheck = mysqli_stmt_get_result($stmtCheck);

                if ($resultadoCheck && mysqli_num_rows($resultadoCheck) > 0) {
                    $mensagemerro = "Este nome de usuário ou e-mail já está cadastrado no sistema.";
                    mysqli_close($conexao);
                } else {
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $sqlInsert = "INSERT INTO usuario (nomeUsuario, emailUsuario, senhaUsuario) VALUES (?, ?, ?)";
                    $stmtInsert = mysqli_prepare($conexao, $sqlInsert);
                    mysqli_stmt_bind_param($stmtInsert, "sss", $usuario, $email, $senhaHash);
                    
                    if (mysqli_stmt_execute($stmtInsert)) {
                        $novoId = mysqli_insert_id($conexao);

                        // Sincronização segura com outros bancos locais do XAMPP (mvm e bdmvm)
                        $uSafe = mysqli_real_escape_string($conexao, $usuario);
                        $eSafe = mysqli_real_escape_string($conexao, $email);
                        $sSafe = mysqli_real_escape_string($conexao, $senhaHash);
                        @mysqli_query($conexao, "INSERT IGNORE INTO mvm.usuario (nomeUsuario, emailUsuario, senhaUsuario) VALUES ('{$uSafe}', '{$eSafe}', '{$sSafe}')");
                        @mysqli_query($conexao, "INSERT IGNORE INTO bdmvm.usuario (nomeUsuario, emailUsuario, senhaUsuario) VALUES ('{$uSafe}', '{$eSafe}', '{$sSafe}')");

                        mysqli_close($conexao);

                        // Conecta diretamente no perfil recém-criado (evita ficar preso na conta anterior)
                        $_SESSION = [];
                        session_regenerate_id(true);

                        $_SESSION['usuarioLogado'] = $usuario;
                        $_SESSION['emailUsuario'] = $email;
                        $_SESSION['idUsuario'] = $novoId;
                        $_SESSION['roleUsuario'] = (strtolower($usuario) === 'admin' || stripos($email, 'admin') !== false) ? 'Administrador' : 'Operador';
                        $_SESSION['flash_dashboard'] = [
                            'tipo' => 'sucesso',
                            'texto' => "Conta criada com sucesso! Você foi conectado ao seu novo perfil como <strong>" . htmlspecialchars($usuario) . "</strong>."
                        ];

                        header("Location: index.php");
                        exit;
                    } else {
                        $mensagemerro = "Erro ao tentar cadastrar o usuário no banco de dados.";
                        mysqli_close($conexao);
                    }
                }
            }
        } else {
            $mensagemerro = "Por favor, preencha todos os campos obrigatórios.";
        }
    }
}

require_once "templates/header.php"; 
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <a href="mascote.php" title="Conheça o Mascote Oficial MVM" style="display: inline-block; text-decoration: none;">
                <img src="img/mascote_mvm.svg?v=3" alt="Mascote MVM Estoque" style="width: 84px; height: 84px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35); transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <h1>Criar Nova Conta</h1>
            <p>Cadastre um novo operador no sistema MVM</p>
        </div>

        <?php if (!empty($mensagemerro)): ?>
            <div class="alert-box error" style="margin-bottom: 20px;">
                <span>⚠️</span>
                <p><?= htmlspecialchars($mensagemerro) ?></p>
            </div>
        <?php endif; ?>

        <form id="signupForm" action="criar_conta.php" method="POST" onsubmit="return validarSenhas(event)">
            <?= campoCSRF() ?>
            <div class="form-group">
                <label for="username">Nome de Usuário *</label>
                <input type="text" id="username" name="usuario" required placeholder="Escolha seu login de acesso">
            </div>

            <div class="form-group">
                <label for="email">E-mail Corporativo *</label>
                <input type="email" id="email" name="email" required placeholder="seu.email@empresa.com">
            </div>

            <div class="form-group">
                <label for="password">Senha de Acesso *</label>
                <input type="password" id="password" name="senha" required placeholder="Mínimo de 6 caracteres">
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirmar Senha *</label>
                <input type="password" id="confirmPassword" required placeholder="Digite a senha novamente">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; margin-top: 8px;">
                Finalizar Cadastro
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 12.5px; color: var(--text-muted);">
            Já possui uma conta ativa? <a href="login.php" style="font-weight: 700;">Faça Login</a>
        </div>
    </div>
</div>

<script>
    function validarSenhas(event) {
        const senha = document.getElementById('password').value;
        const confirmaSenha = document.getElementById('confirmPassword').value;

        if (senha !== confirmaSenha) {
            alert("Aviso: As senhas digitadas não coincidem. Verifique e tente novamente.");
            event.preventDefault();
            return false;
        }
        return true;
    }
</script>

<?php require_once "templates/footer.php"; ?>