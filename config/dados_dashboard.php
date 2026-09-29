<?php
/**
 * Arquivo de inteligência e agregação de dados para o Dashboard Power BI.
 * Retorna os dados isolados por usuário (idUsuario) para os 3 gráficos e KPIs do sistema:
 * 1. Top 5 produtos mais vendidos do usuário
 * 2. Distribuição percentual por categorias (Estoque e Vendas do usuário)
 * 3. Vendas diárias em Reais (R$) do usuário
 */

require_once __DIR__ . '/conexao.php';

function obterDadosDashboardBI($idUsuario = null) {
    if ($idUsuario === null) {
        $idUsuario = obterIdUsuarioLogado();
    }
    $idUsuario = (int)$idUsuario;

    $conexao = conectar();

    $dados = [
        'conexao_ok' => false,
        'mensagem_erro' => null,
        'kpis' => [
            'faturamento_total' => 0,
            'total_vendas_qtd'  => 0,
            'itens_em_estoque'  => 0,
            'total_categorias'  => 0,
            'ticket_medio'      => 0,
            'produto_campeao'   => 'Nenhum'
        ],
        'top_produtos' => [
            'nomes'      => [],
            'quantidades'=> [],
            'faturamentos'=> [],
            'categorias' => []
        ],
        'categorias_estoque' => [
            'labels'      => [],
            'series'      => [],
            'porcentagens'=> []
        ],
        'categorias_vendas' => [
            'labels'      => [],
            'series'      => [],
            'porcentagens'=> []
        ],
        'vendas_diarias' => [
            'datas'       => [],
            'datas_curtas'=> [],
            'valores'     => [],
            'itens'       => [],
            'pedidos'     => []
        ],
        'produtos_estoque_baixo' => [],
        'total_produtos_estoque_baixo' => 0,
        'possui_vendas' => false,
        'dados_demo'    => false
    ];

    if (is_string($conexao) || !$conexao) {
        $dados['mensagem_erro'] = is_string($conexao) ? $conexao : "Não foi possível conectar ao banco de dados.";
        return $dados;
    }

    $dados['conexao_ok'] = true;

    // 1. KPIs Gerais do Usuário: Estoque Total e Total de Categorias
    $sqlEstoque = "SELECT COALESCE(SUM(quantidade), 0) AS total_unidades, COUNT(DISTINCT categoria) AS total_cats FROM produto WHERE idUsuario = ?";
    if ($stmt = mysqli_prepare($conexao, $sqlEstoque)) {
        mysqli_stmt_bind_param($stmt, "i", $idUsuario);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $dados['kpis']['itens_em_estoque'] = (int)$row['total_unidades'];
            $dados['kpis']['total_categorias'] = (int)$row['total_cats'];
        }
    }

    // 1.1 Produtos com Estoque Baixo (< 20 unidades) pertencentes ao Usuário
    $sqlBaixo = "
        SELECT 
            idProduto, 
            nomeProduto, 
            categoria, 
            preco, 
            quantidade, 
            imagem, 
            nomeFornecedor
        FROM produto
        WHERE idUsuario = ? AND quantidade < 20
        ORDER BY quantidade ASC, nomeProduto ASC
    ";
    if ($stmtBaixo = mysqli_prepare($conexao, $sqlBaixo)) {
        mysqli_stmt_bind_param($stmtBaixo, "i", $idUsuario);
        mysqli_stmt_execute($stmtBaixo);
        $resBaixo = mysqli_stmt_get_result($stmtBaixo);
        if ($resBaixo) {
            while ($prod = mysqli_fetch_assoc($resBaixo)) {
                $dados['produtos_estoque_baixo'][] = [
                    'idProduto'      => (int)$prod['idProduto'],
                    'nomeProduto'    => $prod['nomeProduto'],
                    'categoria'      => $prod['categoria'] ?: 'Sem Categoria',
                    'preco'          => (float)$prod['preco'],
                    'quantidade'     => (int)$prod['quantidade'],
                    'imagem'         => $prod['imagem'],
                    'nomeFornecedor' => $prod['nomeFornecedor'] ?: 'Não informado'
                ];
            }
        }
    }
    $dados['total_produtos_estoque_baixo'] = count($dados['produtos_estoque_baixo']);

    // 2. Gráfico 1: Top 5 Produtos Mais Vendidos do Usuário
    $sqlTop = "
        SELECT 
            p.idProduto,
            p.nomeProduto,
            p.categoria,
            p.preco,
            COALESCE(SUM(m.quantidade), 0) AS total_vendido,
            COALESCE(SUM(m.quantidade * p.preco), 0) AS total_faturado
        FROM movimento m
        INNER JOIN produto p ON p.idProduto = m.idProduto AND p.idUsuario = m.idUsuario
        WHERE m.idUsuario = ? AND m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL
        GROUP BY p.idProduto, p.nomeProduto, p.categoria, p.preco
        ORDER BY total_vendido DESC
        LIMIT 5
    ";

    if ($stmtTop = mysqli_prepare($conexao, $sqlTop)) {
        mysqli_stmt_bind_param($stmtTop, "i", $idUsuario);
        mysqli_stmt_execute($stmtTop);
        $res = mysqli_stmt_get_result($stmtTop);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $dados['top_produtos']['nomes'][]        = $row['nomeProduto'];
                $dados['top_produtos']['quantidades'][]  = (int)$row['total_vendido'];
                $dados['top_produtos']['faturamentos'][] = (float)$row['total_faturado'];
                $dados['top_produtos']['categorias'][]   = $row['categoria'] ?: 'Sem Categoria';
            }
        }
    }

    if (!empty($dados['top_produtos']['nomes'])) {
        $dados['possui_vendas'] = true;
        $dados['kpis']['produto_campeao'] = $dados['top_produtos']['nomes'][0] . " (" . $dados['top_produtos']['quantidades'][0] . " un)";
    }

    // 3. Gráfico 2A: Distribuição percentual por Categoria (Estoque do Usuário)
    $sqlCatEstoque = "
        SELECT 
            COALESCE(NULLIF(TRIM(categoria), ''), 'Outros') AS cat,
            COALESCE(SUM(quantidade), 0) AS total_unidades
        FROM produto
        WHERE idUsuario = ?
        GROUP BY cat
        ORDER BY total_unidades DESC
    ";

    $somaTotalEstoque = 0;
    if ($stmtCatEstoque = mysqli_prepare($conexao, $sqlCatEstoque)) {
        mysqli_stmt_bind_param($stmtCatEstoque, "i", $idUsuario);
        mysqli_stmt_execute($stmtCatEstoque);
        $res = mysqli_stmt_get_result($stmtCatEstoque);
        if ($res) {
            $linhasCat = mysqli_fetch_all($res, MYSQLI_ASSOC);
            foreach ($linhasCat as $l) {
                $somaTotalEstoque += (int)$l['total_unidades'];
            }
            foreach ($linhasCat as $l) {
                $unidades = (int)$l['total_unidades'];
                $pct = $somaTotalEstoque > 0 ? round(($unidades / $somaTotalEstoque) * 100, 1) : 0;
                $dados['categorias_estoque']['labels'][]       = $l['cat'];
                $dados['categorias_estoque']['series'][]       = $unidades;
                $dados['categorias_estoque']['porcentagens'][] = $pct;
            }
        }
    }

    // 4. Gráfico 2B: Distribuição percentual por Categoria (Vendas do Usuário)
    $sqlCatVendas = "
        SELECT 
            COALESCE(NULLIF(TRIM(p.categoria), ''), 'Outros') AS cat,
            COALESCE(SUM(m.quantidade), 0) AS total_vendido,
            COALESCE(SUM(m.quantidade * p.preco), 0) AS total_faturado
        FROM movimento m
        INNER JOIN produto p ON p.idProduto = m.idProduto AND p.idUsuario = m.idUsuario
        WHERE m.idUsuario = ? AND m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL
        GROUP BY cat
        ORDER BY total_vendido DESC
    ";

    $somaTotalVendas = 0;
    if ($stmtCatVendas = mysqli_prepare($conexao, $sqlCatVendas)) {
        mysqli_stmt_bind_param($stmtCatVendas, "i", $idUsuario);
        mysqli_stmt_execute($stmtCatVendas);
        $res = mysqli_stmt_get_result($stmtCatVendas);
        if ($res) {
            $linhasCatVendas = mysqli_fetch_all($res, MYSQLI_ASSOC);
            foreach ($linhasCatVendas as $l) {
                $somaTotalVendas += (int)$l['total_vendido'];
            }
            foreach ($linhasCatVendas as $l) {
                $qtd = (int)$l['total_vendido'];
                $pct = $somaTotalVendas > 0 ? round(($qtd / $somaTotalVendas) * 100, 1) : 0;
                $dados['categorias_vendas']['labels'][]       = $l['cat'];
                $dados['categorias_vendas']['series'][]       = $qtd;
                $dados['categorias_vendas']['porcentagens'][] = $pct;
            }
        }
    }

    // 5. Gráfico 3: Vendas diárias em Reais (R$) do Usuário
    $sqlDiario = "
        SELECT 
            DATE(m.dataMovimento) AS data_dia,
            DATE_FORMAT(m.dataMovimento, '%d/%m/%Y') AS data_formatada,
            DATE_FORMAT(m.dataMovimento, '%d/%m') AS data_curta,
            COALESCE(SUM(m.quantidade * p.preco), 0) AS total_reais,
            COALESCE(SUM(m.quantidade * p.precoCusto), 0) AS total_custo,
            COALESCE(SUM(m.quantidade), 0) AS total_itens,
            COUNT(m.idMovimento) AS total_pedidos
        FROM movimento m
        INNER JOIN produto p ON p.idProduto = m.idProduto AND p.idUsuario = m.idUsuario
        WHERE m.idUsuario = ? AND m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL
        GROUP BY DATE(m.dataMovimento)
        ORDER BY data_dia ASC
        LIMIT 30
    ";

    $faturamentoAcumulado = 0;
    $custoTotalAcumulado = 0;
    $totalVendasQtd = 0;

    if ($stmtDiario = mysqli_prepare($conexao, $sqlDiario)) {
        mysqli_stmt_bind_param($stmtDiario, "i", $idUsuario);
        mysqli_stmt_execute($stmtDiario);
        $res = mysqli_stmt_get_result($stmtDiario);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $val = (float)$row['total_reais'];
                $custo = (float)$row['total_custo'];
                $dados['vendas_diarias']['datas'][]        = $row['data_formatada'];
                $dados['vendas_diarias']['datas_curtas'][] = $row['data_curta'];
                $dados['vendas_diarias']['valores'][]      = $val;
                $dados['vendas_diarias']['itens'][]        = (int)$row['total_itens'];
                $dados['vendas_diarias']['pedidos'][]      = (int)$row['total_pedidos'];

                $faturamentoAcumulado += $val;
                $custoTotalAcumulado += $custo;
                $totalVendasQtd += (int)$row['total_pedidos'];
            }
        }
    }

    $lucroBruto = $faturamentoAcumulado - $custoTotalAcumulado;
    $margemLucro = ($faturamentoAcumulado > 0) ? round(($lucroBruto / $faturamentoAcumulado) * 100, 1) : 0;

    $dados['kpis']['faturamento_total'] = $faturamentoAcumulado;
    $dados['kpis']['cmv_total']          = $custoTotalAcumulado;
    $dados['kpis']['lucro_bruto']        = $lucroBruto;
    $dados['kpis']['margem_lucro']       = $margemLucro;
    $dados['kpis']['total_vendas_qtd']   = $totalVendasQtd;
    $dados['kpis']['ticket_medio']       = $totalVendasQtd > 0 ? round($faturamentoAcumulado / $totalVendasQtd, 2) : 0;

    // 6. INTELIGÊNCIA LOGÍSTICA: CURVA ABC DE ESTOQUE (PARETO 80/20)
    $sqlCurva = "
        SELECT 
            p.idProduto, p.nomeProduto, p.categoria, p.preco, p.quantidade,
            COALESCE(SUM(CASE WHEN m.tipoMovimento = 'SAIDA' AND m.dataDevolucao IS NULL THEN m.quantidade * p.preco ELSE 0 END), 0) AS faturamento_produto,
            (p.quantidade * p.preco) AS valor_estoque
        FROM produto p
        LEFT JOIN movimento m ON m.idProduto = p.idProduto AND m.idUsuario = p.idUsuario
        WHERE p.idUsuario = ?
        GROUP BY p.idProduto, p.nomeProduto, p.categoria, p.preco, p.quantidade
        ORDER BY faturamento_produto DESC, valor_estoque DESC
    ";

    $dados['curva_abc'] = [
        'A' => ['produtos' => [], 'total_faturado' => 0, 'qtd_itens' => 0],
        'B' => ['produtos' => [], 'total_faturado' => 0, 'qtd_itens' => 0],
        'C' => ['produtos' => [], 'total_faturado' => 0, 'qtd_itens' => 0]
    ];

    if ($stmtCurva = mysqli_prepare($conexao, $sqlCurva)) {
        mysqli_stmt_bind_param($stmtCurva, "i", $idUsuario);
        mysqli_stmt_execute($stmtCurva);
        $resCurva = mysqli_stmt_get_result($stmtCurva);
        
        $listaItens = [];
        $somaTotalBase = 0;
        if ($resCurva) {
            while ($item = mysqli_fetch_assoc($resCurva)) {
                $baseCalculo = (float)$item['faturamento_produto'] > 0 ? (float)$item['faturamento_produto'] : (float)$item['valor_estoque'];
                $somaTotalBase += $baseCalculo;
                $item['baseCalculo'] = $baseCalculo;
                $listaItens[] = $item;
            }
        }

        $acumulado = 0;
        foreach ($listaItens as $it) {
            $base = $it['baseCalculo'];
            $acumulado += $base;
            $percentAcumulado = ($somaTotalBase > 0) ? ($acumulado / $somaTotalBase) * 100 : 100;

            if ($percentAcumulado <= 80 || count($dados['curva_abc']['A']['produtos']) === 0) {
                $classe = 'A';
            } elseif ($percentAcumulado <= 95) {
                $classe = 'B';
            } else {
                $classe = 'C';
            }

            $it['classeABC'] = $classe;
            $dados['curva_abc'][$classe]['produtos'][] = $it;
            $dados['curva_abc'][$classe]['total_faturado'] += $base;
            $dados['curva_abc'][$classe]['qtd_itens']++;
        }
    }

    // 7. SUGESTÃO INTELIGENTE DE REPOSIÇÃO (PONTO DE PEDIDO POR PRODUTO)
    $sqlReposicao = "
        SELECT 
            p.idProduto, p.nomeProduto, p.categoria, p.preco, p.precoCusto, p.quantidade, p.estoqueMinimo,
            p.nomeFornecedor, f.telefone, f.email
        FROM produto p
        LEFT JOIN fornecedor f ON f.nomeFornecedor = p.nomeFornecedor AND f.idUsuario = p.idUsuario
        WHERE p.idUsuario = ? AND p.quantidade <= p.estoqueMinimo
        ORDER BY (p.quantidade - p.estoqueMinimo) ASC, p.quantidade ASC
        LIMIT 10
    ";
    $dados['sugestoes_reposicao'] = [];
    if ($stmtRepo = mysqli_prepare($conexao, $sqlReposicao)) {
        mysqli_stmt_bind_param($stmtRepo, "i", $idUsuario);
        mysqli_stmt_execute($stmtRepo);
        $resRepo = mysqli_stmt_get_result($stmtRepo);
        if ($resRepo) {
            while ($repo = mysqli_fetch_assoc($resRepo)) {
                $minimo = (int)$repo['estoqueMinimo'];
                $atual = (int)$repo['quantidade'];
                $qtdSugerida = max(($minimo * 2) - $atual, 10);
                
                $telLimpo = preg_replace('/\D/', '', $repo['telefone'] ?? '');
                if (!empty($telLimpo) && strlen($telLimpo) <= 11) {
                    $telLimpo = '55' . $telLimpo;
                }

                $msgZap = urlencode("Olá " . ($repo['nomeFornecedor'] ?: 'Fornecedor') . "! Aqui é da MVM Estoque. Nosso produto '" . $repo['nomeProduto'] . "' atingiu o estoque crítico (restam apenas {$atual} un). Gostaríamos de cotar e solicitar reposição de {$qtdSugerida} unidades. Aguardo retorno!");

                $dados['sugestoes_reposicao'][] = [
                    'idProduto'      => (int)$repo['idProduto'],
                    'nomeProduto'    => $repo['nomeProduto'],
                    'categoria'      => $repo['categoria'] ?: 'Geral',
                    'estoqueAtual'   => $atual,
                    'estoqueMinimo'  => $minimo,
                    'qtdSugerida'    => $qtdSugerida,
                    'custoEstimado'  => round($qtdSugerida * (float)($repo['precoCusto'] > 0 ? $repo['precoCusto'] : $repo['preco'] * 0.65), 2),
                    'nomeFornecedor' => $repo['nomeFornecedor'] ?: 'Fornecedor não vinculado',
                    'telefone'       => $repo['telefone'],
                    'linkWhatsApp'   => !empty($telLimpo) ? "https://wa.me/{$telLimpo}?text={$msgZap}" : null
                ];
            }
        }
    }

    // 8. ALERTA DE PRODUTOS PRÓXIMOS DO VENCIMENTO (CONTROLE DE VALIDADE / FEFO)
    $sqlValidade = "
        SELECT 
            idProduto, nomeProduto, categoria, quantidade, lote, dataValidade,
            DATEDIFF(dataValidade, CURDATE()) AS dias_para_vencer
        FROM produto
        WHERE idUsuario = ? 
          AND dataValidade IS NOT NULL 
          AND dataValidade <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
          AND quantidade > 0
        ORDER BY dataValidade ASC
        LIMIT 10
    ";
    $dados['produtos_vencimento'] = [];
    if ($stmtVal = mysqli_prepare($conexao, $sqlValidade)) {
        mysqli_stmt_bind_param($stmtVal, "i", $idUsuario);
        mysqli_stmt_execute($stmtVal);
        $resVal = mysqli_stmt_get_result($stmtVal);
        if ($resVal) {
            while ($v = mysqli_fetch_assoc($resVal)) {
                $dados['produtos_vencimento'][] = [
                    'idProduto'        => (int)$v['idProduto'],
                    'nomeProduto'      => $v['nomeProduto'],
                    'categoria'        => $v['categoria'] ?: 'Geral',
                    'quantidade'       => (int)$v['quantidade'],
                    'lote'             => $v['lote'] ?: 'Sem Lote',
                    'dataValidade'     => date('d/m/Y', strtotime($v['dataValidade'])),
                    'diasParaVencer'   => (int)$v['dias_para_vencer'],
                    'statusVencimento' => ((int)$v['dias_para_vencer'] < 0) ? 'vencido' : (((int)$v['dias_para_vencer'] <= 15) ? 'urgente' : 'atencao')
                ];
            }
        }
    }

    mysqli_close($conexao);

    return $dados;
}
