<?php
/**
 * API para buscar resumo de pagamentos filtrado por empresa/setor/total
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

header('Content-Type: application/json');

$usuario = $_SESSION['usuario'];
$filtro_tipo = $_GET['filtro_tipo'] ?? 'total'; // total, empresa, setor
$filtro_id = isset($_GET['filtro_id']) ? (int)$_GET['filtro_id'] : null; // ID da empresa ou setor

$pdo = getDB();

$resultado = [
    'success' => true,
    'data' => []
];

try {
    // Monta condições baseadas nas permissões do usuário
    $where_base = [];
    $params_base = [];
    
    if ($usuario['role'] === 'ADMIN') {
        // ADMIN vê todos
    } elseif ($usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
            $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
            $where_base[] = "c.empresa_id IN ($placeholders)";
            $params_base = array_merge($params_base, $usuario['empresas_ids']);
        } else {
            $where_base[] = "c.empresa_id = ?";
            $params_base[] = $usuario['empresa_id'] ?? 0;
        }
    } else {
        $where_base[] = "c.empresa_id = ?";
        $params_base[] = $usuario['empresa_id'] ?? 0;
    }
    
    $where_base[] = "c.status = 'ativo'";
    $where_base_sql = !empty($where_base) ? 'WHERE ' . implode(' AND ', $where_base) : '';
    
    // Monta condições para fechamentos fechados/pagos
    $where_fechamentos = ["fp.status IN ('fechado', 'pago')", "fp.tipo_fechamento = 'regular'"];
    $params_fechamentos = [];
    
    if ($usuario['role'] === 'ADMIN') {
        // ADMIN vê todos
    } elseif ($usuario['role'] === 'RH') {
        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
            $placeholders_fp = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
            $where_fechamentos[] = "fp.empresa_id IN ($placeholders_fp)";
            $params_fechamentos = array_merge($params_fechamentos, $usuario['empresas_ids']);
        } else {
            $where_fechamentos[] = "fp.empresa_id = ?";
            $params_fechamentos[] = $usuario['empresa_id'] ?? 0;
        }
    } else {
        $where_fechamentos[] = "fp.empresa_id = ?";
        $params_fechamentos[] = $usuario['empresa_id'] ?? 0;
    }
    
    $where_fechamentos_sql = 'WHERE ' . implode(' AND ', $where_fechamentos);
    
    // Define campos de agrupamento e filtro baseado no tipo
    $group_by = '';
    $select_extra = '';
    $where_extra = '';
    $params_extra = [];
    
    if ($filtro_tipo === 'empresa') {
        $select_extra = ', e.id as empresa_id, e.nome_fantasia as empresa_nome';
        $group_by = 'GROUP BY e.id, e.nome_fantasia';
        if ($filtro_id) {
            $where_extra = 'AND e.id = ?';
            $params_extra[] = $filtro_id;
        }
    } elseif ($filtro_tipo === 'setor') {
        $select_extra = ', s.id as setor_id, s.nome_setor as setor_nome, e.nome_fantasia as empresa_nome';
        $group_by = 'GROUP BY s.id, s.nome_setor, e.nome_fantasia';
        if ($filtro_id) {
            $where_extra = 'AND s.id = ?';
            $params_extra[] = $filtro_id;
        }
    }
    
    // 1. Total de Folha
    $where_folha = array_merge($where_base, ["c.salario IS NOT NULL AND c.salario > 0"]);
    if ($filtro_tipo === 'empresa') {
        $where_folha[] = "e.id IS NOT NULL";
    } elseif ($filtro_tipo === 'setor') {
        $where_folha[] = "s.id IS NOT NULL";
    }
    if ($where_extra) {
        $where_folha[] = str_replace('AND ', '', $where_extra);
    }
    $where_folha_sql = 'WHERE ' . implode(' AND ', $where_folha);
    $params_folha = array_merge($params_base, $params_extra);
    
    $join_folha = '';
    if ($filtro_tipo === 'empresa') {
        $join_folha = 'LEFT JOIN empresas e ON c.empresa_id = e.id';
    } elseif ($filtro_tipo === 'setor') {
        $join_folha = 'LEFT JOIN setores s ON c.setor_id = s.id LEFT JOIN empresas e ON c.empresa_id = e.id';
    }
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.salario), 0) as total_folha $select_extra
        FROM colaboradores c
        $join_folha
        $where_folha_sql
        $group_by
    ");
    $stmt->execute($params_folha);
    
    if ($filtro_tipo === 'total') {
        $result_folha = $stmt->fetch();
        $resultado['data']['total_folha'] = (float)($result_folha['total_folha'] ?? 0);
    } else {
        $resultado['data']['total_folha'] = [];
        while ($row = $stmt->fetch()) {
            if ($filtro_tipo === 'empresa') {
                $resultado['data']['total_folha'][] = [
                    'id' => $row['empresa_id'],
                    'nome' => $row['empresa_nome'],
                    'valor' => (float)$row['total_folha']
                ];
            } elseif ($filtro_tipo === 'setor') {
                $resultado['data']['total_folha'][] = [
                    'id' => $row['setor_id'],
                    'nome' => $row['setor_nome'],
                    'empresa' => $row['empresa_nome'],
                    'valor' => (float)$row['total_folha']
                ];
            }
        }
    }
    
    // 2. Total de Bônus
    $where_bonus = array_merge($where_base, [
        "tb.tipo_valor != 'informativo'",
        "(cb.data_inicio IS NULL OR cb.data_inicio <= CURDATE())",
        "(cb.data_fim IS NULL OR cb.data_fim >= CURDATE())"
    ]);
    if ($filtro_tipo === 'empresa') {
        $where_bonus[] = "e.id IS NOT NULL";
        if ($filtro_id) {
            $where_bonus[] = "e.id = ?";
        }
    } elseif ($filtro_tipo === 'setor') {
        $where_bonus[] = "s.id IS NOT NULL";
        if ($filtro_id) {
            $where_bonus[] = "s.id = ?";
        }
    }
    $where_bonus_sql = 'WHERE ' . implode(' AND ', $where_bonus);
    $params_bonus = array_merge($params_base, $params_extra);
    
    $join_bonus = '';
    if ($filtro_tipo === 'empresa') {
        $join_bonus = 'LEFT JOIN empresas e ON c.empresa_id = e.id';
    } elseif ($filtro_tipo === 'setor') {
        $join_bonus = 'LEFT JOIN setores s ON c.setor_id = s.id LEFT JOIN empresas e ON c.empresa_id = e.id';
    }
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE 
                WHEN tb.tipo_valor = 'fixo' THEN COALESCE(tb.valor_fixo, 0)
                ELSE COALESCE(cb.valor, 0)
            END
        ), 0) as total_bonus $select_extra
        FROM colaboradores_bonus cb
        INNER JOIN colaboradores c ON cb.colaborador_id = c.id
        INNER JOIN tipos_bonus tb ON cb.tipo_bonus_id = tb.id
        $join_bonus
        $where_bonus_sql
        $group_by
    ");
    $stmt->execute($params_bonus);
    
    if ($filtro_tipo === 'total') {
        $result_bonus = $stmt->fetch();
        $resultado['data']['total_bonus'] = (float)($result_bonus['total_bonus'] ?? 0);
    } else {
        $resultado['data']['total_bonus'] = [];
        while ($row = $stmt->fetch()) {
            if ($filtro_tipo === 'empresa') {
                $resultado['data']['total_bonus'][] = [
                    'id' => $row['empresa_id'],
                    'nome' => $row['empresa_nome'],
                    'valor' => (float)$row['total_bonus']
                ];
            } elseif ($filtro_tipo === 'setor') {
                $resultado['data']['total_bonus'][] = [
                    'id' => $row['setor_id'],
                    'nome' => $row['setor_nome'],
                    'empresa' => $row['empresa_nome'],
                    'valor' => (float)$row['total_bonus']
                ];
            }
        }
    }
    
    // 3. Total de Extras (horas extras não pagas)
    $where_extras = array_merge($where_base, [
        "(he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)",
        "he.fechamento_pagamento_id IS NULL"
    ]);
    if ($filtro_tipo === 'empresa') {
        $where_extras[] = "e.id IS NOT NULL";
        if ($filtro_id) {
            $where_extras[] = "e.id = ?";
        }
    } elseif ($filtro_tipo === 'setor') {
        $where_extras[] = "s.id IS NOT NULL";
        if ($filtro_id) {
            $where_extras[] = "s.id = ?";
        }
    }
    $where_extras_sql = 'WHERE ' . implode(' AND ', $where_extras);
    $params_extras = array_merge($params_base, $params_extra);
    
    $join_extras = '';
    if ($filtro_tipo === 'empresa') {
        $join_extras = 'LEFT JOIN empresas e ON c.empresa_id = e.id';
    } elseif ($filtro_tipo === 'setor') {
        $join_extras = 'LEFT JOIN setores s ON c.setor_id = s.id LEFT JOIN empresas e ON c.empresa_id = e.id';
    }
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(he.valor_total), 0) as total_extras $select_extra
        FROM horas_extras he
        INNER JOIN colaboradores c ON he.colaborador_id = c.id
        $join_extras
        $where_extras_sql
        $group_by
    ");
    $stmt->execute($params_extras);
    
    if ($filtro_tipo === 'total') {
        $result_extras = $stmt->fetch();
        $resultado['data']['total_extras'] = (float)($result_extras['total_extras'] ?? 0);
    } else {
        $resultado['data']['total_extras'] = [];
        while ($row = $stmt->fetch()) {
            if ($filtro_tipo === 'empresa') {
                $resultado['data']['total_extras'][] = [
                    'id' => $row['empresa_id'],
                    'nome' => $row['empresa_nome'],
                    'valor' => (float)$row['total_extras']
                ];
            } elseif ($filtro_tipo === 'setor') {
                $resultado['data']['total_extras'][] = [
                    'id' => $row['setor_id'],
                    'nome' => $row['setor_nome'],
                    'empresa' => $row['empresa_nome'],
                    'valor' => (float)$row['total_extras']
                ];
            }
        }
    }
    
    // Calcula totais derivados
    if ($filtro_tipo === 'total') {
        $resultado['data']['total_folha_bonus'] = $resultado['data']['total_folha'] + $resultado['data']['total_bonus'];
        $resultado['data']['total_geral'] = $resultado['data']['total_folha_bonus'] + $resultado['data']['total_extras'];
    } else {
        // Para empresa/setor, calcula para cada item
        $resultado['data']['total_folha_bonus'] = [];
        $resultado['data']['total_geral'] = [];
        
        $items = [];
        if ($filtro_tipo === 'empresa') {
            // Agrupa por empresa
            foreach ($resultado['data']['total_folha'] as $item) {
                $items[$item['id']] = [
                    'id' => $item['id'],
                    'nome' => $item['nome'],
                    'folha' => $item['valor'],
                    'bonus' => 0,
                    'extras' => 0
                ];
            }
            foreach ($resultado['data']['total_bonus'] as $item) {
                if (!isset($items[$item['id']])) {
                    $items[$item['id']] = ['id' => $item['id'], 'nome' => $item['nome'], 'folha' => 0, 'bonus' => 0, 'extras' => 0];
                }
                $items[$item['id']]['bonus'] = $item['valor'];
            }
            foreach ($resultado['data']['total_extras'] as $item) {
                if (!isset($items[$item['id']])) {
                    $items[$item['id']] = ['id' => $item['id'], 'nome' => $item['nome'], 'folha' => 0, 'bonus' => 0, 'extras' => 0];
                }
                $items[$item['id']]['extras'] = $item['valor'];
            }
        } elseif ($filtro_tipo === 'setor') {
            // Agrupa por setor
            foreach ($resultado['data']['total_folha'] as $item) {
                $items[$item['id']] = [
                    'id' => $item['id'],
                    'nome' => $item['nome'],
                    'empresa' => $item['empresa'],
                    'folha' => $item['valor'],
                    'bonus' => 0,
                    'extras' => 0
                ];
            }
            foreach ($resultado['data']['total_bonus'] as $item) {
                if (!isset($items[$item['id']])) {
                    $items[$item['id']] = ['id' => $item['id'], 'nome' => $item['nome'], 'empresa' => $item['empresa'], 'folha' => 0, 'bonus' => 0, 'extras' => 0];
                }
                $items[$item['id']]['bonus'] = $item['valor'];
            }
            foreach ($resultado['data']['total_extras'] as $item) {
                if (!isset($items[$item['id']])) {
                    $items[$item['id']] = ['id' => $item['id'], 'nome' => $item['nome'], 'empresa' => $item['empresa'], 'folha' => 0, 'bonus' => 0, 'extras' => 0];
                }
                $items[$item['id']]['extras'] = $item['valor'];
            }
        }
        
        foreach ($items as $item) {
            $folha_bonus = $item['folha'] + $item['bonus'];
            $total_geral = $folha_bonus + $item['extras'];
            
            $resultado['data']['total_folha_bonus'][] = [
                'id' => $item['id'],
                'nome' => $item['nome'],
                'empresa' => $item['empresa'] ?? null,
                'valor' => $folha_bonus
            ];
            
            $resultado['data']['total_geral'][] = [
                'id' => $item['id'],
                'nome' => $item['nome'],
                'empresa' => $item['empresa'] ?? null,
                'valor' => $total_geral
            ];
        }
    }
    
} catch (Exception $e) {
    $resultado['success'] = false;
    $resultado['message'] = $e->getMessage();
}

echo json_encode($resultado);

