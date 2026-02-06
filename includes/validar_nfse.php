<?php
/**
 * Funções para validar NFS-e em PDF
 * Extrai data de emissão e valor líquido, compara com dados do fechamento
 * 
 * Compatível com servidores sem bibliotecas externas (cPanel, etc)
 */

/**
 * Extrai texto de um arquivo PDF
 * Tenta múltiplos métodos em ordem de preferência
 * 
 * @param string $pdf_path Caminho completo do arquivo PDF
 * @return string|false Texto extraído ou false em caso de erro
 */
function extrair_texto_pdf($pdf_path) {
    // Verifica se o arquivo existe
    if (!file_exists($pdf_path)) {
        error_log("validar_nfse: Arquivo não encontrado: $pdf_path");
        return false;
    }
    
    $texto = '';
    
    // Método 1: Tenta usar smalot/pdfparser se disponível (via Composer)
    $parser_file = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($parser_file)) {
        try {
            require_once $parser_file;
            if (class_exists('\\Smalot\\PdfParser\\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdf_path);
                $texto = $pdf->getText();
                if (!empty(trim($texto))) {
                    error_log("validar_nfse: Texto extraído via smalot/pdfparser");
                    return $texto;
                }
            }
        } catch (Exception $e) {
            error_log("validar_nfse: Erro smalot/pdfparser: " . $e->getMessage());
            // Continua para próximo método
        }
    }
    
    // Método 2: Tenta usar pdftotext (poppler-utils) - comum em Linux/cPanel
    if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        $output = [];
        $pdftotext = '/usr/bin/pdftotext'; // Caminho comum em servidores Linux
        
        // No Windows, tenta outros caminhos
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $possible_paths = [
                'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
                'C:\\poppler\\bin\\pdftotext.exe',
                'pdftotext'
            ];
            foreach ($possible_paths as $path) {
                if (file_exists($path) || $path === 'pdftotext') {
                    $pdftotext = $path;
                    break;
                }
            }
        } else {
            // Linux - verifica se existe
            if (!file_exists($pdftotext)) {
                $pdftotext = 'pdftotext'; // Tenta no PATH
            }
        }
        
        $temp_txt = sys_get_temp_dir() . '/nfse_' . uniqid() . '.txt';
        $cmd = escapeshellarg($pdftotext) . ' -layout ' . escapeshellarg($pdf_path) . ' ' . escapeshellarg($temp_txt) . ' 2>&1';
        
        @exec($cmd, $output, $return_code);
        
        if ($return_code === 0 && file_exists($temp_txt)) {
            $texto = @file_get_contents($temp_txt);
            @unlink($temp_txt);
            if (!empty(trim($texto))) {
                error_log("validar_nfse: Texto extraído via pdftotext");
                return $texto;
            }
        }
    }
    
    // Método 3: Leitura direta do PDF (funciona para PDFs com texto não comprimido)
    $content = @file_get_contents($pdf_path);
    if ($content !== false) {
        // Tenta extrair streams de texto
        $texto = extrair_texto_pdf_raw($content);
        if (!empty(trim($texto))) {
            error_log("validar_nfse: Texto extraído via leitura raw");
            return $texto;
        }
    }
    
    error_log("validar_nfse: Não foi possível extrair texto do PDF");
    return false;
}

/**
 * Tenta extrair texto de um PDF raw (método básico)
 */
function extrair_texto_pdf_raw($content) {
    $texto = '';
    
    // Procura por streams de texto
    preg_match_all('/\(([^\)]+)\)/', $content, $matches);
    if (!empty($matches[1])) {
        $texto = implode(' ', $matches[1]);
    }
    
    // Procura por BT...ET blocks (text blocks)
    preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $block) {
            preg_match_all('/\(([^\)]+)\)/', $block, $text_matches);
            if (!empty($text_matches[1])) {
                $texto .= ' ' . implode(' ', $text_matches[1]);
            }
        }
    }
    
    return trim($texto);
}

/**
 * Extrai a data de emissão da NFS-e do texto
 * 
 * @param string $texto Texto extraído do PDF
 * @return string|null Data no formato Y-m-d ou null se não encontrada
 */
function extrair_data_emissao_nfse($texto) {
    // Padrão: "Data e Hora da emissão da NFS-e" seguido de data
    // Formato esperado: dd/mm/yyyy HH:ii:ss
    
    // Padrão 1: "Data e Hora da emissão da NFS-e\n05/02/2026 12:48:26"
    if (preg_match('/Data\s+e\s+Hora\s+da\s+emiss[ãa]o\s+da\s+NFS-?e[:\s]*(\d{2}\/\d{2}\/\d{4})/iu', $texto, $matches)) {
        $data_br = $matches[1];
        $partes = explode('/', $data_br);
        if (count($partes) === 3) {
            return $partes[2] . '-' . $partes[1] . '-' . $partes[0]; // Y-m-d
        }
    }
    
    // Padrão 2: Procura por "Competência" ou "Data de Emissão"
    if (preg_match('/Compet[êe]ncia\s+da\s+NFS-?e[:\s]*(\d{2}\/\d{2}\/\d{4})/iu', $texto, $matches)) {
        $data_br = $matches[1];
        $partes = explode('/', $data_br);
        if (count($partes) === 3) {
            return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
        }
    }
    
    // Padrão 3: Procura qualquer data no formato dd/mm/yyyy próxima de "emissão"
    if (preg_match('/emiss[ãa]o[^0-9]*(\d{2}\/\d{2}\/\d{4})/iu', $texto, $matches)) {
        $data_br = $matches[1];
        $partes = explode('/', $data_br);
        if (count($partes) === 3) {
            return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
        }
    }
    
    return null;
}

/**
 * Extrai o valor líquido da NFS-e do texto
 * 
 * @param string $texto Texto extraído do PDF
 * @return float|null Valor como float ou null se não encontrado
 */
function extrair_valor_liquido_nfse($texto) {
    // Padrão 1: "Valor Líquido da NFS-e\nR$ 3.008,26"
    if (preg_match('/Valor\s+L[íi]quido\s+da\s+NFS-?e[:\s]*R?\$?\s*([\d\.,]+)/iu', $texto, $matches)) {
        return converter_valor_br_para_float($matches[1]);
    }
    
    // Padrão 2: "Valor Líquido" seguido de valor
    if (preg_match('/Valor\s+L[íi]quido[:\s]*R?\$?\s*([\d\.,]+)/iu', $texto, $matches)) {
        return converter_valor_br_para_float($matches[1]);
    }
    
    // Padrão 3: "VALOR TOTAL DA NFS-E" - pega o valor do serviço
    if (preg_match('/Valor\s+do\s+Servi[çc]o[:\s]*R?\$?\s*([\d\.,]+)/iu', $texto, $matches)) {
        return converter_valor_br_para_float($matches[1]);
    }
    
    return null;
}

/**
 * Converte valor no formato brasileiro para float
 * Ex: "3.008,26" -> 3008.26
 */
function converter_valor_br_para_float($valor_str) {
    // Remove espaços
    $valor_str = trim($valor_str);
    
    // Remove R$ se presente
    $valor_str = preg_replace('/R\$\s*/', '', $valor_str);
    
    // Remove pontos de milhar e troca vírgula por ponto
    $valor_str = str_replace('.', '', $valor_str);
    $valor_str = str_replace(',', '.', $valor_str);
    
    return (float)$valor_str;
}

/**
 * Valida uma NFS-e contra os dados esperados
 * 
 * @param string $pdf_path Caminho do arquivo PDF
 * @param float $valor_esperado Valor esperado (do fechamento)
 * @param int $dias_tolerancia Dias de tolerância para data de emissão (padrão: 30)
 * @param float $tolerancia_valor Tolerância no valor em percentual (padrão: 0.01 = 1%)
 * @return array ['valido' => bool, 'aprovado' => bool, 'motivos' => [], 'dados_extraidos' => []]
 */
function validar_nfse($pdf_path, $valor_esperado, $dias_tolerancia = 30, $tolerancia_valor = 0.01) {
    $resultado = [
        'valido' => false,
        'aprovado' => false,
        'motivos' => [],
        'dados_extraidos' => [
            'data_emissao' => null,
            'valor_liquido' => null,
            'texto_extraido' => false
        ]
    ];
    
    // Extrai texto do PDF
    $texto = extrair_texto_pdf($pdf_path);
    
    if ($texto === false || empty(trim($texto))) {
        $resultado['motivos'][] = 'Não foi possível ler o conteúdo do PDF. Verifique se o arquivo não está corrompido ou protegido.';
        return $resultado;
    }
    
    $resultado['dados_extraidos']['texto_extraido'] = true;
    
    // Extrai data de emissão
    $data_emissao = extrair_data_emissao_nfse($texto);
    $resultado['dados_extraidos']['data_emissao'] = $data_emissao;
    
    // Extrai valor líquido
    $valor_liquido = extrair_valor_liquido_nfse($texto);
    $resultado['dados_extraidos']['valor_liquido'] = $valor_liquido;
    
    // Valida data
    $data_valida = false;
    if ($data_emissao === null) {
        $resultado['motivos'][] = 'Data de emissão da NFS-e não encontrada no documento.';
    } else {
        $data_emissao_dt = new DateTime($data_emissao);
        $hoje = new DateTime();
        $diff = $hoje->diff($data_emissao_dt);
        $dias = (int)$diff->format('%r%a'); // Negativo se passado
        
        $resultado['dados_extraidos']['data_emissao_formatada'] = $data_emissao_dt->format('d/m/Y');
        $resultado['dados_extraidos']['dias_diferenca'] = abs($dias);
        
        // Data não pode ser do futuro
        if ($dias > 0) {
            $resultado['motivos'][] = 'A data de emissão da NFS-e (' . $data_emissao_dt->format('d/m/Y') . ') está no futuro.';
        } 
        // Data não pode ser muito antiga
        elseif (abs($dias) > $dias_tolerancia) {
            $resultado['motivos'][] = 'A data de emissão da NFS-e (' . $data_emissao_dt->format('d/m/Y') . ') está fora do período permitido de ' . $dias_tolerancia . ' dias. Diferença: ' . abs($dias) . ' dias.';
        } else {
            $data_valida = true;
        }
    }
    
    // Valida valor
    $valor_valido = false;
    if ($valor_liquido === null) {
        $resultado['motivos'][] = 'Valor líquido da NFS-e não encontrado no documento.';
    } else {
        $resultado['dados_extraidos']['valor_liquido_formatado'] = 'R$ ' . number_format($valor_liquido, 2, ',', '.');
        $resultado['dados_extraidos']['valor_esperado_formatado'] = 'R$ ' . number_format($valor_esperado, 2, ',', '.');
        
        // Calcula diferença percentual
        if ($valor_esperado > 0) {
            $diferenca = abs($valor_liquido - $valor_esperado);
            $diferenca_percentual = $diferenca / $valor_esperado;
            $resultado['dados_extraidos']['diferenca_valor'] = $diferenca;
            $resultado['dados_extraidos']['diferenca_percentual'] = $diferenca_percentual * 100;
            
            if ($diferenca_percentual > $tolerancia_valor) {
                $resultado['motivos'][] = 'O valor da NFS-e (R$ ' . number_format($valor_liquido, 2, ',', '.') . ') não corresponde ao valor esperado (R$ ' . number_format($valor_esperado, 2, ',', '.'). '). Diferença: R$ ' . number_format($diferenca, 2, ',', '.');
            } else {
                $valor_valido = true;
            }
        } else {
            $resultado['motivos'][] = 'Valor esperado do fechamento é zero ou inválido.';
        }
    }
    
    // Define resultado final
    $resultado['valido'] = ($data_emissao !== null && $valor_liquido !== null);
    $resultado['aprovado'] = ($data_valida && $valor_valido);
    
    return $resultado;
}

/**
 * Formata os motivos de rejeição para exibição
 */
function formatar_motivos_rejeicao($motivos) {
    if (empty($motivos)) {
        return '';
    }
    
    if (count($motivos) === 1) {
        return $motivos[0];
    }
    
    return "• " . implode("\n• ", $motivos);
}
