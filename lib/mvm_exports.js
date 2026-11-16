const { esc } = require('./mvm_layout');
const { formatDateBR, formatDateTimeBR, formatCurrencyBR } = require('./mvm_db');

function sendCsvExcel(filename, headers, rows) {
  const bom = '\uFEFF';
  const escapeCsv = v => {
    const s = String(v ?? '').replace(/"/g, '""');
    return `"${s}"`;
  };
  const lines = [headers.map(escapeCsv).join(';')];
  rows.forEach(r => lines.push(r.map(escapeCsv).join(';')));
  return {
    download: {
      filename,
      contentType: 'text/csv; charset=utf-8',
      body: bom + lines.join('\r\n')
    }
  };
}

function handleExportacao(pathname, query, db, session) {
  const uid = Number(session.idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const clientes = db.clientes.filter(c => Number(c.idUsuario) === uid);
  const forns = db.fornecedores.filter(f => Number(f.idUsuario) === uid);
  const movs = db.movimentos.filter(m => Number(m.idUsuario) === uid);

  const file = pathname.split('/').pop();

  if (file === 'exportar_clientes.php') {
    return sendCsvExcel(
      'clientes_mvm.csv',
      ['ID', 'Nome', 'Email', 'Telefone', 'Cidade', 'UF', 'Endereco'],
      clientes.map(c => [c.idCliente, c.nomeCliente, c.email, c.telefone, c.cidade, c.uf, c.endereco])
    );
  }

  if (file === 'exportar_fornecedor.php') {
    return sendCsvExcel(
      'fornecedores_mvm.csv',
      ['ID', 'Fornecedor', 'Segmento', 'CNPJ', 'Email', 'Telefone', 'Cidade', 'UF'],
      forns.map(f => [f.idFornecedor, f.nomeFornecedor, f.segmento, f.cnpj, f.email, f.telefone, f.cidade, f.uf])
    );
  }

  if (file === 'exportar_produtos.php' || file === 'relatorio_inventario_excel.php') {
    return sendCsvExcel(
      'inventario_mvm.csv',
      ['ID', 'EAN-13', 'Produto', 'Categoria', 'WMS', 'Lote', 'Validade', 'Estoque Sistema', 'Contagem Fisica', 'Custo Unit', 'Preco Venda'],
      prods.map(p => [
        p.idProduto, p.codigoBarras, p.nomeProduto, p.categoria, p.localizacao || 'A-01-01',
        p.lote || '', p.dataValidade ? formatDateBR(p.dataValidade) : '', p.quantidade, '',
        Number(p.precoCusto || 0).toFixed(2).replace('.', ','), Number(p.preco || 0).toFixed(2).replace('.', ',')
      ])
    );
  }

  if (file === 'relatorio_balanco_excel.php') {
    const mes = String(query.mes || new Date().toISOString().slice(0, 7));
    const movsMes = movs.filter(m => String(m.dataMovimento).startsWith(mes) && !m.dataDevolucao);
    return sendCsvExcel(
      `balanco_mvm_${mes}.csv`,
      ['Data', 'Documento', 'Operacao', 'Produto', 'Quantidade', 'Valor Unit', 'Total (R$)'],
      movsMes.map(m => {
        const p = prods.find(x => x.idProduto === Number(m.idProduto));
        return [
          formatDateTimeBR(m.dataMovimento), m.codigoPedido || ('MOV-' + m.idMovimento), m.tipoMovimento,
          p ? p.nomeProduto : ('#' + m.idProduto), m.quantidade,
          Number(m.precoUnitario || 0).toFixed(2).replace('.', ','),
          Number(m.valorTotal || 0).toFixed(2).replace('.', ',')
        ];
      })
    );
  }

  if (file === 'relatorio_kardex_excel.php') {
    const idProd = Number(query.idProduto || query.produto || (prods[0] && prods[0].idProduto) || 1);
    const prod = prods.find(p => p.idProduto === idProd) || prods[0];
    const movsProd = movs.filter(m => Number(m.idProduto) === (prod ? prod.idProduto : 0));
    return sendCsvExcel(
      `kardex_${prod ? prod.idProduto : 'sku'}.csv`,
      ['Data', 'Documento', 'Operacao', 'Quantidade', 'Valor Unit', 'Total (R$)', 'Observacao'],
      movsProd.map(m => [
        formatDateTimeBR(m.dataMovimento), m.codigoPedido || ('MOV-' + m.idMovimento),
        m.dataDevolucao ? 'ESTORNO' : m.tipoMovimento, m.quantidade,
        Number(m.precoUnitario || 0).toFixed(2).replace('.', ','),
        Number(m.valorTotal || 0).toFixed(2).replace('.', ','),
        m.observacao || ''
      ])
    );
  }

  if (file === 'relatorio_balanco_pdf.php') {
    const mes = String(query.mes || new Date().toISOString().slice(0, 7));
    const movsMes = movs.filter(m => String(m.dataMovimento).startsWith(mes) && !m.dataDevolucao);
    const saidas = movsMes.filter(m => m.tipoMovimento === 'SAIDA');
    const entradas = movsMes.filter(m => m.tipoMovimento === 'ENTRADA');
    const fat = saidas.reduce((s, m) => s + Number(m.valorTotal || 0), 0);
    const cmv = saidas.reduce((s, m) => s + (Number(m.quantidade || 0) * Number(m.custoUnitario || 0)), 0);
    const lucro = fat - cmv;

    const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Balanço Mensal ${esc(mes)} - MVM</title><style>body{font-family:sans-serif;padding:30px;color:#0f172a;} .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:20px 0;} .kpi{border:1px solid #cbd5e1;border-radius:8px;padding:12px;background:#f8fafc;} table{width:100%;border-collapse:collapse;margin-top:16px;} th,td{border:1px solid #cbd5e1;padding:8px;font-size:12px;text-align:left;} th{background:#f1f5f9;} @media print{button{display:none;}}</style></head><body><div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #00704A;padding-bottom:12px;"><div><h1 style="margin:0;font-size:20px;">BALANÇO FINANCEIRO MENSAL — ${esc(mes)}</h1><p style="margin:4px 0 0;font-size:12px;">MVM Gestão de Estoque &amp; Logística</p></div><button onclick="window.print()" style="padding:8px 16px;background:#00704A;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;">🖨️ Imprimir / Salvar PDF</button></div><div class="kpis"><div class="kpi"><small>Faturamento (Saídas)</small><div style="font-size:18px;font-weight:800;color:#00704A;">R$ ${formatCurrencyBR(fat)}</div></div><div class="kpi"><small>CMV (Custo Mercadoria)</small><div style="font-size:18px;font-weight:800;">R$ ${formatCurrencyBR(cmv)}</div></div><div class="kpi"><small>Lucro Bruto</small><div style="font-size:18px;font-weight:800;color:#10b981;">R$ ${formatCurrencyBR(lucro)}</div></div><div class="kpi"><small>Movimentações no Mês</small><div style="font-size:18px;font-weight:800;">${saidas.length} saídas / ${entradas.length} entradas</div></div></div><table><thead><tr><th>Data</th><th>Documento</th><th>Tipo</th><th>Produto</th><th>Qtd</th><th>Total (R$)</th></tr></thead><tbody>${movsMes.map(m => { const p = prods.find(x => x.idProduto === Number(m.idProduto)); return `<tr><td>${esc(formatDateTimeBR(m.dataMovimento))}</td><td>${esc(m.codigoPedido || 'MOV-' + m.idMovimento)}</td><td>${esc(m.tipoMovimento)}</td><td>${esc(p ? p.nomeProduto : '#' + m.idProduto)}</td><td>${m.quantidade} un</td><td>R$ ${formatCurrencyBR(m.valorTotal)}</td></tr>`; }).join('')}</tbody></table></body></html>`;
    return { html };
  }

  if (file === 'relatorio_kardex_pdf.php') {
    const idProd = Number(query.idProduto || query.produto || (prods[0] && prods[0].idProduto) || 1);
    const prod = prods.find(p => p.idProduto === idProd) || prods[0];
    const movsProd = movs.filter(m => Number(m.idProduto) === (prod ? prod.idProduto : 0));
    const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Livro Kardex - ${esc(prod ? prod.nomeProduto : '')}</title><style>body{font-family:sans-serif;padding:30px;color:#0f172a;} table{width:100%;border-collapse:collapse;margin-top:16px;} th,td{border:1px solid #cbd5e1;padding:8px;font-size:12px;text-align:left;} th{background:#f1f5f9;} @media print{button{display:none;}}</style></head><body><div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #00704A;padding-bottom:12px;"><div><h1 style="margin:0;font-size:20px;">FICHA KARDEX OFICIAL — ${esc(prod ? prod.nomeProduto : '')}</h1><p style="margin:4px 0 0;font-size:12px;">Posição WMS: ${esc(prod ? prod.localizacao : 'A-01-01')} &bull; Saldo Atual: ${prod ? prod.quantidade : 0} un</p></div><button onclick="window.print()" style="padding:8px 16px;background:#00704A;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;">🖨️ Imprimir / Salvar PDF</button></div><table><thead><tr><th>Data/Hora</th><th>Documento</th><th>Operação</th><th>Qtd</th><th>Valor Total</th><th>Observação</th></tr></thead><tbody>${movsProd.map(m => `<tr><td>${esc(formatDateTimeBR(m.dataMovimento))}</td><td>${esc(m.codigoPedido || 'MOV-' + m.idMovimento)}</td><td>${esc(m.dataDevolucao ? 'ESTORNO' : m.tipoMovimento)}</td><td>${m.quantidade} un</td><td>R$ ${formatCurrencyBR(m.valorTotal)}</td><td>${esc(m.observacao || '')}</td></tr>`).join('')}</tbody></table></body></html>`;
    return { html };
  }

  const html = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Folha de Inventário Físico - MVM Estoque</title><style>body{font-family:sans-serif;padding:30px;color:#0f172a;} table{width:100%;border-collapse:collapse;margin-top:20px;} th,td{border:1px solid #cbd5e1;padding:8px 10px;text-align:left;font-size:12px;} th{background:#f1f5f9;} @media print{button{display:none;}}</style></head><body><div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #00704A;padding-bottom:12px;"><div><h1 style="margin:0;font-size:20px;">MVM GESTÃO DE ESTOQUE &amp; LOGÍSTICA</h1><p style="margin:4px 0 0;font-size:12px;">Folha Oficial de Inventário Físico para Contagem Cega &amp; Auditoria</p></div><button onclick="window.print()" style="padding:8px 16px;background:#00704A;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;">🖨️ Imprimir / Salvar PDF</button></div><table><thead><tr><th>ID</th><th>EAN-13</th><th>Produto</th><th>WMS</th><th>Lote</th><th>Saldo Sistema</th><th>Contagem Física</th><th>Valor Unit.</th></tr></thead><tbody>${prods.map(p => `<tr><td>#${p.idProduto}</td><td>${esc(p.codigoBarras || '')}</td><td>${esc(p.nomeProduto)}</td><td>${esc(p.localizacao || 'A-01-01')}</td><td>${esc(p.lote || '-')}</td><td>${p.quantidade} un</td><td style="width:110px;"></td><td>R$ ${formatCurrencyBR(p.preco)}</td></tr>`).join('')}</tbody></table></body></html>`;
  return { html };
}

async function handleConsultaEan(query, session) {
  if (!session.usuarioLogado) {
    return { statusCode: 401, json: { sucesso: false, mensagem: 'Não autorizado. Faça login no sistema para consultar a API.' } };
  }
  const ean = String(query.ean || '').replace(/\D/g, '').trim();
  if (!ean || ean.length < 7 || ean.length > 14) {
    return { statusCode: 400, json: { sucesso: false, mensagem: 'Código de barras (EAN/GTIN) inválido. Informe entre 7 e 14 dígitos numéricos.' } };
  }
  try {
    const resp = await fetch(`https://world.openfoodfacts.org/api/v2/product/${ean}.json`, {
      headers: {
        'User-Agent': 'MVM-Estoque-Logistica-TCC/1.0 (https://github.com/Vitor-Bazzo/mvm-estoque)',
        'Accept': 'application/json',
        'Accept-Language': 'pt-BR,pt;q=0.9,en;q=0.8'
      }
    });
    const dados = await resp.json();
    if (!dados || Number(dados.status) !== 1 || !dados.product) {
      return { statusCode: 404, json: { sucesso: false, mensagem: `Produto com código EAN '${ean}' não foi localizado no catálogo global.`, ean } };
    }
    const prod = dados.product;
    const nome = prod.product_name_pt || prod.product_name || prod.generic_name_pt || prod.generic_name || 'Produto Identificado';
    const marca = prod.brands ? String(prod.brands).split(',')[0].trim() : '';
    let categoria = 'Geral';
    if (prod.categories) {
      const partes = String(prod.categories).split(',').map(s => s.trim()).filter(Boolean);
      if (partes.length > 0) categoria = partes[partes.length - 1];
    }
    let descricao = '';
    if (prod.quantity) descricao += `Embalagem/Conteúdo: ${prod.quantity}. `;
    if (prod.ingredients_text_pt) descricao += `Ingredientes: ${String(prod.ingredients_text_pt).slice(0, 160)}`;
    else if (prod.ingredients_text) descricao += `Ingredientes: ${String(prod.ingredients_text).slice(0, 160)}`;

    return {
      json: {
        sucesso: true,
        origem: 'Open Food Facts Global API',
        ean,
        nome: String(nome).trim(),
        marca,
        categoria,
        descricao: descricao.trim(),
        imagem_url: prod.image_front_url || prod.image_url || ''
      }
    };
  } catch (e) {
    return { statusCode: 502, json: { sucesso: false, mensagem: 'Falha de comunicação temporária com a base Open Food Facts. Tente novamente.' } };
  }
}

function handleDispararAlerta(body, db, session) {
  if (!session.usuarioLogado) return { redirect: '/modulos/auth/login.php' };
  const uid = Number(session.idUsuario) || 1;
  const prods = db.produtos.filter(p => Number(p.idUsuario) === uid);
  const criticos = prods.filter(p => Number(p.quantidade) <= Math.max(1, Number(p.estoqueMinimo || 10)));
  const todayMs = new Date(new Date().toISOString().slice(0, 10)).getTime();
  const vencendo = prods.filter(p => {
    if (!p.dataValidade || Number(p.quantidade) <= 0) return false;
    const dias = Math.round((new Date(String(p.dataValidade).slice(0, 10)).getTime() - todayMs) / 86400000);
    return dias <= 60;
  });

  const destino = session.emailUsuario || 'gestor@mvm.com';
  const msg = `📧 Relatório gerencial de alertas processado para ${esc(destino)}! (${criticos.length} produto(s) em estoque crítico e ${vencendo.length} com validade próxima FEFO).`;
  session.flash_dashboard = { tipo: 'sucesso', texto: msg };
  session.flash_mensagem = { tipo: 'sucesso', texto: msg };
  const origem = String(body.origem || '/index.php');
  return { redirect: origem.startsWith('/') ? origem : '/index.php' };
}

module.exports = { handleExportacao, handleConsultaEan, handleDispararAlerta };
