<?php
/**
 * Exportar Fechamento de Pagamento
 * Suporta PDF e XLS (CSV)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/pdf.php';

require_page_permission('fechamento_pagamentos.php');

/**
 * Verifica se o usuário tem permissão para acessar uma empresa específica
 */
function usuario_tem_permissao_empresa($usuario, $empresa_id) {
    if ($usuario['role'] === 'ADMIN') {
        return true;
    }
    if ($usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
            return in_array($empresa_id, $usuario['empresas_ids']);
        }
        return ($usuario['empresa_id'] ?? 0) == $empresa_id;
    }
    return ($usuario['empresa_id'] ?? 0) == $empresa_id;
}

$fechamento_id = (int)($_GET['id'] ?? 0);
$formato = strtolower($_GET['formato'] ?? 'pdf');
$apenas_aprovados = isset($_GET['apenas_aprovados']) && $_GET['apenas_aprovados'] == '1';

if (!$fechamento_id) {
    redirect('fechamento_pagamentos.php', 'Fechamento não encontrado!', 'error');
}

if (!in_array($formato, ['pdf', 'xls'])) {
    redirect('fechamento_pagamentos.php', 'Formato inválido!', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca dados do fechamento
$stmt = $pdo->prepare("
    SELECT f.*, e.nome_fantasia as empresa_nome
    FROM fechamentos_pagamento f
    LEFT JOIN empresas e ON f.empresa_id = e.id
    WHERE f.id = ?
");
$stmt->execute([$fechamento_id]);
$fechamento = $stmt->fetch();

if (!$fechamento) {
    redirect('fechamento_pagamentos.php', 'Fechamento não encontrado!', 'error');
}

// Verifica permissão
if (!usuario_tem_permissao_empresa($usuario, $fechamento['empresa_id'])) {
    redirect('fechamento_pagamentos.php', 'Você não tem permissão para exportar este fechamento!', 'error');
}

// Busca itens do fechamento com dados dos colaboradores
$where_status = $apenas_aprovados ? "AND i.documento_status = 'aprovado'" : "";
$stmt = $pdo->prepare("
    SELECT i.*, 
           c.nome_completo as colaborador_nome,
           c.cpf, c.cnpj,
           c.pix,
           c.banco, c.agencia, c.conta, c.tipo_conta,
           i.documento_status
    FROM fechamentos_pagamento_itens i
    INNER JOIN colaboradores c ON i.colaborador_id = c.id
    WHERE i.fechamento_id = ?
    $where_status
    ORDER BY c.nome_completo
");
$stmt->execute([$fechamento_id]);
$itens = $stmt->fetchAll();

// Busca bônus por colaborador
$bonus_por_colaborador = [];
$stmt_bonus = $pdo->prepare("
    SELECT fb.colaborador_id, fb.valor, tb.nome as tipo_bonus_nome, tb.tipo_valor
    FROM fechamentos_pagamento_bonus fb
    INNER JOIN tipos_bonus tb ON fb.tipo_bonus_id = tb.id
    WHERE fb.fechamento_pagamento_id = ?
");
$stmt_bonus->execute([$fechamento_id]);
$bonus_data = $stmt_bonus->fetchAll();
foreach ($bonus_data as $bonus) {
    if (!isset($bonus_por_colaborador[$bonus['colaborador_id']])) {
        $bonus_por_colaborador[$bonus['colaborador_id']] = [];
    }
    $bonus_por_colaborador[$bonus['colaborador_id']][] = $bonus;
}

// Processa dados para exportação
$dados_exportacao = [];
$is_fechamento_extra = ($fechamento['tipo_fechamento'] ?? 'regular') === 'extra';

foreach ($itens as $item) {
    $colab_id = $item['colaborador_id'];
    
    // Calcula valor total (mesmo cálculo usado na view)
    if ($is_fechamento_extra) {
        // Para fechamentos extras, usa valor_total diretamente
        $valor_total = $item['valor_total'];
    } else {
        // Para fechamentos regulares, calcula com bônus
        $bonus_colab = $bonus_por_colaborador[$colab_id] ?? [];
        $total_bonus = 0;
        foreach ($bonus_colab as $bonus_item) {
            $tipo_valor = $bonus_item['tipo_valor'] ?? 'variavel';
            if ($tipo_valor !== 'informativo') {
                $total_bonus += (float)($bonus_item['valor'] ?? 0);
            }
        }
        $valor_total = $item['salario_base'] + $item['valor_horas_extras'] + $total_bonus + ($item['adicionais'] ?? 0) - ($item['descontos'] ?? 0);
    }
    
    // CPF ou CNPJ (CNPJ tem prioridade)
    $cpf_cnpj = '';
    if (!empty($item['cnpj'])) {
        $cpf_cnpj = formatar_cnpj($item['cnpj']);
    } elseif (!empty($item['cpf'])) {
        $cpf_cnpj = formatar_cpf($item['cpf']);
    }
    
    // PIX ou dados bancários
    $dados_pagamento = '';
    if (!empty($item['pix'])) {
        $dados_pagamento = 'PIX: ' . $item['pix'];
    } elseif (!empty($item['banco'])) {
        $dados_pagamento = $item['banco'];
        if (!empty($item['agencia'])) {
            $dados_pagamento .= ' - Ag: ' . $item['agencia'];
        }
        if (!empty($item['conta'])) {
            $dados_pagamento .= ' - Conta: ' . $item['conta'];
            if (!empty($item['tipo_conta'])) {
                $dados_pagamento .= ' (' . ucfirst($item['tipo_conta']) . ')';
            }
        }
    }
    
    $dados_exportacao[] = [
        'colaborador' => $item['colaborador_nome'],
        'data_fechamento' => $fechamento['data_pagamento'] ? date('d/m/Y', strtotime($fechamento['data_pagamento'])) : date('d/m/Y', strtotime($fechamento['created_at'])),
        'mes_referencia' => date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')),
        'cpf_cnpj' => $cpf_cnpj,
        'dados_pagamento' => $dados_pagamento,
        'valor_pagar' => $valor_total
    ];
}

// Gera exportação
if ($formato === 'pdf') {
    gerar_pdf_fechamento($dados_exportacao, $fechamento);
} else {
    gerar_xls_fechamento($dados_exportacao, $fechamento);
}

/**
 * Gera PDF do fechamento
 */
function gerar_pdf_fechamento($dados, $fechamento) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Cria PDF em modo paisagem (landscape)
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Informações do documento
    $pdf->SetCreator('RH Privus');
    $pdf->SetAuthor('RH Privus');
    $pdf->SetTitle('Fechamento de Pagamento');
    $pdf->SetSubject('Fechamento de Pagamento');
    
    // Remove header e footer padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurações de margem (menores para aproveitar melhor o espaço)
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Adiciona página
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Fechamento de Pagamento', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 5, $fechamento['empresa_nome'], 0, 1, 'C');
    $pdf->Cell(0, 5, 'Mês/Ano: ' . date('m/Y', strtotime($fechamento['mes_referencia'] . '-01')), 0, 1, 'C');
    $pdf->Ln(3);
    
    // Calcula larguras das colunas (total disponível ~277mm em landscape A4)
    // Colaborador: 55mm, Data: 28mm, Mês: 22mm, CPF/CNPJ: 40mm, PIX: 70mm, Valor: 32mm
    $w_colaborador = 55;
    $w_data = 28;
    $w_mes = 22;
    $w_cpf_cnpj = 40;
    $w_pix = 70;
    $w_valor = 32;
    
    // Tabela
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(230, 230, 230);
    
    // Cabeçalho
    $pdf->Cell($w_colaborador, 6, 'Colaborador', 1, 0, 'C', true);
    $pdf->Cell($w_data, 6, 'Data Fech.', 1, 0, 'C', true);
    $pdf->Cell($w_mes, 6, 'Mês Ref.', 1, 0, 'C', true);
    $pdf->Cell($w_cpf_cnpj, 6, 'CPF/CNPJ', 1, 0, 'C', true);
    $pdf->Cell($w_pix, 6, 'PIX/Dados Bancários', 1, 0, 'C', true);
    $pdf->Cell($w_valor, 6, 'Valor a Pagar', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetFillColor(255, 255, 255);
    
    $total_geral = 0;
    foreach ($dados as $item) {
        // Trunca textos longos para caber nas colunas
        $nome = $item['colaborador'];
        if (strlen($nome) > 40) {
            $nome = substr($nome, 0, 37) . '...';
        }
        
        $pix_dados = $item['dados_pagamento'] ?: '-';
        if (strlen($pix_dados) > 55) {
            $pix_dados = substr($pix_dados, 0, 52) . '...';
        }
        
        $pdf->Cell($w_colaborador, 5, $nome, 1, 0, 'L');
        $pdf->Cell($w_data, 5, $item['data_fechamento'], 1, 0, 'C');
        $pdf->Cell($w_mes, 5, $item['mes_referencia'], 1, 0, 'C');
        $pdf->Cell($w_cpf_cnpj, 5, $item['cpf_cnpj'] ?: '-', 1, 0, 'C');
        $pdf->Cell($w_pix, 5, $pix_dados, 1, 0, 'L');
        $pdf->Cell($w_valor, 5, 'R$ ' . number_format($item['valor_pagar'], 2, ',', '.'), 1, 1, 'R');
        $total_geral += $item['valor_pagar'];
    }
    
    // Total
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell($w_colaborador + $w_data + $w_mes + $w_cpf_cnpj + $w_pix, 6, 'TOTAL', 1, 0, 'R', true);
    $pdf->Cell($w_valor, 6, 'R$ ' . number_format($total_geral, 2, ',', '.'), 1, 1, 'R', true);
    
    // Footer
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->Cell(0, 10, 'Gerado em ' . date('d/m/Y H:i') . ' - RH Privus', 0, 0, 'C');
    
    $nome_arquivo = 'fechamento_' . date('Y-m', strtotime($fechamento['mes_referencia'] . '-01')) . '.pdf';
    $pdf->Output($nome_arquivo, 'D');
}

/**
 * Gera XLS (CSV) do fechamento
 */
function gerar_xls_fechamento($dados, $fechamento) {
    $nome_arquivo = 'fechamento_' . date('Y-m', strtotime($fechamento['mes_referencia'] . '-01')) . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Adiciona BOM para UTF-8 (Excel reconhece melhor)
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Cabeçalho
    fputcsv($output, [
        'Colaborador',
        'Data do Fechamento',
        'Mês de Referência',
        'CPF ou CNPJ',
        'PIX ou Dados Bancários',
        'Valor a ser Pago'
    ], ';');
    
    // Dados
    foreach ($dados as $item) {
        fputcsv($output, [
            $item['colaborador'],
            $item['data_fechamento'],
            $item['mes_referencia'],
            $item['cpf_cnpj'],
            $item['dados_pagamento'],
            number_format($item['valor_pagar'], 2, ',', '.')
        ], ';');
    }
    
    // Total
    $total_geral = array_sum(array_column($dados, 'valor_pagar'));
    fputcsv($output, [
        '',
        '',
        '',
        '',
        'TOTAL',
        number_format($total_geral, 2, ',', '.')
    ], ';');
    
    fclose($output);
    exit;
}

