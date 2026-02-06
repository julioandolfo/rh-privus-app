<?php
/**
 * Componente de Seleção de Colaborador com Busca e Avatar
 * 
 * Função para gerar select de colaborador com busca e avatar
 */

/**
 * Busca empresas disponíveis baseado no role do usuário
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @param array $usuario Usuário logado da sessão
 * @return array Array de empresas
 */
function get_empresas_disponiveis($pdo, $usuario) {
    if ($usuario['role'] === 'ADMIN') {
        $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
        return $stmt->fetchAll();
    } elseif ($usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
            $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
            $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
            $stmt->execute($usuario['empresas_ids']);
            return $stmt->fetchAll();
        }
    }
    return [];
}

/**
 * Busca colaboradores E usuários disponíveis baseado no role do usuário
 * Inclui tanto colaboradores quanto usuários sem colaborador vinculado
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @param array $usuario Usuário logado da sessão
 * @return array Array de pessoas (colaboradores e usuários) com id, nome_completo, foto e tipo
 */
function get_colaboradores_disponiveis($pdo, $usuario) {
    $pessoas = [];
    
    try {
        // Validação básica
        if (empty($usuario) || empty($usuario['role'])) {
            return [];
        }
        
        if ($usuario['role'] === 'ADMIN') {
            // ADMIN vê todos: colaboradores ativos + usuários sem colaborador
            $stmt = $pdo->query("
                SELECT 
                    CONCAT('c_', c.id) as id,
                    c.id as colaborador_id,
                    NULL as usuario_id,
                    c.nome_completo,
                    c.foto,
                    'colaborador' as tipo
                FROM colaboradores c
                WHERE c.status = 'ativo'
                
                UNION ALL
                
                SELECT 
                    CONCAT('u_', u.id) as id,
                    NULL as colaborador_id,
                    u.id as usuario_id,
                    u.nome as nome_completo,
                    NULL as foto,
                    'usuario' as tipo
                FROM usuarios u
                WHERE u.colaborador_id IS NULL
                AND u.status = 'ativo'
                
                ORDER BY nome_completo
            ");
        } elseif ($usuario['role'] === 'RH') {
            // RH vê colaboradores da(s) empresa(s) dele + usuários sem colaborador
            if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("
                    SELECT 
                        CONCAT('c_', c.id) as id,
                        c.id as colaborador_id,
                        NULL as usuario_id,
                        c.nome_completo,
                        c.foto,
                        'colaborador' as tipo
                    FROM colaboradores c
                    WHERE c.empresa_id IN ($placeholders) AND c.status = 'ativo'
                    
                    UNION ALL
                    
                    SELECT 
                        CONCAT('u_', u.id) as id,
                        NULL as colaborador_id,
                        u.id as usuario_id,
                        u.nome as nome_completo,
                        NULL as foto,
                        'usuario' as tipo
                    FROM usuarios u
                    WHERE u.colaborador_id IS NULL
                    AND u.status = 'ativo'
                    
                    ORDER BY nome_completo
                ");
                $stmt->execute($usuario['empresas_ids']);
            } elseif (!empty($usuario['empresa_id'])) {
                $stmt = $pdo->prepare("
                    SELECT 
                        CONCAT('c_', c.id) as id,
                        c.id as colaborador_id,
                        NULL as usuario_id,
                        c.nome_completo,
                        c.foto,
                        'colaborador' as tipo
                    FROM colaboradores c
                    WHERE c.empresa_id = ? AND c.status = 'ativo'
                    
                    UNION ALL
                    
                    SELECT 
                        CONCAT('u_', u.id) as id,
                        NULL as colaborador_id,
                        u.id as usuario_id,
                        u.nome as nome_completo,
                        NULL as foto,
                        'usuario' as tipo
                    FROM usuarios u
                    WHERE u.colaborador_id IS NULL
                    AND u.status = 'ativo'
                    
                    ORDER BY nome_completo
                ");
                $stmt->execute([$usuario['empresa_id']]);
            } else {
                return [];
            }
        } elseif ($usuario['role'] === 'GESTOR') {
            if (empty($usuario['id'])) {
                return [];
            }
            // Busca setor do gestor
            $stmt_user = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
            $stmt_user->execute([$usuario['id']]);
            $user_data = $stmt_user->fetch();
            $setor_id = $user_data['setor_id'] ?? 0;
            
            if (empty($setor_id)) {
                return [];
            }
            
            // GESTOR vê colaboradores do setor dele + usuários sem colaborador
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT('c_', c.id) as id,
                    c.id as colaborador_id,
                    NULL as usuario_id,
                    c.nome_completo,
                    c.foto,
                    'colaborador' as tipo
                FROM colaboradores c
                WHERE c.setor_id = ? AND c.status = 'ativo'
                
                UNION ALL
                
                SELECT 
                    CONCAT('u_', u.id) as id,
                    NULL as colaborador_id,
                    u.id as usuario_id,
                    u.nome as nome_completo,
                    NULL as foto,
                    'usuario' as tipo
                FROM usuarios u
                WHERE u.colaborador_id IS NULL
                AND u.status = 'ativo'
                
                ORDER BY nome_completo
            ");
            $stmt->execute([$setor_id]);
        } elseif ($usuario['role'] === 'COLABORADOR') {
            // Colaboradores podem ver outros da mesma empresa + usuários sem colaborador
            $colaborador_id = $usuario['colaborador_id'] ?? null;
            $empresa_id = $usuario['empresa_id'] ?? null;
            
            if (empty($colaborador_id)) {
                return [];
            }
            
            // Busca dados do colaborador logado para obter empresa_id se não estiver na sessão
            if (empty($empresa_id)) {
                $stmt_colab = $pdo->prepare("SELECT empresa_id FROM colaboradores WHERE id = ?");
                $stmt_colab->execute([$colaborador_id]);
                $colab_data = $stmt_colab->fetch();
                $empresa_id = $colab_data['empresa_id'] ?? null;
            }
            
            if (empty($empresa_id)) {
                return [];
            }
            
            // Busca todos da mesma empresa exceto ele mesmo + usuários sem colaborador
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT('c_', c.id) as id,
                    c.id as colaborador_id,
                    NULL as usuario_id,
                    c.nome_completo,
                    c.foto,
                    'colaborador' as tipo
                FROM colaboradores c
                WHERE c.empresa_id = ? AND c.id != ? AND c.status = 'ativo'
                
                UNION ALL
                
                SELECT 
                    CONCAT('u_', u.id) as id,
                    NULL as colaborador_id,
                    u.id as usuario_id,
                    u.nome as nome_completo,
                    NULL as foto,
                    'usuario' as tipo
                FROM usuarios u
                WHERE u.colaborador_id IS NULL
                AND u.status = 'ativo'
                
                ORDER BY nome_completo
            ");
            $stmt->execute([$empresa_id, $colaborador_id]);
        } else {
            return [];
        }
        
        if (!isset($stmt)) {
            return [];
        }
        
        $pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Garante que foto sempre tenha um valor padrão e adiciona badge de tipo
        foreach ($pessoas as &$pessoa) {
            if (empty($pessoa['foto'])) {
                $pessoa['foto'] = null;
            }
            // Adiciona indicador visual no nome se for usuário
            if ($pessoa['tipo'] === 'usuario') {
                $pessoa['nome_completo'] .= ' (Usuário)';
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar colaboradores e usuários: " . $e->getMessage());
        return [];
    }
    
    return $pessoas;
}

/**
 * Gera HTML do select de colaborador com suporte a busca e avatar
 * 
 * @param string $name Nome do campo (padrão: 'colaborador_id')
 * @param string $id ID do campo (padrão: mesmo que name)
 * @param int|null $selected_id ID do colaborador selecionado
 * @param array $colaboradores Array de colaboradores (pode conter campos extras como 'salario', etc.)
 * @param bool $required Se o campo é obrigatório
 * @param string $class Classes CSS adicionais
 * @return string HTML do select
 */
function render_select_colaborador($name = 'colaborador_id', $id = null, $selected_id = null, $colaboradores = [], $required = true, $class = '') {
    if ($id === null) {
        $id = $name;
    }
    
    $required_attr = $required ? 'required' : '';
    $class_attr = !empty($class) ? ' ' . $class : '';
    
    $html = '<select name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '" class="form-select form-select-solid select-colaborador' . $class_attr . '" ' . $required_attr . '>';
    $html .= '<option value="">Selecione um colaborador...</option>';
    
    foreach ($colaboradores as $colab) {
        if (empty($colab['id']) || empty($colab['nome_completo'])) {
            continue;
        }
        $selected = ($selected_id == $colab['id']) ? 'selected' : '';
        $nome = htmlspecialchars($colab['nome_completo']);
        $foto_url = !empty($colab['foto']) ? '../' . htmlspecialchars($colab['foto']) : null;
        
        // Armazena dados do colaborador em data attributes para uso no JavaScript
        $data_attrs = [];
        $data_attrs[] = $foto_url ? 'data-foto="' . htmlspecialchars($foto_url) . '"' : '';
        $data_attrs[] = 'data-nome="' . htmlspecialchars($colab['nome_completo']) . '"';
        
        // Adiciona atributos customizados se existirem (ex: salario, empresa_id, etc.)
        $custom_attrs = ['salario', 'empresa_id', 'setor_id', 'cargo_id'];
        foreach ($custom_attrs as $attr) {
            if (isset($colab[$attr]) && $colab[$attr] !== null && $colab[$attr] !== '') {
                $data_attrs[] = 'data-' . $attr . '="' . htmlspecialchars($colab[$attr]) . '"';
            }
        }
        
        $data_attrs_str = implode(' ', array_filter($data_attrs));
        
        $html .= '<option value="' . $colab['id'] . '"' . ($selected ? ' ' . $selected : '') . ($data_attrs_str ? ' ' . $data_attrs_str : '') . '>';
        $html .= $nome;
        $html .= '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

