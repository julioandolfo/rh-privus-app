<?php
/**
 * Funções Auxiliares para Sistema de Contratos
 */

require_once __DIR__ . '/functions.php';

/**
 * Substitui variáveis no template com dados do colaborador
 */
function substituir_variaveis_contrato($template, $colaborador, $contrato_data = []) {
    // Dados do colaborador
    $variaveis = [
        '{{colaborador.nome_completo}}' => $colaborador['nome_completo'] ?? '',
        '{{colaborador.cpf}}' => formatar_cpf($colaborador['cpf'] ?? ''),
        '{{colaborador.rg}}' => $colaborador['rg'] ?? '',
        '{{colaborador.email_pessoal}}' => $colaborador['email_pessoal'] ?? '',
        '{{colaborador.telefone}}' => formatar_telefone($colaborador['telefone'] ?? ''),
        '{{colaborador.data_nascimento}}' => formatar_data($colaborador['data_nascimento'] ?? ''),
        '{{colaborador.endereco_completo}}' => $colaborador['endereco_completo'] ?? '',
        '{{colaborador.cidade}}' => $colaborador['cidade'] ?? '',
        '{{colaborador.estado}}' => $colaborador['estado'] ?? '',
        '{{colaborador.cep}}' => $colaborador['cep'] ?? '',
        '{{colaborador.empresa_nome}}' => $colaborador['empresa_nome'] ?? '',
        '{{colaborador.setor_nome}}' => $colaborador['setor_nome'] ?? '',
        '{{colaborador.cargo_nome}}' => $colaborador['cargo_nome'] ?? '',
        '{{colaborador.salario}}' => formatar_moeda($colaborador['salario'] ?? 0),
        '{{colaborador.data_admissao}}' => formatar_data($colaborador['data_admissao'] ?? ''),
    ];
    
    // Dados do contrato
    $variaveis['{{contrato.titulo}}'] = $contrato_data['titulo'] ?? '';
    $variaveis['{{contrato.descricao_funcao}}'] = $contrato_data['descricao_funcao'] ?? '';
    $variaveis['{{contrato.data_criacao}}'] = formatar_data($contrato_data['data_criacao'] ?? date('Y-m-d'));
    $variaveis['{{contrato.data_vencimento}}'] = formatar_data($contrato_data['data_vencimento'] ?? '');
    $variaveis['{{contrato.observacoes}}'] = $contrato_data['observacoes'] ?? '';
    
    // Dados de data/hora
    $variaveis['{{data_atual}}'] = date('d/m/Y');
    $variaveis['{{hora_atual}}'] = date('H:i');
    $variaveis['{{data_formatada}}'] = date('d de ') . getNomeMes(date('m')) . date(' de Y');
    
    // Substitui todas as variáveis
    $resultado = $template;
    foreach ($variaveis as $variavel => $valor) {
        $resultado = str_replace($variavel, $valor, $resultado);
    }
    
    return $resultado;
}

/**
 * Retorna nome do mês em português
 */
function getNomeMes($mes) {
    $meses = [
        '01' => 'janeiro', '02' => 'fevereiro', '03' => 'março',
        '04' => 'abril', '05' => 'maio', '06' => 'junho',
        '07' => 'julho', '08' => 'agosto', '09' => 'setembro',
        '10' => 'outubro', '11' => 'novembro', '12' => 'dezembro'
    ];
    return $meses[$mes] ?? '';
}

/**
 * Extrai variáveis usadas no template
 */
function extrair_variaveis_template($template) {
    preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
    return array_unique($matches[1] ?? []);
}

/**
 * Gera PDF do contrato usando TCPDF
 */
function gerar_pdf_contrato($html, $titulo = 'Contrato') {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Cria diretório se não existir
    $upload_dir = __DIR__ . '/../uploads/contratos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Cria PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Remove header e footer padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Adiciona página
    $pdf->AddPage();
    
    // Converte HTML para PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Gera nome único para o arquivo
    $filename = 'contrato_' . time() . '_' . uniqid() . '.pdf';
    $filepath = $upload_dir . $filename;
    
    // Salva PDF
    $pdf->Output($filepath, 'F');
    
    return 'uploads/contratos/' . $filename;
}

/**
 * Converte PDF para base64 (para envio ao Autentique)
 */
function pdf_para_base64($pdf_path) {
    $full_path = __DIR__ . '/../' . $pdf_path;
    if (!file_exists($full_path)) {
        throw new Exception('Arquivo PDF não encontrado: ' . $pdf_path);
    }
    
    $content = file_get_contents($full_path);
    return base64_encode($content);
}

/**
 * Busca dados completos do colaborador para substituição
 */
function buscar_dados_colaborador_completos($colaborador_id) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT c.*, 
               e.nome_fantasia as empresa_nome,
               s.nome_setor as setor_nome,
               car.nome_cargo as cargo_nome
        FROM colaboradores c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        LEFT JOIN setores s ON c.setor_id = s.id
        LEFT JOIN cargos car ON c.cargo_id = car.id
        WHERE c.id = ?
    ");
    $stmt->execute([$colaborador_id]);
    
    return $stmt->fetch();
}

