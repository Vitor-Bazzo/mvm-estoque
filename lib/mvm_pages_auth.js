const crypto = require('crypto');
const { esc, renderLayout } = require('./mvm_layout');
const { saveDb, hashPassword, verifyPassword, addDaysStr, addDaysDateTimeStr, formatDateBR } = require('./mvm_db');

function handleLogin(req, body, db, session) {
  let erro = '';
  if (req.method === 'POST') {
    const usuario = String(body.usuario || '').trim();
    const senha = String(body.senha || '').trim();
    if (!usuario || !senha) {
      erro = 'Preencha o nome do usuário e a senha.';
    } else {
      const u = db.usuarios.find(
        x => x.nomeUsuario.toLowerCase() === usuario.toLowerCase() || (x.emailUsuario && x.emailUsuario.toLowerCase() === usuario.toLowerCase())
      );
      if (u && verifyPassword(senha, u.senhaUsuario)) {
        session.usuarioLogado = u.nomeUsuario;
        session.emailUsuario = u.emailUsuario || '';
        session.idUsuario = u.idUsuario;
        session.planoUsuario = u.planoAssinatura || 'PREMIUM';
        session.roleUsuario = u.nomeUsuario.toLowerCase() === 'admin' ? 'Administrador' : 'Operador';
        return { redirect: '/index.php' };
      } else {
        erro = 'Usuário ou senha incorretos. (Para demonstração use: admin / 123)';
      }
    }
  }

  const html = `
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/docs/mascote.php" title="Conheça o Mascote Oficial MVM" style="display: inline-block; text-decoration: none;">
                <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM Estoque" style="width: 84px; height: 84px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35); transition: transform 0.3s ease; object-fit: cover;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <h1>Acesso ao Sistema</h1>
            <p>Entre com suas credenciais para gerenciar o estoque</p>
        </div>

        ${erro ? `
            <div class="alert-box error" style="margin-bottom: 20px;">
                <span>⚠️</span>
                <p>${esc(erro)}</p>
            </div>
        ` : ''}

        <form action="/modulos/auth/login.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="acao" value="acessar">
            <div class="form-group">
                <label for="usuario">Usuário ou E-mail</label>
                <input type="text" id="usuario" name="usuario" required placeholder="Ex: admin ou seu.email@mvm.com" value="admin">
            </div>
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="senha" style="margin-bottom: 0;">Senha de Acesso</label>
                    <a href="/modulos/auth/esqueci_senha.php" style="font-size: 12px; font-weight: 700; color: var(--primary); text-decoration: none;">Esqueceu sua senha?</a>
                </div>
                <input type="password" id="senha" name="senha" required placeholder="Digite sua senha" value="123">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; margin-top: 8px;">Entrar no Sistema</button>
        </form>

        <div class="auth-footer">
            Ainda não possui uma conta? <a href="/modulos/auth/criar_conta.php">Cadastre-se agora</a>
        </div>
    </div>
</div>`;

  return { html: renderLayout('MVM - Acesso ao Sistema', 'login.php', session, html) };
}

async function handleCriarConta(req, body, query, db, session) {
  let erro = '';
  const planoPre = ['ESSENCIAL', 'PADRAO', 'PREMIUM'].includes(String(query.plano || '').toUpperCase())
    ? String(query.plano).toUpperCase()
    : 'ESSENCIAL';

  if (req.method === 'POST') {
    const usuario = String(body.usuario || '').trim();
    const email = String(body.email || '').trim();
    const senha = String(body.senha || '');
    const planoEscolhido = ['ESSENCIAL', 'PADRAO', 'PREMIUM'].includes(String(body.plano || '').toUpperCase())
      ? String(body.plano).toUpperCase()
      : 'ESSENCIAL';
    const aceitouTermos = String(body.aceitou_termos || '') === '1';

    if (!aceitouTermos) {
      erro = 'Você precisa ler e aceitar os Termos e Condições de Uso para criar sua conta.';
    } else if (senha.length < 6) {
      erro = 'A senha deve conter no mínimo 6 caracteres.';
    } else if (!usuario || !email) {
      erro = 'Por favor, preencha todos os campos obrigatórios.';
    } else {
      const existe = db.usuarios.find(
        x => x.nomeUsuario.toLowerCase() === usuario.toLowerCase() || (x.emailUsuario && x.emailUsuario.toLowerCase() === email.toLowerCase())
      );
      if (existe) {
        erro = 'Este nome de usuário ou e-mail já está cadastrado no sistema.';
      } else {
        const novoId = db.usuarios.reduce((m, x) => Math.max(m, x.idUsuario), 0) + 1;
        const senhaHash = hashPassword(senha);
        const novoU = {
          idUsuario: novoId,
          nomeUsuario: usuario,
          emailUsuario: email,
          senhaUsuario: senhaHash,
          planoAssinatura: planoEscolhido,
          cicloPlano: 'MENSAL',
          validadePlano: addDaysStr(30),
          dataAceiteTermos: addDaysDateTimeStr(0),
          apiToken: 'mvm_live_' + crypto.randomBytes(8).toString('hex'),
          tokenRecuperacao: null,
          expiraRecuperacao: null
        };
        db.usuarios.push(novoU);
        await saveDb(db);

        // Limpa qualquer resquício de sessão anterior para garantir isolamento total
        delete session.flash_plano;
        delete session.flash_mensagem;
        delete session.mensagem_movimento;
        delete session.recToken;
        delete session.recUserId;
        delete session.cicloPlano;
        delete session.validadePlano;

        session.usuarioLogado = usuario;
        session.emailUsuario = email;
        session.idUsuario = novoId;
        session.senhaHash = senhaHash;
        session.planoUsuario = planoEscolhido;
        session.roleUsuario = usuario.toLowerCase() === 'admin' ? 'Administrador' : 'Operador';
        session.flash_dashboard = {
          tipo: 'sucesso',
          texto: `Conta criada com sucesso! Seu ambiente isolado e zerado está pronto para <strong>${esc(usuario)}</strong>.`
        };
        return { redirect: '/index.php' };
      }
    }
  }

  const html = `
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/docs/mascote.php" title="Conheça o Mascote Oficial MVM" style="display: inline-block; text-decoration: none;">
                <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM Estoque" style="width: 84px; height: 84px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35); transition: transform 0.3s ease; object-fit: cover;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <h1>Criar Nova Conta</h1>
            <p>Cadastre um novo operador no sistema MVM</p>
        </div>

        ${erro ? `
            <div class="alert-box error" style="margin-bottom: 20px;">
                <span>⚠️</span>
                <p>${esc(erro)}</p>
            </div>
        ` : ''}

        <form id="signupForm" action="/modulos/auth/criar_conta.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
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
                <input type="password" id="password" name="senha" required minlength="6" placeholder="Mínimo de 6 caracteres">
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirmar Senha *</label>
                <input type="password" id="confirmPassword" required placeholder="Digite a senha novamente">
            </div>
            <div class="form-group">
                <label for="plano">Plano de Assinatura (<a href="/modulos/planos/planos.php" style="color: var(--primary); text-decoration: none; font-weight: 700;">Comparar planos</a>)</label>
                <select id="plano" name="plano">
                    <option value="ESSENCIAL" ${planoPre === 'ESSENCIAL' ? 'selected' : ''}>📦 Essencial — R$ 89,90/mês (Pequenas empresas • Até 100 produtos)</option>
                    <option value="PADRAO" ${planoPre === 'PADRAO' ? 'selected' : ''}>⭐ Padrão Comercial — R$ 199,90/mês (Médias empresas • Mais Popular)</option>
                    <option value="PREMIUM" ${planoPre === 'PREMIUM' ? 'selected' : ''}>👑 Premium Enterprise — R$ 449,90/mês (Grandes empresas • Ilimitado)</option>
                </select>
            </div>

            <div class="form-group" style="margin-top: 12px; margin-bottom: 18px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 12.5px; font-weight: 500; color: var(--text-muted); cursor: pointer; line-height: 1.45; background: var(--bg-subtle); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                    <input type="checkbox" id="aceitou_termos" name="aceitou_termos" value="1" required style="width: 17px; height: 17px; margin-top: 2px; accent-color: var(--primary); cursor: pointer; flex-shrink: 0;">
                    <span>
                        Li e concordo com os <a href="javascript:void(0)" onclick="document.getElementById('modal-termos-uso').classList.add('active')" style="color: var(--primary); font-weight: 700; text-decoration: underline;">Termos e Condições de Uso</a> e a <a href="javascript:void(0)" onclick="document.getElementById('modal-termos-uso').classList.add('active')" style="color: var(--primary); font-weight: 700; text-decoration: underline;">Política de Privacidade (LGPD)</a> do Sistema MVM Estoque. *
                    </span>
                </label>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px;">Criar Minha Conta</button>
        </form>

        <div class="auth-footer">
            Já possui uma conta? <a href="/modulos/auth/login.php">Fazer Login</a>
        </div>
    </div>
</div>

<div id="modal-termos-uso" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card" style="max-width: 580px; text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--border);">
            <h3 style="margin: 0; font-size: 17px;">📜 Termos e Condições de Uso &amp; LGPD</h3>
            <button type="button" class="btn-close-drawer" onclick="document.getElementById('modal-termos-uso').classList.remove('active')">✕</button>
        </div>
        <div style="max-height: 320px; overflow-y: auto; font-size: 13px; color: var(--text-muted); line-height: 1.65; padding-right: 6px;">
            <p><strong>1. Objeto e Aceitação:</strong> O presente instrumento regula o uso da plataforma MVM Gestão de Estoque. Ao marcar a caixa de confirmação, o usuário manifesta consentimento livre, expresso e informado.</p>
            <p><strong>2. Isolamento de Dados e Segurança:</strong> Cada operador possui ambiente isolado com criptografia de senhas, tokens CSRF e registro de auditoria Kardex.</p>
            <p><strong>3. Conformidade com a LGPD (Lei nº 13.709/2018):</strong> Os dados de clientes, fornecedores e operadores são armazenados exclusivamente para execução das rotinas de inventário e emissão de recibos.</p>
            <p><strong>4. Assinatura e Cancelamento:</strong> Os planos Essencial, Padrão e Premium não possuem fidelidade e podem ser alterados a qualquer momento.</p>
        </div>
        <div class="confirm-dialog-actions" style="margin-top: 16px;">
            <button type="button" class="btn-secondary" onclick="document.getElementById('modal-termos-uso').classList.remove('active')">Fechar</button>
            <button type="button" class="btn-primary" onclick="document.getElementById('aceitou_termos').checked = true; document.getElementById('modal-termos-uso').classList.remove('active');">✓ Li e Aceito os Termos</button>
        </div>
    </div>
</div>`;

  return { html: renderLayout('MVM - Criar Conta', 'criar_conta.php', session, html) };
}

async function handleEsqueciSenha(req, body, db, session) {
  let msgSucesso = '';
  let msgErro = '';
  let linkRedefinicao = '';

  if (req.method === 'POST') {
    const identificador = String(body.identificador || '').trim();
    if (!identificador) {
      msgErro = 'Informe seu nome de usuário ou e-mail cadastrado.';
    } else {
      const u = db.usuarios.find(
        x => x.nomeUsuario.toLowerCase() === identificador.toLowerCase() || (x.emailUsuario && x.emailUsuario.toLowerCase() === identificador.toLowerCase())
      ) || db.usuarios[0];
      const token = crypto.randomBytes(24).toString('hex');
      u.tokenRecuperacao = token;
      u.expiraRecuperacao = Date.now() + 30 * 60 * 1000;
      await saveDb(db);
      session.recToken = token;
      session.recUserId = u.idUsuario;
      linkRedefinicao = `/modulos/auth/redefinir_senha.php?token=${token}`;
      msgSucesso = `Link seguro de recuperação gerado para a conta "${u.nomeUsuario}".`;
    }
  }

  const html = `
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM" style="width: 76px; height: 76px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35);">
            <h1>Recuperar Senha</h1>
            <p>Informe seu usuário ou e-mail para redefinir sua senha de acesso</p>
        </div>

        ${msgErro ? `<div class="alert-box error" style="margin-bottom:18px;"><span>⚠️</span><p>${esc(msgErro)}</p></div>` : ''}
        ${msgSucesso ? `
            <div class="alert-box success" style="margin-bottom:18px; flex-direction:column; align-items:flex-start; gap:10px;">
                <div style="display:flex; gap:8px; align-items:center;"><span>✅</span><p style="margin:0;">${esc(msgSucesso)}</p></div>
                <a href="${esc(linkRedefinicao)}" class="btn-primary" style="width:100%; justify-content:center; text-decoration:none; padding:10px 16px;">🔑 Clique Aqui para Redefinir sua Senha Agora</a>
            </div>
        ` : ''}

        <form action="/modulos/auth/esqueci_senha.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <div class="form-group">
                <label for="identificador">Usuário ou E-mail Cadastrado</label>
                <input type="text" id="identificador" name="identificador" required placeholder="Ex: admin ou admin@mvm.com" value="admin@mvm.com">
            </div>
            <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:12px;">Gerar Link de Recuperação</button>
        </form>

        <div class="auth-footer">
            Lembrou a senha? <a href="/modulos/auth/login.php">Voltar para o Login</a>
        </div>
    </div>
</div>`;

  return { html: renderLayout('MVM - Recuperar Senha', 'esqueci_senha.php', session, html) };
}

async function handleRedefinirSenha(req, body, query, db, session) {
  const token = String(body.token || query.token || session.recToken || '');
  let erro = '';
  let sucesso = false;

  if (req.method === 'POST') {
    const novaSenha = String(body.nova_senha || '');
    const confirmar = String(body.confirmar_senha || '');
    if (novaSenha.length < 3) {
      erro = 'A nova senha deve ter pelo menos 3 caracteres.';
    } else if (novaSenha !== confirmar) {
      erro = 'As senhas informadas não coincidem.';
    } else {
      const u = db.usuarios.find(x => x.tokenRecuperacao === token || x.idUsuario === Number(session.recUserId || 1)) || db.usuarios[0];
      u.senhaUsuario = hashPassword(novaSenha);
      u.tokenRecuperacao = null;
      await saveDb(db);
      sucesso = true;
    }
  }

  const html = `
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM" style="width: 76px; height: 76px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35);">
            <h1>Definir Nova Senha</h1>
            <p>Crie uma nova credencial de acesso para sua conta MVM</p>
        </div>

        ${erro ? `<div class="alert-box error" style="margin-bottom:18px;"><span>⚠️</span><p>${esc(erro)}</p></div>` : ''}
        ${sucesso ? `
            <div class="alert-box success" style="margin-bottom:18px; flex-direction:column; gap:10px;">
                <div><span>🎉</span> Sua senha foi atualizada com sucesso!</div>
                <a href="/modulos/auth/login.php" class="btn-primary" style="width:100%; justify-content:center; text-decoration:none; padding:10px;">Acessar com Nova Senha</a>
            </div>
        ` : `
            <form action="/modulos/auth/redefinir_senha.php" method="POST">
                <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
                <input type="hidden" name="token" value="${esc(token)}">
                <div class="form-group">
                    <label for="nova_senha">Nova Senha *</label>
                    <input type="password" id="nova_senha" name="nova_senha" required placeholder="Digite a nova senha">
                </div>
                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Nova Senha *</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required placeholder="Repita a nova senha">
                </div>
                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:12px;">Salvar Nova Senha</button>
            </form>
        `}
    </div>
</div>`;

  return { html: renderLayout('MVM - Redefinir Senha', 'redefinir_senha.php', session, html) };
}

async function handlePlanos(req, body, db, session) {
  const idUsuario = session.usuarioLogado ? (Number(session.idUsuario) || 1) : 0;
  const userObj = db.usuarios.find(u => u.idUsuario === (idUsuario || 1)) || db.usuarios[0];

  if (req.method === 'POST' && body.acao === 'assinar_plano') {
    const novoPlano = ['ESSENCIAL', 'PADRAO', 'PREMIUM'].includes(String(body.plano_escolhido || '').toUpperCase())
      ? String(body.plano_escolhido).toUpperCase()
      : 'ESSENCIAL';
    const novoCiclo = String(body.ciclo_escolhido || 'MENSAL').toUpperCase() === 'ANUAL' ? 'ANUAL' : 'MENSAL';
    const metodoPagamento = String(body.metodo_pagamento || 'PIX');
    const dias = novoCiclo === 'ANUAL' ? 365 : 30;
    const novaVal = addDaysStr(dias);

    userObj.planoAssinatura = novoPlano;
    userObj.cicloPlano = novoCiclo;
    userObj.validadePlano = novaVal;
    await saveDb(db);

    session.planoUsuario = novoPlano;
    session.cicloPlano = novoCiclo;
    session.validadePlano = novaVal;
    const nomesPlanos = {
      ESSENCIAL: 'Essencial (Básico)',
      PADRAO: 'Padrão (Comercial)',
      PREMIUM: 'Premium (Enterprise 4K BI & WMS)'
    };
    session.flash_plano = {
      tipo: 'sucesso',
      texto: `🎉 Assinatura confirmada via ${esc(metodoPagamento)}! Seu plano agora é <strong>${nomesPlanos[novoPlano]}</strong> (${novoCiclo}).`
    };
    return { redirect: '/modulos/planos/planos.php' };
  }

  const planoAtual = String(session.planoUsuario || userObj.planoAssinatura || 'PREMIUM').toUpperCase();
  const cicloAtual = String(session.cicloPlano || userObj.cicloPlano || 'MENSAL').toUpperCase();
  const validadePlano = session.validadePlano || userObj.validadePlano || addDaysStr(30);
  const flash = session.flash_plano;
  delete session.flash_plano;

  const html = `
<style>
    .pricing-hero { text-align: center; max-width: 720px; margin: 10px auto 28px; }
    .pricing-badge-top { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 30px; font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; background: rgba(229, 9, 20, 0.12); color: #e50914; border: 1px solid rgba(229, 9, 20, 0.3); margin-bottom: 12px; }
    [data-theme="dark"] .pricing-badge-top { background: rgba(229, 9, 20, 0.2); color: #ff4d57; }
    .pricing-hero h1 { font-size: 30px; font-weight: 800; letter-spacing: -0.03em; color: var(--text); margin-bottom: 8px; }
    .pricing-hero p { font-size: 14.5px; color: var(--text-muted); line-height: 1.5; }
    .billing-toggle-bar { display: inline-flex; align-items: center; background: var(--card); border: 1px solid var(--border); padding: 5px; border-radius: 40px; margin-top: 18px; box-shadow: var(--shadow-sm); gap: 4px; }
    .billing-btn { border: none; background: transparent; color: var(--text-muted); font-size: 13px; font-weight: 700; padding: 8px 18px; border-radius: 30px; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; }
    .billing-btn.active { background: var(--primary); color: #ffffff; box-shadow: 0 3px 10px rgba(99, 91, 255, 0.35); }
    .discount-pill { background: #10b981; color: #ffffff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 12px; text-transform: uppercase; }
    .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; align-items: stretch; margin-bottom: 36px; }
    .plan-card { background: var(--card); border: 1.5px solid var(--border); border-radius: 18px; padding: 0; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; box-shadow: var(--shadow-md); transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease; }
    .plan-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .plan-card.popular { border-color: #635bff; box-shadow: 0 12px 32px rgba(99, 91, 255, 0.16); }
    .plan-card.premium-tier { border-color: #e50914; box-shadow: 0 12px 34px rgba(229, 9, 20, 0.16); }
    .plan-card.is-current { outline: 3px solid #10b981; outline-offset: -3px; }
    .plan-banner { padding: 22px 24px 18px; color: #ffffff; position: relative; }
    .banner-essencial { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); }
    .banner-padrao { background: linear-gradient(135deg, #4338ca 0%, #635bff 50%, #3b82f6 100%); }
    .banner-premium { background: linear-gradient(135deg, #7f1d1d 0%, #e50914 55%, #f59e0b 100%); }
    .plan-ribbon { position: absolute; top: 12px; right: 14px; background: rgba(255, 255, 255, 0.22); backdrop-filter: blur(6px); color: #ffffff; font-size: 10.5px; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.5px; border: 1px solid rgba(255, 255, 255, 0.35); }
    .plan-banner h3 { font-size: 20px; font-weight: 800; margin-bottom: 3px; display: flex; align-items: center; gap: 8px; }
    .plan-banner span.plan-res { font-size: 12px; opacity: 0.9; font-weight: 600; }
    .plan-body { padding: 24px; flex: 1; display: flex; flex-direction: column; }
    .plan-price-box { margin-bottom: 20px; padding-bottom: 18px; border-bottom: 1px solid var(--border); }
    .plan-price-row { display: flex; align-items: baseline; gap: 4px; }
    .plan-currency { font-size: 16px; font-weight: 700; color: var(--text-muted); }
    .plan-amount { font-size: 36px; font-weight: 900; color: var(--text); letter-spacing: -0.03em; font-family: 'Plus Jakarta Sans', sans-serif; }
    .plan-period { font-size: 13px; color: var(--text-muted); font-weight: 600; }
    .plan-annual-note { font-size: 11.5px; color: #10b981; font-weight: 700; margin-top: 4px; min-height: 18px; }
    .plan-features { list-style: none; padding: 0; margin: 0 0 24px 0; display: flex; flex-direction: column; gap: 11px; flex: 1; }
    .plan-features li { font-size: 13px; color: var(--text); display: flex; align-items: flex-start; gap: 10px; line-height: 1.4; }
    .plan-features li .feat-check { color: #10b981; font-weight: 900; flex-shrink: 0; }
    .plan-features li.disabled { color: var(--text-light); text-decoration: line-through; opacity: 0.65; }
    .plan-features li.disabled .feat-check { color: var(--text-light); }
    .btn-plan-select { width: 100%; padding: 13px 18px; border-radius: 10px; font-size: 14px; font-weight: 800; border: none; cursor: pointer; transition: all 0.2s ease; text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-plan-essencial { background: var(--bg-subtle); color: var(--text); border: 1px solid var(--border); }
    .btn-plan-essencial:hover { background: var(--border); }
    .btn-plan-padrao { background: linear-gradient(135deg, #635bff 0%, #4338ca 100%); color: #ffffff; box-shadow: 0 4px 14px rgba(99, 91, 255, 0.35); }
    .btn-plan-padrao:hover { filter: brightness(1.08); color: #ffffff; }
    .btn-plan-premium { background: linear-gradient(135deg, #e50914 0%, #b91c1c 100%); color: #ffffff; box-shadow: 0 4px 14px rgba(229, 9, 20, 0.35); }
    .btn-plan-premium:hover { filter: brightness(1.08); color: #ffffff; }
    .btn-plan-current { background: rgba(16, 185, 129, 0.14); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.35); cursor: default; }
    .compare-table th, .compare-table td { padding: 13px 16px; border-bottom: 1px solid var(--border); font-size: 13px; }
    .compare-table th { background: var(--bg-subtle); font-weight: 800; text-transform: uppercase; font-size: 11.5px; color: var(--text-muted); }
    .compare-center { text-align: center; font-weight: 700; }
</style>

<div class="page-container">
    ${flash ? `
        <div class="alert-box ${flash.tipo === 'sucesso' ? 'success' : 'error'}">
            <span>${flash.tipo === 'sucesso' ? '🎉' : '⚠️'}</span>
            <p>${flash.texto}</p>
        </div>
    ` : ''}

    ${idUsuario > 0 ? `
        <div style="background: var(--card); border: 1px solid var(--border); border-left: 5px solid #10b981; border-radius: var(--radius); padding: 16px 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                    ${planoAtual === 'PREMIUM' ? '👑' : (planoAtual === 'PADRAO' ? '⭐' : '📦')}
                </div>
                <div>
                    <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Sua Assinatura Ativa</div>
                    <div style="font-size: 16px; font-weight: 800; color: var(--text);">
                        Plano MVM ${esc(planoAtual)} <span class="status-pill success" style="margin-left: 6px;">Ativo • Ciclo ${esc(cicloAtual)}</span>
                    </div>
                </div>
            </div>
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Próxima renovação: <strong>${esc(formatDateBR(validadePlano))}</strong>
                &bull; Cancele ou altere quando quiser, sem multa.
            </div>
        </div>
    ` : ''}

    <div class="pricing-hero">
        <span class="pricing-badge-top">💎 MVM SaaS • Assinatura Flexível</span>
        <h1>Escolha o plano ideal para a sua operação</h1>
        <p>Assim como no streaming, você pode fazer upgrade, downgrade ou cancelar a qualquer momento. Sem fidelidade e com ativação imediata.</p>

        <div class="billing-toggle-bar">
            <button type="button" class="billing-btn active" id="btn-ciclo-mensal" onclick="alternarCiclo('MENSAL')">
                Cobrança Mensal
            </button>
            <button type="button" class="billing-btn" id="btn-ciclo-anual" onclick="alternarCiclo('ANUAL')">
                Cobrança Anual <span class="discount-pill">Economize 20%</span>
            </button>
        </div>
    </div>

    <div class="pricing-grid">
        <!-- 1. PLANO ESSENCIAL -->
        <div class="plan-card ${idUsuario > 0 && planoAtual === 'ESSENCIAL' ? 'is-current' : ''}">
            <div>
                <div class="plan-banner banner-essencial">
                    <span class="plan-ribbon">Pequenas Empresas • 720p</span>
                    <h3>📦 Essencial</h3>
                    <span class="plan-res">Para pequenas empresas e comércios iniciando o controle</span>
                </div>
                <div class="plan-body">
                    <div class="plan-price-box">
                        <div class="plan-price-row">
                            <span class="plan-currency">R$</span>
                            <span class="plan-amount" data-mensal="89,90" data-anual="71,90">89,90</span>
                            <span class="plan-period">/mês</span>
                        </div>
                        <div class="plan-annual-note" data-note-mensal="Faturado mensalmente sem fidelidade" data-note-anual="R$ 862,80/ano (Economia de R$ 216,00)">
                            Faturado mensalmente sem fidelidade
                        </div>
                    </div>
                    <ul class="plan-features">
                        <li><span class="feat-check">✓</span> <span>Até <strong>100 produtos</strong> cadastrados no catálogo</span></li>
                        <li><span class="feat-check">✓</span> <span>Registro de <strong>Entradas, Saídas e Estornos</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Emissão de <strong>Recibo Térmico (80mm/A4)</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Carteira de Clientes e Fornecedores</span></li>
                        <li><span class="feat-check">✓</span> <span><strong>1 Operador</strong> conectado</span></li>
                        <li class="disabled"><span class="feat-check">✕</span> <span>Controle de Lotes, Validade (FEFO) e Kardex</span></li>
                        <li class="disabled"><span class="feat-check">✕</span> <span>Dashboard Power BI, Curva ABC e WMS</span></li>
                    </ul>
                </div>
            </div>
            <div style="padding: 0 24px 24px;">
                ${idUsuario > 0 && planoAtual === 'ESSENCIAL' ? `
                    <button type="button" class="btn-plan-select btn-plan-current" disabled>✓ Seu Plano Atual</button>
                ` : idUsuario > 0 ? `
                    <button type="button" class="btn-plan-select btn-plan-essencial" onclick="abrirCheckoutPlano('ESSENCIAL', 'Essencial', '89,90', '71,90')">Mudar para Essencial</button>
                ` : `
                    <a href="/modulos/auth/criar_conta.php?plano=ESSENCIAL" class="btn-plan-select btn-plan-essencial">Começar com Essencial</a>
                `}
            </div>
        </div>

        <!-- 2. PLANO PADRÃO -->
        <div class="plan-card popular ${idUsuario > 0 && planoAtual === 'PADRAO' ? 'is-current' : ''}">
            <div>
                <div class="plan-banner banner-padrao">
                    <span class="plan-ribbon">🔥 Médias Empresas • Full HD</span>
                    <h3>⭐ Padrão Comercial</h3>
                    <span class="plan-res">Para médias empresas, lojas e distribuidoras em expansão</span>
                </div>
                <div class="plan-body">
                    <div class="plan-price-box">
                        <div class="plan-price-row">
                            <span class="plan-currency">R$</span>
                            <span class="plan-amount" data-mensal="199,90" data-anual="159,90">199,90</span>
                            <span class="plan-period">/mês</span>
                        </div>
                        <div class="plan-annual-note" data-note-mensal="Faturado mensalmente sem fidelidade" data-note-anual="R$ 1.918,80/ano (Economia de R$ 480,00)">
                            Faturado mensalmente sem fidelidade
                        </div>
                    </div>
                    <ul class="plan-features">
                        <li><span class="feat-check">✓</span> <span>Até <strong>1.000 produtos</strong> com fotos</span></li>
                        <li><span class="feat-check">✓</span> <span>Controle de <strong>Lotes e Validade (FEFO)</strong></span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Ficha Kardex Contábil</strong> (CMP) + Etiquetas EAN</span></li>
                        <li><span class="feat-check">✓</span> <span>Exportação oficial em <strong>Excel (.xlsx) e PDF</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Alertas automáticos de estoque por <strong>E-mail</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Até <strong>3 Operadores</strong> simultâneos</span></li>
                        <li class="disabled"><span class="feat-check">✕</span> <span>Roteirização WMS + API RESTful Ilimitada</span></li>
                    </ul>
                </div>
            </div>
            <div style="padding: 0 24px 24px;">
                ${idUsuario > 0 && planoAtual === 'PADRAO' ? `
                    <button type="button" class="btn-plan-select btn-plan-current" disabled>✓ Seu Plano Atual</button>
                ` : idUsuario > 0 ? `
                    <button type="button" class="btn-plan-select btn-plan-padrao" onclick="abrirCheckoutPlano('PADRAO', 'Padrão Comercial', '199,90', '159,90')"><span>⚡</span> Assinar Plano Padrão</button>
                ` : `
                    <a href="/modulos/auth/criar_conta.php?plano=PADRAO" class="btn-plan-select btn-plan-padrao"><span>⚡</span> Começar com Padrão</a>
                `}
            </div>
        </div>

        <!-- 3. PLANO PREMIUM -->
        <div class="plan-card premium-tier ${idUsuario > 0 && planoAtual === 'PREMIUM' ? 'is-current' : ''}">
            <div>
                <div class="plan-banner banner-premium">
                    <span class="plan-ribbon">👑 Grandes Empresas • BI + WMS</span>
                    <h3>👑 Premium Enterprise</h3>
                    <span class="plan-res">Para grandes empresas: BI Executivo, WMS de Galpão e API</span>
                </div>
                <div class="plan-body">
                    <div class="plan-price-box">
                        <div class="plan-price-row">
                            <span class="plan-currency">R$</span>
                            <span class="plan-amount" data-mensal="449,90" data-anual="359,90">449,90</span>
                            <span class="plan-period">/mês</span>
                        </div>
                        <div class="plan-annual-note" data-note-mensal="Faturado mensalmente sem fidelidade" data-note-anual="R$ 4.318,80/ano (Economia de R$ 1.080,00)">
                            Faturado mensalmente sem fidelidade
                        </div>
                    </div>
                    <ul class="plan-features">
                        <li><span class="feat-check">✓</span> <span>Produtos, Clientes e Vendas <strong>ILIMITADOS</strong></span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Dashboard Power BI</strong> + Curva ABC (Pareto 80/20)</span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Endereçamento WMS</strong> + Picking List de Galpão</span></li>
                        <li><span class="feat-check">✓</span> <span>Cotação inteligente via <strong>WhatsApp Direto</strong></span></li>
                        <li><span class="feat-check">✓</span> <span>Consulta Global <strong>EAN-13</strong> + API RESTful v1</span></li>
                        <li><span class="feat-check">✓</span> <span>Balanço Mensal Financeiro + Auditoria Cega</span></li>
                        <li><span class="feat-check">✓</span> <span><strong>Operadores Ilimitados</strong> + Suporte VIP 24/7</span></li>
                    </ul>
                </div>
            </div>
            <div style="padding: 0 24px 24px;">
                ${idUsuario > 0 && planoAtual === 'PREMIUM' ? `
                    <button type="button" class="btn-plan-select btn-plan-current" disabled>👑 Seu Plano Atual (Máximo)</button>
                ` : idUsuario > 0 ? `
                    <button type="button" class="btn-plan-select btn-plan-premium" onclick="abrirCheckoutPlano('PREMIUM', 'Premium Enterprise', '449,90', '359,90')"><span>👑</span> Fazer Upgrade para Premium</button>
                ` : `
                    <a href="/modulos/auth/criar_conta.php?plano=PREMIUM" class="btn-plan-select btn-plan-premium"><span>👑</span> Começar com Premium</a>
                `}
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-bottom: 30px;">
        <div style="padding: 18px 22px; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--text);">Comparativo Completo de Recursos por Plano</h3>
            <p style="font-size: 12.5px; color: var(--text-muted);">Confira em detalhes o que cada assinatura libera para sua empresa</p>
        </div>
        <div class="table-responsive">
            <table class="data-table compare-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Recurso / Capacidade</th>
                        <th class="compare-center" style="width: 20%;">📦 Essencial</th>
                        <th class="compare-center" style="width: 20%; color: var(--primary);">⭐ Padrão</th>
                        <th class="compare-center" style="width: 20%; color: #e50914;">👑 Premium</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>Limite de Produtos (SKUs)</strong></td><td class="compare-center">Até 100 itens</td><td class="compare-center">Até 1.000 itens</td><td class="compare-center" style="color: #10b981;">Ilimitado</td></tr>
                    <tr><td><strong>Operadores Simultâneos</strong></td><td class="compare-center">1 usuário</td><td class="compare-center">Até 3 usuários</td><td class="compare-center" style="color: #10b981;">Ilimitados</td></tr>
                    <tr><td><strong>PDV com Carrinho Multi-Itens &amp; Recibo Térmico</strong></td><td class="compare-center">✅</td><td class="compare-center">✅</td><td class="compare-center">✅</td></tr>
                    <tr><td><strong>Ficha Kardex (CMP) &amp; Controle de Validade FEFO</strong></td><td class="compare-center">—</td><td class="compare-center">✅</td><td class="compare-center">✅</td></tr>
                    <tr><td><strong>Gerador de Etiquetas de Gôndola (Código de Barras)</strong></td><td class="compare-center">—</td><td class="compare-center">✅</td><td class="compare-center">✅</td></tr>
                    <tr><td><strong>Alertas Automáticos de Estoque Baixo por E-mail</strong></td><td class="compare-center">—</td><td class="compare-center">✅</td><td class="compare-center">✅</td></tr>
                    <tr><td><strong>Dashboard Executivo Power BI &amp; Curva ABC (Pareto)</strong></td><td class="compare-center">—</td><td class="compare-center">—</td><td class="compare-center">✅</td></tr>
                    <tr><td><strong>Endereçamento Logístico WMS &amp; Picking List</strong></td><td class="compare-center">—</td><td class="compare-center">—</td><td class="compare-center">✅</td></tr>
                    <tr><td><strong>API RESTful v1 + Cadastro Rápido EAN-13 Global</strong></td><td class="compare-center">—</td><td class="compare-center">—</td><td class="compare-center">✅</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-checkout-plano" class="modal-container modal-dialog-center">
    <div class="confirm-dialog-card" style="max-width: 480px; text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
            <div>
                <h3 style="margin: 0; font-size: 18px;">Finalizar Assinatura MVM</h3>
                <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">Ativação instantânea na sua conta</p>
            </div>
            <button type="button" class="btn-close-drawer" onclick="fecharCheckoutPlano()">✕</button>
        </div>

        <form action="/modulos/planos/planos.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="acao" value="assinar_plano">
            <input type="hidden" name="plano_escolhido" id="input-plano-escolhido" value="PREMIUM">
            <input type="hidden" name="ciclo_escolhido" id="input-ciclo-escolhido" value="MENSAL">

            <div style="background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Plano Selecionado</div>
                    <strong id="checkout-nome-plano" style="font-size: 16px; color: var(--text);">Premium Enterprise</strong>
                    <div id="checkout-ciclo-label" style="font-size: 11.5px; color: #10b981; font-weight: 700;">Ciclo Mensal</div>
                </div>
                <div style="text-align: right;">
                    <strong id="checkout-valor-plano" style="font-size: 22px; font-weight: 900; color: var(--primary);">R$ 449,90</strong>
                    <div style="font-size: 11px; color: var(--text-muted);">/mês</div>
                </div>
            </div>

            <div class="form-group">
                <label>Forma de Pagamento *</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1.5px solid var(--primary); border-radius: 8px; cursor: pointer; background: var(--primary-subtle);" id="lbl-metodo-pix">
                        <input type="radio" name="metodo_pagamento" value="PIX Instantâneo" checked onchange="alternarMetodoPagamento('PIX')" style="width: auto;">
                        <span style="font-weight: 700; font-size: 12.5px;">⚡ PIX (Imediato)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; cursor: pointer;" id="lbl-metodo-cartao">
                        <input type="radio" name="metodo_pagamento" value="Cartão de Crédito" onchange="alternarMetodoPagamento('CARTAO')" style="width: auto;">
                        <span style="font-weight: 700; font-size: 12.5px;">💳 Cartão Crédito</span>
                    </label>
                </div>
            </div>

            <div id="box-pagamento-pix" style="text-align: center; background: var(--bg-subtle); border: 1px dashed var(--border); border-radius: 8px; padding: 14px; margin-bottom: 18px;">
                <div style="font-size: 12px; font-weight: 700; color: var(--text); margin-bottom: 6px;">Chave PIX Copia e Cola (CNPJ MVM SaaS):</div>
                <code style="display: block; padding: 8px; background: var(--card); border: 1px solid var(--border); border-radius: 6px; font-size: 11px; color: var(--primary); word-break: break-all;">00020126580014BR.GOV.BCB.PIX0136mvm.centralestoque@gmail.com5204000053039865802BR5913MVM ESTOQUE6009SAO PAULO62070503***6304ABCD</code>
                <span style="display: block; font-size: 11px; color: var(--text-muted); margin-top: 6px;">Liberação automática após confirmação</span>
            </div>

            <div id="box-pagamento-cartao" style="display: none; margin-bottom: 18px;">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label>Número do Cartão</label>
                    <input type="text" placeholder="0000 0000 0000 0000" maxlength="19" value="4532 •••• •••• 8891" style="font-family: 'JetBrains Mono', monospace;">
                </div>
                <div class="form-row-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Validade</label>
                        <input type="text" placeholder="MM/AA" maxlength="5" value="12/29" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>CVV</label>
                        <input type="text" placeholder="123" maxlength="4" value="999" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                </div>
            </div>

            <div class="confirm-dialog-actions">
                <button type="button" class="btn-secondary" onclick="fecharCheckoutPlano()">Cancelar</button>
                <button type="submit" class="btn-primary" style="justify-content: center;">
                    <span>🚀</span> Confirmar e Ativar Plano
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let cicloSelecionado = 'MENSAL';
    function alternarCiclo(ciclo) {
        cicloSelecionado = ciclo;
        document.getElementById('btn-ciclo-mensal').classList.toggle('active', ciclo === 'MENSAL');
        document.getElementById('btn-ciclo-anual').classList.toggle('active', ciclo === 'ANUAL');
        document.querySelectorAll('.plan-amount').forEach(el => {
            el.textContent = ciclo === 'ANUAL' ? el.getAttribute('data-anual') : el.getAttribute('data-mensal');
        });
        document.querySelectorAll('.plan-annual-note').forEach(el => {
            el.textContent = ciclo === 'ANUAL' ? el.getAttribute('data-note-anual') : el.getAttribute('data-note-mensal');
        });
    }
    function abrirCheckoutPlano(codigo, nome, valorMensal, valorAnual) {
        document.getElementById('input-plano-escolhido').value = codigo;
        document.getElementById('input-ciclo-escolhido').value = cicloSelecionado;
        document.getElementById('checkout-nome-plano').textContent = 'Plano ' + nome;
        document.getElementById('checkout-ciclo-label').textContent = cicloSelecionado === 'ANUAL' ? 'Ciclo Anual (20% de desconto aplicado)' : 'Ciclo Mensal (Sem fidelidade)';
        document.getElementById('checkout-valor-plano').textContent = 'R$ ' + (cicloSelecionado === 'ANUAL' ? valorAnual : valorMensal);
        const modal = document.getElementById('modal-checkout-plano');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    function fecharCheckoutPlano() {
        const modal = document.getElementById('modal-checkout-plano');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    function alternarMetodoPagamento(metodo) {
        const isPix = metodo === 'PIX';
        document.getElementById('box-pagamento-pix').style.display = isPix ? 'block' : 'none';
        document.getElementById('box-pagamento-cartao').style.display = isPix ? 'none' : 'block';
        document.getElementById('lbl-metodo-pix').style.borderColor = isPix ? 'var(--primary)' : 'var(--border)';
        document.getElementById('lbl-metodo-pix').style.background = isPix ? 'var(--primary-subtle)' : 'transparent';
        document.getElementById('lbl-metodo-cartao').style.borderColor = !isPix ? 'var(--primary)' : 'var(--border)';
        document.getElementById('lbl-metodo-cartao').style.background = !isPix ? 'var(--primary-subtle)' : 'transparent';
    }
</script>`;

  return { html: renderLayout('Planos & Assinaturas MVM - Escolha seu Nível', 'planos.php', session, html) };
}

module.exports = {
  handleLogin,
  handleCriarConta,
  handleEsqueciSenha,
  handleRedefinirSenha,
  handlePlanos
};
