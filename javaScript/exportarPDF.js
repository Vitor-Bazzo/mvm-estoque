/**
 * Exportação de PDF 100% no navegador (client-side), usando jsPDF + jsPDF-AutoTable.
 * Usa os dados já carregados na página (produtosData, clientesData, fornecedoresData).
 *
 * Depende de:
 * https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js
 * https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js
 */

function exportarProdutosPDF() {
    if (typeof produtosData === 'undefined' || !Array.isArray(produtosData) || produtosData.length === 0) {
        alert('Não há produtos para exportar.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFontSize(15);
    doc.text('Relatório de Produtos - Sistema MVM', 14, 15);
    doc.setFontSize(9);
    doc.text(`Gerado em: ${dataHoraFormatada()}`, 14, 21);

    const colunas = ['ID', 'Nome do Produto', 'Categoria', 'Descrição', 'Fornecedor', 'Preço (R$)', 'Estoque'];
    const linhas = produtosData.map(p => [
        p.idProduto ?? p.id ?? '',
        p.nomeProduto ?? p.nome ?? '',
        p.categoria ?? '',
        p.descricao ?? '',
        p.nomeFornecedor ?? '',
        formatarMoeda(p.preco ?? 0),
        p.quantidade ?? p.estoque ?? 0,
    ]);

    doc.autoTable({
        head: [colunas],
        body: linhas,
        startY: 26,
        theme: 'striped',
        headStyles: { fillColor: [33, 115, 70], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [249, 251, 249] },
        styles: { fontSize: 8, cellPadding: 3 },
        columnStyles: {
            0: { halign: 'center', cellWidth: 15 },
            5: { halign: 'right', cellWidth: 28 },
            6: { halign: 'center', cellWidth: 20 },
        },
    });

    doc.save(`relatorio_produtos_${dataFormatada()}.pdf`);
}

function exportarClientesPDF() {
    if (typeof clientesData === 'undefined' || !Array.isArray(clientesData) || clientesData.length === 0) {
        alert('Não há clientes para exportar.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFontSize(15);
    doc.text('Relatório de Clientes - Sistema MVM', 14, 15);
    doc.setFontSize(9);
    doc.text(`Gerado em: ${dataHoraFormatada()}`, 14, 21);

    const colunas = ['ID', 'Nome do Cliente', 'Email', 'Telefone', 'Endereço', 'Cidade', 'UF'];
    const linhas = clientesData.map(c => [
        c.idCliente ?? '',
        c.nomeCliente ?? '',
        c.email ?? '',
        c.telefone ?? '',
        c.endereco ?? '',
        c.cidade ?? '',
        c.uf ?? '',
    ]);

    doc.autoTable({
        head: [colunas],
        body: linhas,
        startY: 26,
        theme: 'striped',
        headStyles: { fillColor: [16, 124, 65], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [249, 251, 249] },
        styles: { fontSize: 8, cellPadding: 3 },
        columnStyles: {
            0: { halign: 'center', cellWidth: 15 },
            6: { halign: 'center', cellWidth: 15 },
        },
    });

    doc.save(`relatorio_clientes_${dataFormatada()}.pdf`);
}

function exportarFornecedoresPDF() {
    if (typeof fornecedoresData === 'undefined' || !Array.isArray(fornecedoresData) || fornecedoresData.length === 0) {
        alert('Não há fornecedores para exportar.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFontSize(15);
    doc.text('Relatório de Fornecedores - Sistema MVM', 14, 15);
    doc.setFontSize(9);
    doc.text(`Gerado em: ${dataHoraFormatada()}`, 14, 21);

    const colunas = ['ID', 'Nome do Fornecedor', 'CNPJ', 'Segmento', 'Email', 'Telefone', 'Cidade/UF'];
    const linhas = fornecedoresData.map(f => [
        f.idFornecedor ?? '',
        f.nomeFornecedor ?? '',
        f.cnpj ?? '',
        f.segmento ?? '',
        f.email ?? '',
        f.telefone ?? '',
        (f.cidade ? f.cidade + (f.uf ? ' - ' + f.uf : '') : (f.uf ?? '')),
    ]);

    doc.autoTable({
        head: [colunas],
        body: linhas,
        startY: 26,
        theme: 'striped',
        headStyles: { fillColor: [33, 115, 70], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [249, 251, 249] },
        styles: { fontSize: 8, cellPadding: 3 },
        columnStyles: {
            0: { halign: 'center', cellWidth: 15 },
            2: { cellWidth: 38 },
        },
    });

    doc.save(`relatorio_fornecedores_${dataFormatada()}.pdf`);
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function dataFormatada() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}_${pad(d.getHours())}-${pad(d.getMinutes())}`;
}

function dataHoraFormatada() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// Liga o botão de PDF da página atual
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnExportarPDF');
    if (!btn) return;

    btn.addEventListener('click', () => {
        if (typeof produtosData !== 'undefined' && Array.isArray(produtosData)) {
            exportarProdutosPDF();
        } else if (typeof clientesData !== 'undefined' && Array.isArray(clientesData)) {
            exportarClientesPDF();
        } else if (typeof fornecedoresData !== 'undefined' && Array.isArray(fornecedoresData)) {
            exportarFornecedoresPDF();
        } else {
            alert('Nenhum dado encontrado para exportação nesta página.');
        }
    });
});