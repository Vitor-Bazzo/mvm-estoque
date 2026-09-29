/**
 * Renderização dos Gráficos Interativos estilo Power BI com ApexCharts
 * Sistema MVM - Totalmente adaptável aos temas Claro (Stripe) e Escuro (Linear)
 */

document.addEventListener('DOMContentLoaded', function () {
    if (typeof dadosBI === 'undefined') {
        console.warn('Dados do dashboard não encontrados.');
        return;
    }

    // Formatação de moeda BRL
    const formatarMoeda = (valor) => {
        return Number(valor).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 2
        });
    };

    // Paleta de Cores Oficial Power BI
    const paletaPowerBI = [
        '#F2C811', // Power BI Yellow
        '#118DFF', // Power BI Blue
        '#744EC2', // Power BI Purple
        '#10B981', // Emerald
        '#F59E0B', // Amber
        '#D64550', // Coral
        '#00B4D8', // Light Blue / Cyan
        '#E044A7', // Magenta
        '#431407'  // Brown/Dark
    ];

    // Helper para detectar tema atual e extrair tokens de cor dinâmicos
    const obterTemaAtual = () => {
        return document.documentElement.getAttribute('data-theme') || localStorage.getItem('mvm_theme') || 'light';
    };

    const getThemeTokens = (tema) => {
        const isDark = tema === 'dark';
        return {
            isDark: isDark,
            mode: isDark ? 'dark' : 'light',
            gridBorder: isDark ? '#1e2433' : '#f1f5f9',
            axisLabels: isDark ? '#8b9bb4' : '#64748b',
            textMain: isDark ? '#f1f5f9' : '#0f172a',
            textMuted: isDark ? '#8b9bb4' : '#64748b',
            cardBg: isDark ? '#151922' : '#ffffff',
            donutStroke: isDark ? '#151922' : '#ffffff',
            markerStroke: isDark ? '#151922' : '#ffffff'
        };
    };

    let tokens = getThemeTokens(obterTemaAtual());
    let chartTop = null;
    let chartCategorias = null;
    let chartLinhas = null;

    /* =========================================================================
       1. GRÁFICO 1: TOP 5 PRODUTOS MAIS VENDIDOS DE TODO O ESTOQUE
       ========================================================================= */
    const elTopProdutos = document.querySelector('#chart-top-produtos');
    if (elTopProdutos) {
        const nomesProdutos = dadosBI.top_produtos.nomes || [];
        const qtdsProdutos   = dadosBI.top_produtos.quantidades || [];
        const faturamento    = dadosBI.top_produtos.faturamentos || [];
        const categorias     = dadosBI.top_produtos.categorias || [];

        const optionsTop = {
            series: [{
                name: 'Unidades Vendidas',
                data: qtdsProdutos
            }],
            chart: {
                type: 'bar',
                height: 330,
                fontFamily: 'Plus Jakarta Sans, Inter, system-ui, sans-serif',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                }
            },
            noData: {
                text: 'Nenhuma venda registrada ainda',
                align: 'center',
                verticalAlign: 'middle',
                style: {
                    color: tokens.textMuted,
                    fontSize: '13px'
                }
            },
            theme: {
                mode: tokens.mode
            },
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    horizontal: true,
                    distributed: true,
                    barHeight: '62%',
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            colors: ['#118DFF', '#00B4D8', '#10B981', '#744EC2', '#F59E0B'],
            dataLabels: {
                enabled: true,
                textAnchor: 'start',
                style: {
                    colors: [tokens.textMain],
                    fontWeight: 700,
                    fontSize: '12px'
                },
                formatter: function (val) {
                    return val + ' un';
                },
                offsetX: 10
            },
            legend: {
                show: false
            },
            xaxis: {
                categories: nomesProdutos,
                labels: {
                    style: {
                        colors: tokens.axisLabels,
                        fontSize: '12px'
                    },
                    formatter: function (val) {
                        return Math.round(val);
                    }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: tokens.textMain,
                        fontSize: '12px',
                        fontWeight: 600
                    },
                    maxWidth: 190
                }
            },
            grid: {
                borderColor: tokens.gridBorder,
                strokeDashArray: 4,
                xaxis: { lines: { show: true } },
                yaxis: { lines: { show: false } }
            },
            tooltip: {
                custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                    const prodNome = nomesProdutos[dataPointIndex] || 'Produto';
                    const qtd = qtdsProdutos[dataPointIndex] || 0;
                    const fat = faturamento[dataPointIndex] || 0;
                    const cat = categorias[dataPointIndex] || 'Geral';

                    return `
                        <div class="pbi-chart-tooltip">
                            <div class="pbi-tooltip-header">Top #${dataPointIndex + 1} • ${cat}</div>
                            <div class="pbi-tooltip-title">${prodNome}</div>
                            <div class="pbi-tooltip-row">
                                <span class="pbi-tooltip-label">Total Vendido:</span>
                                <strong class="pbi-tooltip-val-blue">${qtd} un</strong>
                            </div>
                            <div class="pbi-tooltip-row">
                                <span class="pbi-tooltip-label">Faturamento:</span>
                                <strong class="pbi-tooltip-val-green">${formatarMoeda(fat)}</strong>
                            </div>
                        </div>
                    `;
                }
            }
        };

        chartTop = new ApexCharts(elTopProdutos, optionsTop);
        chartTop.render();
    }

    /* =========================================================================
       2. GRÁFICO 2: PORCENTAGEM DE CADA CATEGORIA (EX: BEBIDAS COM 50%)
       ========================================================================= */
    const elCategorias = document.querySelector('#chart-categorias');
    if (elCategorias) {
        // Inicializa com base no mix de VENDAS (ou Estoque caso não haja vendas)
        const usaVendasPadrao = dadosBI.categorias_vendas.series.length > 0;
        let dadosAtuais = usaVendasPadrao ? dadosBI.categorias_vendas : dadosBI.categorias_estoque;

        const temCategorias = dadosAtuais && dadosAtuais.series && dadosAtuais.series.length > 0;
        const optionsCategorias = {
            series: temCategorias ? dadosAtuais.series : [],
            labels: temCategorias ? dadosAtuais.labels : [],
            chart: {
                type: 'donut',
                height: 330,
                fontFamily: 'Plus Jakarta Sans, Inter, system-ui, sans-serif',
                toolbar: {
                    show: true,
                    tools: { download: true }
                }
            },
            noData: {
                text: 'Nenhum produto cadastrado ainda',
                align: 'center',
                verticalAlign: 'middle',
                style: {
                    color: tokens.textMuted,
                    fontSize: '13px'
                }
            },
            theme: {
                mode: tokens.mode
            },
            colors: paletaPowerBI,
            stroke: {
                show: true,
                width: 3,
                colors: [tokens.donutStroke]
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val.toFixed(1) + '%';
                },
                style: {
                    fontSize: '12px',
                    fontWeight: 700,
                    colors: ['#ffffff']
                },
                dropShadow: {
                    enabled: true,
                    top: 1,
                    left: 1,
                    blur: 2,
                    opacity: 0.5
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '13px',
                                fontWeight: 600,
                                color: tokens.textMuted,
                                offsetY: -4
                            },
                            value: {
                                show: true,
                                fontSize: '24px',
                                fontWeight: 800,
                                color: tokens.textMain,
                                offsetY: 6,
                                formatter: function (val) {
                                    return val + ' un';
                                }
                            },
                            total: {
                                show: true,
                                label: 'TOTAL GERAL',
                                fontSize: '11px',
                                fontWeight: 700,
                                color: tokens.textMuted,
                                formatter: function (w) {
                                    const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    return total + ' un';
                                }
                            }
                        }
                    }
                }
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                fontSize: '12px',
                fontWeight: 600,
                labels: {
                    colors: tokens.axisLabels
                },
                formatter: function (seriesName, opts) {
                    const val = opts.w.globals.series[opts.seriesIndex];
                    const percent = opts.w.globals.seriesPercent[opts.seriesIndex][0];
                    return `${seriesName}: <b>${percent.toFixed(1)}%</b> (${val} un)`;
                },
                markers: {
                    radius: 4,
                    width: 10,
                    height: 10
                }
            },
            tooltip: {
                y: {
                    formatter: function (value, { seriesIndex, w }) {
                        const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                        const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `<b>${value} unidades</b> (${pct}% do total)`;
                    }
                }
            }
        };

        chartCategorias = new ApexCharts(elCategorias, optionsCategorias);
        chartCategorias.render();

        // Alternância de visão: Mix por Vendas vs Mix por Estoque
        const botoesModo = document.querySelectorAll('[data-cat-mode]');
        botoesModo.forEach(btn => {
            btn.addEventListener('click', function () {
                botoesModo.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const modo = this.getAttribute('data-cat-mode');
                const novosDados = modo === 'estoque' ? dadosBI.categorias_estoque : dadosBI.categorias_vendas;

                chartCategorias.updateOptions({
                    series: novosDados.series,
                    labels: novosDados.labels
                });
            });
        });
    }

    /* =========================================================================
       3. GRÁFICO 3: QUANTIDADE EM REAIS DE VENDAS FEITAS EM UM DIA (LINHA / ÁREA)
       ========================================================================= */
    const elVendasDiarias = document.querySelector('#chart-vendas-diarias');
    if (elVendasDiarias) {
        const todasDatas   = dadosBI.vendas_diarias.datas || [];
        const datasCurtas  = dadosBI.vendas_diarias.datas_curtas || [];
        const todosValores = dadosBI.vendas_diarias.valores || [];
        const todosItens   = dadosBI.vendas_diarias.itens || [];
        const todosPedidos = dadosBI.vendas_diarias.pedidos || [];

        const optionsLinhas = {
            series: [{
                name: 'Faturamento do Dia',
                data: todosValores
            }],
            chart: {
                type: 'area',
                height: 340,
                fontFamily: 'Plus Jakarta Sans, Inter, system-ui, sans-serif',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        pan: false,
                        reset: true
                    }
                },
                zoom: {
                    enabled: true
                }
            },
            noData: {
                text: 'Nenhum faturamento registrado ainda',
                align: 'center',
                verticalAlign: 'middle',
                style: {
                    color: tokens.textMuted,
                    fontSize: '13px'
                }
            },
            theme: {
                mode: tokens.mode
            },
            colors: ['#10B981'], // Verde Esmeralda Power BI para Faturamento
            stroke: {
                curve: 'smooth',
                width: 3.5
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    type: 'vertical',
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 95, 100],
                    colorStops: [
                        { offset: 0, color: '#10B981', opacity: 0.45 },
                        { offset: 100, color: '#10B981', opacity: 0.02 }
                    ]
                }
            },
            markers: {
                size: 5,
                colors: ['#10B981'],
                strokeColors: tokens.markerStroke,
                strokeWidth: 2.5,
                hover: {
                    size: 8,
                    strokeWidth: 3
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: datasCurtas.length > 0 ? datasCurtas : todasDatas,
                labels: {
                    style: {
                        colors: tokens.axisLabels,
                        fontSize: '12px',
                        fontWeight: 600
                    }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: tokens.axisLabels,
                        fontSize: '12px',
                        fontWeight: 600
                    },
                    formatter: function (val) {
                        return 'R$ ' + Number(val).toLocaleString('pt-BR', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        });
                    }
                }
            },
            grid: {
                borderColor: tokens.gridBorder,
                strokeDashArray: 4,
                xaxis: { lines: { show: false } },
                yaxis: { lines: { show: true } }
            },
            tooltip: {
                custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                    const dataFormatada = todasDatas[dataPointIndex] || 'Data';
                    const valorReais = todosValores[dataPointIndex] || 0;
                    const itensQtd = todosItens[dataPointIndex] || 0;
                    const pedidosQtd = todosPedidos[dataPointIndex] || 0;
                    const ticketDia = pedidosQtd > 0 ? valorReais / pedidosQtd : 0;

                    return `
                        <div class="pbi-chart-tooltip">
                            <div class="pbi-tooltip-header">📅 Dia: ${dataFormatada}</div>
                            <div class="pbi-tooltip-main-val">
                                ${formatarMoeda(valorReais)}
                            </div>
                            <div class="pbi-tooltip-divider">
                                <div class="pbi-tooltip-row">
                                    <span class="pbi-tooltip-label">Vendas registradas:</span>
                                    <strong class="pbi-tooltip-val">${pedidosQtd} transações</strong>
                                </div>
                                <div class="pbi-tooltip-row">
                                    <span class="pbi-tooltip-label">Itens vendidos:</span>
                                    <strong class="pbi-tooltip-val">${itensQtd} peças</strong>
                                </div>
                                <div class="pbi-tooltip-row">
                                    <span class="pbi-tooltip-label">Ticket Médio do dia:</span>
                                    <strong class="pbi-tooltip-val-blue">${formatarMoeda(ticketDia)}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }
        };

        chartLinhas = new ApexCharts(elVendasDiarias, optionsLinhas);
        chartLinhas.render();

        // Filtro de período estilo Slicer do Power BI (Últimos 7 dias, 15 dias, Todos)
        const botoesPeriodo = document.querySelectorAll('[data-periodo]');
        botoesPeriodo.forEach(btn => {
            btn.addEventListener('click', function () {
                botoesPeriodo.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const periodo = parseInt(this.getAttribute('data-periodo'), 10);
                let novDatas = [...datasCurtas];
                let novValores = [...todosValores];

                if (periodo > 0 && novDatas.length > periodo) {
                    novDatas = novDatas.slice(-periodo);
                    novValores = novValores.slice(-periodo);
                }

                chartLinhas.updateOptions({
                    xaxis: { categories: novDatas },
                    series: [{ data: novValores }]
                });
            });
        });
    }

    /* =========================================================================
       4. SUPORTE DINÂMICO AO TEMA CLARO (STRIPE) / ESCURO (LINEAR)
       ========================================================================= */
    window.addEventListener('temaAlterado', function (e) {
        const novoTema = e.detail ? e.detail.tema : (document.documentElement.getAttribute('data-theme') || 'light');
        const tok = getThemeTokens(novoTema);

        if (chartTop) {
            chartTop.updateOptions({
                theme: { mode: tok.mode },
                grid: { borderColor: tok.gridBorder },
                xaxis: { labels: { style: { colors: tok.axisLabels } } },
                yaxis: { labels: { style: { colors: tok.textMain } } },
                dataLabels: { style: { colors: [tok.textMain] } }
            });
        }

        if (chartCategorias) {
            chartCategorias.updateOptions({
                theme: { mode: tok.mode },
                stroke: { colors: [tok.donutStroke] },
                legend: { labels: { colors: tok.axisLabels } },
                plotOptions: {
                    pie: {
                        donut: {
                            labels: {
                                name: { color: tok.textMuted },
                                value: { color: tok.textMain },
                                total: { color: tok.textMuted }
                            }
                        }
                    }
                }
            });
        }

        if (chartLinhas) {
            chartLinhas.updateOptions({
                theme: { mode: tok.mode },
                grid: { borderColor: tok.gridBorder },
                xaxis: { labels: { style: { colors: tok.axisLabels } } },
                yaxis: { labels: { style: { colors: tok.axisLabels } } },
                markers: { strokeColors: tok.markerStroke }
            });
        }
    });
});
