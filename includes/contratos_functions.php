<?php
/**
 * Funções Auxiliares para Sistema de Contratos
 */

require_once __DIR__ . '/functions.php';

/**
 * Endereço da empresa em uma linha (logradouro, bairro, cidade/UF, CEP).
 */
function montar_endereco_completo_empresa(array $empresa) {
    $partes = [];
    if (!empty($empresa['endereco'])) {
        $partes[] = trim($empresa['endereco']);
    }
    if (!empty($empresa['bairro'])) {
        $partes[] = trim($empresa['bairro']);
    }
    $loc = array_filter([trim($empresa['cidade'] ?? ''), trim($empresa['estado'] ?? '')]);
    if ($loc) {
        $partes[] = implode('/', $loc);
    }
    if (!empty($empresa['cep'])) {
        $partes[] = formatar_cep($empresa['cep']);
    }
    return implode(', ', array_filter($partes));
}

/**
 * Estado civil para texto de contrato (ex.: distrato).
 */
function label_estado_civil_contrato($valor) {
    $map = [
        'solteiro' => 'solteiro(a)',
        'casado' => 'casado(a)',
        'divorciado' => 'divorciado(a)',
        'viuvo' => 'viúvo(a)',
        'uniao_estavel' => 'em união estável',
        'outro' => 'outro',
    ];
    $v = strtolower(trim((string)$valor));
    return $map[$v] ?? ($valor !== '' && $valor !== null ? (string)$valor : 'estado civil não informado');
}

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

    $tipo_contr = strtoupper(trim($colaborador['tipo_contrato'] ?? 'PJ'));
    $eh_clt = ($tipo_contr === 'CLT');
    $categoria_titulo = $eh_clt ? 'TRABALHO' : 'PRESTAÇÃO DE SERVIÇOS';
    $qualificacao = $eh_clt ? 'com vínculo empregatício' : 'na qualidade de prestador(a) de serviços';
    
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
        '{{colaborador.valor_hora}}' => formatar_moeda($colaborador['valor_hora'] ?? 0),
        '{{colaborador.valor_hora_extenso}}' => numero_por_extenso($colaborador['valor_hora'] ?? 0),
        '{{colaborador.data_admissao}}' => formatar_data($colaborador['data_admissao'] ?? ''),
        '{{colaborador.regiao}}' => $colaborador['regiao'] ?? '',
        '{{colaborador.estado_civil_label}}' => label_estado_civil_contrato($colaborador['estado_civil'] ?? ''),
        '{{colaborador.qualificacao_contratual}}' => $qualificacao,
        '{{colaborador.categoria_contrato_titulo}}' => $categoria_titulo,
    ];

    // Dados da empresa (contratante)
    $variaveis['{{empresa.nome_fantasia}}'] = $empresa['nome_fantasia'] ?? $colaborador['empresa_nome'] ?? '';
    $variaveis['{{empresa.razao_social}}'] = $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? $colaborador['empresa_nome'] ?? '';
    $variaveis['{{empresa.cnpj}}'] = formatar_cnpj($empresa['cnpj'] ?? '');
    $variaveis['{{empresa.telefone}}'] = formatar_telefone($empresa['telefone'] ?? '');
    $variaveis['{{empresa.email}}'] = $empresa['email'] ?? '';
    $variaveis['{{empresa.cidade}}'] = $empresa['cidade'] ?? '';
    $variaveis['{{empresa.estado}}'] = $empresa['estado'] ?? '';
    $variaveis['{{empresa.endereco}}'] = $empresa['endereco'] ?? '';
    $variaveis['{{empresa.bairro}}'] = $empresa['bairro'] ?? '';
    $variaveis['{{empresa.cep}}'] = formatar_cep($empresa['cep'] ?? '');
    $variaveis['{{empresa.endereco_completo}}'] = montar_endereco_completo_empresa($empresa) ?: ($empresa['endereco'] ?? '');

    // Dados do contrato
    $variaveis['{{contrato.titulo}}'] = $contrato_data['titulo'] ?? '';
    // Descrição da função: primeiro tenta usar do contrato, depois do cadastro do colaborador
    $descricao_raw = !empty($contrato_data['descricao_funcao'])
        ? $contrato_data['descricao_funcao']
        : ($colaborador['descricao_funcao'] ?? '');
    $variaveis['{{contrato.descricao_funcao}}'] = formatar_descricao_funcao_contrato($descricao_raw);
    // Também disponibiliza como variável do colaborador
    $variaveis['{{colaborador.descricao_funcao}}'] = formatar_descricao_funcao_contrato($colaborador['descricao_funcao'] ?? '');
    $variaveis['{{contrato.data_criacao}}'] = formatar_data($contrato_data['data_criacao'] ?? date('Y-m-d'));
    $variaveis['{{contrato.data_vencimento}}'] = formatar_data($contrato_data['data_vencimento'] ?? '');
    $variaveis['{{contrato.observacoes}}'] = $contrato_data['observacoes'] ?? '';

    // Demissão / distrato (preenchidos em contrato_data ao gerar distrato automático)
    $variaveis['{{demissao.data}}'] = formatar_data($contrato_data['demissao_data'] ?? '');
    $variaveis['{{demissao.tipo}}'] = $contrato_data['demissao_tipo'] ?? '';
    $variaveis['{{demissao.tipo_label}}'] = $contrato_data['demissao_tipo_label'] ?? '';
    $variaveis['{{demissao.motivo}}'] = $contrato_data['demissao_motivo'] ?? '';
    
    // Dados de data/hora
    $variaveis['{{data_atual}}'] = date('d/m/Y');
    $variaveis['{{hora_atual}}'] = date('H:i');
    $variaveis['{{data_formatada}}'] = date('d') . ' de ' . getNomeMes(date('m')) . ' de ' . date('Y');
    
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
 * Formata a descrição de função para HTML, preservando estrutura.
 * - Se cada linha (não vazia) começar com letra/número (a., b., 1., -, *), gera lista <ol> com letras a,b,c
 * - Caso contrário, usa nl2br para preservar quebras de linha
 * Aceita texto puro (textarea) e devolve HTML pronto para inserir no contrato.
 */
function formatar_descricao_funcao_contrato($texto) {
    if (empty($texto)) return '';

    // Se já vier com tag HTML (ex: lista pronta), retorna como está
    if (strip_tags($texto) !== $texto) {
        return $texto;
    }

    // Quebra em linhas não vazias
    $linhas = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $texto)), function($l) {
        return $l !== '';
    }));

    if (empty($linhas)) return '';

    // Detecta se as linhas já têm marcadores (a., b., 1., -, *) — remove o marcador
    $tem_marcadores = false;
    $linhas_limpas = [];
    foreach ($linhas as $l) {
        if (preg_match('/^([a-zA-Z]\.|[a-zA-Z]\)|\d+\.|\d+\)|\-|\*)\s*(.+)$/u', $l, $m)) {
            $tem_marcadores = true;
            $linhas_limpas[] = $m[2];
        } else {
            $linhas_limpas[] = $l;
        }
    }

    // Se tem mais de 1 linha (com ou sem marcadores), gera lista com letras a,b,c...
    if (count($linhas_limpas) > 1) {
        $html = '<ol type="a" style="margin-left: 20px; padding-left: 10px;">';
        foreach ($linhas_limpas as $item) {
            $html .= '<li style="margin-bottom: 4px;">' . htmlspecialchars($item) . '</li>';
        }
        $html .= '</ol>';
        return $html;
    }

    // Linha única — apenas escapa
    return nl2br(htmlspecialchars($linhas_limpas[0]));
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
 * Verifica quais campos do template estão faltando dados
 * Retorna array com variáveis faltantes e seus labels amigáveis
 */
function verificar_campos_faltantes_contrato($template, $colaborador, $contrato_data = [], $campos_manuais = []) {
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
    
    // Mapeamento de variáveis para valores e labels
    $mapa_variaveis = [
        // Colaborador
        'colaborador.nome_completo' => ['valor' => $colaborador['nome_completo'] ?? '', 'label' => 'Nome do Colaborador', 'tipo' => 'text'],
        'colaborador.cpf' => ['valor' => $colaborador['cpf'] ?? '', 'label' => 'CPF do Colaborador', 'tipo' => 'text'],
        'colaborador.cnpj' => ['valor' => $colaborador['cnpj'] ?? '', 'label' => 'CNPJ do Colaborador (PJ)', 'tipo' => 'text'],
        'colaborador.rg' => ['valor' => $colaborador['rg'] ?? '', 'label' => 'RG do Colaborador', 'tipo' => 'text'],
        'colaborador.email_pessoal' => ['valor' => $colaborador['email_pessoal'] ?? '', 'label' => 'Email do Colaborador', 'tipo' => 'email'],
        'colaborador.telefone' => ['valor' => $colaborador['telefone'] ?? '', 'label' => 'Telefone do Colaborador', 'tipo' => 'text'],
        'colaborador.endereco_completo' => ['valor' => $endereco_completo ?: ($colaborador['endereco_completo'] ?? ''), 'label' => 'Endereço do Colaborador', 'tipo' => 'text'],
        'colaborador.logradouro' => ['valor' => $colaborador['logradouro'] ?? '', 'label' => 'Logradouro do Colaborador', 'tipo' => 'text'],
        'colaborador.numero' => ['valor' => $colaborador['numero'] ?? '', 'label' => 'Número do Endereço', 'tipo' => 'text'],
        'colaborador.bairro' => ['valor' => $colaborador['bairro'] ?? '', 'label' => 'Bairro do Colaborador', 'tipo' => 'text'],
        'colaborador.cidade' => ['valor' => $colaborador['cidade_endereco'] ?? $colaborador['cidade'] ?? '', 'label' => 'Cidade do Colaborador', 'tipo' => 'text'],
        'colaborador.estado' => ['valor' => $colaborador['estado_endereco'] ?? $colaborador['estado'] ?? '', 'label' => 'Estado do Colaborador', 'tipo' => 'text'],
        'colaborador.cep' => ['valor' => $colaborador['cep'] ?? '', 'label' => 'CEP do Colaborador', 'tipo' => 'text'],
        'colaborador.salario' => ['valor' => $colaborador['salario'] ?? 0, 'label' => 'Salário/Valor Mensal', 'tipo' => 'number'],
        'colaborador.valor_hora' => ['valor' => $colaborador['valor_hora'] ?? 0, 'label' => 'Valor da Hora (PJ)', 'tipo' => 'number'],
        'colaborador.regiao' => ['valor' => $colaborador['regiao'] ?? '', 'label' => 'Região do Colaborador', 'tipo' => 'text'],
        
        // Empresa
        'empresa.nome_fantasia' => ['valor' => $empresa['nome_fantasia'] ?? $colaborador['empresa_nome'] ?? '', 'label' => 'Nome Fantasia da Empresa', 'tipo' => 'text'],
        'empresa.razao_social' => ['valor' => $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? $colaborador['empresa_nome'] ?? '', 'label' => 'Razão Social da Empresa', 'tipo' => 'text'],
        'empresa.cnpj' => ['valor' => $empresa['cnpj'] ?? '', 'label' => 'CNPJ da Empresa', 'tipo' => 'text'],
        'empresa.telefone' => ['valor' => $empresa['telefone'] ?? '', 'label' => 'Telefone da Empresa', 'tipo' => 'text'],
        'empresa.email' => ['valor' => $empresa['email'] ?? '', 'label' => 'Email da Empresa', 'tipo' => 'email'],
        'empresa.cidade' => ['valor' => $empresa['cidade'] ?? '', 'label' => 'Cidade da Empresa', 'tipo' => 'text'],
        'empresa.estado' => ['valor' => $empresa['estado'] ?? '', 'label' => 'Estado da Empresa', 'tipo' => 'text'],
        'empresa.endereco' => ['valor' => $empresa['endereco'] ?? '', 'label' => 'Endereço da Empresa', 'tipo' => 'text'],
        'empresa.cep' => ['valor' => $empresa['cep'] ?? '', 'label' => 'CEP da Empresa', 'tipo' => 'text'],
        'empresa.bairro' => ['valor' => $empresa['bairro'] ?? '', 'label' => 'Bairro da Empresa', 'tipo' => 'text'],
        'empresa.endereco_completo' => ['valor' => montar_endereco_completo_empresa($empresa), 'label' => 'Endereço completo da Empresa', 'tipo' => 'text'],
        
        // Contrato (descrição da função pode vir do contrato ou do cadastro do colaborador)
        'contrato.descricao_funcao' => [
            'valor' => !empty($contrato_data['descricao_funcao']) ? $contrato_data['descricao_funcao'] : ($colaborador['descricao_funcao'] ?? ''),
            'label' => 'Descrição da Função',
            'tipo' => 'textarea'
        ],
        'colaborador.descricao_funcao' => ['valor' => $colaborador['descricao_funcao'] ?? '', 'label' => 'Descrição da Função do Colaborador', 'tipo' => 'textarea'],

        // Valores financeiros do contrato (para templates de representação comercial)
        'contrato.valor_pedido' => ['valor' => $contrato_data['valor_pedido'] ?? '', 'label' => 'Valor por Pedido (R$)', 'tipo' => 'number'],
        'contrato.valor_pedido_extenso' => ['valor' => $contrato_data['valor_pedido_extenso'] ?? '', 'label' => 'Valor por Pedido (por extenso)', 'tipo' => 'text'],
        'contrato.valor_cliente_novo' => ['valor' => $contrato_data['valor_cliente_novo'] ?? '', 'label' => 'Valor Cliente Novo (R$)', 'tipo' => 'number'],
        'contrato.valor_cliente_novo_extenso' => ['valor' => $contrato_data['valor_cliente_novo_extenso'] ?? '', 'label' => 'Valor Cliente Novo (por extenso)', 'tipo' => 'text'],
        'contrato.ajuda_custo' => ['valor' => $contrato_data['ajuda_custo'] ?? '', 'label' => 'Ajuda de Custo (R$)', 'tipo' => 'number'],
        'contrato.ajuda_custo_extenso' => ['valor' => $contrato_data['ajuda_custo_extenso'] ?? '', 'label' => 'Ajuda de Custo (por extenso)', 'tipo' => 'text'],
        'contrato.percentual_minimo' => ['valor' => $contrato_data['percentual_minimo'] ?? '', 'label' => 'Percentual Mínimo Comissão (%)', 'tipo' => 'number'],
        'contrato.percentual_minimo_extenso' => ['valor' => $contrato_data['percentual_minimo_extenso'] ?? '', 'label' => 'Percentual Mínimo (por extenso)', 'tipo' => 'text'],
        'contrato.percentual_maximo' => ['valor' => $contrato_data['percentual_maximo'] ?? '', 'label' => 'Percentual Máximo Comissão (%)', 'tipo' => 'number'],
        'contrato.percentual_maximo_extenso' => ['valor' => $contrato_data['percentual_maximo_extenso'] ?? '', 'label' => 'Percentual Máximo (por extenso)', 'tipo' => 'text'],
        'contrato.bonificacao' => ['valor' => $contrato_data['bonificacao'] ?? '', 'label' => 'Bonificação por Produtividade (R$)', 'tipo' => 'number'],
        'contrato.bonificacao_extenso' => ['valor' => $contrato_data['bonificacao_extenso'] ?? '', 'label' => 'Bonificação (por extenso)', 'tipo' => 'text'],
        'contrato.valor_kit' => ['valor' => $contrato_data['valor_kit'] ?? '', 'label' => 'Valor do Kit de Produtos (R$)', 'tipo' => 'number'],
        'contrato.valor_kit_extenso' => ['valor' => $contrato_data['valor_kit_extenso'] ?? '', 'label' => 'Valor do Kit (por extenso)', 'tipo' => 'text'],

        // Demissão / distrato
        'demissao.data' => ['valor' => !empty($contrato_data['demissao_data']) ? formatar_data($contrato_data['demissao_data']) : '', 'label' => 'Data do desligamento', 'tipo' => 'text'],
        'demissao.tipo' => ['valor' => $contrato_data['demissao_tipo'] ?? '', 'label' => 'Tipo de demissão (código)', 'tipo' => 'text'],
        'demissao.tipo_label' => ['valor' => $contrato_data['demissao_tipo_label'] ?? '', 'label' => 'Tipo de demissão', 'tipo' => 'text'],
        'demissao.motivo' => ['valor' => $contrato_data['demissao_motivo'] ?? '', 'label' => 'Motivo do desligamento', 'tipo' => 'textarea'],
    ];
    
    // Extrai variáveis usadas no template
    $variaveis_template = extrair_variaveis_template($template);
    
    // Verifica quais estão faltando
    $faltantes = [];
    foreach ($variaveis_template as $variavel) {
        // Ignora variáveis de data (sempre preenchidas automaticamente)
        if (in_array($variavel, ['data_atual', 'hora_atual', 'data_formatada', 'contrato.titulo', 'contrato.data_criacao', 'contrato.data_vencimento', 'contrato.observacoes', 'demissao.tipo', 'colaborador.estado_civil_label', 'colaborador.qualificacao_contratual', 'colaborador.categoria_contrato_titulo'])) {
            continue;
        }
        
        // Ignora variáveis que não são obrigatórias (complemento, observacoes, etc)
        if (in_array($variavel, ['colaborador.complemento', 'colaborador.rg', 'demissao.motivo'])) {
            continue;
        }
        
        // Verifica se foi preenchido manualmente
        if (isset($campos_manuais[$variavel]) && !empty(trim($campos_manuais[$variavel]))) {
            continue;
        }
        
        // Verifica se tem valor no mapa
        if (isset($mapa_variaveis[$variavel])) {
            $info = $mapa_variaveis[$variavel];
            $valor = $info['valor'];
            
            // Considera vazio se for string vazia, null, ou 0 para salário
            $vazio = empty($valor) || (is_string($valor) && trim($valor) === '');
            if ($variavel === 'colaborador.salario' && (empty($valor) || floatval($valor) == 0)) {
                $vazio = true;
            }
            if ($variavel === 'colaborador.valor_hora' && (empty($valor) || floatval($valor) == 0)) {
                $vazio = true;
            }
            
            if ($vazio) {
                $faltantes[$variavel] = [
                    'variavel' => $variavel,
                    'label' => $info['label'],
                    'tipo' => $info['tipo'],
                    'placeholder' => "{{" . $variavel . "}}"
                ];
            }
        }
    }
    
    return $faltantes;
}

/**
 * Substitui variáveis no template, usando campos manuais quando disponíveis
 */
function substituir_variaveis_contrato_com_manuais($template, $colaborador, $contrato_data = [], $campos_manuais = []) {
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

    $tipo_contr = strtoupper(trim($colaborador['tipo_contrato'] ?? 'PJ'));
    $eh_clt = ($tipo_contr === 'CLT');
    $categoria_titulo = $eh_clt ? 'TRABALHO' : 'PRESTAÇÃO DE SERVIÇOS';
    $qualificacao = $eh_clt ? 'com vínculo empregatício' : 'na qualidade de prestador(a) de serviços';
    
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
        '{{colaborador.valor_hora}}' => formatar_moeda($colaborador['valor_hora'] ?? 0),
        '{{colaborador.valor_hora_extenso}}' => numero_por_extenso($colaborador['valor_hora'] ?? 0),
        '{{colaborador.data_admissao}}' => formatar_data($colaborador['data_admissao'] ?? ''),
        '{{colaborador.regiao}}' => $colaborador['regiao'] ?? '',
        '{{colaborador.estado_civil_label}}' => label_estado_civil_contrato($colaborador['estado_civil'] ?? ''),
        '{{colaborador.qualificacao_contratual}}' => $qualificacao,
        '{{colaborador.categoria_contrato_titulo}}' => $categoria_titulo,
    ];

    // Dados da empresa (contratante)
    $variaveis['{{empresa.nome_fantasia}}'] = $empresa['nome_fantasia'] ?? $colaborador['empresa_nome'] ?? '';
    $variaveis['{{empresa.razao_social}}'] = $empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? $colaborador['empresa_nome'] ?? '';
    $variaveis['{{empresa.cnpj}}'] = formatar_cnpj($empresa['cnpj'] ?? '');
    $variaveis['{{empresa.telefone}}'] = formatar_telefone($empresa['telefone'] ?? '');
    $variaveis['{{empresa.email}}'] = $empresa['email'] ?? '';
    $variaveis['{{empresa.cidade}}'] = $empresa['cidade'] ?? '';
    $variaveis['{{empresa.estado}}'] = $empresa['estado'] ?? '';
    $variaveis['{{empresa.endereco}}'] = $empresa['endereco'] ?? '';
    $variaveis['{{empresa.bairro}}'] = $empresa['bairro'] ?? '';
    $variaveis['{{empresa.cep}}'] = formatar_cep($empresa['cep'] ?? '');
    $variaveis['{{empresa.endereco_completo}}'] = montar_endereco_completo_empresa($empresa) ?: ($empresa['endereco'] ?? '');
    
    // Dados do contrato
    $variaveis['{{contrato.titulo}}'] = $contrato_data['titulo'] ?? '';
    // Descrição da função: primeiro tenta usar do contrato, depois do cadastro do colaborador
    // Se cada linha começar com letra/número (a., b., 1., -, *), gera lista HTML; senão usa nl2br
    $descricao_raw = !empty($contrato_data['descricao_funcao'])
        ? $contrato_data['descricao_funcao']
        : ($colaborador['descricao_funcao'] ?? '');
    $variaveis['{{contrato.descricao_funcao}}'] = formatar_descricao_funcao_contrato($descricao_raw);
    // Também disponibiliza como variável do colaborador
    $variaveis['{{colaborador.descricao_funcao}}'] = formatar_descricao_funcao_contrato($colaborador['descricao_funcao'] ?? '');
    $variaveis['{{contrato.data_criacao}}'] = formatar_data($contrato_data['data_criacao'] ?? date('Y-m-d'));
    $variaveis['{{contrato.data_vencimento}}'] = formatar_data($contrato_data['data_vencimento'] ?? '');
    $variaveis['{{contrato.observacoes}}'] = $contrato_data['observacoes'] ?? '';

    // Demissão / distrato
    $variaveis['{{demissao.data}}'] = formatar_data($contrato_data['demissao_data'] ?? '');
    $variaveis['{{demissao.tipo}}'] = $contrato_data['demissao_tipo'] ?? '';
    $variaveis['{{demissao.tipo_label}}'] = $contrato_data['demissao_tipo_label'] ?? '';
    $variaveis['{{demissao.motivo}}'] = $contrato_data['demissao_motivo'] ?? '';

    // Valores financeiros do contrato (para templates de representação comercial)
    $variaveis['{{contrato.valor_pedido}}'] = $contrato_data['valor_pedido'] ?? '';
    $variaveis['{{contrato.valor_pedido_extenso}}'] = $contrato_data['valor_pedido_extenso'] ?? '';
    $variaveis['{{contrato.valor_cliente_novo}}'] = $contrato_data['valor_cliente_novo'] ?? '';
    $variaveis['{{contrato.valor_cliente_novo_extenso}}'] = $contrato_data['valor_cliente_novo_extenso'] ?? '';
    $variaveis['{{contrato.ajuda_custo}}'] = $contrato_data['ajuda_custo'] ?? '';
    $variaveis['{{contrato.ajuda_custo_extenso}}'] = $contrato_data['ajuda_custo_extenso'] ?? '';
    $variaveis['{{contrato.percentual_minimo}}'] = $contrato_data['percentual_minimo'] ?? '';
    $variaveis['{{contrato.percentual_minimo_extenso}}'] = $contrato_data['percentual_minimo_extenso'] ?? '';
    $variaveis['{{contrato.percentual_maximo}}'] = $contrato_data['percentual_maximo'] ?? '';
    $variaveis['{{contrato.percentual_maximo_extenso}}'] = $contrato_data['percentual_maximo_extenso'] ?? '';
    $variaveis['{{contrato.bonificacao}}'] = $contrato_data['bonificacao'] ?? '';
    $variaveis['{{contrato.bonificacao_extenso}}'] = $contrato_data['bonificacao_extenso'] ?? '';
    $variaveis['{{contrato.valor_kit}}'] = $contrato_data['valor_kit'] ?? '';
    $variaveis['{{contrato.valor_kit_extenso}}'] = $contrato_data['valor_kit_extenso'] ?? '';

    // Dados de data/hora
    $variaveis['{{data_atual}}'] = date('d/m/Y');
    $variaveis['{{hora_atual}}'] = date('H:i');
    $variaveis['{{data_formatada}}'] = date('d') . ' de ' . getNomeMes(date('m')) . ' de ' . date('Y');

    // Aplica campos manuais (sobrescreve valores vazios)
    foreach ($campos_manuais as $variavel => $valor) {
        $chave = '{{' . $variavel . '}}';
        if (isset($variaveis[$chave]) && !empty(trim($valor))) {
            // Para salário manual, formata corretamente
            if ($variavel === 'colaborador.salario') {
                $variaveis[$chave] = formatar_moeda(floatval(str_replace(['.', ','], ['', '.'], $valor)));
                $variaveis['{{colaborador.salario_extenso}}'] = numero_por_extenso(floatval(str_replace(['.', ','], ['', '.'], $valor)));
            } elseif ($variavel === 'colaborador.valor_hora') {
                $variaveis[$chave] = formatar_moeda(floatval(str_replace(['.', ','], ['', '.'], $valor)));
                $variaveis['{{colaborador.valor_hora_extenso}}'] = numero_por_extenso(floatval(str_replace(['.', ','], ['', '.'], $valor)));
            } else {
                $variaveis[$chave] = htmlspecialchars($valor);
            }
        }
    }
    
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
    
    // Decodifica entidades HTML que podem ter sido codificadas pelo editor
    // Isso corrige problemas como &CCEDIL; => Ç, &ATILDE; => Ã, etc.
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Cria PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Remove header e footer padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Define margens
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
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

/**
 * Rótulo amigável do tipo de demissão (cadastro demissoes.tipo_demissao)
 */
function label_tipo_demissao($tipo) {
    $map = [
        'sem_justa_causa' => 'Dispensa sem justa causa',
        'justa_causa' => 'Dispensa por justa causa',
        'pedido_demissao' => 'Pedido de demissão',
        'aposentadoria' => 'Aposentadoria',
        'falecimento' => 'Falecimento',
        'outro' => 'Outro',
    ];
    return $map[$tipo] ?? ($tipo ?: '—');
}

