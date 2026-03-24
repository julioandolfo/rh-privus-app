<?php
/**
 * Rótulos de interface: horas adicionais da prestação de serviço (evita vocabulário típico de CLT).
 * Tabelas e rotas continuam com nomes técnicos (horas_extras, etc.).
 */

function hx_ui_menu_registro(): string {
    return 'Horas adicionais';
}

function hx_ui_menu_aprovar(): string {
    return 'Aprovar horas adicionais';
}

function hx_ui_menu_solicitar(): string {
    return 'Solicitar horas adicionais';
}

/** Título da tela de gestão (RH/admin) */
function hx_ui_titulo_gestao(): string {
    return 'Horas adicionais (prestadores)';
}

function hx_ui_contexto_prestador(): string {
    return 'Referente à sua prestação de serviço — não se aplica a relação CLT. Regras, valores e forma de compensação: consulte seu gestor.';
}

function hx_ui_consulte_gestor_valores(): string {
    return 'Valores, percentuais e forma de compensação não são exibidos aqui. Consulte seu gestor.';
}
