<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/config/conexao.php";

$titulopagina = "MVM - Acesso ao Sistema";
$mensagemerro = "";

// Controle de Rate Limiting contra Força Bruta
$tempoBloqueio = $_SESSION['login_bloqueado_ate'] ?? 0;
if ($tempoBloqueio > time()) {
    $minutosRestantes = ceil(($tempoBloqueio - time()) / 60);
    $mensagemerro = "Muitas tentativas incorretas. Acesso bloqueado por mais {$minutosRestantes} minuto(s).";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validarTokenCSRF()) {
        $mensagemerro = "Sessão expirada. Por favor, recarregue a página e tente novamente.";
    } elseif (isset($_POST['usuario'], $_POST['senha'], $_POST['acao'])){
        $usuario = trim($_POST['usuario']);
        $senha = trim($_POST['senha']);
        $acao = $_POST['acao'];
        
        if ($acao == "acessar") {
            if (!empty($usuario) && !empty($senha)){
                $conexao = conectar();
                if (is_string($conexao)) {
                    $mensagemerro = $conexao;
                } else {
                    $sql = "SELECT * FROM usuario WHERE nomeUsuario = ? OR emailUsuario = ?";
                    $stmt = mysqli_prepare($conexao, $sql);
                    mysqli_stmt_bind_param($stmt, "ss", $usuario, $usuario);
                    mysqli_stmt_execute($stmt);
                    $resultado = mysqli_stmt_get_result($stmt);
                    
                    if ($resultado && mysqli_num_rows($resultado) > 0) {
                        $entrar = mysqli_fetch_assoc($resultado);
                        $senhaValida = ($entrar['senhaUsuario'] === $senha) || 
                                       (password_get_info($entrar['senhaUsuario'])['algo'] !== 0 && password_verify($senha, $entrar['senhaUsuario']));

                        if ($senhaValida) {
                            // Se a senha estiver em texto puro, faz upgrade transparente para BCRYPT
                            if (password_get_info($entrar['senhaUsuario'])['algo'] === 0) {
                                $novoHash = password_hash($senha, PASSWORD_DEFAULT);
                                $stmtUpd = mysqli_prepare($conexao, "UPDATE usuario SET senhaUsuario = ? WHERE idUsuario = ?");
                                if ($stmtUpd) {
                                    mysqli_stmt_bind_param($stmtUpd, "si", $novoHash, $entrar['idUsuario']);
                                    mysqli_stmt_execute($stmtUpd);
                                }
                            }
                            mysqli_close($conexao);

                            // Limpa tentativas de bloqueio
                            unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueado_ate']);

                            session_regenerate_id(true);
                            $_SESSION['usuarioLogado'] = $entrar['nomeUsuario'];
                            $_SESSION['emailUsuario'] = $entrar['emailUsuario'] ?? '';
                            $_SESSION['idUsuario'] = $entrar['idUsuario'];
                            $_SESSION['roleUsuario'] = (strtolower($entrar['nomeUsuario']) === 'admin' || (isset($entrar['emailUsuario']) && stripos($entrar['emailUsuario'], 'admin') !== false)) ? 'Administrador' : 'Operador';
                            
                            header('Location: index.php');
                            exit;
                        } else {
                            $tentativas = ($_SESSION['login_tentativas'] ?? 0) + 1;
                            $_SESSION['login_tentativas'] = $tentativas;
                            if ($tentativas >= 5) {
                                $_SESSION['login_bloqueado_ate'] = time() + (15 * 60); // 15 minutos
                                $mensagemerro = "Limite de tentativas excedido. Acesso bloqueado por 15 minutos.";
                            } else {
                                $restantes = 5 - $tentativas;
                                $mensagemerro = "Usuário ou senha incorretos. ({$restantes} tentativa(s) restante(s)).";
                            }
                        } 
                    } else {
                        $tentativas = ($_SESSION['login_tentativas'] ?? 0) + 1;
                        $_SESSION['login_tentativas'] = $tentativas;
                        if ($tentativas >= 5) {
                            $_SESSION['login_bloqueado_ate'] = time() + (15 * 60);
                            $mensagemerro = "Limite de tentativas excedido. Acesso bloqueado por 15 minutos.";
                        } else {
                            $restantes = 5 - $tentativas;
                            $mensagemerro = "Usuário ou senha incorretos. ({$restantes} tentativa(s) restante(s)).";
                        }
                    }
                    if (isset($conexao) && is_object($conexao)) {
                        mysqli_close($conexao);
                    }
                }
            } else {
                $mensagemerro = "Preencha o nome do usuário e a senha.";
            }
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
            <h1>Acesso ao Sistema</h1>
            <p>Entre com suas credenciais para gerenciar o estoque</p>
        </div>

        <?php if (!empty($mensagemerro)): ?>
            <div class="alert-box error" style="margin-bottom: 20px;">
                <span>⚠️</span>
                <p><?= htmlspecialchars($mensagemerro) ?></p>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <?= campoCSRF() ?>
            <div class="form-group">
                <label for="usuario">Nome de Usuário</label>
                <input type="text" id="usuario" name="usuario" placeholder="Digite seu usuário..." required autofocus>
            </div>

            <div class="form-group">
                <label for="senha">Senha de Acesso</label>
                <input type="password" id="senha" name="senha" placeholder="••••••••" required>
            </div>


            <button type="submit" name="acao" value="acessar" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px;">
                Entrar no Sistema
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 12.5px; color: var(--text-muted);">
            Não tem uma conta de acesso? <a href="criar_conta.php" style="font-weight: 700;">Criar Conta</a>
        </div>
    </div>
</div>

<?php require_once "templates/footer.php"; ?>