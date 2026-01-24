<?php
/**
 * Funções Auxiliares para Sistema de Contratos
 */

require_once __DIR__ . '/functions.php';

/**
 * Substitui variáveis no template com dados do colaborador
 */
function substituir_variaveis_contrato($template, $colaborador, $contrato_data = []) {
    // Busca dados completos da empresa se tiver empresa_id
    $empresa = [];
    if (!empty($colaborador['empresa_id'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt->execute([$colaborador['empresa_id']]);
        $empresa = $stmt->fetch() ?: [];
    }
    
    // Monta endereço completo do colaborador
    $endereco_partes = [];
    if (!empty($colaborador['logradouro'])) {
        $endereco_partes[] = $colaborador['logradouro'];
        if (!empty($colaborador['numero'])) {
            $endereco_partes[0] .= ', ' . $colaborador['numero'];
        }
    }
    if (!empty($colaborador['complemento'])) {
        $endereco_partes[] = $colaborador['complemento'];
    }
    if (!empty($colaborador['bairro'])) {
        $endereco_partes[] = $colaborador['bairro'];
    }
    $cidade_estado = [];
    if (!empty($colaborador['cidade_endereco'])) {
        $cidade_estado[] = $colaborador['cidade_endereco'];
    }
    if (!empty($colaborador['estado_endereco'])) {
        $cidade_estado[] = $colaborador['estado_endereco'];
    }
    if (!empty($cidade_estado)) {
        $endereco_partes[] = implode('/', $cidade_estado);
    }
    $endereco_completo = implode(', ', $endereco_partes);
    
    // Dados do colaborador
    $variaveis = [
        '{{colaborador.nome_completo}}' => $colaborador['nome_completo'] ?? '',
        '{{colaborador.cpf}}' => formatar_cpf($colaborador['cpf'] ?? ''),
        '{{colaborador.cnpj}}' => formatar_cnpj($colaborador['cnpj'] ?? ''),
        '{{colaborador.rg}}' => $colaborador['rg'] ?? '',
        '{{colaborador.email_pessoal}}' => $colaborador['email_pessoal'] ?? '',
        '{{colaborador.telefone}}' => formatar_telefone($colaborador['telefone'] ?? ''),
        '{{colaborador.data_nascimento}}' => formatar_data($colaborador['data_nascimento'] ?? ''),
        '{{colaborador.endereco_completo}}' => $endereco_completo ?: ($colaborador['endereco_completo'] ?? ''),
        '{{colaborador.logradouro}}' => $colaborador['logradouro'] ?? '',
        '{{colaborador.numero}}' => $colaborador['numero'] ?? '',
        '{{colaborador.complemento}}' => $colaborador['complemento'] ?? '',
        '{{colaborador.bairro}}' => $colaborador['bairro'] ?? '',
        '{{colaborador.cidade}}' => $colaborador['cidade_endereco'] ?? $colaborador['cidade'] ?? '',
        '{{colaborador.estado}}' => $colaborador['estado_endereco'] ?? $colaborador['estado'] ?? '',
        '{{colaborador.cep}}' => formatar_cep($colaborador['cep'] ?? ''),
        '{{colaborador.empresa_nome}}' => $colaborador['empresa_nome'] ?? '',
        '{{colaborador.setor_nome}}' => $colaborador['setor_nome'] ?? '',
        '{{colaborador.cargo_nome}}' => $colaborador['cargo_nome'] ?? '',
        '{{colaborador.salario}}' => formatar_moeda($colaborador['salario'] ?? 0),
        '{{colaborador.salario_extenso}}' => numero_por_extenso($colaborador['salario'] ?? 0),
        '{{colaborador.data_admissao}}' => formatar_data($colaborador['data_admissao'] ?? ''),
    ];
    
    // Dados da empresa (contratante)
    $variaveis['{{empresa.nome_fantasia}}'] = $empresa['nome_fantasia'] ?? $colaborador['empresa_nome'] ?? '';
    $variaveis['{{empresa.razao_social}}'] = $empresa['razao_social'] ?? '';
    $variaveis['{{empresa.cnpj}}'] = formatar_cnpj($empresa['cnpj'] ?? '');
    $variaveis['{{empresa.telefone}}'] = formatar_telefone($empresa['telefone'] ?? '');
    $variaveis['{{empresa.email}}'] = $empresa['email'] ?? '';
    $variaveis['{{empresa.cidade}}'] = $empresa['cidade'] ?? '';
    $variaveis['{{empresa.estado}}'] = $empresa['estado'] ?? '';
    $variaveis['{{empresa.endereco}}'] = $empresa['endereco'] ?? '';
    $variaveis['{{empresa.cep}}'] = formatar_cep($empresa['cep'] ?? '');
    
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
 * Converte número para valor por extenso em reais
 */
function numero_por_extenso($valor) {
    $valor = floatval($valor);
    if ($valor == 0) {
        return 'zero reais';
    }
    
    $singular = ['centavo', 'real', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'];
    $plural = ['centavos', 'reais', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'];
    
    $c = ['', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
    $d = ['', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $d10 = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    $u = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
    
    $z = 0;
    $valor = number_format($valor, 2, '.', '.');
    $inteiro = explode('.', $valor);
    
    for ($i = 0; $i < count($inteiro); $i++) {
        for ($ii = mb_strlen($inteiro[$i]); $ii < 3; $ii++) {
            $inteiro[$i] = '0' . $inteiro[$i];
        }
    }
    
    $fim = count($inteiro) - ($inteiro[count($inteiro) - 1] > 0 ? 1 : 2);
    $rt = '';
    
    for ($i = 0; $i < count($inteiro); $i++) {
        $valor = $inteiro[$i];
        $rc = (($valor > 100) && ($valor < 200)) ? 'cento' : $c[$valor[0]];
        $rd = ($valor[1] < 2) ? '' : $d[$valor[1]];
        $ru = ($valor > 0) ? (($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]]) : '';
        
        $r = $rc . (($rc && ($rd || $ru)) ? ' e ' : '') . $rd . (($rd && $ru) ? ' e ' : '') . $ru;
        $t = count($inteiro) - 1 - $i;
        $r .= $r ? ' ' . ($valor > 1 ? $plural[$t] : $singular[$t]) : '';
        if ($valor == '000') $z++; elseif ($z > 0) $z--;
        if (($t == 1) && ($z > 0) && ($inteiro[0] > 0)) $r .= (($z > 1) ? ' de ' : '') . $plural[$t];
        if ($r) $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? (($i < $fim) ? ', ' : ' e ') : ' ') . $r;
    }
    
    return trim($rt);
}

/**
 * Formata CEP
 */
function formatar_cep($cep) {
    $cep = preg_replace('/[^0-9]/', '', $cep);
    if (strlen($cep) === 8) {
        return substr($cep, 0, 5) . '-' . substr($cep, 5);
    }
    return $cep;
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

