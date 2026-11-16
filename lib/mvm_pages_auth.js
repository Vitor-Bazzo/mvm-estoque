const crypto = require('crypto');
const { esc, renderLayout } = require('./mvm_layout');
const { saveDb, hashPassword, verifyPassword, addDaysStr, addDaysDateTimeStr, formatDateBR } = require('./mvm_db');

function cloneDemoDataForNewUser(db, newUserId) {
  const baseProds = db.produtos.filter(p => Number(p.idUsuario) === 1);
  const baseClients = db.clientes.filter(c => Number(c.idUsuario) === 1);
  const baseForns = db.fornecedores.filter(f => Number(f.idUsuario) === 1);
  const baseMovs = db.movimentos.filter(m => Number(m.idUsuario) === 1);

  let maxC = db.clientes.reduce((m, x) => Math.max(m, x.idCliente), 0);
  const clientMap = new Map();
  baseClients.forEach(c => {
    const nid = ++maxC;
    clientMap.set(c.idCliente, nid);
    db.clientes.push({ ...c, idCliente: nid, idUsuario: newUserId });
  });

  let maxF = db.fornecedores.reduce((m, x) => Math.max(m, x.idFornecedor), 0);
  const fornMap = new Map();
  baseForns.forEach(f => {
    const nid = ++maxF;
    fornMap.set(f.idFornecedor, nid);
    db.fornecedores.push({ ...f, idFornecedor: nid, idUsuario: newUserId });
  });

  let maxP = db.produtos.reduce((m, x) => Math.max(m, x.idProduto), 0);
  const prodMap = new Map();
  baseProds.forEach(p => {
    const nid = ++maxP;
    prodMap.set(p.idProduto, nid);
    db.produtos.push({
      ...p,
      idProduto: nid,
      idUsuario: newUserId,
      idFornecedor: p.idFornecedor ? (fornMap.get(p.idFornecedor) || p.idFornecedor) : null
    });
  });

  let maxM = db.movimentos.reduce((m, x) => Math.max(m, x.idMovimento), 0);
  baseMovs.forEach(m => {
    const nid = ++maxM;
    db.movimentos.push({
      ...m,
      idMovimento: nid,
      idUsuario: newUserId,
      idProduto: prodMap.get(m.idProduto) || m.idProduto,
      idCliente: m.idCliente ? (clientMap.get(m.idCliente) || m.idCliente) : null,
      idFornecedor: m.idFornecedor ? (fornMap.get(m.idFornecedor) || m.idFornecedor) : null
    });
  });
}

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
                <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM Estoque" style="width: 84px; height: 84px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35); object-fit: cover;">
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
                    <a href="/modulos/auth/esqueci_senha.php" style="font-size: 12.5px; font-weight: 600; color: var(--brand-primary); text-decoration: none;">Esqueceu sua senha?</a>
                </div>
                <input type="password" id="senha" name="senha" required placeholder="Digite sua senha" value="123">
            </div>
            <button type="submit" class="btn-submit" style="width: 100%; margin-top: 8px;">Entrar no Sistema</button>
        </form>

        <div class="auth-footer" style="margin-top: 22px; text-align: center; font-size: 13.5px; color: var(--text-secondary);">
            Ainda não possui uma conta? <a href="/modulos/auth/criar_conta.php" style="color: var(--brand-primary); font-weight: 700; text-decoration: none;">Cadastre-se agora</a>
        </div>
    </div>
</div>`;

  return { html: renderLayout('MVM - Acesso ao Sistema', 'login.php', session, html) };
}

function handleCriarConta(req, body, query, db, session) {
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
        cloneDemoDataForNewUser(db, novoId);
        saveDb(db);

        session.usuarioLogado = usuario;
        session.emailUsuario = email;
        session.idUsuario = novoId;
        session.senhaHash = senhaHash;
        session.planoUsuario = planoEscolhido;
        session.roleUsuario = usuario.toLowerCase() === 'admin' ? 'Administrador' : 'Operador';
        session.flash_dashboard = {
          tipo: 'sucesso',
          texto: `Conta criada com sucesso! Você foi conectado ao seu novo perfil como <strong>${esc(usuario)}</strong> no plano <strong>${esc(planoEscolhido)}</strong>.`
        };
        return { redirect: '/index.php' };
      }
    }
  }

  const html = `
<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 500px;">
        <div class="auth-header">
            <a href="/docs/mascote.php" title="Conheça o Mascote Oficial MVM" style="display: inline-block; text-decoration: none;">
                <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM Estoque" style="width: 84px; height: 84px; margin: 0 auto 12px; display: block; border-radius: 50%; box-shadow: 0 8px 24px rgba(0, 112, 74, 0.35); object-fit: cover;">
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
                <label for="plano">Plano de Assinatura Inicial (<a href="/modulos/planos/planos.php" style="color: #e50914; text-decoration: none; font-weight: 700;">Ver comparativo</a>)</label>
                <select id="plano" name="plano" style="width: 100%; padding: 11px 14px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-surface-2); color: var(--text-primary); font-weight: 600;">
                    <option value="ESSENCIAL" ${planoPre === 'ESSENCIAL' ? 'selected' : ''}>📦 Essencial — R$ 49,90/mês (Básico)</option>
                    <option value="PADRAO" ${planoPre === 'PADRAO' ? 'selected' : ''}>⭐ Padrão — R$ 99,90/mês (Mais Popular)</option>
                    <option value="PREMIUM" ${planoPre === 'PREMIUM' ? 'selected' : ''}>👑 Premium — R$ 189,90/mês (Enterprise 4K BI + WMS)</option>
                </select>
            </div>

            <div class="form-group" style="margin-top: 14px; margin-bottom: 18px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 13px; font-weight: 500; color: var(--text-secondary); cursor: pointer; line-height: 1.45; background: var(--bg-surface-2); padding: 12px 14px; border-radius: 10px; border: 1px solid var(--border-subtle);">
                    <input type="checkbox" id="aceitou_termos" name="aceitou_termos" value="1" required style="width: 18px; height: 18px; margin-top: 2px; accent-color: var(--brand-primary); cursor: pointer; flex-shrink: 0;">
                    <span>
                        Li e concordo com os <a href="javascript:void(0)" onclick="document.getElementById('modalTermosUso').style.display='flex'" style="color: var(--brand-primary); font-weight: 700; text-decoration: underline;">Termos e Condições de Uso</a> e com a <a href="javascript:void(0)" onclick="document.getElementById('modalTermosUso').style.display='flex'" style="color: var(--brand-primary); font-weight: 700; text-decoration: underline;">Política de Privacidade (LGPD)</a> da plataforma MVM Estoque. *
                    </span>
                </label>
            </div>

            <button type="submit" class="btn-submit" style="width: 100%;">Cadastrar e Acessar</button>
        </form>

        <div class="auth-footer" style="margin-top: 18px; text-align: center; font-size: 13.5px;">
            Já possui uma conta? <a href="/modulos/auth/login.php" style="color: var(--brand-primary); font-weight: 700; text-decoration: none;">Fazer Login</a>
        </div>
    </div>
</div>

<div id="modalTermosUso" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(2, 6, 23, 0.82); backdrop-filter: blur(5px); align-items: center; justify-content: center; padding: 16px;">
    <div style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 16px; max-width: 600px; width: 100%; max-height: 82vh; display: flex; flex-direction: column; overflow: hidden;">
        <div style="padding: 18px 22px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-primary);">📜 Termos e Condições de Uso &amp; LGPD</h3>
            <button type="button" onclick="document.getElementById('modalTermosUso').style.display='none'" style="background: transparent; border: none; color: var(--text-muted); font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 20px 22px; overflow-y: auto; font-size: 13px; line-height: 1.65; color: var(--text-secondary);">
            <p><strong>1. Aceitação dos Termos:</strong> Ao criar uma conta no sistema MVM Estoque, o usuário declara ter lido, compreendido e aceitado integralmente as cláusulas deste contrato de licença de software como serviço (SaaS).</p>
            <p><strong>2. Isolamento Multi-Tenant e Segurança:</strong> Cada conta opera em ambiente lógico isolado com criptografia de credenciais, proteção CSRF e rastreabilidade completa de movimentações no livro fiscal Kardex.</p>
            <p><strong>3. Proteção de Dados (Lei nº 13.709/2018 - LGPD):</strong> Os dados cadastrais de clientes, fornecedores e operadores são utilizados exclusivamente para a execução das rotinas logísticas e fiscais do próprio titular.</p>
            <p><strong>4. Planos e Assinaturas:</strong> O usuário pode alternar entre os níveis Essencial, Padrão e Premium a qualquer momento no painel de Planos &amp; Assinatura, sem multa de fidelidade.</p>
        </div>
        <div style="padding: 14px 22px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="document.getElementById('aceitou_termos').checked=true; document.getElementById('modalTermosUso').style.display='none';" class="btn-submit" style="width: auto; padding: 10px 20px;">✅ Li e Aceito os Termos</button>
        </div>
    </div>
</div>`;

  return { html: renderLayout('MVM - Criar Conta', 'criar_conta.php', session, html) };
}

function handleEsqueciSenha(req, body, db, session) {
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
      saveDb(db);
      session.recToken = token;
      session.recUserId = u.idUsuario;
      linkRedefinicao = `/modulos/auth/redefinir_senha.php?token=${token}`;
      msgSucesso = `Instruções de recuperação geradas para a conta "${u.nomeUsuario}".`;
    }
  }

  const html = `
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM" style="width: 76px; height: 76px; margin: 0 auto 12px; display: block; border-radius: 50%;">
            <h1>Recuperar Senha</h1>
            <p>Informe seu usuário ou e-mail para gerar um link seguro de redefinição</p>
        </div>

        ${msgErro ? `<div class="alert-box error" style="margin-bottom:18px;"><span>⚠️</span><p>${esc(msgErro)}</p></div>` : ''}
        ${msgSucesso ? `
            <div class="alert-box success" style="margin-bottom:18px;">
                <span>✅</span>
                <div>
                    <p style="margin:0 0 8px;">${esc(msgSucesso)}</p>
                    <a href="${esc(linkRedefinicao)}" class="btn-submit" style="display:inline-block; text-decoration:none; padding:9px 16px; font-size:13px;">🔑 Redefinir Minha Senha Agora</a>
                </div>
            </div>
        ` : ''}

        <form action="/modulos/auth/esqueci_senha.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <div class="form-group">
                <label for="identificador">Usuário ou E-mail Corporativo</label>
                <input type="text" id="identificador" name="identificador" required placeholder="Ex: admin ou admin@mvm.com" value="admin@mvm.com">
            </div>
            <button type="submit" class="btn-submit" style="width:100%;">Enviar Link de Recuperação</button>
        </form>

        <div class="auth-footer" style="margin-top: 18px; text-align: center;">
            <a href="/modulos/auth/login.php" style="color: var(--brand-primary); font-weight: 700; text-decoration: none;">← Voltar para o Login</a>
        </div>
    </div>
</div>`;

  return { html: renderLayout('MVM - Recuperar Senha', 'esqueci_senha.php', session, html) };
}

function handleRedefinirSenha(req, body, query, db, session) {
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
      saveDb(db);
      sucesso = true;
    }
  }

  const html = `
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <img src="/img/mascote_mvm.svg?v=5" alt="Mascote MVM" style="width: 76px; height: 76px; margin: 0 auto 12px; display: block; border-radius: 50%;">
            <h1>Definir Nova Senha</h1>
            <p>Crie uma nova credencial de acesso para sua conta MVM</p>
        </div>

        ${erro ? `<div class="alert-box error" style="margin-bottom:18px;"><span>⚠️</span><p>${esc(erro)}</p></div>` : ''}
        ${sucesso ? `
            <div class="alert-box success" style="margin-bottom:18px;">
                <span>🎉</span>
                <div>
                    <p style="margin:0 0 10px;">Sua senha foi redefinida com sucesso!</p>
                    <a href="/modulos/auth/login.php" class="btn-submit" style="display:inline-block; text-decoration:none; padding:9px 18px;">Ir para o Login</a>
                </div>
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
                <button type="submit" class="btn-submit" style="width:100%;">Salvar Nova Senha</button>
            </form>
        `}
    </div>
</div>`;

  return { html: renderLayout('MVM - Redefinir Senha', 'redefinir_senha.php', session, html) };
}

function handlePlanos(req, body, db, session) {
  const uid = Number(session.idUsuario) || 1;
  const userObj = db.usuarios.find(u => u.idUsuario === uid) || db.usuarios[0];

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
    saveDb(db);

    session.planoUsuario = novoPlano;
    session.cicloPlano = novoCiclo;
    session.validadePlano = novaVal;
    const nomes = {
      ESSENCIAL: 'Essencial (Básico)',
      PADRAO: 'Padrão (Comercial)',
      PREMIUM: 'Premium (Enterprise 4K BI & WMS)'
    };
    session.flash_plano = {
      tipo: 'sucesso',
      texto: `🎉 Assinatura confirmada via ${esc(metodoPagamento)}! Seu plano agora é <strong>${nomes[novoPlano]}</strong> (${novoCiclo}).`
    };
    return { redirect: '/modulos/planos/planos.php' };
  }

  const planoAtual = String(session.planoUsuario || userObj.planoAssinatura || 'PREMIUM').toUpperCase();
  const cicloAtual = String(session.cicloPlano || userObj.cicloPlano || 'MENSAL').toUpperCase();
  const validadePlano = session.validadePlano || userObj.validadePlano || addDaysStr(30);
  const flash = session.flash_plano;
  delete session.flash_plano;

  const html = `
<div class="page-container">
    ${flash ? `
        <div class="alert-box ${flash.tipo === 'sucesso' ? 'success' : 'error'}" style="margin-bottom: 22px;">
            <span>${flash.tipo === 'sucesso' ? '✨' : '⚠️'}</span>
            <p>${flash.texto}</p>
        </div>
    ` : ''}

    <div style="text-align: center; max-width: 740px; margin: 10px auto 28px;">
        <span style="display:inline-flex; align-items:center; gap:6px; padding:5px 14px; border-radius:30px; font-size:11.5px; font-weight:800; text-transform:uppercase; background:rgba(229,9,20,0.12); color:#e50914; border:1px solid rgba(229,9,20,0.3); margin-bottom:12px;">
            💎 MVM Cloud SaaS &bull; Sem Fidelidade
        </span>
        <h1 style="font-size: 30px; font-weight: 900; margin: 0 0 10px; color: var(--text-primary);">Escolha o Plano Ideal para sua Operação</h1>
        <p style="color: var(--text-secondary); font-size: 15px; margin: 0 0 18px;">Mude de plano ou cancele quando quiser. Todos os planos incluem backup em nuvem e segurança LGPD.</p>

        ${session.usuarioLogado ? `
            <div style="display:inline-flex; align-items:center; gap:12px; padding:10px 18px; border-radius:12px; background:var(--bg-surface-2); border:1px solid var(--border-subtle); font-size:13px;">
                <span>Seu Plano Atual: <strong style="color:#e50914;">${esc(planoAtual)}</strong> (${esc(cicloAtual)})</span>
                <span>&bull;</span>
                <span>Renovação: <strong>${esc(formatDateBR(validadePlano))}</strong></span>
            </div>
        ` : ''}
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 22px; margin-bottom: 36px;">
        <!-- ESSENCIAL -->
        <div style="background: var(--bg-surface); border: 2px solid ${planoAtual === 'ESSENCIAL' ? '#10b981' : 'var(--border-subtle)'}; border-radius: 18px; padding: 26px; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">📦 Entrada &amp; MEI</div>
                <h2 style="margin: 0 0 8px; font-size: 22px; color: var(--text-primary);">Essencial</h2>
                <div style="font-size: 32px; font-weight: 900; color: var(--text-primary); margin-bottom: 16px;">R$ 49,90 <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">/mês</span></div>
                <ul style="list-style: none; padding: 0; margin: 0 0 24px; font-size: 13.5px; color: var(--text-secondary); line-height: 2.1;">
                    <li>✔️ Até <strong>200 produtos</strong> no catálogo</li>
                    <li>✔️ <strong>1 operador</strong> simultâneo</li>
                    <li>✔️ Controle de Entradas e Saídas</li>
                    <li>✔️ Cadastro de Clientes e Fornecedores</li>
                    <li style="opacity:0.45;">❌ Dashboard Power BI Executivo</li>
                    <li style="opacity:0.45;">❌ API RESTful v1 &amp; Webhooks</li>
                </ul>
            </div>
            <button type="button" onclick="abrirCheckoutPlano('ESSENCIAL', 'R$ 49,90/mês')" class="btn-submit" style="width:100%; background:${planoAtual === 'ESSENCIAL' ? '#10b981' : '#334155'};">
                ${planoAtual === 'ESSENCIAL' ? '✅ Plano Atual Ativo' : 'Assinar Essencial'}
            </button>
        </div>

        <!-- PADRÃO -->
        <div style="background: var(--bg-surface); border: 2px solid #38bdf8; border-radius: 18px; padding: 26px; display: flex; flex-direction: column; justify-content: space-between; position: relative; box-shadow: 0 12px 32px rgba(56,189,248,0.14);">
            <div style="position:absolute; top:-12px; right:20px; background:#38bdf8; color:#0f172a; font-size:11px; font-weight:900; padding:4px 12px; border-radius:20px; text-transform:uppercase;">⭐ Mais Popular</div>
            <div>
                <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: #38bdf8; margin-bottom: 6px;">🚀 Varejo &amp; Distribuidoras</div>
                <h2 style="margin: 0 0 8px; font-size: 22px; color: var(--text-primary);">Padrão</h2>
                <div style="font-size: 32px; font-weight: 900; color: var(--text-primary); margin-bottom: 16px;">R$ 99,90 <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">/mês</span></div>
                <ul style="list-style: none; padding: 0; margin: 0 0 24px; font-size: 13.5px; color: var(--text-secondary); line-height: 2.1;">
                    <li>✔️ Até <strong>2.000 produtos</strong> com EAN-13</li>
                    <li>✔️ Até <strong>5 operadores</strong> simultâneos</li>
                    <li>✔️ <strong>Leitor PDV Quick Scan</strong> &amp; Etiquetas</li>
                    <li>✔️ <strong>Extrato Kardex</strong> + Exportação PDF/Excel</li>
                    <li>✔️ Controle de Lotes e Validade (FEFO)</li>
                    <li style="opacity:0.45;">❌ API RESTful v1 &amp; Multi-Filial</li>
                </ul>
            </div>
            <button type="button" onclick="abrirCheckoutPlano('PADRAO', 'R$ 99,90/mês')" class="btn-submit" style="width:100%; background:#0284c7;">
                ${planoAtual === 'PADRAO' ? '✅ Plano Atual Ativo' : 'Assinar Padrão'}
            </button>
        </div>

        <!-- PREMIUM -->
        <div style="background: var(--bg-surface); border: 2px solid #e50914; border-radius: 18px; padding: 26px; display: flex; flex-direction: column; justify-content: space-between; position: relative; box-shadow: 0 14px 36px rgba(229,9,20,0.18);">
            <div style="position:absolute; top:-12px; right:20px; background:#e50914; color:#fff; font-size:11px; font-weight:900; padding:4px 12px; border-radius:20px; text-transform:uppercase;">👑 Enterprise 4K BI</div>
            <div>
                <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: #e50914; margin-bottom: 6px;">💎 Operação Completa Irrestrita</div>
                <h2 style="margin: 0 0 8px; font-size: 22px; color: var(--text-primary);">Premium</h2>
                <div style="font-size: 32px; font-weight: 900; color: var(--text-primary); margin-bottom: 16px;">R$ 189,90 <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">/mês</span></div>
                <ul style="list-style: none; padding: 0; margin: 0 0 24px; font-size: 13.5px; color: var(--text-secondary); line-height: 2.1;">
                    <li>✔️ <strong>Produtos e Operadores Ilimitados</strong></li>
                    <li>✔️ <strong>Dashboard Power BI + Curva ABC Pareto</strong></li>
                    <li>✔️ <strong>Endereçamento WMS</strong> (Corredor-Prateleira)</li>
                    <li>✔️ <strong>Cotação Automática via WhatsApp</strong></li>
                    <li>✔️ <strong>API RESTful v1</strong> com Token Dedicado</li>
                    <li>✔️ Suporte Prioritário 24/7 &amp; Auditoria</li>
                </ul>
            </div>
            <button type="button" onclick="abrirCheckoutPlano('PREMIUM', 'R$ 189,90/mês')" class="btn-submit" style="width:100%; background:#e50914;">
                ${planoAtual === 'PREMIUM' ? '✅ Plano Atual Ativo' : 'Assinar Premium'}
            </button>
        </div>
    </div>
</div>

<div id="modalCheckoutPlano" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(2,6,23,0.82); backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:16px;">
    <div style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:16px; max-width:460px; width:100%; padding:24px;">
        <h3 style="margin:0 0 8px; font-size:18px; color:var(--text-primary);">💳 Confirmar Assinatura MVM Cloud</h3>
        <p id="resumoPlanoModal" style="margin:0 0 18px; font-size:13.5px; color:var(--text-secondary);"></p>
        <form action="/modulos/planos/planos.php" method="POST">
            <input type="hidden" name="csrf_token" value="${esc(session.csrf_token)}">
            <input type="hidden" name="acao" value="assinar_plano">
            <input type="hidden" id="inputPlanoEscolhido" name="plano_escolhido" value="PREMIUM">
            <div class="form-group">
                <label>Ciclo de Faturamento</label>
                <select name="ciclo_escolhido" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-surface-2); color:var(--text-primary);">
                    <option value="MENSAL">Mensal (Sem fidelidade)</option>
                    <option value="ANUAL">Anual (Com 20% de desconto)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Forma de Pagamento</label>
                <select name="metodo_pagamento" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-surface-2); color:var(--text-primary);">
                    <option value="PIX Instantâneo">⚡ PIX Instantâneo (Liberação Imediata)</option>
                    <option value="Cartão de Crédito">💳 Cartão de Crédito Corporativo</option>
                    <option value="Boleto Faturado">📄 Boleto Bancário PJ</option>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                <button type="button" onclick="document.getElementById('modalCheckoutPlano').style.display='none'" class="btn-secondary" style="padding:10px 16px; border-radius:8px; cursor:pointer;">Cancelar</button>
                <button type="submit" class="btn-submit" style="width:auto; padding:10px 20px; background:#e50914;">Ativar Plano Agora</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirCheckoutPlano(plano, preco) {
    document.getElementById('inputPlanoEscolhido').value = plano;
    document.getElementById('resumoPlanoModal').innerHTML = 'Plano selecionado: <strong>' + plano + '</strong> (' + preco + ')';
    document.getElementById('modalCheckoutPlano').style.display = 'flex';
}
</script>`;

  return { html: renderLayout('Planos & Assinaturas MVM', 'planos.php', session, html) };
}

module.exports = {
  handleLogin,
  handleCriarConta,
  handleEsqueciSenha,
  handleRedefinirSenha,
  handlePlanos
};
