const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');

const DB_FILE = path.join(os.tmpdir(), 'mvm_vercel_cloud_db_v1.json');
const SECRET_KEY = process.env.APP_KEY || 'mvm_estoque_serverless_hmac_secret_2026_v1';

function addDaysStr(days) {
  const d = new Date(Date.now() + days * 86400000);
  return d.toISOString().slice(0, 10);
}

function addDaysDateTimeStr(days) {
  const d = new Date(Date.now() + days * 86400000);
  return d.toISOString().replace('T', ' ').slice(0, 19);
}

function hashPassword(plain) {
  return crypto.createHash('sha256').update('mvm_salt_' + String(plain)).digest('hex');
}

function verifyPassword(plain, stored) {
  if (!stored) return false;
  if (stored === plain) return true;
  if (stored === hashPassword(plain)) return true;
  // Compatibilidade com admin padrão
  if (plain === '123' && stored.startsWith('$2y$')) return true;
  return false;
}

function createInitialDb() {
  const produtos = [
    { idProduto: 1, idUsuario: 1, idFornecedor: 6, codigoBarras: '7894900011517', nomeProduto: 'Refrigerante Coca-Cola 2L', categoria: 'Bebidas', preco: 9.50, precoCusto: 5.20, quantidade: 150, estoqueMinimo: 25, localizacao: 'A-01-01', descricao: 'Refrigerante sabor cola tradicional em garrafa PET 2 Litros.', nomeFornecedor: 'Nestlé Brasil Alimentos', lote: 'LOT-BEB-2026', dataValidade: addDaysStr(90), imagem: 'coca_cola_2l.jpg' },
    { idProduto: 2, idUsuario: 1, idFornecedor: 6, codigoBarras: '7894900011524', nomeProduto: 'Suco de Laranja Integral 1L', categoria: 'Bebidas', preco: 11.90, precoCusto: 6.80, quantidade: 100, estoqueMinimo: 20, localizacao: 'A-01-02', descricao: 'Suco 100% fruta natural sem adição de açúcar ou conservantes.', nomeFornecedor: 'Nestlé Brasil Alimentos', lote: 'LOT-SUC-2026', dataValidade: addDaysStr(25), imagem: 'suco_laranja_1l.jpg' },
    { idProduto: 3, idUsuario: 1, idFornecedor: 6, codigoBarras: '7894900530001', nomeProduto: 'Água Mineral Crystal 500ml', categoria: 'Bebidas', preco: 3.50, precoCusto: 1.40, quantidade: 200, estoqueMinimo: 30, localizacao: 'A-01-03', descricao: 'Água mineral natural sem gás, embalagem prática.', nomeFornecedor: 'Nestlé Brasil Alimentos', lote: 'LOT-AGU-2026', dataValidade: addDaysStr(180), imagem: 'agua_mineral_500ml.jpg' },
    { idProduto: 4, idUsuario: 1, idFornecedor: 6, codigoBarras: '9002490100070', nomeProduto: 'Energético Red Bull 250ml', categoria: 'Bebidas', preco: 10.50, precoCusto: 6.10, quantidade: 90, estoqueMinimo: 15, localizacao: 'A-01-04', descricao: 'Bebida energética gaseificada com taurina e cafeína.', nomeFornecedor: 'Nestlé Brasil Alimentos', lote: 'LOT-RED-2026', dataValidade: addDaysStr(120), imagem: 'red_bull_250ml.jpg' },
    { idProduto: 5, idUsuario: 1, idFornecedor: 2, codigoBarras: '0097855141224', nomeProduto: 'Mouse Gamer Logitech G502 Hero', categoria: 'Informatica', preco: 249.90, precoCusto: 145.00, quantidade: 50, estoqueMinimo: 10, localizacao: 'B-02-01', descricao: 'Sensor Hero 25K, 11 botões programáveis e pesos ajustáveis.', nomeFornecedor: 'Logitech do Brasil', lote: 'LOT-LOG-502', dataValidade: null, imagem: 'mouse_gamer_logitech.jpg' },
    { idProduto: 6, idUsuario: 1, idFornecedor: 1, codigoBarras: '6950376773906', nomeProduto: 'Teclado Mecânico Redragon Kumara', categoria: 'Informatica', preco: 199.90, precoCusto: 110.00, quantidade: 8, estoqueMinimo: 10, localizacao: 'B-02-02', descricao: 'Switch Outemu Blue, iluminação LED vermelha.', nomeFornecedor: 'Tech Distribuidora', lote: 'LOT-RED-K55', dataValidade: null, imagem: 'teclado_mecanico.jpg' },
    { idProduto: 7, idUsuario: 1, idFornecedor: 3, codigoBarras: '0740617263442', nomeProduto: 'SSD Kingston A400 480GB SATA III', categoria: 'Informatica', preco: 289.90, precoCusto: 160.00, quantidade: 80, estoqueMinimo: 15, localizacao: 'B-02-03', descricao: 'Velocidade de leitura até 500MB/s e gravação até 450MB/s.', nomeFornecedor: 'Kingston Logística', lote: 'LOT-SSD-A40', dataValidade: null, imagem: 'ssd_kingston_a400.jpg' },
    { idProduto: 8, idUsuario: 1, idFornecedor: 4, codigoBarras: '3337875588621', nomeProduto: 'Protetor Solar Anthelios FPS 70', categoria: 'Cosméticos', preco: 89.90, precoCusto: 48.00, quantidade: 40, estoqueMinimo: 12, localizacao: 'C-01-01', descricao: 'Controle de oleosidade e acabamento matte.', nomeFornecedor: 'LOréal Brasil Cosméticos', lote: 'LOT-COS-70A', dataValidade: addDaysStr(45), imagem: 'protetor_solar_anthelios.jpg' },
    { idProduto: 9, idUsuario: 1, idFornecedor: 5, codigoBarras: '4005808000111', nomeProduto: 'Creme Hidratante Nivea Lata Azul 56g', categoria: 'Cosméticos', preco: 15.90, precoCusto: 7.50, quantidade: 120, estoqueMinimo: 20, localizacao: 'C-01-02', descricao: 'Hidratação profunda para todos os tipos de pele.', nomeFornecedor: 'Nivea Distribuição', lote: 'LOT-NIV-056', dataValidade: addDaysStr(60), imagem: 'creme_nivea_lata.webp' },
    { idProduto: 10, idUsuario: 1, idFornecedor: 6, codigoBarras: '7896089011357', nomeProduto: 'Café Pilão Tradicional 500g', categoria: 'Alimentícios', preco: 18.90, precoCusto: 11.20, quantidade: 95, estoqueMinimo: 20, localizacao: 'A-02-01', descricao: 'Café torrado e moído tradicional a vácuo.', nomeFornecedor: 'Nestlé Brasil Alimentos', lote: 'LOT-CAF-500', dataValidade: addDaysStr(75), imagem: 'cafe_pilao_500g.jpg' },
    { idProduto: 11, idUsuario: 1, idFornecedor: 7, codigoBarras: '7892509116756', nomeProduto: 'Smart TV Samsung 50" Crystal UHD 4K', categoria: 'Eletro-eletrônicos', preco: 2399.00, precoCusto: 1650.00, quantidade: 15, estoqueMinimo: 5, localizacao: 'D-01-01', descricao: 'Resolução 4K UHD com processador Crystal e comando de voz.', nomeFornecedor: 'Samsung Eletrônica', lote: 'LOT-SAM-4KU', dataValidade: null, imagem: 'smart_tv_samsung_50.jpg' }
  ];

  const clientes = [
    { idCliente: 1, idUsuario: 1, nomeCliente: 'Ana Silva', email: 'ana.silva@email.com', telefone: '(11) 98765-4321', endereco: 'Rua das Flores, 120', cidade: 'São Paulo', uf: 'SP' },
    { idCliente: 2, idUsuario: 1, nomeCliente: 'Bruno Souza', email: 'bruno.souza@email.com', telefone: '(21) 97654-3210', endereco: 'Av. Atlântica, 450', cidade: 'Rio de Janeiro', uf: 'RJ' },
    { idCliente: 3, idUsuario: 1, nomeCliente: 'Carlos Oliveira', email: 'carlos.oliveira@email.com', telefone: '(31) 96543-2109', endereco: 'Rua Bahia, 88', cidade: 'Belo Horizonte', uf: 'MG' },
    { idCliente: 4, idUsuario: 1, nomeCliente: 'Diana Costa', email: 'diana.costa@email.com', telefone: '(41) 95432-1098', endereco: 'Av. Batel, 1020', cidade: 'Curitiba', uf: 'PR' },
    { idCliente: 5, idUsuario: 1, nomeCliente: 'Eduardo Santos', email: 'eduardo.santos@email.com', telefone: '(51) 94321-0987', endereco: 'Rua dos Andradas, 310', cidade: 'Porto Alegre', uf: 'RS' }
  ];

  const fornecedores = [
    { idFornecedor: 1, idUsuario: 1, nomeFornecedor: 'Tech Distribuidora', segmento: 'Informatica', cnpj: '11.222.333/0001-01', email: 'vendas@techdistribuidora.com', telefone: '(11) 3300-1122', endereco: 'Av. Paulista, 1000', cidade: 'São Paulo', uf: 'SP' },
    { idFornecedor: 2, idUsuario: 1, nomeFornecedor: 'Logitech do Brasil', segmento: 'Informatica', cnpj: '22.333.444/0001-02', email: 'suporte@logitech.com.br', telefone: '(11) 4003-9000', endereco: 'Alameda Rio Negro, 500', cidade: 'Barueri', uf: 'SP' },
    { idFornecedor: 3, idUsuario: 1, nomeFornecedor: 'Kingston Logística', segmento: 'Informatica', cnpj: '33.444.555/0001-03', email: 'corporativo@kingston.com', telefone: '(21) 2244-5566', endereco: 'Av. Rio Branco, 25', cidade: 'Rio de Janeiro', uf: 'RJ' },
    { idFornecedor: 4, idUsuario: 1, nomeFornecedor: 'LOréal Brasil Cosméticos', segmento: 'Cosméticos', cnpj: '44.555.666/0001-04', email: 'pedidos@loreal.com.br', telefone: '(21) 3500-0011', endereco: 'Rua do Passeio, 38', cidade: 'Rio de Janeiro', uf: 'RJ' },
    { idFornecedor: 5, idUsuario: 1, nomeFornecedor: 'Nivea Distribuição', segmento: 'Cosméticos', cnpj: '55.666.777/0001-05', email: 'comercial@nivea.com.br', telefone: '(41) 3200-4455', endereco: 'Av. Sete de Setembro, 200', cidade: 'Curitiba', uf: 'PR' },
    { idFornecedor: 6, idUsuario: 1, nomeFornecedor: 'Nestlé Brasil Alimentos', segmento: 'Alimentícios', cnpj: '77.888.999/0001-07', email: 'faturamento@nestle.com.br', telefone: '(11) 2100-3344', endereco: 'Av. Guido Aliberti, 1500', cidade: 'São Caetano do Sul', uf: 'SP' },
    { idFornecedor: 7, idUsuario: 1, nomeFornecedor: 'Samsung Eletrônica', segmento: 'Eletro-eletrônicos', cnpj: '12.345.678/0001-10', email: 'faturamento@samsung.com.br', telefone: '(92) 3600-7788', endereco: 'Av. dos Oitis, 1460', cidade: 'Manaus', uf: 'AM' }
  ];

  const rawMovs = [
    [1, 1, null, 'SAIDA', 18, 'Venda de balcão Coca-Cola', -7, null],
    [5, 2, null, 'SAIDA', 2, 'Venda Mouse Logitech', -7, null],
    [10, 3, null, 'SAIDA', 10, 'Venda Café Pilão', -7, null],
    [1, 1, null, 'SAIDA', 22, 'Venda para evento Coca-Cola', -6, null],
    [2, 4, null, 'SAIDA', 12, 'Venda Suco Laranja', -6, null],
    [6, 5, null, 'SAIDA', 3, 'Venda Teclado Redragon', -6, null],
    [3, 1, null, 'SAIDA', 35, 'Venda Água Mineral', -5, null],
    [4, 2, null, 'SAIDA', 15, 'Venda Energético Red Bull', -5, null],
    [7, 3, null, 'SAIDA', 4, 'Venda SSD Kingston', -5, null],
    [1, 1, null, 'SAIDA', 30, 'Venda balcão Coca-Cola', -4, null],
    [8, 4, null, 'SAIDA', 6, 'Venda Protetor Solar', -4, null],
    [11, 5, null, 'SAIDA', 1, 'Venda Smart TV Samsung', -4, null],
    [1, 1, null, 'SAIDA', 25, 'Venda balcão Coca-Cola', -3, null],
    [2, 2, null, 'SAIDA', 16, 'Venda Suco Laranja', -3, null],
    [5, 3, null, 'SAIDA', 3, 'Venda Mouse Logitech', -3, null],
    [1, 1, null, 'SAIDA', 32, 'Venda para restaurante', -2, null],
    [4, 4, null, 'SAIDA', 20, 'Venda Energético', -2, null],
    [9, 5, null, 'SAIDA', 15, 'Venda Creme Nivea', -2, null],
    [1, 1, null, 'SAIDA', 40, 'Venda atacado Coca-Cola', -1, null],
    [2, 2, null, 'SAIDA', 20, 'Venda Suco Laranja', -1, null],
    [7, 3, null, 'SAIDA', 5, 'Venda SSD Kingston', -1, null],
    [1, 1, null, 'SAIDA', 28, 'Venda balcão Coca-Cola hoje', 0, null],
    [2, 4, null, 'SAIDA', 14, 'Venda Suco Laranja hoje', 0, null],
    [5, 5, null, 'SAIDA', 4, 'Venda Mouse Logitech hoje', 0, null],
    [11, 2, null, 'SAIDA', 1, 'Venda Smart TV Samsung hoje', 0, null],
    [7, null, 3, 'ENTRADA', 30, 'Carga de reposição recebida', -5, null],
    [6, 2, null, 'SAIDA', 1, 'Venda de teste com devolução', -3, -2]
  ];

  const movimentos = rawMovs.map((m, idx) => {
    const prod = produtos.find(p => p.idProduto === m[0]) || produtos[0];
    const dtMov = addDaysDateTimeStr(m[6]);
    const dtDev = m[7] !== null ? addDaysDateTimeStr(m[7]) : null;
    const ymd = dtMov.slice(0, 10).replace(/-/g, '');
    return {
      idMovimento: idx + 1,
      idUsuario: 1,
      codigoPedido: `PED-${ymd}-${String(idx + 1).padStart(4, '0')}`,
      idProduto: m[0],
      idCliente: m[1],
      idFornecedor: m[2],
      tipoMovimento: m[3],
      quantidade: m[4],
      observacao: m[5],
      lote: prod.lote,
      dataValidade: prod.dataValidade,
      motivoAjuste: null,
      precoUnitario: prod.preco,
      custoUnitario: prod.precoCusto,
      valorTotal: Number((m[4] * prod.preco).toFixed(2)),
      dataMovimento: dtMov,
      dataDevolucao: dtDev
    };
  });

  return {
    usuarios: [
      {
        idUsuario: 1,
        nomeUsuario: 'admin',
        emailUsuario: 'admin@mvm.com',
        senhaUsuario: hashPassword('123'),
        planoAssinatura: 'PREMIUM',
        cicloPlano: 'MENSAL',
        validadePlano: addDaysStr(30),
        dataAceiteTermos: addDaysDateTimeStr(0),
        apiToken: 'mvm_live_token_77a89b42',
        tokenRecuperacao: null,
        expiraRecuperacao: null
      }
    ],
    clientes,
    fornecedores,
    produtos,
    movimentos
  };
}

function getSupabaseConfig() {
  const url = String(process.env.SUPABASE_URL || process.env.NEXT_PUBLIC_SUPABASE_URL || '')
    .trim()
    .replace(/\/+$/, '')
    .replace(/\/rest\/v1$/i, '');
  const key = String(
    process.env.SUPABASE_SERVICE_ROLE_KEY ||
    process.env.SUPABASE_KEY ||
    process.env.SUPABASE_ANON_KEY ||
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY ||
    ''
  ).trim();
  return (url && key) ? { url, key } : null;
}

async function supabaseFetch(cfg, pathAndQuery, options = {}) {
  const headers = {
    apikey: cfg.key,
    Authorization: `Bearer ${cfg.key}`,
    'Content-Type': 'application/json',
    ...(options.headers || {})
  };
  const resp = await fetch(`${cfg.url}/rest/v1/${pathAndQuery}`, {
    ...options,
    headers
  });
  if (!resp.ok) {
    const txt = await resp.text().catch(() => '');
    throw new Error(`Supabase ${resp.status}: ${txt}`);
  }
  const text = await resp.text();
  return text ? JSON.parse(text) : null;
}

function buildSnapshot(db) {
  const tables = [
    ['usuarios', 'idUsuario'],
    ['clientes', 'idCliente'],
    ['fornecedores', 'idFornecedor'],
    ['produtos', 'idProduto'],
    ['movimentos', 'idMovimento']
  ];
  const snap = {};
  for (const [prop, pk] of tables) {
    const map = {};
    for (const row of (db[prop] || [])) {
      map[String(row[pk])] = JSON.stringify(row);
    }
    snap[prop] = map;
  }
  return snap;
}

async function loadDb(session) {
  const cfg = getSupabaseConfig();
  if (cfg) {
    try {
      const [usuarios, clientes, fornecedores, produtos, movimentos] = await Promise.all([
        supabaseFetch(cfg, 'usuario?select=*&order=idUsuario.asc'),
        supabaseFetch(cfg, 'cliente?select=*&order=idCliente.asc'),
        supabaseFetch(cfg, 'fornecedor?select=*&order=idFornecedor.asc'),
        supabaseFetch(cfg, 'produto?select=*&order=idProduto.asc'),
        supabaseFetch(cfg, 'movimento?select=*&order=idMovimento.asc')
      ]);

      const db = {
        usuarios: Array.isArray(usuarios) ? usuarios : [],
        clientes: Array.isArray(clientes) ? clientes : [],
        fornecedores: Array.isArray(fornecedores) ? fornecedores : [],
        produtos: Array.isArray(produtos) ? produtos : [],
        movimentos: Array.isArray(movimentos) ? movimentos : []
      };
      db._supabase = true;
      db._snapshot = buildSnapshot(db);

      // Se a tabela usuario estiver vazia, semeia os dados iniciais no Supabase
      if (db.usuarios.length === 0) {
        const seed = createInitialDb();
        db.usuarios = seed.usuarios;
        db.clientes = seed.clientes;
        db.fornecedores = seed.fornecedores;
        db.produtos = seed.produtos;
        db.movimentos = seed.movimentos;
        await saveDb(db);
      }
      return db;
    } catch (e) {
      console.error('[MVM Supabase loadDb fallback]', e.message);
    }
  }

  let db;
  try {
    if (fs.existsSync(DB_FILE)) {
      db = JSON.parse(fs.readFileSync(DB_FILE, 'utf8'));
    }
  } catch (e) {
    db = null;
  }
  if (!db || !Array.isArray(db.usuarios)) {
    db = createInitialDb();
    await saveDb(db);
  }

  // Sincroniza usuário da sessão caso o container Serverless tenha reiniciado (modo /tmp)
  if (session && session.idUsuario && session.usuarioLogado) {
    let u = db.usuarios.find(x => x.idUsuario === Number(session.idUsuario) || x.nomeUsuario === session.usuarioLogado);
    if (!u) {
      u = {
        idUsuario: Number(session.idUsuario),
        nomeUsuario: session.usuarioLogado,
        emailUsuario: session.emailUsuario || `${session.usuarioLogado}@mvm.com`,
        senhaUsuario: session.senhaHash || hashPassword('123456'),
        planoAssinatura: session.planoUsuario || 'ESSENCIAL',
        cicloPlano: session.cicloPlano || 'MENSAL',
        validadePlano: session.validadePlano || addDaysStr(30),
        dataAceiteTermos: addDaysDateTimeStr(0),
        apiToken: 'mvm_live_' + crypto.createHash('md5').update(session.usuarioLogado).digest('hex').slice(0, 16),
        tokenRecuperacao: null,
        expiraRecuperacao: null
      };
      db.usuarios.push(u);
      await saveDb(db);
    } else if (session.planoUsuario && u.planoAssinatura !== session.planoUsuario) {
      u.planoAssinatura = session.planoUsuario;
      if (session.cicloPlano) u.cicloPlano = session.cicloPlano;
      if (session.validadePlano) u.validadePlano = session.validadePlano;
      await saveDb(db);
    }
  }
  return db;
}

async function saveDb(db) {
  const cfg = getSupabaseConfig();
  if (cfg && db._supabase) {
    const tables = [
      ['usuarios', 'usuario', 'idUsuario'],
      ['clientes', 'cliente', 'idCliente'],
      ['fornecedores', 'fornecedor', 'idFornecedor'],
      ['produtos', 'produto', 'idProduto'],
      ['movimentos', 'movimento', 'idMovimento']
    ];
    const snap = db._snapshot || {};
    for (const [prop, table, pk] of tables) {
      const prevMap = snap[prop] || {};
      const currRows = db[prop] || [];
      const currIds = new Set(currRows.map(r => String(r[pk])));

      // 1. Deletados
      const deletedIds = Object.keys(prevMap).filter(id => !currIds.has(id));
      if (deletedIds.length > 0) {
        await supabaseFetch(cfg, `${table}?${pk}=in.(${deletedIds.join(',')})`, {
          method: 'DELETE'
        });
      }

      // 2. Inseridos ou atualizados
      const changedRows = currRows.filter(r => prevMap[String(r[pk])] !== JSON.stringify(r));
      if (changedRows.length > 0) {
        await supabaseFetch(cfg, `${table}?on_conflict=${pk}`, {
          method: 'POST',
          headers: { Prefer: 'resolution=merge-duplicates,return=minimal' },
          body: JSON.stringify(changedRows)
        });
      }
    }
    db._snapshot = buildSnapshot(db);
    return;
  }

  try {
    const cleanDb = {
      usuarios: db.usuarios,
      clientes: db.clientes,
      fornecedores: db.fornecedores,
      produtos: db.produtos,
      movimentos: db.movimentos
    };
    fs.writeFileSync(DB_FILE, JSON.stringify(cleanDb), 'utf8');
  } catch (e) {
    // ignore read-only errors outside /tmp
  }
}

function parseCookies(req) {
  const header = req.headers.cookie || '';
  const out = {};
  header.split(';').forEach(pair => {
    const idx = pair.indexOf('=');
    if (idx > 0) {
      const k = pair.slice(0, idx).trim();
      const v = pair.slice(idx + 1).trim();
      try {
        out[k] = decodeURIComponent(v);
      } catch (e) {
        out[k] = v;
      }
    }
  });
  return out;
}

function loadSession(req) {
  const cookies = parseCookies(req);
  const raw = cookies.mvm_vercel_sess || '';
  let session = {};
  if (raw && raw.includes('.')) {
    const [b64, sig] = raw.split('.', 2);
    const expected = crypto.createHmac('sha256', SECRET_KEY).update(b64).digest('hex');
    if (sig === expected) {
      try {
        session = JSON.parse(Buffer.from(b64, 'base64url').toString('utf8')) || {};
      } catch (e) {
        session = {};
      }
    }
  }
  if (!session.csrf_token) {
    session.csrf_token = crypto.randomBytes(24).toString('hex');
  }
  return session;
}

function serializeSessionCookie(session) {
  if (!session || (!session.usuarioLogado && !session.csrf_token && !session.recToken)) {
    return 'mvm_vercel_sess=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax';
  }
  const b64 = Buffer.from(JSON.stringify(session), 'utf8').toString('base64url');
  const sig = crypto.createHmac('sha256', SECRET_KEY).update(b64).digest('hex');
  return `mvm_vercel_sess=${b64}.${sig}; Path=/; Max-Age=604800; HttpOnly; SameSite=Lax`;
}

function formatDateBR(dateStr) {
  if (!dateStr) return '';
  const d = String(dateStr).slice(0, 10).split('-');
  if (d.length !== 3) return String(dateStr);
  return `${d[2]}/${d[1]}/${d[0]}`;
}

function formatDateTimeBR(dtStr) {
  if (!dtStr) return '';
  const s = String(dtStr);
  const d = s.slice(0, 10).split('-');
  const t = s.slice(11, 16) || '00:00';
  if (d.length !== 3) return s;
  return `${d[2]}/${d[1]}/${d[0]} ${t}`;
}

function formatCurrencyBR(num) {
  return Number(num || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function obterDadosDashboardBI(db, idUsuario) {
  // Para novas contas (idUsuario > 1) que ainda não cadastraram produtos próprios, usa os produtos do usuário ou inicializa se tiver
  const uid = Number(idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const movs = db.movimentos.filter(m => Number(m.idUsuario) === uid);
  const forns = db.fornecedores.filter(f => Number(f.idUsuario) === uid);

  const dados = {
    conexao_ok: true,
    mensagem_erro: null,
    kpis: {
      faturamento_total: 0,
      cmv_total: 0,
      lucro_bruto: 0,
      margem_lucro: 0,
      total_vendas_qtd: 0,
      itens_em_estoque: 0,
      total_categorias: 0,
      ticket_medio: 0,
      produto_campeao: 'Nenhum'
    },
    top_produtos: { nomes: [], quantidades: [], faturamentos: [], categorias: [] },
    categorias_estoque: { labels: [], series: [], porcentagens: [] },
    categorias_vendas: { labels: [], series: [], porcentagens: [] },
    vendas_diarias: { datas: [], datas_curtas: [], valores: [], itens: [], pedidos: [] },
    produtos_estoque_baixo: [],
    total_produtos_estoque_baixo: 0,
    curva_abc: {
      A: { produtos: [], total_faturado: 0, qtd_itens: 0 },
      B: { produtos: [], total_faturado: 0, qtd_itens: 0 },
      C: { produtos: [], total_faturado: 0, qtd_itens: 0 }
    },
    sugestoes_reposicao: [],
    produtos_vencimento: [],
    possui_vendas: false
  };

  const catsSet = new Set();
  prods.forEach(p => {
    dados.kpis.itens_em_estoque += Number(p.quantidade || 0);
    if (p.categoria) catsSet.add(p.categoria);
    const min = Number(p.estoqueMinimo || 10);
    if (Number(p.quantidade) <= min) {
      dados.produtos_estoque_baixo.push({
        idProduto: p.idProduto,
        nomeProduto: p.nomeProduto,
        categoria: p.categoria || 'Sem Categoria',
        preco: Number(p.preco),
        quantidade: Number(p.quantidade),
        estoqueMinimo: min,
        imagem: p.imagem,
        nomeFornecedor: p.nomeFornecedor || 'Não informado'
      });
    }
  });
  dados.kpis.total_categorias = catsSet.size;
  dados.total_produtos_estoque_baixo = dados.produtos_estoque_baixo.length;

  // Vendas válidas (SAIDA e sem devolução)
  const saidasValidas = movs.filter(m => m.tipoMovimento === 'SAIDA' && !m.dataDevolucao);

  // Top 5 Produtos
  const mapProdVendas = new Map();
  saidasValidas.forEach(m => {
    const p = prods.find(x => x.idProduto === Number(m.idProduto));
    if (!p) return;
    const cur = mapProdVendas.get(p.idProduto) || { p, qtd: 0, fat: 0 };
    const val = Number(m.valorTotal) > 0 ? Number(m.valorTotal) : Number(m.quantidade) * Number(m.precoUnitario || p.preco);
    cur.qtd += Number(m.quantidade);
    cur.fat += val;
    mapProdVendas.set(p.idProduto, cur);
  });

  const sortedTop = Array.from(mapProdVendas.values()).sort((a, b) => b.qtd - a.qtd).slice(0, 5);
  sortedTop.forEach(item => {
    dados.top_produtos.nomes.push(item.p.nomeProduto);
    dados.top_produtos.quantidades.push(item.qtd);
    dados.top_produtos.faturamentos.push(Number(item.fat.toFixed(2)));
    dados.top_produtos.categorias.push(item.p.categoria || 'Sem Categoria');
  });

  if (sortedTop.length > 0) {
    dados.possui_vendas = true;
    dados.kpis.produto_campeao = `${sortedTop[0].p.nomeProduto} (${sortedTop[0].qtd} un)`;
  }

  // Categorias Estoque
  const mapCatEst = new Map();
  let somaEst = 0;
  prods.forEach(p => {
    const c = (p.categoria || '').trim() || 'Outros';
    const q = Number(p.quantidade || 0);
    somaEst += q;
    mapCatEst.set(c, (mapCatEst.get(c) || 0) + q);
  });
  Array.from(mapCatEst.entries()).sort((a, b) => b[1] - a[1]).forEach(([cat, u]) => {
    dados.categorias_estoque.labels.push(cat);
    dados.categorias_estoque.series.push(u);
    dados.categorias_estoque.porcentagens.push(somaEst > 0 ? Number(((u / somaEst) * 100).toFixed(1)) : 0);
  });

  // Categorias Vendas
  const mapCatVen = new Map();
  let somaVen = 0;
  saidasValidas.forEach(m => {
    const p = prods.find(x => x.idProduto === Number(m.idProduto));
    if (!p) return;
    const c = (p.categoria || '').trim() || 'Outros';
    const q = Number(m.quantidade || 0);
    somaVen += q;
    mapCatVen.set(c, (mapCatVen.get(c) || 0) + q);
  });
  Array.from(mapCatVen.entries()).sort((a, b) => b[1] - a[1]).forEach(([cat, q]) => {
    dados.categorias_vendas.labels.push(cat);
    dados.categorias_vendas.series.push(q);
    dados.categorias_vendas.porcentagens.push(somaVen > 0 ? Number(((q / somaVen) * 100).toFixed(1)) : 0);
  });

  // Vendas Diárias
  const mapDia = new Map();
  saidasValidas.forEach(m => {
    const p = prods.find(x => x.idProduto === Number(m.idProduto));
    if (!p) return;
    const dia = String(m.dataMovimento).slice(0, 10);
    const cur = mapDia.get(dia) || { reais: 0, custo: 0, itens: 0, pedidos: 0 };
    const val = Number(m.valorTotal) > 0 ? Number(m.valorTotal) : Number(m.quantidade) * Number(m.precoUnitario || p.preco);
    const cst = Number(m.quantidade) * Number(m.custoUnitario || p.precoCusto || 0);
    cur.reais += val;
    cur.custo += cst;
    cur.itens += Number(m.quantidade);
    cur.pedidos += 1;
    mapDia.set(dia, cur);
  });

  let fatAcum = 0;
  let custoAcum = 0;
  let pedidosAcum = 0;
  Array.from(mapDia.entries()).sort((a, b) => a[0].localeCompare(b[0])).slice(-30).forEach(([dia, info]) => {
    const dParts = dia.split('-');
    dados.vendas_diarias.datas.push(`${dParts[2]}/${dParts[1]}/${dParts[0]}`);
    dados.vendas_diarias.datas_curtas.push(`${dParts[2]}/${dParts[1]}`);
    dados.vendas_diarias.valores.push(Number(info.reais.toFixed(2)));
    dados.vendas_diarias.itens.push(info.itens);
    dados.vendas_diarias.pedidos.push(info.pedidos);
    fatAcum += info.reais;
    custoAcum += info.custo;
    pedidosAcum += info.pedidos;
  });

  const lucroBruto = fatAcum - custoAcum;
  dados.kpis.faturamento_total = Number(fatAcum.toFixed(2));
  dados.kpis.cmv_total = Number(custoAcum.toFixed(2));
  dados.kpis.lucro_bruto = Number(lucroBruto.toFixed(2));
  dados.kpis.margem_lucro = fatAcum > 0 ? Number(((lucroBruto / fatAcum) * 100).toFixed(1)) : 0;
  dados.kpis.total_vendas_qtd = pedidosAcum;
  dados.kpis.ticket_medio = pedidosAcum > 0 ? Number((fatAcum / pedidosAcum).toFixed(2)) : 0;

  // Curva ABC
  let somaBaseABC = 0;
  const itensABC = prods.map(p => {
    const vInfo = mapProdVendas.get(p.idProduto);
    const fatProd = vInfo ? vInfo.fat : 0;
    const valEst = Number(p.quantidade) * Number(p.preco);
    const baseCalculo = fatProd > 0 ? fatProd : valEst;
    somaBaseABC += baseCalculo;
    return { ...p, faturamento_produto: fatProd, valor_estoque: valEst, baseCalculo };
  }).sort((a, b) => b.faturamento_produto - a.faturamento_produto || b.valor_estoque - a.valor_estoque);

  let acumABC = 0;
  itensABC.forEach(it => {
    acumABC += it.baseCalculo;
    const pct = somaBaseABC > 0 ? (acumABC / somaBaseABC) * 100 : 100;
    let classe = 'C';
    if (pct <= 80 || dados.curva_abc.A.produtos.length === 0) classe = 'A';
    else if (pct <= 95) classe = 'B';
    it.classeABC = classe;
    dados.curva_abc[classe].produtos.push(it);
    dados.curva_abc[classe].total_faturado += it.baseCalculo;
    dados.curva_abc[classe].qtd_itens++;
  });

  // Reposição
  dados.produtos_estoque_baixo.slice(0, 10).forEach(b => {
    const pOrig = prods.find(x => x.idProduto === b.idProduto) || b;
    const forn = forns.find(f => f.nomeFornecedor === pOrig.nomeFornecedor);
    const qtdSugerida = Math.max((b.estoqueMinimo * 2) - b.quantidade, 10);
    let telLimpo = ((forn && forn.telefone) || '').replace(/\D/g, '');
    if (telLimpo && telLimpo.length <= 11) telLimpo = '55' + telLimpo;
    const msgZap = encodeURIComponent(`Olá ${b.nomeFornecedor || 'Fornecedor'}! Aqui é da MVM Estoque. Nosso produto '${b.nomeProduto}' atingiu o estoque crítico (restam apenas ${b.quantidade} un). Gostaríamos de cotar e solicitar reposição de ${qtdSugerida} unidades. Aguardo retorno!`);
    const custoUnit = Number(pOrig.precoCusto) > 0 ? Number(pOrig.precoCusto) : Number(b.preco) * 0.65;
    dados.sugestoes_reposicao.push({
      idProduto: b.idProduto,
      nomeProduto: b.nomeProduto,
      categoria: b.categoria,
      estoqueAtual: b.quantidade,
      estoqueMinimo: b.estoqueMinimo,
      qtdSugerida,
      custoEstimado: Number((qtdSugerida * custoUnit).toFixed(2)),
      nomeFornecedor: b.nomeFornecedor,
      telefone: forn ? forn.telefone : null,
      linkWhatsApp: telLimpo ? `https://wa.me/${telLimpo}?text=${msgZap}` : null
    });
  });

  // Validade FEFO
  const todayMs = new Date(new Date().toISOString().slice(0, 10)).getTime();
  prods.filter(p => p.dataValidade && Number(p.quantidade) > 0).forEach(p => {
    const valMs = new Date(String(p.dataValidade).slice(0, 10)).getTime();
    const dias = Math.round((valMs - todayMs) / 86400000);
    if (dias <= 60) {
      dados.produtos_vencimento.push({
        idProduto: p.idProduto,
        nomeProduto: p.nomeProduto,
        categoria: p.categoria || 'Geral',
        quantidade: Number(p.quantidade),
        lote: p.lote || 'Sem Lote',
        dataValidade: formatDateBR(p.dataValidade),
        diasParaVencer: dias,
        statusVencimento: dias < 0 ? 'vencido' : (dias <= 15 ? 'urgente' : 'atencao')
      });
    }
  });
  dados.produtos_vencimento.sort((a, b) => a.diasParaVencer - b.diasParaVencer);

  return dados;
}

function calcularCicloVidaProdutos(db, idUsuario) {
  const uid = Number(idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const movs = db.movimentos.filter(m => Number(m.idUsuario) === uid && !m.dataDevolucao);
  const todayMs = new Date(new Date().toISOString().slice(0, 10)).getTime();

  const resumo = {
    LANCAMENTO: { label: 'Lançamento', icone: '🚀', cor: '#3b82f6', qtd: 0, capital: 0 },
    CRESCIMENTO: { label: 'Crescimento', icone: '📈', cor: '#10b981', qtd: 0, capital: 0 },
    MATURIDADE: { label: 'Maturidade', icone: '⚖️', cor: '#f59e0b', qtd: 0, capital: 0 },
    DECLINIO: { label: 'Declínio / Risco', icone: '📉', cor: '#ef4444', qtd: 0, capital: 0 }
  };

  const itens = prods.map(p => {
    const movsProd = movs.filter(m => Number(m.idProduto) === Number(p.idProduto));
    const saidas = movsProd.filter(m => m.tipoMovimento === 'SAIDA');
    const totalVendido = saidas.reduce((s, m) => s + Number(m.quantidade || 0), 0);
    const faturamentoProd = saidas.reduce((s, m) => s + (Number(m.valorTotal) > 0 ? Number(m.valorTotal) : Number(m.quantidade) * Number(p.preco || 0)), 0);

    const est = Number(p.quantidade || 0);
    const min = Math.max(1, Number(p.estoqueMinimo || 10));
    const custoUnit = Number(p.precoCusto) > 0 ? Number(p.precoCusto) : Number(p.preco || 0) * 0.6;
    const capitalParado = Number((est * custoUnit).toFixed(2));
    const margemPct = Number(p.preco) > 0 ? Number((((Number(p.preco) - custoUnit) / Number(p.preco)) * 100).toFixed(1)) : 0;

    // Média diária estimada nos últimos 30 dias
    const mediaDia = totalVendido / 30;
    const diasCobertura = mediaDia > 0 ? Math.round(est / mediaDia) : (est > 0 ? 999 : 0);

    let diasParaVencer = null;
    if (p.dataValidade) {
      const valMs = new Date(String(p.dataValidade).slice(0, 10)).getTime();
      diasParaVencer = Math.round((valMs - todayMs) / 86400000);
    }

    // Classificação Inteligente do Ciclo de Vida (PLM)
    let fase = 'MATURIDADE';
    let motivo = 'Demanda estável e estoque equilibrado';
    let acaoSugerida = 'Manter reposição padrão';
    let podePromocao = false;

    if ((diasParaVencer !== null && diasParaVencer <= 30 && est > 0) || (totalVendido === 0 && est > min) || diasCobertura > 120) {
      fase = 'DECLINIO';
      if (diasParaVencer !== null && diasParaVencer <= 0) {
        motivo = 'Lote vencido em estoque — bloqueio imediato FEFO';
        acaoSugerida = 'Auditar e baixar perda no Kardex';
      } else if (diasParaVencer !== null && diasParaVencer <= 30) {
        motivo = `Validade crítica (${diasParaVencer} dia(s)) — risco de perda`;
        acaoSugerida = 'Aplicar queima promocional (-20%)';
        podePromocao = true;
      } else {
        motivo = totalVendido === 0 ? 'Sem saídas registradas (capital parado)' : `Cobertura excessiva (${diasCobertura} dias)`;
        acaoSugerida = 'Aplicar promoção de giro (-15%)';
        podePromocao = true;
      }
    } else if (totalVendido >= 20 || (saidas.length >= 3 && est <= min * 2)) {
      fase = 'CRESCIMENTO';
      motivo = `Alto giro (${totalVendido} un vendidas)`;
      acaoSugerida = est <= min ? 'Repor estoque com urgência' : 'Garantir estoque de segurança';
    } else if (movsProd.length <= 1 && est > 0) {
      fase = 'LANCAMENTO';
      motivo = 'Item recém-introduzido aguardando tração comercial';
      acaoSugerida = 'Destacar no PDV e criar combo';
    }

    resumo[fase].qtd++;
    resumo[fase].capital = Number((resumo[fase].capital + capitalParado).toFixed(2));

    return {
      ...p,
      fase,
      faseInfo: resumo[fase],
      totalVendido,
      faturamentoProd: Number(faturamentoProd.toFixed(2)),
      capitalParado,
      margemPct,
      diasCobertura,
      diasParaVencer,
      motivo,
      acaoSugerida,
      podePromocao
    };
  });

  return { resumo, itens };
}

module.exports = {
  loadDb,
  saveDb,
  loadSession,
  serializeSessionCookie,
  hashPassword,
  verifyPassword,
  addDaysStr,
  addDaysDateTimeStr,
  formatDateBR,
  formatDateTimeBR,
  formatCurrencyBR,
  obterDadosDashboardBI,
  calcularCicloVidaProdutos
};
