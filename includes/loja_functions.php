<?php
/**
 * Funções auxiliares da Loja de Pontos
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pontuacao.php';

/**
 * Obtém configuração da loja
 */
function loja_config($chave, $default = null) {
    static $cache = null;
    
    if ($cache === null) {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT chave, valor, tipo FROM loja_config");
        $cache = [];
        while ($row = $stmt->fetch()) {
            $valor = $row['valor'];
            if ($row['tipo'] === 'boolean') {
                $valor = (bool)$valor;
            } elseif ($row['tipo'] === 'number') {
                $valor = (int)$valor;
            } elseif ($row['tipo'] === 'json') {
                $valor = json_decode($valor, true);
            }
            $cache[$row['chave']] = $valor;
        }
    }
    
    return $cache[$chave] ?? $default;
}

/**
 * Atualiza configuração da loja
 */
function loja_config_set($chave, $valor) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE loja_config SET valor = ? WHERE chave = ?");
    return $stmt->execute([$valor, $chave]);
}

/**
 * Verifica se a loja está ativa
 */
function loja_ativa() {
    return loja_config('loja_ativa', true);
}

/**
 * Verifica se aprovação é obrigatória
 */
function loja_aprovacao_obrigatoria() {
    return loja_config('aprovacao_obrigatoria', true);
}

/**
 * Obtém categorias ativas
 */
function loja_get_categorias($apenas_ativas = true) {
    $pdo = getDB();
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM loja_produtos p WHERE p.categoria_id = c.id AND p.ativo = 1) as total_produtos
            FROM loja_categorias c";
    if ($apenas_ativas) {
        $sql .= " WHERE c.ativo = 1";
    }
    $sql .= " ORDER BY c.ordem, c.nome";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Obtém produtos com filtros
 */
function loja_get_produtos($filtros = []) {
    $pdo = getDB();
    
    $where = ["p.ativo = 1"];
    $params = [];
    
    // Filtro por categoria
    if (!empty($filtros['categoria_id'])) {
        $where[] = "p.categoria_id = ?";
        $params[] = $filtros['categoria_id'];
    }
    
    // Filtro por destaque
    if (isset($filtros['destaque']) && $filtros['destaque']) {
        $where[] = "p.destaque = 1";
    }
    
    // Filtro por novidade (produtos criados nos últimos X dias)
    if (isset($filtros['novidade']) && $filtros['novidade']) {
        $dias = loja_config('dias_novidade', 7);
        $where[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $dias;
    }
    
    // Filtro por disponibilidade
    $where[] = "(p.disponivel_de IS NULL OR p.disponivel_de <= CURDATE())";
    $where[] = "(p.disponivel_ate IS NULL OR p.disponivel_ate >= CURDATE())";
    
    // Filtro por estoque
    if (isset($filtros['em_estoque']) && $filtros['em_estoque']) {
        $where[] = "(p.estoque IS NULL OR p.estoque > 0)";
    }
    
    // Filtro por busca
    if (!empty($filtros['busca'])) {
        $where[] = "(p.nome LIKE ? OR p.descricao LIKE ? OR p.descricao_curta LIKE ?)";
        $busca = '%' . $filtros['busca'] . '%';
        $params[] = $busca;
        $params[] = $busca;
        $params[] = $busca;
    }
    
    // Filtro por pontos máximos
    if (!empty($filtros['pontos_max'])) {
        $where[] = "p.pontos_necessarios <= ?";
        $params[] = $filtros['pontos_max'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Ordenação
    $order = "p.destaque DESC, p.created_at DESC";
    if (!empty($filtros['ordem'])) {
        switch ($filtros['ordem']) {
            case 'pontos_asc': $order = "p.pontos_necessarios ASC"; break;
            case 'pontos_desc': $order = "p.pontos_necessarios DESC"; break;
            case 'nome': $order = "p.nome ASC"; break;
            case 'popular': $order = "p.total_resgates DESC"; break;
            case 'recente': $order = "p.created_at DESC"; break;
        }
    }
    
    // Limite
    $limit = "";
    if (!empty($filtros['limite'])) {
        $limit = " LIMIT " . (int)$filtros['limite'];
    }
    
    $sql = "
        SELECT p.*, c.nome as categoria_nome, c.icone as categoria_icone, c.cor as categoria_cor
        FROM loja_produtos p
        INNER JOIN loja_categorias c ON p.categoria_id = c.id
        WHERE $where_sql
        ORDER BY $order
        $limit
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Obtém um produto por ID
 */
function loja_get_produto($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome as categoria_nome, c.icone as categoria_icone, c.cor as categoria_cor
        FROM loja_produtos p
        INNER JOIN loja_categorias c ON p.categoria_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Verifica se colaborador pode resgatar produto
 * 
 * @param int $colaborador_id ID do colaborador
 * @param int $produto_id ID do produto
 * @param int $quantidade Quantidade a resgatar
 * @param string $forma_pagamento 'pontos' ou 'dinheiro'
 */
function loja_pode_resgatar($colaborador_id, $produto_id, $quantidade = 1, $forma_pagamento = 'pontos') {
    $pdo = getDB();
    
    // Verifica se loja está ativa
    if (!loja_ativa()) {
        return ['pode' => false, 'motivo' => 'A loja está temporariamente fechada.'];
    }
    
    // Busca produto
    $produto = loja_get_produto($produto_id);
    if (!$produto || !$produto['ativo']) {
        return ['pode' => false, 'motivo' => 'Produto não encontrado ou indisponível.'];
    }
    
    // Verifica disponibilidade por data
    $hoje = date('Y-m-d');
    if ($produto['disponivel_de'] && $produto['disponivel_de'] > $hoje) {
        return ['pode' => false, 'motivo' => 'Este produto ainda não está disponível.'];
    }
    if ($produto['disponivel_ate'] && $produto['disponivel_ate'] < $hoje) {
        return ['pode' => false, 'motivo' => 'Este produto não está mais disponível.'];
    }
    
    // Verifica estoque
    if ($produto['estoque'] !== null && $produto['estoque'] < $quantidade) {
        if ($produto['estoque'] == 0) {
            return ['pode' => false, 'motivo' => 'Produto esgotado.'];
        }
        return ['pode' => false, 'motivo' => "Estoque insuficiente. Disponível: {$produto['estoque']}"];
    }
    
    // Verifica limite por colaborador
    if ($produto['limite_por_colaborador'] !== null) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantidade), 0) as total 
            FROM loja_resgates 
            WHERE colaborador_id = ? AND produto_id = ? AND status NOT IN ('cancelado', 'rejeitado')
        ");
        $stmt->execute([$colaborador_id, $produto_id]);
        $total_resgatado = $stmt->fetch()['total'];
        
        if (($total_resgatado + $quantidade) > $produto['limite_por_colaborador']) {
            $restante = $produto['limite_por_colaborador'] - $total_resgatado;
            if ($restante <= 0) {
                return ['pode' => false, 'motivo' => 'Você já atingiu o limite de resgates deste produto.'];
            }
            return ['pode' => false, 'motivo' => "Limite por colaborador: você pode resgatar mais {$restante} unidade(s)."];
        }
    }
    
    // Verifica limite mensal de resgates
    $limite_mes = loja_config('limite_resgates_mes', 0);
    if ($limite_mes > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM loja_resgates 
            WHERE colaborador_id = ? 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
            AND status NOT IN ('cancelado', 'rejeitado')
        ");
        $stmt->execute([$colaborador_id]);
        $resgates_mes = $stmt->fetch()['total'];
        
        if ($resgates_mes >= $limite_mes) {
            return ['pode' => false, 'motivo' => "Você atingiu o limite de {$limite_mes} resgates por mês."];
        }
    }
    
    // Verifica saldo conforme forma de pagamento
    if ($forma_pagamento === 'dinheiro') {
        // Verifica se produto aceita pagamento em R$
        if (empty($produto['preco_dinheiro']) || floatval($produto['preco_dinheiro']) <= 0) {
            return ['pode' => false, 'motivo' => 'Este produto não aceita pagamento em R$.'];
        }
        
        $valor_necessario = floatval($produto['preco_dinheiro']) * $quantidade;
        $saldo_dinheiro = obter_saldo_dinheiro($colaborador_id);
        
        if ($saldo_dinheiro < $valor_necessario) {
            $faltam = $valor_necessario - $saldo_dinheiro;
            return ['pode' => false, 'motivo' => "Saldo insuficiente. Faltam R$ " . number_format($faltam, 2, ',', '.')];
        }
        
        return [
            'pode' => true,
            'produto' => $produto,
            'forma_pagamento' => 'dinheiro',
            'valor_necessario' => $valor_necessario,
            'saldo_atual' => $saldo_dinheiro,
            'saldo_restante' => $saldo_dinheiro - $valor_necessario
        ];
    } else {
        // Pagamento com pontos
        $pontos = obter_pontos(null, $colaborador_id);
        $pontos_necessarios = $produto['pontos_necessarios'] * $quantidade;
        
        if ($pontos['pontos_totais'] < $pontos_necessarios) {
            $faltam = $pontos_necessarios - $pontos['pontos_totais'];
            return ['pode' => false, 'motivo' => "Pontos insuficientes. Faltam {$faltam} pontos."];
        }
        
        return [
            'pode' => true,
            'produto' => $produto,
            'forma_pagamento' => 'pontos',
            'pontos_necessarios' => $pontos_necessarios,
            'pontos_atuais' => $pontos['pontos_totais'],
            'pontos_restantes' => $pontos['pontos_totais'] - $pontos_necessarios
        ];
    }
}

/**
 * Processa resgate de produto
 * 
 * @param int $colaborador_id ID do colaborador
 * @param int $produto_id ID do produto
 * @param int $quantidade Quantidade a resgatar
 * @param string $observacao Observação do colaborador
 * @param string $forma_pagamento 'pontos' ou 'dinheiro'
 */
function loja_resgatar($colaborador_id, $produto_id, $quantidade = 1, $observacao = null, $forma_pagamento = 'pontos') {
    $pdo = getDB();
    
    // Verifica se pode resgatar
    $verificacao = loja_pode_resgatar($colaborador_id, $produto_id, $quantidade, $forma_pagamento);
    if (!$verificacao['pode']) {
        return ['success' => false, 'message' => $verificacao['motivo']];
    }
    
    $produto = $verificacao['produto'];
    
    // Define valores conforme forma de pagamento
    if ($forma_pagamento === 'dinheiro') {
        $pontos_unitario = 0;
        $pontos_total = 0;
        $valor_dinheiro = floatval($produto['preco_dinheiro']) * $quantidade;
    } else {
        $pontos_unitario = $produto['pontos_necessarios'];
        $pontos_total = $pontos_unitario * $quantidade;
        $valor_dinheiro = null;
    }
    
    // Define status inicial
    $status = loja_aprovacao_obrigatoria() ? 'pendente' : 'aprovado';
    
    $pdo->beginTransaction();
    
    try {
        // Cria o resgate
        $stmt = $pdo->prepare("
            INSERT INTO loja_resgates 
            (colaborador_id, produto_id, quantidade, pontos_unitario, pontos_total, forma_pagamento, valor_dinheiro, status, observacao_colaborador)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$colaborador_id, $produto_id, $quantidade, $pontos_unitario, $pontos_total, $forma_pagamento, $valor_dinheiro, $status, $observacao]);
        $resgate_id = $pdo->lastInsertId();
        
        // Debita conforme forma de pagamento
        if ($forma_pagamento === 'dinheiro') {
            // Debita saldo em R$
            debitar_saldo_loja($colaborador_id, $valor_dinheiro, $resgate_id);
        } else {
            // Debita pontos
            $stmt = $pdo->prepare("
                INSERT INTO pontos_historico (colaborador_id, acao, pontos, referencia_id, referencia_tipo, data_registro)
                VALUES (?, 'resgate_loja', ?, ?, 'loja_resgate', CURDATE())
            ");
            $stmt->execute([$colaborador_id, -$pontos_total, $resgate_id]);
            
            // Atualiza total de pontos
            atualizar_total_pontos(null, $colaborador_id);
        }
        
        // Atualiza estoque (se não for ilimitado)
        if ($produto['estoque'] !== null) {
            $stmt = $pdo->prepare("UPDATE loja_produtos SET estoque = estoque - ? WHERE id = ?");
            $stmt->execute([$quantidade, $produto_id]);
        }
        
        // Atualiza contador de resgates do produto
        $stmt = $pdo->prepare("UPDATE loja_produtos SET total_resgates = total_resgates + ? WHERE id = ?");
        $stmt->execute([$quantidade, $produto_id]);
        
        // Se aprovação automática, define data de aprovação
        if ($status === 'aprovado') {
            $stmt = $pdo->prepare("UPDATE loja_resgates SET data_aprovacao = NOW() WHERE id = ?");
            $stmt->execute([$resgate_id]);
        }
        
        $pdo->commit();
        
        // Notifica admin sobre novo resgate (se configurado)
        if (loja_config('notificar_admin_resgate', true)) {
            loja_notificar_admins_resgate($resgate_id);
        }
        
        // Monta resposta conforme forma de pagamento
        if ($forma_pagamento === 'dinheiro') {
            $novo_saldo = obter_saldo_dinheiro($colaborador_id);
            return [
                'success' => true,
                'message' => $status === 'aprovado' 
                    ? 'Resgate realizado com sucesso! Aguarde a preparação do seu produto.'
                    : 'Resgate solicitado com sucesso! Aguarde a aprovação.',
                'resgate_id' => $resgate_id,
                'status' => $status,
                'forma_pagamento' => 'dinheiro',
                'valor_gasto' => $valor_dinheiro,
                'saldo_restante' => $novo_saldo
            ];
        } else {
            $novo_saldo = obter_pontos(null, $colaborador_id);
            return [
                'success' => true,
                'message' => $status === 'aprovado' 
                    ? 'Resgate realizado com sucesso! Aguarde a preparação do seu produto.'
                    : 'Resgate solicitado com sucesso! Aguarde a aprovação.',
                'resgate_id' => $resgate_id,
                'status' => $status,
                'forma_pagamento' => 'pontos',
                'pontos_gastos' => $pontos_total,
                'pontos_restantes' => $novo_saldo['pontos_totais']
            ];
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao processar resgate: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao processar o resgate. Tente novamente.'];
    }
}

/**
 * Atualiza status do resgate
 */
function loja_atualizar_status_resgate($resgate_id, $novo_status, $usuario_id, $dados = []) {
    $pdo = getDB();
    
    // Busca resgate atual
    $stmt = $pdo->prepare("SELECT * FROM loja_resgates WHERE id = ?");
    $stmt->execute([$resgate_id]);
    $resgate = $stmt->fetch();
    
    if (!$resgate) {
        return ['success' => false, 'message' => 'Resgate não encontrado.'];
    }
    
    $status_anterior = $resgate['status'];
    
    // Validação de transições de status
    $transicoes_validas = [
        'pendente' => ['aprovado', 'rejeitado', 'cancelado'],
        'aprovado' => ['preparando', 'cancelado'],
        'preparando' => ['enviado', 'entregue', 'cancelado'],
        'enviado' => ['entregue'],
        'rejeitado' => [],
        'entregue' => [],
        'cancelado' => []
    ];
    
    if (!in_array($novo_status, $transicoes_validas[$status_anterior])) {
        return ['success' => false, 'message' => "Não é possível mudar de '{$status_anterior}' para '{$novo_status}'."];
    }
    
    $pdo->beginTransaction();
    
    try {
        $updates = ["status = ?"];
        $params = [$novo_status];
        
        switch ($novo_status) {
            case 'aprovado':
                $updates[] = "aprovado_por = ?";
                $updates[] = "data_aprovacao = NOW()";
                $params[] = $usuario_id;
                break;
                
            case 'rejeitado':
                $updates[] = "aprovado_por = ?";
                $updates[] = "data_aprovacao = NOW()";
                $updates[] = "motivo_rejeicao = ?";
                $params[] = $usuario_id;
                $params[] = $dados['motivo'] ?? null;
                break;
                
            case 'preparando':
                $updates[] = "preparado_por = ?";
                $updates[] = "data_preparacao = NOW()";
                $params[] = $usuario_id;
                break;
                
            case 'enviado':
                $updates[] = "enviado_por = ?";
                $updates[] = "data_envio = NOW()";
                $updates[] = "codigo_rastreio = ?";
                $params[] = $usuario_id;
                $params[] = $dados['codigo_rastreio'] ?? null;
                break;
                
            case 'entregue':
                $updates[] = "entregue_por = ?";
                $updates[] = "data_entrega = NOW()";
                $params[] = $usuario_id;
                break;
                
            case 'cancelado':
                $updates[] = "cancelado_por = ?";
                $updates[] = "data_cancelamento = NOW()";
                $updates[] = "observacao_admin = ?";
                $params[] = $usuario_id;
                $params[] = $dados['motivo'] ?? null;
                break;
        }
        
        // Adiciona observação admin se fornecida
        if (!empty($dados['observacao'])) {
            $updates[] = "observacao_admin = ?";
            $params[] = $dados['observacao'];
        }
        
        $params[] = $resgate_id;
        $sql = "UPDATE loja_resgates SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Se rejeitado ou cancelado, devolve saldo e estoque
        if (in_array($novo_status, ['rejeitado', 'cancelado'])) {
            // Devolve conforme forma de pagamento
            $forma_pagamento = $resgate['forma_pagamento'] ?? 'pontos';
            
            if ($forma_pagamento === 'dinheiro' && !empty($resgate['valor_dinheiro'])) {
                // Devolve saldo em R$
                estornar_saldo_loja($resgate['colaborador_id'], $resgate['valor_dinheiro'], $resgate_id, $usuario_id);
            } else {
                // Devolve pontos
                $stmt = $pdo->prepare("
                    INSERT INTO pontos_historico (colaborador_id, acao, pontos, referencia_id, referencia_tipo, data_registro)
                    VALUES (?, 'estorno_resgate', ?, ?, 'loja_resgate', CURDATE())
                ");
                $stmt->execute([$resgate['colaborador_id'], $resgate['pontos_total'], $resgate_id]);
                
                atualizar_total_pontos(null, $resgate['colaborador_id']);
            }
            
            // Devolve estoque
            $stmt = $pdo->prepare("
                UPDATE loja_produtos SET estoque = estoque + ? 
                WHERE id = ? AND estoque IS NOT NULL
            ");
            $stmt->execute([$resgate['quantidade'], $resgate['produto_id']]);
            
            // Decrementa contador de resgates
            $stmt = $pdo->prepare("UPDATE loja_produtos SET total_resgates = GREATEST(0, total_resgates - ?) WHERE id = ?");
            $stmt->execute([$resgate['quantidade'], $resgate['produto_id']]);
        }
        
        $pdo->commit();
        
        // Notifica colaborador
        if (loja_config('notificar_colaborador_status', true)) {
            loja_notificar_colaborador_status($resgate_id, $novo_status);
        }
        
        return ['success' => true, 'message' => 'Status atualizado com sucesso!'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao atualizar status do resgate: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao atualizar status.'];
    }
}

/**
 * Obtém resgates do colaborador
 */
function loja_get_resgates_colaborador($colaborador_id, $limite = 50) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT r.*, p.nome as produto_nome, p.imagem as produto_imagem, 
               c.nome as categoria_nome
        FROM loja_resgates r
        INNER JOIN loja_produtos p ON r.produto_id = p.id
        INNER JOIN loja_categorias c ON p.categoria_id = c.id
        WHERE r.colaborador_id = ?
        ORDER BY r.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$colaborador_id, $limite]);
    return $stmt->fetchAll();
}

/**
 * Obtém todos os resgates (admin)
 */
function loja_get_resgates_admin($filtros = []) {
    $pdo = getDB();
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filtros['status'])) {
        $where[] = "r.status = ?";
        $params[] = $filtros['status'];
    }
    
    if (!empty($filtros['colaborador_id'])) {
        $where[] = "r.colaborador_id = ?";
        $params[] = $filtros['colaborador_id'];
    }
    
    if (!empty($filtros['produto_id'])) {
        $where[] = "r.produto_id = ?";
        $params[] = $filtros['produto_id'];
    }
    
    if (!empty($filtros['data_inicio'])) {
        $where[] = "DATE(r.created_at) >= ?";
        $params[] = $filtros['data_inicio'];
    }
    
    if (!empty($filtros['data_fim'])) {
        $where[] = "DATE(r.created_at) <= ?";
        $params[] = $filtros['data_fim'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    $sql = "
        SELECT r.*, 
               p.nome as produto_nome, p.imagem as produto_imagem,
               col.nome_completo as colaborador_nome, col.foto as colaborador_foto,
               e.nome_fantasia as empresa_nome,
               ua.nome as aprovador_nome
        FROM loja_resgates r
        INNER JOIN loja_produtos p ON r.produto_id = p.id
        INNER JOIN colaboradores col ON r.colaborador_id = col.id
        LEFT JOIN empresas e ON col.empresa_id = e.id
        LEFT JOIN usuarios ua ON r.aprovado_por = ua.id
        WHERE $where_sql
        ORDER BY r.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Verifica se produto está na wishlist
 */
function loja_is_wishlist($colaborador_id, $produto_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id FROM loja_wishlist WHERE colaborador_id = ? AND produto_id = ?");
    $stmt->execute([$colaborador_id, $produto_id]);
    return (bool)$stmt->fetch();
}

/**
 * Adiciona/remove da wishlist
 */
function loja_toggle_wishlist($colaborador_id, $produto_id) {
    $pdo = getDB();
    
    if (loja_is_wishlist($colaborador_id, $produto_id)) {
        $stmt = $pdo->prepare("DELETE FROM loja_wishlist WHERE colaborador_id = ? AND produto_id = ?");
        $stmt->execute([$colaborador_id, $produto_id]);
        return ['success' => true, 'action' => 'removed', 'message' => 'Removido da lista de desejos'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO loja_wishlist (colaborador_id, produto_id) VALUES (?, ?)");
        $stmt->execute([$colaborador_id, $produto_id]);
        return ['success' => true, 'action' => 'added', 'message' => 'Adicionado à lista de desejos'];
    }
}

/**
 * Obtém wishlist do colaborador
 */
function loja_get_wishlist($colaborador_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome as categoria_nome, c.icone as categoria_icone, c.cor as categoria_cor
        FROM loja_wishlist w
        INNER JOIN loja_produtos p ON w.produto_id = p.id
        INNER JOIN loja_categorias c ON p.categoria_id = c.id
        WHERE w.colaborador_id = ? AND p.ativo = 1
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$colaborador_id]);
    return $stmt->fetchAll();
}

/**
 * Obtém estatísticas da loja
 */
function loja_get_estatisticas() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM vw_loja_estatisticas");
    return $stmt->fetch();
}

/**
 * Notifica admins sobre novo resgate
 */
function loja_notificar_admins_resgate($resgate_id) {
    // Implementar notificação
    // Por enquanto, apenas log
    error_log("Novo resgate pendente: #$resgate_id");
}

/**
 * Notifica colaborador sobre mudança de status
 */
function loja_notificar_colaborador_status($resgate_id, $status) {
    $pdo = getDB();
    
    // Busca dados do resgate
    $stmt = $pdo->prepare("
        SELECT r.*, p.nome as produto_nome, c.nome_completo
        FROM loja_resgates r
        INNER JOIN loja_produtos p ON r.produto_id = p.id
        INNER JOIN colaboradores c ON r.colaborador_id = c.id
        WHERE r.id = ?
    ");
    $stmt->execute([$resgate_id]);
    $resgate = $stmt->fetch();
    
    if (!$resgate) return;
    
    // Busca usuario vinculado ao colaborador
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE colaborador_id = ?");
    $stmt->execute([$resgate['colaborador_id']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) return;
    
    $mensagens = [
        'aprovado' => "Seu resgate de '{$resgate['produto_nome']}' foi aprovado!",
        'rejeitado' => "Seu resgate de '{$resgate['produto_nome']}' foi rejeitado. Seus pontos foram devolvidos.",
        'preparando' => "Seu produto '{$resgate['produto_nome']}' está sendo preparado!",
        'enviado' => "Seu produto '{$resgate['produto_nome']}' foi enviado!",
        'entregue' => "Seu produto '{$resgate['produto_nome']}' foi marcado como entregue!",
        'cancelado' => "Seu resgate de '{$resgate['produto_nome']}' foi cancelado. Seus pontos foram devolvidos."
    ];
    
    if (isset($mensagens[$status]) && function_exists('criar_notificacao')) {
        criar_notificacao(
            $usuario['id'],
            'Loja de Pontos',
            $mensagens[$status],
            'loja_meus_resgates.php',
            'loja'
        );
    }
}

/**
 * Registra log de ação
 */
function loja_log($usuario_id, $acao, $entidade, $entidade_id, $dados_anteriores = null, $dados_novos = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO loja_log (usuario_id, acao, entidade, entidade_id, dados_anteriores, dados_novos, ip, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuario_id,
        $acao,
        $entidade,
        $entidade_id,
        $dados_anteriores ? json_encode($dados_anteriores) : null,
        $dados_novos ? json_encode($dados_novos) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
