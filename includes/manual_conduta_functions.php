<?php
/**
 * Funções auxiliares para o sistema de Manual de Conduta
 */

require_once __DIR__ . '/functions.php';

/**
 * Obtém o manual de conduta ativo
 */
function get_manual_conduta_ativo() {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.nome as publicado_por_nome,
               criador.nome as criado_por_nome
        FROM manual_conduta m
        LEFT JOIN usuarios u ON m.publicado_por = u.id
        LEFT JOIN usuarios criador ON m.criado_por = criador.id
        WHERE m.ativo = 1
        ORDER BY m.publicado_em DESC, m.created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Obtém todas as FAQs ativas, ordenadas por categoria e ordem
 */
function get_faqs_ativas($categoria = null) {
    $pdo = getDB();
    
    if ($categoria) {
        $stmt = $pdo->prepare("
            SELECT * FROM faq_manual_conduta
            WHERE ativo = 1 AND categoria = ?
            ORDER BY ordem ASC, id ASC
        ");
        $stmt->execute([$categoria]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM faq_manual_conduta
            WHERE ativo = 1
            ORDER BY categoria ASC, ordem ASC, id ASC
        ");
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

/**
 * Obtém categorias únicas das FAQs
 */
function get_faq_categorias() {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT DISTINCT categoria 
        FROM faq_manual_conduta
        WHERE ativo = 1 AND categoria IS NOT NULL AND categoria != ''
        ORDER BY categoria ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Registra visualização do manual ou FAQ
 */
function registrar_visualizacao_manual($tipo = 'manual', $faq_id = null) {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'] ?? null;
    
    $usuario_id = $usuario['id'] ?? null;
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO manual_conduta_visualizacoes 
            (usuario_id, colaborador_id, tipo, faq_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $colaborador_id, $tipo, $faq_id, $ip_address, $user_agent]);
        
        // Incrementa contador de visualizações se for FAQ
        if ($tipo === 'faq' && $faq_id) {
            $stmt = $pdo->prepare("
                UPDATE faq_manual_conduta 
                SET visualizacoes = visualizacoes + 1 
                WHERE id = ?
            ");
            $stmt->execute([$faq_id]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar visualização: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra histórico de alteração do manual
 */
function registrar_historico_manual($manual_id, $conteudo_anterior, $conteudo_novo, $versao, $motivo = null) {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO manual_conduta_historico 
            (manual_conduta_id, versao, conteudo_anterior, conteudo_novo, alterado_por, motivo_alteracao)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $manual_id,
            $versao,
            $conteudo_anterior,
            $conteudo_novo,
            $usuario['id'],
            $motivo
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar histórico: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra histórico de alteração do FAQ
 */
function registrar_historico_faq($faq_id, $pergunta_anterior, $pergunta_nova, $resposta_anterior, $resposta_nova, $motivo = null) {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO faq_manual_conduta_historico 
            (faq_id, pergunta_anterior, pergunta_nova, resposta_anterior, resposta_nova, alterado_por, motivo_alteracao)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $faq_id,
            $pergunta_anterior,
            $pergunta_nova,
            $resposta_anterior,
            $resposta_nova,
            $usuario['id'],
            $motivo
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar histórico FAQ: " . $e->getMessage());
        return false;
    }
}

/**
 * Incrementa contador de "útil" ou "não útil" do FAQ
 */
function marcar_faq_util($faq_id, $util = true) {
    $pdo = getDB();
    
    try {
        if ($util) {
            $stmt = $pdo->prepare("
                UPDATE faq_manual_conduta 
                SET util_respondeu_sim = util_respondeu_sim + 1 
                WHERE id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE faq_manual_conduta 
                SET util_respondeu_nao = util_respondeu_nao + 1 
                WHERE id = ?
            ");
        }
        $stmt->execute([$faq_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao marcar FAQ útil: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém histórico do manual
 */
function get_historico_manual($manual_id, $limit = 50) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT h.*, u.nome as alterado_por_nome
        FROM manual_conduta_historico h
        LEFT JOIN usuarios u ON h.alterado_por = u.id
        WHERE h.manual_conduta_id = ?
        ORDER BY h.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$manual_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * Obtém histórico do FAQ
 */
function get_historico_faq($faq_id, $limit = 50) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT h.*, u.nome as alterado_por_nome
        FROM faq_manual_conduta_historico h
        LEFT JOIN usuarios u ON h.alterado_por = u.id
        WHERE h.faq_id = ?
        ORDER BY h.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$faq_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * Calcula próxima versão do manual
 */
function calcular_proxima_versao($versao_atual) {
    if (empty($versao_atual)) {
        return '1.0';
    }
    
    // Tenta incrementar versão menor (ex: 1.0 -> 1.1)
    if (preg_match('/^(\d+)\.(\d+)$/', $versao_atual, $matches)) {
        $major = (int)$matches[1];
        $minor = (int)$matches[2];
        return $major . '.' . ($minor + 1);
    }
    
    // Se não conseguir parsear, retorna versão incrementada
    return $versao_atual . '.1';
}

/**
 * Busca FAQs por termo
 */
function buscar_faqs($termo) {
    $pdo = getDB();
    $termo = '%' . $termo . '%';
    
    $stmt = $pdo->prepare("
        SELECT * FROM faq_manual_conduta
        WHERE ativo = 1 
        AND (pergunta LIKE ? OR resposta LIKE ?)
        ORDER BY 
            CASE 
                WHEN pergunta LIKE ? THEN 1
                WHEN resposta LIKE ? THEN 2
                ELSE 3
            END,
            ordem ASC, id ASC
    ");
    $stmt->execute([$termo, $termo, $termo, $termo]);
    return $stmt->fetchAll();
}

/**
 * Destaca termos na busca (highlight)
 */
function destacar_termo($texto, $termo) {
    if (empty($termo)) {
        return htmlspecialchars($texto);
    }
    
    $termo_escaped = preg_quote($termo, '/');
    $texto_escaped = htmlspecialchars($texto);
    
    return preg_replace(
        '/(' . $termo_escaped . ')/i',
        '<mark class="bg-warning">$1</mark>',
        $texto_escaped
    );
}

/**
 * Gera PDF do Manual de Conduta
 */
function gerar_pdf_manual_conduta($manual) {
    require_once __DIR__ . '/pdf.php';
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $pdf = criar_pdf(
        $manual['titulo'] ?? 'Manual de Conduta Privus',
        'RH Privus',
        'Manual de Conduta'
    );
    
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, $manual['titulo'] ?? 'Manual de Conduta Privus', 0, 1, 'C');
    
    if (!empty($manual['versao'])) {
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Versão ' . $manual['versao'], 0, 1, 'C');
    }
    
    if (!empty($manual['publicado_em'])) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(120, 120, 120);
        $data_publicacao = date('d/m/Y H:i', strtotime($manual['publicado_em']));
        $pdf->Cell(0, 5, 'Publicado em: ' . $data_publicacao, 0, 1, 'C');
    }
    
    $pdf->Ln(10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Converte HTML para texto simples e formata para PDF
    $conteudo = strip_tags($manual['conteudo']);
    $conteudo = html_entity_decode($conteudo, ENT_QUOTES, 'UTF-8');
    
    // Remove múltiplos espaços e quebras de linha excessivas
    $conteudo = preg_replace('/\s+/', ' ', $conteudo);
    $conteudo = preg_replace('/\n\s*\n/', "\n\n", $conteudo);
    
    // Processa o conteúdo preservando parágrafos
    $paragrafos = preg_split('/\n\s*\n/', $conteudo);
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    
    foreach ($paragrafos as $paragrafo) {
        $paragrafo = trim($paragrafo);
        if (empty($paragrafo)) {
            continue;
        }
        
        // Detecta títulos (linhas curtas e em maiúsculas ou começando com número)
        if (strlen($paragrafo) < 100 && (ctype_upper(substr($paragrafo, 0, 20)) || preg_match('/^\d+\./', $paragrafo))) {
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(0, 7, $paragrafo, 0, 'L');
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Ln(3);
        } else {
            $pdf->MultiCell(0, 6, $paragrafo, 0, 'L');
            $pdf->Ln(3);
        }
    }
    
    // Footer
    $pdf->SetY(-20);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 5, 'Gerado em ' . date('d/m/Y H:i') . ' - RH Privus', 0, 0, 'C');
    
    return $pdf;
}

