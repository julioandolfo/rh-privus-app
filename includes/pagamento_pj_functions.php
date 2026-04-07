<?php
/**
 * Funções do sistema de Solicitação de Pagamento PJ
 * - Upload de anexos (planilha CSV, NFe, Boleto)
 * - Parser e validação de planilha CSV
 * - Logs de auditoria
 */

/**
 * Faz upload de um anexo da solicitação PJ
 * @param array $file       $_FILES['xxx']
 * @param int   $colab_id   Colaborador
 * @param string $tipo      'planilha', 'nfe', 'boleto'
 * @param string $mes_ref   YYYY-MM
 * @return array
 */
function upload_anexo_pagamento_pj($file, $colab_id, $tipo, $mes_ref) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado'];
    }

    // Tipos permitidos por categoria
    $regras = [
        'planilha' => [
            'mimes' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
            'exts' => ['csv'],
            'label' => 'CSV'
        ],
        'nfe' => [
            'mimes' => ['application/pdf'],
            'exts' => ['pdf'],
            'label' => 'PDF'
        ],
        'boleto' => [
            'mimes' => ['application/pdf'],
            'exts' => ['pdf'],
            'label' => 'PDF'
        ]
    ];

    if (!isset($regras[$tipo])) {
        return ['success' => false, 'error' => 'Tipo de anexo inválido'];
    }

    $regra = $regras[$tipo];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $regra['exts'])) {
        return ['success' => false, 'error' => 'Formato inválido. Envie um arquivo ' . $regra['label']];
    }

    // Tamanho máximo: 10MB
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 10MB'];
    }

    // Diretório de destino
    $base_dir = __DIR__ . '/../uploads/pagamentos_pj/' . $colab_id . '/' . $mes_ref . '/';
    if (!file_exists($base_dir)) {
        @mkdir($base_dir, 0755, true);
    }

    $filename = $tipo . '_' . time() . '_' . uniqid() . '.' . $ext;
    $filepath = $base_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Erro ao salvar arquivo'];
    }

    return [
        'success' => true,
        'path' => 'uploads/pagamentos_pj/' . $colab_id . '/' . $mes_ref . '/' . $filename,
        'nome_original' => $file['name']
    ];
}

/**
 * Faz o parse e validação de uma planilha CSV de horas trabalhadas
 *
 * Formato esperado (Opção C - colunas fixas):
 *   Data;Hora Início;Hora Fim;Pausa (min);Horas Trabalhadas;Projeto;Descrição
 *
 * @param string $filepath Caminho absoluto do arquivo CSV
 * @param string $mes_ref  YYYY-MM (mês de referência)
 * @return array {valido, erros[], avisos[], total_horas, linhas[]}
 */
function validar_planilha_pagamento_pj($filepath, $mes_ref) {
    $resultado = [
        'valido' => false,
        'erros' => [],
        'avisos' => [],
        'total_horas' => 0.0,
        'linhas' => [],
        'total_linhas' => 0
    ];

    if (!file_exists($filepath)) {
        $resultado['erros'][] = 'Arquivo não encontrado';
        return $resultado;
    }

    // Detecta delimitador (vírgula, ponto-e-vírgula ou tab)
    $primeira_linha = '';
    $fp = fopen($filepath, 'r');
    if ($fp) {
        $primeira_linha = fgets($fp);
        fclose($fp);
    }
    $delim = ';';
    if (substr_count($primeira_linha, ',') > substr_count($primeira_linha, ';')) {
        $delim = ',';
    } elseif (substr_count($primeira_linha, "\t") > substr_count($primeira_linha, ';')) {
        $delim = "\t";
    }

    $fp = fopen($filepath, 'r');
    if (!$fp) {
        $resultado['erros'][] = 'Não foi possível abrir o arquivo';
        return $resultado;
    }

    // Lê cabeçalho
    $header = fgetcsv($fp, 0, $delim);
    if (!$header) {
        $resultado['erros'][] = 'Arquivo CSV vazio ou inválido';
        fclose($fp);
        return $resultado;
    }

    // Normaliza cabeçalho (remove acentos, lowercase)
    $header_norm = array_map(function($h) {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        return $h;
    }, $header);

    // Mapeia índices das colunas obrigatórias e opcionais
    $col_map = [
        'data' => array_search('data', $header_norm),
        'hora_inicio' => false,
        'hora_fim' => false,
        'pausa' => false,
        'horas_trabalhadas' => false,
        'projeto' => array_search('projeto', $header_norm),
        'descricao' => array_search('descricao', $header_norm),
    ];

    foreach ($header_norm as $i => $h) {
        if ($h === 'hora inicio' || $h === 'inicio' || $h === 'hora_inicio') $col_map['hora_inicio'] = $i;
        if ($h === 'hora fim' || $h === 'fim' || $h === 'hora_fim') $col_map['hora_fim'] = $i;
        if (strpos($h, 'pausa') !== false) $col_map['pausa'] = $i;
        if (strpos($h, 'horas trabalhadas') !== false || $h === 'horas') $col_map['horas_trabalhadas'] = $i;
        if (strpos($h, 'descric') !== false) $col_map['descricao'] = $i;
    }

    if ($col_map['data'] === false || $col_map['horas_trabalhadas'] === false) {
        $resultado['erros'][] = 'Cabeçalho inválido. Colunas obrigatórias: Data, Horas Trabalhadas. Use o modelo disponível para download.';
        fclose($fp);
        return $resultado;
    }

    $linhas_parseadas = [];
    $datas_vistas = [];
    $linha_num = 1;
    $ano_mes_ref = $mes_ref; // YYYY-MM

    while (($row = fgetcsv($fp, 0, $delim)) !== false) {
        $linha_num++;

        // Pula linhas vazias
        if (count(array_filter($row, function($v) { return trim($v) !== ''; })) === 0) {
            continue;
        }

        $data_raw = trim($row[$col_map['data']] ?? '');
        $horas_raw = trim($row[$col_map['horas_trabalhadas']] ?? '');
        $hora_ini_raw = $col_map['hora_inicio'] !== false ? trim($row[$col_map['hora_inicio']] ?? '') : '';
        $hora_fim_raw = $col_map['hora_fim'] !== false ? trim($row[$col_map['hora_fim']] ?? '') : '';
        $pausa_raw = $col_map['pausa'] !== false ? trim($row[$col_map['pausa']] ?? '') : '0';
        $projeto = $col_map['projeto'] !== false ? trim($row[$col_map['projeto']] ?? '') : '';
        $descricao = $col_map['descricao'] !== false ? trim($row[$col_map['descricao']] ?? '') : '';

        // Valida data (aceita DD/MM/YYYY ou YYYY-MM-DD)
        $data_obj = null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data_raw, $m)) {
            $data_obj = "{$m[3]}-{$m[2]}-{$m[1]}";
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data_raw, $m)) {
            $data_obj = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        if (!$data_obj || !strtotime($data_obj)) {
            $resultado['erros'][] = "Linha $linha_num: data inválida ('$data_raw'). Use DD/MM/AAAA";
            continue;
        }

        // Valida que data está no mês de referência
        if (substr($data_obj, 0, 7) !== $ano_mes_ref) {
            $resultado['erros'][] = "Linha $linha_num: data $data_raw fora do mês de referência ($ano_mes_ref)";
            continue;
        }

        // Valida horas trabalhadas
        $horas = (float) str_replace(',', '.', $horas_raw);
        if ($horas <= 0) {
            $resultado['erros'][] = "Linha $linha_num: horas trabalhadas devem ser maiores que zero";
            continue;
        }

        // Pausa em minutos
        $pausa_min = (int) preg_replace('/[^0-9]/', '', $pausa_raw);

        // Valida hora_inicio e hora_fim se preenchidas
        $hora_ini = null;
        $hora_fim = null;
        if ($hora_ini_raw && preg_match('/^(\d{1,2}):(\d{2})/', $hora_ini_raw, $m)) {
            $hora_ini = sprintf('%02d:%02d:00', $m[1], $m[2]);
        }
        if ($hora_fim_raw && preg_match('/^(\d{1,2}):(\d{2})/', $hora_fim_raw, $m)) {
            $hora_fim = sprintf('%02d:%02d:00', $m[1], $m[2]);
        }

        // Se ambas as horas foram preenchidas, valida coerência com horas_trabalhadas
        if ($hora_ini && $hora_fim) {
            $ts_ini = strtotime("$data_obj $hora_ini");
            $ts_fim = strtotime("$data_obj $hora_fim");
            if ($ts_fim <= $ts_ini) {
                $resultado['erros'][] = "Linha $linha_num: hora fim ($hora_fim_raw) deve ser maior que hora início ($hora_ini_raw)";
                continue;
            }
            $horas_calc = (($ts_fim - $ts_ini) - ($pausa_min * 60)) / 3600;
            // Tolerância de 5 minutos
            if (abs($horas_calc - $horas) > 0.0834) {
                $resultado['avisos'][] = "Linha $linha_num: horas trabalhadas ($horas) não bate com cálculo (início/fim - pausa = " . number_format($horas_calc, 2, ',', '.') . ")";
            }
        }

        // Avisa horas excessivas
        if ($horas > 14) {
            $resultado['avisos'][] = "Linha $linha_num: $horas horas em um único dia parece excessivo";
        }

        // Avisa fim de semana
        $dia_sem = (int) date('w', strtotime($data_obj));
        if ($dia_sem === 0 || $dia_sem === 6) {
            $resultado['avisos'][] = "Linha $linha_num: data $data_raw é fim de semana";
        }

        // Conta data duplicada apenas como aviso
        $datas_vistas[$data_obj] = ($datas_vistas[$data_obj] ?? 0) + 1;

        $linhas_parseadas[] = [
            'data_trabalho' => $data_obj,
            'hora_inicio' => $hora_ini,
            'hora_fim' => $hora_fim,
            'pausa_minutos' => $pausa_min,
            'horas_trabalhadas' => $horas,
            'projeto' => $projeto,
            'descricao' => $descricao
        ];

        $resultado['total_horas'] += $horas;
    }

    fclose($fp);

    if (empty($linhas_parseadas) && empty($resultado['erros'])) {
        $resultado['erros'][] = 'Nenhum registro válido encontrado na planilha';
    }

    if ($resultado['total_horas'] <= 0 && empty($resultado['erros'])) {
        $resultado['erros'][] = 'Total de horas deve ser maior que zero';
    }

    $resultado['linhas'] = $linhas_parseadas;
    $resultado['total_linhas'] = count($linhas_parseadas);
    $resultado['valido'] = empty($resultado['erros']);

    return $resultado;
}

/**
 * Registra log de auditoria de uma solicitação PJ
 */
function log_solicitacao_pj($pdo, $solicitacao_id, $acao, $detalhes = null, $usuario_id = null, $colaborador_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO solicitacoes_pagamento_pj_log
            (solicitacao_id, usuario_id, colaborador_id, acao, detalhes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$solicitacao_id, $usuario_id, $colaborador_id, $acao, $detalhes]);
    } catch (Exception $e) {
        error_log('Erro ao gravar log solicitacao_pj: ' . $e->getMessage());
    }
}

/**
 * Gera o conteúdo CSV do modelo de planilha (para download)
 */
function gerar_modelo_csv_pagamento_pj() {
    $linhas = [
        ['Data', 'Hora Inicio', 'Hora Fim', 'Pausa (min)', 'Horas Trabalhadas', 'Projeto', 'Descricao'],
        ['01/04/2026', '09:00', '18:00', '60', '8.00', 'Projeto X', 'Desenvolvimento de tela de login'],
        ['02/04/2026', '09:00', '18:00', '60', '8.00', 'Projeto X', 'Implementacao de API'],
        ['03/04/2026', '08:00', '12:00', '0', '4.00', 'Projeto Y', 'Reuniao com cliente'],
    ];
    $out = fopen('php://temp', 'r+');
    foreach ($linhas as $l) {
        fputcsv($out, $l, ';');
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    // BOM UTF-8 para Excel reconhecer acentos
    return "\xEF\xBB\xBF" . $csv;
}
