/**
 * Exportação de planilhas Excel 100% no navegador (client-side), usando SheetJS.
 * Não faz nenhuma requisição ao servidor: usa os dados que o PHP já carregou
 * na página (produtosData, clientesData, fornecedoresData).
 *
 * Depende de: https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js
 */

function exportarProdutosExcel() {
    if (typeof produtosData === 'undefined' || !Array.isArray(produtosData) || produtosData.length === 0) {
        alert('Não há produtos para exportar.');
        return;
    }

    const linhas = produtosData.map(p => ({
        'Código/ID': p.idProduto ?? p.id ?? '',
        'Nome do Produto': p.nomeProduto ?? p.nome ?? '',
        'Categoria': p.categoria ?? '',
        'Descrição': p.descricao ?? '',
        'Fornecedor': p.nomeFornecedor ?? '',
        'Preço (R$)': Number(p.preco ?? 0),
        'Estoque': Number(p.quantidade ?? p.estoque ?? 0),
    }));

    const planilha = XLSX.utils.json_to_sheet(linhas);

    planilha['!cols'] = [
        { wch: 10 }, { wch: 30 }, { wch: 18 }, { wch: 40 }, { wch: 25 }, { wch: 14 }, { wch: 10 }
    ];

    const livro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(livro, planilha, 'Produtos');

    const nomeArquivo = `relatorio_produtos_${dataFormatada()}.xlsx`;
    XLSX.writeFile(livro, nomeArquivo);
}

function exportarClientesExcel() {
    if (typeof clientesData === 'undefined' || !Array.isArray(clientesData) || clientesData.length === 0) {
        alert('Não há clientes para exportar.');
        return;
    }

    const linhas = clientesData.map(c => ({
        'Código/ID': c.idCliente ?? '',
        'Nome do Cliente': c.nomeCliente ?? '',
        'Email': c.email ?? '',
        'Telefone': c.telefone ?? '',
        'Endereço': c.endereco ?? '',
        'Cidade': c.cidade ?? '',
        'UF': c.uf ?? '',
    }));

    const planilha = XLSX.utils.json_to_sheet(linhas);
    planilha['!cols'] = [
        { wch: 10 }, { wch: 28 }, { wch: 28 }, { wch: 16 }, { wch: 32 }, { wch: 18 }, { wch: 6 }
    ];

    const livro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(livro, planilha, 'Clientes');

    const nomeArquivo = `relatorio_clientes_${dataFormatada()}.xlsx`;
    XLSX.writeFile(livro, nomeArquivo);
}

function exportarFornecedoresExcel() {
    if (typeof fornecedoresData === 'undefined' || !Array.isArray(fornecedoresData) || fornecedoresData.length === 0) {
        alert('Não há fornecedores para exportar.');
        return;
    }

    const linhas = fornecedoresData.map(f => ({
        'Código/ID': f.idFornecedor ?? '',
        'Nome do Fornecedor': f.nomeFornecedor ?? '',
        'CNPJ': f.cnpj ?? '',
        'Segmento': f.segmento ?? '',
        'Email': f.email ?? '',
        'Telefone': f.telefone ?? '',
        'Endereço': f.endereco ?? '',
        'Cidade': f.cidade ?? '',
        'UF': f.uf ?? '',
    }));

    const planilha = XLSX.utils.json_to_sheet(linhas);
    planilha['!cols'] = [
        { wch: 10 }, { wch: 28 }, { wch: 20 }, { wch: 18 }, { wch: 28 }, { wch: 16 }, { wch: 30 }, { wch: 18 }, { wch: 6 }
    ];

    const livro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(livro, planilha, 'Fornecedores');

    const nomeArquivo = `relatorio_fornecedores_${dataFormatada()}.xlsx`;
    XLSX.writeFile(livro, nomeArquivo);
}

function dataFormatada() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}_${pad(d.getHours())}-${pad(d.getMinutes())}`;
}

// Liga o botão de Excel da página atual
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnExportarExcel');
    if (!btn) return;

    btn.addEventListener('click', () => {
        if (typeof produtosData !== 'undefined' && Array.isArray(produtosData)) {
            exportarProdutosExcel();
        } else if (typeof clientesData !== 'undefined' && Array.isArray(clientesData)) {
            exportarClientesExcel();
        } else if (typeof fornecedoresData !== 'undefined' && Array.isArray(fornecedoresData)) {
            exportarFornecedoresExcel();
        } else {
            alert('Nenhum dado encontrado para exportação nesta página.');
        }
    });
});