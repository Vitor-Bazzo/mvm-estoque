<?php
/**
 * Script para popular dados de exemplo reais no banco bdmvm:
 * - Garante produtos na categoria 'Bebidas' (e outras)
 * - Cria saídas/vendas realistas nos últimos 10 dias
 * Permite visualizar o Power BI com dados 100% gravados no banco.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';

function popularBancoComDadosBI($idUsuario = null) {
    if ($idUsuario === null) {
        $idUsuario = obterIdUsuarioLogado();
    }
    $idUsuario = max(1, (int)$idUsuario);

    $conexao = conectar();
    if (is_string($conexao) || !$conexao) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível conectar ao banco de dados: ' . $conexao];
    }

    // 1. Inserir ou atualizar produtos de exemplo, garantindo a categoria 'Bebidas'
    $produtosParaInserir = [
        ['nome' => 'Refrigerante Coca-Cola 2L', 'categoria' => 'Bebidas', 'preco' => 9.50, 'custo' => 5.20, 'qtd' => 120, 'min' => 25, 'loc' => 'A-01-01', 'img' => 'coca_cola_2l.jpg', 'desc' => 'Refrigerante de cola 2 litros garrafa PET', 'forn' => 'Nestlé Brasil Alimentos'],
        ['nome' => 'Suco de Laranja Integral 1L', 'categoria' => 'Bebidas', 'preco' => 11.90, 'custo' => 6.80, 'qtd' => 90, 'min' => 20, 'loc' => 'A-01-02', 'img' => 'suco_laranja_1l.jpg', 'desc' => 'Suco 100% natural sem adição de açúcares', 'forn' => 'Nestlé Brasil Alimentos'],
        ['nome' => 'Água Mineral Crystal 500ml', 'categoria' => 'Bebidas', 'preco' => 3.50, 'custo' => 1.40, 'qtd' => 200, 'min' => 30, 'loc' => 'A-01-03', 'img' => 'agua_mineral_500ml.jpg', 'desc' => 'Água mineral natural sem gás', 'forn' => 'Nestlé Brasil Alimentos'],
        ['nome' => 'Energético Red Bull 250ml', 'categoria' => 'Bebidas', 'preco' => 10.50, 'custo' => 6.10, 'qtd' => 85, 'min' => 15, 'loc' => 'A-01-04', 'img' => 'red_bull_250ml.jpg', 'desc' => 'Bebida energética gaseificada', 'forn' => 'Nestlé Brasil Alimentos'],
        ['nome' => 'Mouse Gamer Logitech G502 Hero', 'categoria' => 'Informatica', 'preco' => 249.90, 'custo' => 145.00, 'qtd' => 60, 'min' => 10, 'loc' => 'B-02-01', 'img' => 'mouse_gamer_logitech.jpg', 'desc' => 'Sensor Hero 25K, 11 botões programáveis', 'forn' => 'Logitech do Brasil'],
        ['nome' => 'Teclado Mecânico Redragon Kumara', 'categoria' => 'Informatica', 'preco' => 199.90, 'custo' => 110.00, 'qtd' => 45, 'min' => 10, 'loc' => 'B-02-02', 'img' => 'teclado_mecanico.jpg', 'desc' => 'Switch Outemu Blue LED', 'forn' => 'Tech Distribuidora'],
        ['nome' => 'SSD Kingston A400 480GB SATA III', 'categoria' => 'Informatica', 'preco' => 289.90, 'custo' => 160.00, 'qtd' => 70, 'min' => 15, 'loc' => 'B-02-03', 'img' => 'ssd_kingston_a400.jpg', 'desc' => 'Velocidade 500MB/s', 'forn' => 'Kingston Logística'],
        ['nome' => 'Protetor Solar Anthelios FPS 70', 'categoria' => 'Cosméticos', 'preco' => 89.90, 'custo' => 48.00, 'qtd' => 50, 'min' => 12, 'loc' => 'C-01-01', 'img' => 'protetor_solar_anthelios.jpg', 'desc' => 'Controle de oleosidade matte', 'forn' => 'LOréal Brasil Cosméticos'],
        ['nome' => 'Creme Hidratante Nivea Lata 56g', 'categoria' => 'Cosméticos', 'preco' => 15.90, 'custo' => 7.50, 'qtd' => 110, 'min' => 20, 'loc' => 'C-01-02', 'img' => 'creme_nivea_lata.webp', 'desc' => 'Hidratação profunda', 'forn' => 'Nivea Distribuição'],
        ['nome' => 'Café Pilão Tradicional 500g', 'categoria' => 'Alimentícios', 'preco' => 18.90, 'custo' => 11.20, 'qtd' => 85, 'min' => 20, 'loc' => 'A-02-01', 'img' => 'cafe_pilao_500g.jpg', 'desc' => 'Café torrado e moído', 'forn' => 'Nestlé Brasil Alimentos'],
        ['nome' => 'Smart TV Samsung 50 Crystal 4K', 'categoria' => 'Eletro-eletrônicos', 'preco' => 2399.00, 'custo' => 1650.00, 'qtd' => 12, 'min' => 5, 'loc' => 'D-01-01', 'img' => 'smart_tv_samsung_50.jpg', 'desc' => 'Resolução 4K UHD Smart TV', 'forn' => 'Samsung Eletrônica']
    ];

    $mapaIds = [];
    $mapaPrecos = [];

    foreach ($produtosParaInserir as $prod) {
        $mapaPrecos[$prod['nome']] = (float)$prod['preco'];
        $stmt = mysqli_prepare($conexao, "SELECT idProduto FROM produto WHERE nomeProduto = ? AND idUsuario = ?");
        mysqli_stmt_bind_param($stmt, "si", $prod['nome'], $idUsuario);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($linha = mysqli_fetch_assoc($res)) {
            $mapaIds[$prod['nome']] = (int)$linha['idProduto'];
        } else {
            $stmtIns = mysqli_prepare($conexao, "INSERT INTO produto (idUsuario, nomeProduto, categoria, preco, precoCusto, quantidade, estoqueMinimo, localizacao, imagem, descricao, nomeFornecedor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmtIns, "issddiissss", $idUsuario, $prod['nome'], $prod['categoria'], $prod['preco'], $prod['custo'], $prod['qtd'], $prod['min'], $prod['loc'], $prod['img'], $prod['desc'], $prod['forn']);
            mysqli_stmt_execute($stmtIns);
            $mapaIds[$prod['nome']] = mysqli_insert_id($conexao);
        }
    }

    // Atualiza lotes e datas de validade de exemplo para demonstrar a inteligência FEFO
    $validadesDemo = [
        'Suco de Laranja Integral 1L' => ['lote' => 'LOT-SUC-26A', 'dias' => 12],
        'Refrigerante Coca-Cola 2L' => ['lote' => 'LOT-REF-26B', 'dias' => 45],
        'Protetor Solar Anthelios FPS 70' => ['lote' => 'LOT-COS-70X', 'dias' => 20],
        'Creme Hidratante Nivea Lata 56g' => ['lote' => 'LOT-NIV-056', 'dias' => 90],
        'Café Pilão Tradicional 500g' => ['lote' => 'LOT-CAF-P50', 'dias' => 60]
    ];
    foreach ($validadesDemo as $pNome => $infoV) {
        $stmtV = mysqli_prepare($conexao, "UPDATE produto SET lote = ?, dataValidade = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE nomeProduto = ? AND idUsuario = ?");
        if ($stmtV) {
            mysqli_stmt_bind_param($stmtV, "sisi", $infoV['lote'], $infoV['dias'], $pNome, $idUsuario);
            mysqli_stmt_execute($stmtV);
        }
    }

    // 2. Criar saídas (vendas) com datas variadas nos últimos 10 dias
    // Configurado para dar destaque especial para Bebidas (ex: 50% de volume), Top 5 claro e curva diária
    $vendasExemplo = [
        // Dia 1 (9 dias atrás)
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 15, 'dias_atras' => 9, 'obs' => 'Venda balcão loja'],
        ['prod' => 'Suco de Laranja Integral 1L', 'qtd' => 8, 'dias_atras' => 9, 'obs' => 'Venda balcão loja'],
        ['prod' => 'Mouse Gamer Logitech G502 Hero', 'qtd' => 2, 'dias_atras' => 9, 'obs' => 'Venda e-commerce'],

        // Dia 2 (8 dias atrás)
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 20, 'dias_atras' => 8, 'obs' => 'Venda para evento'],
        ['prod' => 'Água Mineral Crystal 500ml', 'qtd' => 30, 'dias_atras' => 8, 'obs' => 'Venda balcão'],
        ['prod' => 'Teclado Mecânico Redragon Kumara', 'qtd' => 3, 'dias_atras' => 8, 'obs' => 'Venda direta'],

        // Dia 3 (7 dias atrás)
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 18, 'dias_atras' => 7, 'obs' => 'Venda balcão'],
        ['prod' => 'Energético Red Bull 250ml', 'qtd' => 25, 'dias_atras' => 7, 'obs' => 'Venda para confraternização'],
        ['prod' => 'SSD Kingston A400 480GB SATA III', 'qtd' => 4, 'dias_atras' => 7, 'obs' => 'Pedido cliente #104'],

        // Dia 4 (6 dias atrás)
        ['prod' => 'Suco de Laranja Integral 1L', 'qtd' => 22, 'dias_atras' => 6, 'obs' => 'Venda balcão'],
        ['prod' => 'Café Pilão Tradicional 500g', 'qtd' => 14, 'dias_atras' => 6, 'obs' => 'Venda mercadinho'],
        ['prod' => 'Protetor Solar Anthelios FPS 70', 'qtd' => 5, 'dias_atras' => 6, 'obs' => 'Venda farmácia parceira'],

        // Dia 5 (5 dias atrás)
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 35, 'dias_atras' => 5, 'obs' => 'Venda corporativa'],
        ['prod' => 'Mouse Gamer Logitech G502 Hero', 'qtd' => 5, 'dias_atras' => 5, 'obs' => 'Venda e-commerce'],
        ['prod' => 'Creme Hidratante Nivea Lata 56g', 'qtd' => 10, 'dias_atras' => 5, 'obs' => 'Venda balcão'],

        // Dia 6 (4 dias atrás)
        ['prod' => 'Água Mineral Crystal 500ml', 'qtd' => 45, 'dias_atras' => 4, 'obs' => 'Venda evento esportivo'],
        ['prod' => 'Energético Red Bull 250ml', 'qtd' => 18, 'dias_atras' => 4, 'obs' => 'Venda balcão'],
        ['prod' => 'Smart TV Samsung 50 Crystal 4K', 'qtd' => 1, 'dias_atras' => 4, 'obs' => 'Venda cliente Ana Silva'],

        // Dia 7 (3 dias atrás)
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 25, 'dias_atras' => 3, 'obs' => 'Venda balcão'],
        ['prod' => 'Suco de Laranja Integral 1L', 'qtd' => 15, 'dias_atras' => 3, 'obs' => 'Venda balcão'],
        ['prod' => 'Mouse Gamer Logitech G502 Hero', 'qtd' => 4, 'dias_atras' => 3, 'obs' => 'Venda online'],

        // Dia 8 (2 dias atrás)
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 30, 'dias_atras' => 2, 'obs' => 'Venda balcão'],
        ['prod' => 'Teclado Mecânico Redragon Kumara', 'qtd' => 4, 'dias_atras' => 2, 'obs' => 'Venda cliente Bruno'],
        ['prod' => 'Café Pilão Tradicional 500g', 'qtd' => 20, 'dias_atras' => 2, 'obs' => 'Venda atacado'],

        // Dia 9 (ontem)
        ['prod' => 'Energético Red Bull 250ml', 'qtd' => 22, 'dias_atras' => 1, 'obs' => 'Venda balcão'],
        ['prod' => 'SSD Kingston A400 480GB SATA III', 'qtd' => 6, 'dias_atras' => 1, 'obs' => 'Pedido #302'],
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 40, 'dias_atras' => 1, 'obs' => 'Venda balcão'],

        // Dia 10 (hoje)
        ['prod' => 'Refrigerante Coca-Cola 2L', 'qtd' => 28, 'dias_atras' => 0, 'obs' => 'Venda realizada hoje'],
        ['prod' => 'Suco de Laranja Integral 1L', 'qtd' => 18, 'dias_atras' => 0, 'obs' => 'Venda realizada hoje'],
        ['prod' => 'Mouse Gamer Logitech G502 Hero', 'qtd' => 3, 'dias_atras' => 0, 'obs' => 'Venda realizada hoje'],
        ['prod' => 'Smart TV Samsung 50 Crystal 4K', 'qtd' => 1, 'dias_atras' => 0, 'obs' => 'Venda realizada hoje']
    ];

    $totalInseridos = 0;
    foreach ($vendasExemplo as $v) {
        if (!isset($mapaIds[$v['prod']])) continue;
        $idProd = $mapaIds[$v['prod']];
        $qtd = (int)$v['qtd'];
        $obs = $v['obs'];
        $dias = (int)$v['dias_atras'];
        $valTotal = round($qtd * ($mapaPrecos[$v['prod']] ?? 0), 2);

        $sql = "INSERT INTO movimento (idUsuario, idProduto, idCliente, idFornecedor, tipoMovimento, quantidade, observacao, valorTotal, dataMovimento, dataDevolucao) 
                VALUES (?, ?, NULL, NULL, 'SAIDA', ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY), NULL)";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "iiisdi", $idUsuario, $idProd, $qtd, $obs, $valTotal, $dias);
        if (mysqli_stmt_execute($stmt)) {
            $totalInseridos++;
        }
    }

    mysqli_close($conexao);
    return ['sucesso' => true, 'mensagem' => "Dados de teste gerados com sucesso! ($totalInseridos vendas registradas em vários dias)."];
}

// Se for chamado diretamente via GET ou POST (com confirmação e autenticação)
if (isset($_GET['executar']) && $_GET['executar'] === 'sim') {
    if (!isset($_SESSION['usuarioLogado'])) {
        header('Location: ../modulos/auth/login.php');
        exit;
    }
    $res = popularBancoComDadosBI();
    $_SESSION['flash_dashboard'] = [
        'tipo'  => $res['sucesso'] ? 'sucesso' : 'erro',
        'texto' => $res['mensagem']
    ];
    header('Location: ../index.php');
    exit;
}
?>
