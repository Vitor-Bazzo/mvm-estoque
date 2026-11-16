<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../../config/conexao.php";

$titulopagina = "MVM - Criar Conta";
$mensagemerro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validarTokenCSRF()) {
        $mensagemerro = "Sessão expirada ou requisição inválida. Por favor, tente novamente.";
    } elseif (isset($_POST['usuario'], $_POST['email'], $_POST['senha'])) {
        $usuario = trim($_POST['usuario']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        $planoEscolhido = in_array(strtoupper($_POST['plano'] ?? ''), ['ESSENCIAL', 'PADRAO', 'PREMIUM']) ? strtoupper($_POST['plano']) : 'ESSENCIAL';
        $aceitouTermos = isset($_POST['aceitou_termos']) && $_POST['aceitou_termos'] === '1';

        if (!$aceitouTermos) {
            $mensagemerro = "Você precisa ler e aceitar os Termos e Condições de Uso para criar sua conta.";
        } elseif (strlen($senha) < 6) {
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
                    mysqli_stmt_close($stmtCheck);
                    mysqli_close($conexao);
                } else {
                    mysqli_stmt_close($stmtCheck);
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $sqlInsert = "INSERT INTO usuario (nomeUsuario, emailUsuario, senhaUsuario, planoAssinatura, cicloPlano, validadePlano, dataAceiteTermos) VALUES (?, ?, ?, ?, 'MENSAL', DATE_ADD(CURDATE(), INTERVAL 30 DAY), NOW())";
                    $stmtInsert = mysqli_prepare($conexao, $sqlInsert);
                    mysqli_stmt_bind_param($stmtInsert, "ssss", $usuario, $email, $senhaHash, $planoEscolhido);
                    
                    if (mysqli_stmt_execute($stmtInsert)) {
                        $novoId = mysqli_insert_id($conexao);
                        mysqli_stmt_close($stmtInsert);
                        mysqli_close($conexao);

                        // Conecta diretamente no perfil recém-criado (evita ficar preso na conta anterior)
                        $_SESSION = [];
                        session_regenerate_id(true);

                        $_SESSION['usuarioLogado'] = $usuario;
                        $_SESSION['emailUsuario'] = $email;
                        $_SESSION['idUsuario'] = $novoId;
                        $_SESSION['planoUsuario'] = $planoEscolhido;
                        $_SESSION['roleUsuario'] = (strtolower($usuario) === 'admin' || stripos($email, 'admin') !== false) ? 'Administrador' : 'Operador';
                        $_SESSION['flash_dashboard'] = [
                            'tipo' => 'sucesso',
                            'texto' => "Conta criada com sucesso! Você foi conectado ao seu novo perfil como <strong>" . htmlspecialchars($usuario) . "</strong>."
                        ];

                        header("Location: " . url('index.php'));
                        exit;
                    } else {
                        $mensagemerro = "Erro ao tentar cadastrar o usuário no banco de dados.";
                        mysqli_stmt_close($stmtInsert);
                        mysqli_close($conexao);
                    }
                }
            }
        } else {
            $mensagemerro = "Por favor, preencha todos os campos obrigatórios.";
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

            <?php $planoGet = strtoupper($_GET['plano'] ?? 'ESSENCIAL'); ?>
            <div class="form-group">
                <label for="plano">Plano de Assinatura MVM *</label>
                <select id="plano" name="plano" required>
                    <option value="ESSENCIAL" <?= $planoGet === 'ESSENCIAL' ? 'selected' : '' ?>>📦 Essencial (R$ 29,90/mês) — Até 100 produtos</option>
                    <option value="PADRAO" <?= $planoGet === 'PADRAO' ? 'selected' : '' ?>>⭐ Padrão (R$ 59,90/mês) — Até 1.000 produtos + Kardex &amp; FEFO</option>
                    <option value="PREMIUM" <?= $planoGet === 'PREMIUM' ? 'selected' : '' ?>>👑 Premium (R$ 99,90/mês) — Ilimitado + Power BI &amp; WMS</option>
                </select>
            </div>

            <!-- Aceite Obrigatório dos Termos e Condições (LGPD) -->
            <div class="form-group" style="background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; margin-bottom: 14px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; font-weight: 500; font-size: 12.5px; color: var(--text); margin: 0; cursor: pointer; line-height: 1.45;">
                    <input type="checkbox" id="aceitou_termos" name="aceitou_termos" value="1" required style="width: 17px; height: 17px; margin-top: 2px; flex-shrink: 0; cursor: pointer; accent-color: var(--primary);">
                    <span>
                        Li e concordo com os <a href="#" onclick="abrirModalTermos(event)" style="font-weight: 700; text-decoration: underline;">Termos e Condições de Uso</a> e com a <a href="#" onclick="abrirModalTermos(event)" style="font-weight: 700; text-decoration: underline;">Política de Privacidade (LGPD)</a> do Sistema MVM. *
                    </span>
                </label>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; margin-top: 4px;">
                Finalizar Cadastro
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 12.5px; color: var(--text-muted);">
            Já possui uma conta ativa? <a href="login.php" style="font-weight: 700;">Faça Login</a>
        </div>
    </div>
</div>

<!-- MODAL DE TERMOS E CONDIÇÕES DE USO & POLÍTICA DE PRIVACIDADE (LGPD) -->
<div id="modal-termos-uso" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card" style="max-width: 600px; text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; margin-bottom: 14px; border-bottom: 1px solid var(--border);">
            <div>
                <h3 style="margin: 0; font-size: 18px;">📜 Termos e Condições de Uso &amp; LGPD</h3>
                <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">MVM Gestão de Estoque &amp; Inteligência Comercial</p>
            </div>
            <button type="button" class="btn-close-drawer" onclick="fecharModalTermos()">✕</button>
        </div>

        <div style="max-height: 340px; overflow-y: auto; padding: 14px 16px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12.5px; color: var(--text); line-height: 1.6; margin-bottom: 18px;">
            <h4 style="font-size: 13.5px; margin-bottom: 6px; color: var(--primary);">1. Aceitação dos Termos</h4>
            <p style="margin-bottom: 12px;">
                Ao criar uma conta no <strong>Sistema MVM Estoque</strong>, o usuário declara ter lido, compreendido e aceitado integralmente as regras operacionais, comerciais e de segurança estabelecidas neste instrumento.
            </p>

            <h4 style="font-size: 13.5px; margin-bottom: 6px; color: var(--primary);">2. Uso da Plataforma e Responsabilidades</h4>
            <p style="margin-bottom: 12px;">
                O operador é inteiramente responsável pela veracidade dos dados cadastrados em sua conta (produtos, lotes, validades FEFO, preços, clientes, fornecedores e ajustes de inventário), bem como pelo sigilo de suas credenciais de login e chave de API (<code>X-API-KEY</code>).
            </p>

            <h4 style="font-size: 13.5px; margin-bottom: 6px; color: var(--primary);">3. Proteção de Dados e Privacidade (LGPD - Lei nº 13.709/2018)</h4>
            <p style="margin-bottom: 12px;">
                Em conformidade com a Lei Geral de Proteção de Dados (LGPD), os dados pessoais de clientes, fornecedores e operadores são armazenados de forma isolada por conta (<code>idUsuario</code>), com senhas criptografadas via algoritmo <strong>BCRYPT</strong> e proteção contra ataques CSRF e SQL Injection.
            </p>

            <h4 style="font-size: 13.5px; margin-bottom: 6px; color: var(--primary);">4. Planos de Assinatura e Cancelamento</h4>
            <p style="margin-bottom: 12px;">
                Os planos de assinatura (<strong>Essencial</strong>, <strong>Padrão</strong> e <strong>Premium</strong>) funcionam em modelo pré-pago sem fidelidade, permitindo upgrade, downgrade ou cancelamento a qualquer momento diretamente no painel.
            </p>

            <h4 style="font-size: 13.5px; margin-bottom: 6px; color: var(--primary);">5. Auditoria e Registro de Consentimento</h4>
            <p style="margin-bottom: 0;">
                A data e o horário exatos do aceite destes termos são gravados eletronicamente no banco de dados (<code>dataAceiteTermos</code>) para fins de conformidade legal e auditoria.
            </p>
        </div>

        <div class="confirm-dialog-actions">
            <button type="button" class="btn-secondary" onclick="fecharModalTermos()">Fechar</button>
            <button type="button" class="btn-primary" onclick="aceitarTermosPeloModal()" style="justify-content: center;">
                <span>✅</span> Li e Aceito os Termos
            </button>
        </div>
    </div>
</div>

<script>
    function abrirModalTermos(event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('modal-termos-uso');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function fecharModalTermos() {
        const modal = document.getElementById('modal-termos-uso');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function aceitarTermosPeloModal() {
        const chk = document.getElementById('aceitou_termos');
        if (chk) chk.checked = true;
        fecharModalTermos();
    }

    function validarSenhas(event) {
        const senha = document.getElementById('password').value;
        const confirmaSenha = document.getElementById('confirmPassword').value;
        const termos = document.getElementById('aceitou_termos');

        if (senha !== confirmaSenha) {
            alert("Aviso: As senhas digitadas não coincidem. Verifique e tente novamente.");
            event.preventDefault();
            return false;
        }

        if (!termos || !termos.checked) {
            alert("Aviso: Você precisa aceitar os Termos e Condições de Uso para criar sua conta.");
            event.preventDefault();
            return false;
        }
        return true;
    }
</script>

<?php require_once __DIR__ . "/../../templates/footer.php"; ?>