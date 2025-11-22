/**
 * Inicialização de Select2 para campos de seleção de colaborador
 * Mostra avatar e permite busca
 */

(function() {
    'use strict';
    
    // Carrega CSS do Select2 dinamicamente se não estiver carregado
    function loadSelect2CSS() {
        if (document.getElementById('select2-css-loaded')) {
            return;
        }
        
        var link1 = document.createElement('link');
        link1.id = 'select2-css-loaded';
        link1.rel = 'stylesheet';
        link1.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
        document.head.appendChild(link1);
        
        var link2 = document.createElement('link');
        link2.rel = 'stylesheet';
        link2.href = 'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css';
        document.head.appendChild(link2);
    }
    
    // Carrega JS do Select2 dinamicamente se não estiver carregado
    function loadSelect2JS(callback) {
        if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined') {
            callback();
            return;
        }
        
        if (document.getElementById('select2-js-loaded')) {
            // Já está carregando, aguarda
            setTimeout(function() {
                loadSelect2JS(callback);
            }, 100);
            return;
        }
        
        var script = document.createElement('script');
        script.id = 'select2-js-loaded';
        script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
        script.onload = function() {
            callback();
        };
        script.onerror = function() {
            console.error('Erro ao carregar Select2');
        };
        document.head.appendChild(script);
    }
    
    function initSelectColaborador() {
        // Carrega CSS primeiro
        loadSelect2CSS();
        
        // Aguarda jQuery estar disponível
        if (typeof window.jQuery === 'undefined') {
            setTimeout(initSelectColaborador, 100);
            return;
        }
        
        // Carrega Select2 JS se necessário
        if (typeof window.jQuery.fn.select2 === 'undefined') {
            loadSelect2JS(function() {
                setTimeout(initSelectColaborador, 100);
            });
            return;
        }
        
        var $ = window.jQuery;
        
        // Verifica se há selects para inicializar
        var selects = $('.select-colaborador');
        if (selects.length === 0) {
            return;
        }
        
        // Inicializa todos os selects com classe .select-colaborador
        selects.each(function() {
            var $select = $(this);
            
            // Ignora se não tem ID (elemento inválido)
            if (!$select.attr('id')) {
                return;
            }
            
            // Se já foi inicializado, não inicializa novamente
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            
            // Verifica se há opções no select (deve ter pelo menos 2: placeholder + 1 colaborador)
            var optionsCount = $select.find('option').length;
            if (optionsCount <= 1) {
                return;
            }
            
            $select.select2({
                placeholder: 'Selecione um colaborador...',
                allowClear: true,
                width: '100%',
                dropdownAutoWidth: false,
                minimumResultsForSearch: 0, // Sempre mostra campo de busca
                language: {
                    noResults: function() {
                        return 'Nenhum colaborador encontrado';
                    },
                    searching: function() {
                        return 'Buscando...';
                    }
                },
                // Template para mostrar avatar e nome no dropdown
                templateResult: function(data) {
                    // Se não tem ID, é o placeholder
                    if (!data.id) {
                        return data.text;
                    }
                    
                    // Se não tem elemento, retorna texto simples
                    if (!data.element) {
                        return data.text;
                    }
                    
                    try {
                        var $option = $(data.element);
                        if (!$option.length) {
                            return data.text;
                        }
                        
                        var foto = $option.attr('data-foto') || null;
                        var nome = $option.attr('data-nome') || data.text || '';
                        
                        if (!nome) {
                            nome = data.text || '';
                        }
                        
                        // Se não tem nome, retorna texto simples
                        if (!nome) {
                            return data.text;
                        }
                        
                        // Cria HTML string ao invés de jQuery object para garantir compatibilidade
                        var html = '<span style="display: flex; align-items: center;">';
                        
                        // Avatar
                        if (foto) {
                            html += '<img src="' + foto + '" class="rounded-circle me-2" width="32" height="32" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                        } else {
                            // Avatar padrão com inicial do nome
                            var inicial = nome.charAt(0).toUpperCase();
                            html += '<span class="symbol symbol-circle symbol-32px me-2"><span class="symbol-label fs-6 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                        }
                        
                        // Nome
                        html += '<span>' + nome + '</span>';
                        html += '</span>';
                        
                        return $(html);
                    } catch (e) {
                        return data.text;
                    }
                },
                // Template para mostrar avatar e nome quando selecionado
                templateSelection: function(data) {
                    // Se não tem ID, é o placeholder
                    if (!data.id) {
                        return data.text;
                    }
                    
                    // Se não tem elemento, retorna texto simples
                    if (!data.element) {
                        return data.text;
                    }
                    
                    try {
                        var $option = $(data.element);
                        if (!$option.length) {
                            return data.text;
                        }
                        
                        var foto = $option.attr('data-foto') || null;
                        var nome = $option.attr('data-nome') || data.text || '';
                        
                        if (!nome) {
                            nome = data.text || '';
                        }
                        
                        // Se não tem nome, retorna texto simples
                        if (!nome) {
                            return data.text;
                        }
                        
                        // Cria HTML string ao invés de jQuery object para garantir compatibilidade
                        var html = '<span style="display: flex; align-items: center;">';
                        
                        // Avatar menor quando selecionado
                        if (foto) {
                            html += '<img src="' + foto + '" class="rounded-circle me-2" width="24" height="24" style="object-fit: cover;" onerror="this.src=\'../assets/media/avatars/blank.png\'" />';
                        } else {
                            // Avatar padrão menor
                            var inicial = nome.charAt(0).toUpperCase();
                            html += '<span class="symbol symbol-circle symbol-24px me-2"><span class="symbol-label fs-7 fw-semibold bg-primary text-white">' + inicial + '</span></span>';
                        }
                        
                        // Nome
                        html += '<span>' + nome + '</span>';
                        html += '</span>';
                        
                        return $(html);
                    } catch (e) {
                        return data.text;
                    }
                },
                // Permite busca por nome
                matcher: function(params, data) {
                    // Se não há termo de busca, mostra todos
                    if ($.trim(params.term) === '') {
                        return data;
                    }
                    
                    // Se não tem ID, é o placeholder - não mostra na busca
                    if (!data.id) {
                        return null;
                    }
                    
                    // Busca no texto (nome do colaborador)
                    var searchTerm = params.term.toUpperCase();
                    if (data.text && data.text.toUpperCase().indexOf(searchTerm) !== -1) {
                        return data;
                    }
                    
                    // Busca também no atributo data-nome
                    if (data.element) {
                        try {
                            var $option = $(data.element);
                            if ($option.length) {
                                var nome = $option.data('nome');
                                if (nome && nome.toUpperCase().indexOf(searchTerm) !== -1) {
                                    return data;
                                }
                            }
                        } catch (e) {
                            // Ignora erro e continua
                        }
                    }
                    
                    return null;
                },
                // Desabilita busca mínima para mostrar todos os resultados
                minimumInputLength: 0,
                // Garante que todas as opções sejam mostradas
                minimumResultsForSearch: 0
            });
        });
    }
    
    // Flag para evitar múltiplas inicializações (mas permite reinicialização quando necessário)
    var initialized = false;
    
    function tryInit(force) {
        // Verifica se jQuery e Select2 estão disponíveis
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
            // Se Select2 não estiver disponível, tenta carregar
            loadSelect2JS(function() {
                setTimeout(function() {
                    tryInit(force);
                }, 100);
            });
            return;
        }
        
        // Verifica se há selects para inicializar
        var $ = window.jQuery;
        var selects = $('.select-colaborador');
        if (selects.length === 0) {
            return;
        }
        
        // Se não for forçado, verifica se há selects não inicializados
        if (!force) {
            var uninitialized = selects.not('.select2-hidden-accessible');
            if (uninitialized.length === 0) {
                return; // Todos já foram inicializados
            }
        }
        
        // Inicializa apenas os que ainda não foram inicializados
        initSelectColaborador();
        initialized = true;
    }
    
    // Expõe função pública para inicialização manual (útil para modais)
    window.initSelectColaboradorManual = function() {
        tryInit(true); // Força reinicialização
    };
    
    // Expõe função para verificar se Select2 está disponível
    window.isSelect2Available = function() {
        return typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined';
    };
    
    // Inicializa quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(tryInit, 300);
        });
    } else {
        setTimeout(tryInit, 300);
    }
    
    // Também tenta após window.load para garantir que Select2 esteja carregado
    window.addEventListener('load', function() {
        setTimeout(tryInit, 200);
    });
    
    // Escuta eventos de modais do Bootstrap para inicializar Select2 quando o modal for aberto
    // Isso é necessário porque elementos dentro de modais podem não estar visíveis quando a página carrega
    document.addEventListener('DOMContentLoaded', function() {
        // Escuta todos os modais que podem conter selects de colaborador
        var modalIds = ['kt_modal_horaextra', 'kt_modal_promocao', 'kt_modal_usuario'];
        
        modalIds.forEach(function(modalId) {
            var modalElement = document.getElementById(modalId);
            if (modalElement) {
                modalElement.addEventListener('shown.bs.modal', function() {
                    // Aguarda um pouco para garantir que o modal está totalmente renderizado
                    setTimeout(function() {
                        // Força nova tentativa de inicialização
                        tryInit(true);
                    }, 300);
                });
            }
        });
        
        // Também escuta qualquer modal que contenha um select-colaborador
        // Usa event delegation para capturar modais criados dinamicamente
        document.addEventListener('shown.bs.modal', function(e) {
            var modal = e.target;
            if (modal && modal.querySelector && modal.querySelector('.select-colaborador')) {
                setTimeout(function() {
                    tryInit(true); // Força reinicialização
                }, 300);
            }
        }, true); // Usa capture phase para pegar o evento antes que seja tratado
    });
})();

