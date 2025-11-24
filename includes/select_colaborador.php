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
 * Busca colaboradores disponíveis baseado no role do usuário
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @param array $usuario Usuário logado da sessão
 * @return array Array de colaboradores com id, nome_completo e foto
 */
function get_colaboradores_disponiveis($pdo, $usuario) {
    $colaboradores = [];
    
    try {
        // Validação básica
        if (empty($usuario) || empty($usuario['role'])) {
            return [];
        }
        
        if ($usuario['role'] === 'ADMIN') {
            $stmt = $pdo->query("
                SELECT id, nome_completo, foto 
                FROM colaboradores 
                WHERE status = 'ativo' 
                ORDER BY nome_completo
            ");
        } elseif ($usuario['role'] === 'RH') {
            // Verifica se tem empresas_ids (novo formato) ou empresa_id (formato antigo)
            if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
                $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
                $stmt = $pdo->prepare("
                    SELECT id, nome_completo, foto 
                    FROM colaboradores 
                    WHERE empresa_id IN ($placeholders) AND status = 'ativo' 
                    ORDER BY nome_completo
                ");
                $stmt->execute($usuario['empresas_ids']);
            } elseif (!empty($usuario['empresa_id'])) {
                $stmt = $pdo->prepare("
                    SELECT id, nome_completo, foto 
                    FROM colaboradores 
                    WHERE empresa_id = ? AND status = 'ativo' 
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
            
            $stmt = $pdo->prepare("
                SELECT id, nome_completo, foto 
                FROM colaboradores 
                WHERE setor_id = ? AND status = 'ativo' 
                ORDER BY nome_completo
            ");
            $stmt->execute([$setor_id]);
        } else {
            return [];
        }
        
        if (!isset($stmt)) {
            return [];
        }
        
        $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Garante que foto sempre tenha um valor padrão
        foreach ($colaboradores as &$colab) {
            if (empty($colab['foto'])) {
                $colab['foto'] = null;
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar colaboradores: " . $e->getMessage());
        return [];
    }
    
    return $colaboradores;
}

/**
 * Gera HTML do select de colaborador com suporte a busca e avatar
 * 
 * @param string $name Nome do campo (padrão: 'colaborador_id')
 * @param string $id ID do campo (padrão: mesmo que name)
 * @param int|null $selected_id ID do colaborador selecionado
 * @param array $colaboradores Array de colaboradores
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
        $data_foto = $foto_url ? ' data-foto="' . htmlspecialchars($foto_url) . '"' : '';
        $data_nome = ' data-nome="' . htmlspecialchars($colab['nome_completo']) . '"';
        
        $html .= '<option value="' . $colab['id'] . '"' . ($selected ? ' ' . $selected : '') . $data_foto . $data_nome . '>';
        $html .= $nome;
        $html .= '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

