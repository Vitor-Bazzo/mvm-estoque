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
                    <option value="ESSENCIAL" <?= $planoGet === 'ESSENCIAL' ? 'selected' : '' ?>>📦 Essencial (R$ 149,90/mês) — Pequenas empresas • Até 100 produtos</option>
                    <option value="PADRAO" <?= $planoGet === 'PADRAO' ? 'selected' : '' ?>>⭐ Padrão (R$ 349,90/mês) — Médias empresas • Até 1.000 produtos + Kardex &amp; FEFO</option>
                    <option value="PREMIUM" <?= $planoGet === 'PREMIUM' ? 'selected' : '' ?>>👑 Premium Enterprise (Preços a consultar) — Grandes empresas • Sob medida</option>
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
    <div class="confirm-dialog-card" style="max-width: 740px; text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; margin-bottom: 14px; border-bottom: 1px solid var(--border);">
            <div>
                <h3 style="margin: 0; font-size: 18px;">📜 Termos de Uso e Política de Privacidade — MVM Gestão de Estoque</h3>
                <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">Última atualização: Novembro de 2026</p>
            </div>
            <button type="button" class="btn-close-drawer" onclick="fecharModalTermos()">✕</button>
        </div>

        <div style="max-height: 400px; overflow-y: auto; padding: 14px 16px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 12.5px; color: var(--text); line-height: 1.65; margin-bottom: 18px;">
            <p>Este documento estabelece as regras de utilização da plataforma SaaS (Software as a Service) <strong>MVM Gestão de Estoque</strong> e esclarece como os dados pessoais e empresariais são coletados, protegidos e tratados em conformidade com a Lei Geral de Proteção de Dados Pessoais (LGPD — Lei nº 13.709/2018) e o Marco Civil da Internet (Lei nº 12.965/2014).</p>

            <h4 style="font-size: 13.5px; margin: 14px 0 6px; color: var(--primary);">Parte I — Termos e Condições de Uso</h4>

            <p><strong>1. Objeto e Aceitação</strong><br>
            O presente instrumento regula o acesso e a utilização da plataforma MVM Gestão de Estoque, voltada ao controle de inventário, movimentações de produtos, gestão de cadastros e emissão de recibos operacionais. Ao marcar a caixa de confirmação no momento do cadastro ou utilizar o sistema, o usuário declara ter lido, compreendido e manifestado seu consentimento livre, expresso e informado com todas as disposições deste documento.</p>

            <p><strong>2. Cadastro, Credenciais e Uso Adequado</strong><br>
            O usuário é integralmente responsável pela veracidade e atualização dos dados fornecidos no cadastro, bem como pela guarda e sigilo de suas credenciais de acesso (login e senha).<br>
            Toda ação executada dentro da plataforma por meio das credenciais de um operador será presumida como de sua autoria.<br>
            É estritamente proibido utilizar o sistema para fins ilícitos, tentar burlar mecanismos de autenticação, realizar engenharia reversa, compartilhar acessos individuais não autorizados ou comprometer a estabilidade da infraestrutura.</p>

            <p><strong>3. Planos, Assinatura, Upgrade e Cancelamento</strong><br>
            O acesso à plataforma é disponibilizado por meio dos planos <strong>Essencial</strong>, <strong>Padrão</strong> e <strong>Premium</strong>, cujos limites de recursos, operadores e funcionalidades são detalhados na página de contratação.<br>
            <strong>Sem fidelidade:</strong> Nenhum dos planos possui cláusula de fidelidade ou multa rescisória.<br>
            <strong>Alteração de plano:</strong> O usuário pode realizar o upgrade ou downgrade entre os planos a qualquer momento, com ajuste proporcional ou aplicação no ciclo de faturamento seguinte, conforme a regra vigente no painel financeiro.<br>
            <strong>Cancelamento:</strong> O cancelamento pode ser efetuado a qualquer momento pelo próprio usuário, interrompendo cobranças futuras a partir do próximo ciclo.</p>

            <p><strong>4. Propriedade Intelectual</strong><br>
            Todo o código-fonte, arquitetura, interface, marca, logotipos e funcionalidades do MVM Gestão de Estoque são de propriedade exclusiva de seus desenvolvedores. A assinatura concede apenas uma licença de uso revogável, não exclusiva e intransferível. Todos os dados, cadastros e relatórios inseridos pelo usuário permanecem de propriedade exclusiva do respectivo usuário.</p>

            <p><strong>5. Limitação de Responsabilidade e Disponibilidade</strong><br>
            Empregamos boas práticas de hospedagem e segurança para manter a alta disponibilidade do serviço, mas não garantimos operação ininterrupta em casos de manutenção programada, falhas de conexão do usuário ou instabilidades em provedores externos de nuvem.<br>
            O MVM Gestão de Estoque é uma ferramenta de apoio operacional e não substitui sistemas fiscais ou contábeis oficiais, não se responsabilizando por prejuízos financeiros, divergências de inventário ou perdas comerciais decorrentes de lançamentos incorretos, exclusões acidentais ou falhas operacionais cometidas pelos próprios operadores da conta.</p>

            <h4 style="font-size: 13.5px; margin: 16px 0 6px; color: var(--primary);">Parte II — Segurança e Política de Privacidade (LGPD)</h4>

            <p><strong>6. Papéis no Tratamento de Dados (Controlador vs. Operador)</strong><br>
            <strong>Dados da Conta e Assinatura (Controlador):</strong> Em relação aos dados cadastrais e financeiros do próprio assinante e de seus operadores (nome, e-mail, credenciais de acesso e dados de cobrança), a MVM Gestão de Estoque atua como Controladora.<br>
            <strong>Dados Operacionais de Terceiros (Operadora):</strong> Em relação aos dados de clientes, fornecedores, produtos e movimentações inseridos pelos usuários dentro de seus respectivos ambientes isolados, o usuário assinante atua como Controlador e a MVM Gestão de Estoque atua exclusivamente como Operadora, processando tais informações estritamente para executar as rotinas de inventário e emissão de recibos contratadas.</p>

            <p><strong>7. Dados Coletados e Finalidades</strong><br>
            Coletamos apenas os dados estritamente necessários para a operação segura do sistema:</p>
            <table style="width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: 11.8px; background: var(--card);">
                <thead>
                    <tr style="background: var(--bg-subtle); color: var(--text);">
                        <th style="padding: 8px; border: 1px solid var(--border); text-align: left;">Categoria de Dados</th>
                        <th style="padding: 8px; border: 1px solid var(--border); text-align: left;">Exemplos de Informações</th>
                        <th style="padding: 8px; border: 1px solid var(--border); text-align: left;">Finalidade e Base Legal (LGPD)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 8px; border: 1px solid var(--border); font-weight: 700;">Cadastro e Operadores</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Nome completo, e-mail, telefone, perfil de permissão e senha (armazenada em formato criptografado/hash).</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Criação de conta, autenticação segura e controle de acesso (Execução de contrato — Art. 7º, V).</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid var(--border); font-weight: 700;">Operacionais (Inventário)</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Cadastros de clientes e fornecedores (nome/razão social, CPF/CNPJ, contato), entradas/saídas de estoque e recibos emitidos.</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Execução das rotinas de inventário e emissão de recibos (Execução de contrato — Art. 7º, V).</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid var(--border); font-weight: 700;">Assinatura e Cobrança</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Plano contratado (Essencial, Padrão ou Premium), histórico de faturas e status de pagamento (processados via gateway seguro; não armazenamos dados completos de cartão).</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Gestão financeira e obrigações fiscais (Execução de contrato e obrigação legal — Art. 7º, II e V).</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid var(--border); font-weight: 700;">Auditoria e Segurança</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Registros de movimentação Kardex (data, hora, item alterado e operador), endereço IP e tokens de sessão/CSRF.</td>
                        <td style="padding: 8px; border: 1px solid var(--border);">Rastreabilidade do estoque, prevenção a fraudes e guarda legal (Legítimo interesse e obrigação legal — Art. 7º, II e IX).</td>
                    </tr>
                </tbody>
            </table>

            <p><strong>8. Isolamento de Ambiente e Segurança da Informação</strong><br>
            A plataforma adota arquitetura multi-tenant com medidas técnicas rigorosas de proteção:<br>
            <strong>Isolamento Lógico:</strong> Cada conta/organização opera em ambiente logicamente isolado, impedindo que outros usuários da plataforma visualizem ou acessem seus registros.<br>
            <strong>Criptografia e Proteção de Sessão:</strong> As senhas são armazenadas com criptografia irreversível (hash), as comunicações são cifradas via HTTPS/TLS e as requisições são protegidas contra ataques de falsificação por meio de tokens CSRF (Cross-Site Request Forgery).<br>
            <strong>Auditoria Kardex:</strong> Todas as movimentações críticas de estoque geram histórico imutável de auditoria para rastreabilidade operacional do próprio assinante.</p>

            <p><strong>9. Compartilhamento de Dados e Cookies</strong><br>
            <strong>Sem comercialização:</strong> A MVM Gestão de Estoque não vende, aluga ou comercializa dados pessoais ou empresariais sob nenhuma hipótese. O compartilhamento ocorre apenas com provedores essenciais de infraestrutura em nuvem, gateways de pagamento (para processar os planos) ou mediante requisição legal/judicial.<br>
            <strong>Cookies:</strong> Utilizamos apenas cookies e armazenamento local estritamente necessários para manter a sessão autenticada do operador e validar tokens de segurança (CSRF), sem rastreamento publicitário de terceiros.</p>

            <p><strong>10. Retenção, Exclusão e Direitos dos Titulares (Art. 18 da LGPD)</strong><br>
            O usuário pode solicitar a qualquer momento a confirmação de tratamento, acesso, correção (disponível no próprio painel), exportação/portabilidade ou eliminação de seus dados cadastrais.<br>
            Após o cancelamento da assinatura, recomenda-se a exportação prévia dos relatórios. Os dados operacionais serão excluídos ou anonimizados após o encerramento definitivo da conta, ressalvados os registros mínimos de acesso (logs) mantidos pelo prazo legal de 6 meses conforme o Art. 15 do Marco Civil da Internet e obrigações fiscais aplicáveis.<br>
            Solicitações de exclusão feitas por clientes ou fornecedores cadastrados por um assinante devem ser tratadas diretamente pelo próprio assinante (Controlador) dentro do sistema.</p>

            <h4 style="font-size: 13.5px; margin: 16px 0 6px; color: var(--primary);">Parte III — Disposições Gerais e Foro</h4>

            <p style="margin-bottom: 0;"><strong>11. Atualizações, Contato (DPO) e Foro</strong><br>
            Este documento poderá ser atualizado periodicamente para refletir melhorias técnicas ou adequações legais, sendo as alterações relevantes comunicadas na plataforma. Dúvidas ou solicitações relacionadas à privacidade e à LGPD podem ser enviadas ao Encarregado pelo Tratamento de Dados (DPO) através do canal oficial de suporte indicado no rodapé do sistema. Fica eleito o foro da comarca de domicílio do responsável pela plataforma no Brasil para dirimir quaisquer controvérsias oriundas deste instrumento.</p>
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