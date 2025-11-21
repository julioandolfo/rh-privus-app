<?php
/**
 * Sistema de Geração de PDFs usando TCPDF
 */

require_once __DIR__ . '/../vendor/autoload.php';
// TCPDF não usa namespaces, então não precisa de 'use'

require_once __DIR__ . '/functions.php';

/**
 * Cria uma instância do TCPDF com configurações padrão
 * 
 * @param string $titulo Título do documento
 * @param string $autor Autor do documento
 * @param string $assunto Assunto do documento
 * @return TCPDF Instância configurada do TCPDF
 */
function criar_pdf($titulo = 'Documento', $autor = 'RH Privus', $assunto = '') {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Informações do documento
    $pdf->SetCreator('RH Privus');
    $pdf->SetAuthor($autor);
    $pdf->SetTitle($titulo);
    $pdf->SetSubject($assunto);
    $pdf->SetKeywords('RH, Privus, Relatório');
    
    // Remove header e footer padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurações de margem
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Fonte padrão
    $pdf->SetFont('helvetica', '', 10);
    
    return $pdf;
}

/**
 * Adiciona header customizado ao PDF
 */
function adicionar_header_pdf($pdf, $titulo, $subtitulo = '') {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $titulo, 0, 1, 'C');
    
    if ($subtitulo) {
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 5, $subtitulo, 0, 1, 'C');
    }
    
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 10);
}

/**
 * Adiciona footer customizado ao PDF
 */
function adicionar_footer_pdf($pdf, $texto = '') {
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $texto_footer = $texto ?: 'Gerado em ' . date('d/m/Y H:i') . ' - RH Privus';
    $pdf->Cell(0, 10, $texto_footer, 0, 0, 'C');
}

/**
 * Gera PDF de relatório de ocorrências
 */
function gerar_pdf_ocorrencias($ocorrencias, $filtros = []) {
    $pdf = criar_pdf('Relatório de Ocorrências', 'RH Privus', 'Relatório de Ocorrências');
    $pdf->AddPage();
    
    // Header
    adicionar_header_pdf($pdf, 'Relatório de Ocorrências', 'Sistema RH Privus');
    
    // Informações do filtro
    if (!empty($filtros)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Filtros Aplicados:', 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        
        if (isset($filtros['data_inicio'])) {
            $pdf->Cell(0, 5, 'Período: ' . formatar_data($filtros['data_inicio']) . ' até ' . formatar_data($filtros['data_fim'] ?? date('Y-m-d')), 0, 1);
        }
        if (isset($filtros['empresa_id'])) {
            $pdf->Cell(0, 5, 'Empresa: ' . ($filtros['empresa_nome'] ?? ''), 0, 1);
        }
        $pdf->Ln(3);
    }
    
    // Tabela
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    
    // Cabeçalho da tabela
    $pdf->Cell(25, 7, 'Data', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Colaborador', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Tipo', 1, 0, 'C', true);
    $pdf->Cell(0, 7, 'Descrição', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(255, 255, 255);
    
    foreach ($ocorrencias as $ocorrencia) {
        $pdf->Cell(25, 6, formatar_data($ocorrencia['data_ocorrencia']), 1, 0, 'C');
        $pdf->Cell(50, 6, substr($ocorrencia['colaborador_nome'] ?? '', 0, 30), 1, 0, 'L');
        $pdf->Cell(40, 6, substr($ocorrencia['tipo'] ?? '', 0, 20), 1, 0, 'L');
        
        // Descrição com quebra de linha
        $descricao = substr($ocorrencia['descricao'] ?? '', 0, 60);
        $pdf->MultiCell(0, 6, $descricao, 1, 'L', false, 1);
    }
    
    // Footer
    adicionar_footer_pdf($pdf);
    
    return $pdf;
}

/**
 * Gera PDF de holerite/pagamento
 */
function gerar_pdf_holerite($colaborador, $fechamento, $itens) {
    $pdf = criar_pdf('Holerite', 'RH Privus', 'Holerite de Pagamento');
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'HOLERITE DE PAGAMENTO', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Dados da empresa
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6, $fechamento['empresa_nome'] ?? 'Empresa', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'CNPJ: ' . ($fechamento['empresa_cnpj'] ?? ''), 0, 1, 'L');
    $pdf->Ln(3);
    
    // Dados do colaborador
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'Dados do Colaborador', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Nome: ' . ($colaborador['nome_completo'] ?? ''), 0, 1, 'L');
    $pdf->Cell(0, 5, 'CPF: ' . formatar_cpf($colaborador['cpf'] ?? ''), 0, 1, 'L');
    $pdf->Cell(0, 5, 'Cargo: ' . ($colaborador['cargo_nome'] ?? ''), 0, 1, 'L');
    $pdf->Cell(0, 5, 'Setor: ' . ($colaborador['setor_nome'] ?? ''), 0, 1, 'L');
    $pdf->Ln(3);
    
    // Período
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'Período de Referência: ' . ($fechamento['mes_referencia'] ?? ''), 0, 1, 'L');
    $pdf->Ln(3);
    
    // Tabela de valores
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);
    
    $pdf->Cell(100, 7, 'Descrição', 1, 0, 'L', true);
    $pdf->Cell(0, 7, 'Valor (R$)', 1, 1, 'R', true);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(255, 255, 255);
    
    $total = 0;
    foreach ($itens as $item) {
        $pdf->Cell(100, 6, $item['descricao'], 1, 0, 'L');
        $pdf->Cell(0, 6, number_format($item['valor'], 2, ',', '.'), 1, 1, 'R');
        $total += $item['valor'];
    }
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(100, 7, 'TOTAL', 1, 0, 'L', true);
    $pdf->Cell(0, 7, 'R$ ' . number_format($total, 2, ',', '.'), 1, 1, 'R', true);
    
    // Footer
    adicionar_footer_pdf($pdf, 'Documento gerado automaticamente pelo sistema RH Privus');
    
    return $pdf;
}

/**
 * Gera PDF de relatório de colaboradores
 */
function gerar_pdf_colaboradores($colaboradores, $filtros = []) {
    $pdf = criar_pdf('Relatório de Colaboradores', 'RH Privus', 'Relatório de Colaboradores');
    $pdf->AddPage();
    
    // Header
    adicionar_header_pdf($pdf, 'Relatório de Colaboradores', 'Sistema RH Privus');
    
    // Tabela
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    
    // Cabeçalho
    $pdf->Cell(60, 7, 'Nome', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'CPF', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Cargo', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Setor', 1, 0, 'C', true);
    $pdf->Cell(0, 7, 'Status', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(255, 255, 255);
    
    foreach ($colaboradores as $colab) {
        $pdf->Cell(60, 6, substr($colab['nome_completo'] ?? '', 0, 35), 1, 0, 'L');
        $pdf->Cell(30, 6, formatar_cpf($colab['cpf'] ?? ''), 1, 0, 'C');
        $pdf->Cell(50, 6, substr($colab['cargo_nome'] ?? '', 0, 30), 1, 0, 'L');
        $pdf->Cell(30, 6, substr($colab['setor_nome'] ?? '', 0, 20), 1, 0, 'L');
        $pdf->Cell(0, 6, ucfirst($colab['status'] ?? ''), 1, 1, 'C');
    }
    
    // Footer
    adicionar_footer_pdf($pdf);
    
    return $pdf;
}

/**
 * Salva ou exibe o PDF
 */
function output_pdf($pdf, $nome_arquivo = 'documento.pdf', $destino = 'I') {
    // I = mostra no navegador
    // D = força download
    // F = salva em arquivo
    // S = retorna como string
    
    if ($destino === 'F') {
        $caminho = __DIR__ . '/../temp/' . $nome_arquivo;
        if (!is_dir(dirname($caminho))) {
            mkdir(dirname($caminho), 0755, true);
        }
        $pdf->Output($caminho, 'F');
        return $caminho;
    }
    
    $pdf->Output($nome_arquivo, $destino);
}

