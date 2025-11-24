<?php
/**
 * Sistema de Autenticação
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session_config.php';

// Inicia sessão com configuração de 30 dias
iniciar_sessao_30_dias();

// Verifica e renova sessão se usuário estiver logado
if (isset($_SESSION['usuario'])) {
    verificar_e_renovar_sessao();
}

/**
 * Verifica se usuário pode acessar colaboradores de uma empresa
 */
function can_access_empresa($empresa_id) {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $user = $_SESSION['usuario'];
    
    // ADMIN pode acessar tudo
    if ($user['role'] === 'ADMIN') {
        return true;
    }
    
    // RH pode acessar empresas associadas a ele
    if ($user['role'] === 'RH') {
        // Verifica se tem empresas_ids na sessão (novo formato)
        if (isset($user['empresas_ids']) && is_array($user['empresas_ids'])) {
            return in_array($empresa_id, $user['empresas_ids']);
        }
        // Fallback para compatibilidade com formato antigo
        if (isset($user['empresa_id']) && $user['empresa_id'] == $empresa_id) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica se usuário pode acessar colaborador de um setor
 */
function can_access_setor($setor_id) {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $user = $_SESSION['usuario'];
    
    // ADMIN pode acessar tudo
    if ($user['role'] === 'ADMIN') {
        return true;
    }
    
    // RH pode acessar setores das empresas associadas a ele
    if ($user['role'] === 'RH') {
        $pdo = getDB();
        // Busca empresa do setor
        $stmt = $pdo->prepare("SELECT empresa_id FROM setores WHERE id = ?");
        $stmt->execute([$setor_id]);
        $setor = $stmt->fetch();
        
        if ($setor) {
            return can_access_empresa($setor['empresa_id']);
        }
        return false;
    }
    
    // GESTOR só pode acessar seu setor
    if ($user['role'] === 'GESTOR') {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user_data = $stmt->fetch();
        
        // Se usuário tem setor_id definido, verifica
        if (isset($user_data['setor_id']) && $user_data['setor_id'] == $setor_id) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica se usuário pode acessar um colaborador específico
 */
function can_access_colaborador($colaborador_id) {
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $user = $_SESSION['usuario'];
    
    // ADMIN pode acessar tudo
    if ($user['role'] === 'ADMIN') {
        return true;
    }
    
    // RH pode acessar colaboradores das empresas associadas a ele
    if ($user['role'] === 'RH') {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.empresa_id 
            FROM colaboradores c
            WHERE c.id = ?
        ");
        $stmt->execute([$colaborador_id]);
        $colaborador = $stmt->fetch();
        
        if ($colaborador) {
            return can_access_empresa($colaborador['empresa_id']);
        }
        return false;
    }
    
    // COLABORADOR só pode acessar seu próprio perfil
    if ($user['role'] === 'COLABORADOR' && $user['colaborador_id'] == $colaborador_id) {
        return true;
    }
    
    // GESTOR pode acessar colaboradores do seu setor
    if ($user['role'] === 'GESTOR') {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.setor_id 
            FROM colaboradores c
            WHERE c.id = ?
        ");
        $stmt->execute([$colaborador_id]);
        $colaborador = $stmt->fetch();
        
        if ($colaborador) {
            // Busca setor do gestor
            $stmt2 = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
            $stmt2->execute([$user['id']]);
            $user_data = $stmt2->fetch();
            
            if (isset($user_data['setor_id']) && $user_data['setor_id'] == $colaborador['setor_id']) {
                return true;
            }
        }
    }
    
    return false;
}

