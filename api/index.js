/**
 * Front Controller Serverless Nativo (Node.js) para Vercel
 * Serve tanto os assets estáticos (CSS, JS, SVG, Fotos) com MIME type exato
 * quanto todas as rotas dinâmicas da aplicação MVM Estoque.
 */
const fs = require('fs');
const path = require('path');
const querystring = require('querystring');
const { loadDb, loadSession, serializeSessionCookie } = require('../lib/mvm_db');
const {
  handleLogin,
  handleCriarConta,
  handleEsqueciSenha,
  handleRedefinirSenha,
  handlePlanos
} = require('../lib/mvm_pages_auth');
const {
  handleDashboard,
  handleProdutos,
  handleClientes,
  handleFornecedores
} = require('../lib/mvm_pages_core');
const {
  handleMovimentos,
  handleKardex,
  handleEtiquetas,
  handleMascote,
  handleApiRest,
  handleExportacao
} = require('../lib/mvm_pages_extra');

let staticAssets = {};
try {
  staticAssets = require('../lib/mvm_static_assets.json');
} catch (e) {
  staticAssets = {};
}

const MIME_MAP = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2'
};

function tryServeStaticAsset(pathname, res) {
  const cleanPath = decodeURIComponent(pathname).replace(/\/+/g, '/');

  // 1. Verifica no bundle estático pré-compilado (garantido na Lambda da Vercel)
  const hit = staticAssets[cleanPath] || staticAssets[cleanPath.toLowerCase()];
  if (hit) {
    res.statusCode = 200;
    res.setHeader('Content-Type', hit.mime);
    res.setHeader('Cache-Control', 'public, max-age=86400');
    return res.end(Buffer.from(hit.b64, 'base64'));
  }

  // 2. Fallback no filesystem se for requisição de pasta estática
  if (/^\/(css|javaScript|img|uploads)\//i.test(cleanPath) || cleanPath === '/manifest.json' || cleanPath === '/sw.js') {
    const ext = path.extname(cleanPath).toLowerCase();
    if (MIME_MAP[ext]) {
      const candidate = path.join(path.resolve(__dirname, '..'), cleanPath.slice(1));
      if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
        res.statusCode = 200;
        res.setHeader('Content-Type', MIME_MAP[ext]);
        res.setHeader('Cache-Control', 'public, max-age=86400');
        return res.end(fs.readFileSync(candidate));
      }
    }
  }
  return false;
}

function readRequestBody(req) {
  return new Promise(resolve => {
    if (req.body && typeof req.body === 'object') {
      return resolve(req.body);
    }
    if (typeof req.body === 'string') {
      try {
        return resolve(JSON.parse(req.body));
      } catch (e) {
        return resolve(querystring.parse(req.body));
      }
    }
    let raw = '';
    req.on('data', chunk => {
      raw += chunk.toString('utf8');
    });
    req.on('end', () => {
      if (!raw) return resolve({});
      const ct = String(req.headers['content-type'] || '').toLowerCase();
      if (ct.includes('application/json')) {
        try {
          return resolve(JSON.parse(raw));
        } catch (e) {
          return resolve({});
        }
      }
      return resolve(querystring.parse(raw));
    });
    req.on('error', () => resolve({}));
  });
}

module.exports = async (req, res) => {
  const urlObj = new URL(req.url || '/', `https://${req.headers.host || 'localhost'}`);
  const pathname = urlObj.pathname.replace(/\/+/g, '/');

  // Serve imediatamente qualquer asset estático (CSS, JS, SVG, PNG, JPG, WebP)
  if (tryServeStaticAsset(pathname, res) !== false) {
    return;
  }

  const query = Object.fromEntries(urlObj.searchParams.entries());
  const body = req.method === 'POST' ? await readRequestBody(req) : {};

  const session = loadSession(req);
  const db = loadDb(session);
  const basename = pathname.split('/').filter(Boolean).pop() || 'index.php';

  let result;

  if (pathname === '/' || basename === 'index.php' || basename === 'dashboard.php' || pathname === '/api/index' || pathname === '/api/index.js') {
    result = handleDashboard(req, query, db, session);
  } else if (basename === 'login.php' || basename === 'login') {
    result = handleLogin(req, body, db, session);
  } else if (basename === 'criar_conta.php' || basename === 'criar_conta') {
    result = handleCriarConta(req, body, query, db, session);
  } else if (basename === 'esqueci_senha.php' || basename === 'esqueci_senha') {
    result = handleEsqueciSenha(req, body, db, session);
  } else if (basename === 'redefinir_senha.php' || basename === 'redefinir_senha') {
    result = handleRedefinirSenha(req, body, query, db, session);
  } else if (basename === 'logout.php' || basename === 'logout') {
    Object.keys(session).forEach(k => delete session[k]);
    result = { redirect: '/modulos/auth/login.php' };
  } else if (basename === 'planos.php' || basename === 'planos') {
    result = handlePlanos(req, body, db, session);
  } else if (['produto.php', 'produto_editar.php', 'produto_excluir.php'].includes(basename)) {
    result = handleProdutos(req, body, query, db, session, basename);
  } else if (['cliente.php', 'cliente_editar.php', 'cliente_excluir.php'].includes(basename)) {
    result = handleClientes(req, body, query, db, session, basename);
  } else if (['fornecedor.php', 'fornecedor_editar.php', 'fornecedor_excluir.php'].includes(basename)) {
    result = handleFornecedores(req, body, query, db, session, basename);
  } else if (['movimento.php', 'movimento_devolucao.php', 'movimento_excluir.php', 'recibo.php'].includes(basename)) {
    result = handleMovimentos(req, body, query, db, session, basename);
  } else if (basename === 'kardex.php' || basename === 'kardex') {
    result = handleKardex(req, query, db, session);
  } else if (basename === 'etiquetas.php' || basename === 'etiquetas') {
    result = handleEtiquetas(req, db, session);
  } else if (basename === 'mascote.php' || basename === 'mascote' || basename === 'api_docs.php') {
    result = handleMascote(session);
  } else if (basename === 'estoque.php') {
    result = handleApiRest(req, body, query, db, session);
  } else if (pathname.startsWith('/exportacao/')) {
    result = handleExportacao(pathname, query, db, session);
  } else {
    result = handleDashboard(req, query, db, session);
  }

  res.setHeader('Set-Cookie', serializeSessionCookie(session));
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');

  if (result.redirect) {
    res.statusCode = 302;
    res.setHeader('Location', result.redirect);
    return res.end();
  }

  if (result.json) {
    res.statusCode = 200;
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    return res.end(JSON.stringify(result.json, null, 2));
  }

  res.statusCode = 200;
  res.setHeader('Content-Type', 'text/html; charset=utf-8');
  res.setHeader('Content-Disposition', 'inline');
  return res.end(result.html || '');
};
