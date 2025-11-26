/**
 * Sistema de Tutorial Interativo usando Intro.js
 * Guia o usuário pelas principais funcionalidades das páginas
 */

// Verifica se Intro.js está disponível
function initTutorialSystem() {
    if (typeof introJs === 'undefined') {
        console.warn('Intro.js não está carregado. Carregando...');
        loadIntroJS();
        return;
    }
    
    // Verifica se já existe um tutorial configurado para esta página
    const pageTutorial = window.pageTutorial;
    if (!pageTutorial || !pageTutorial.steps || pageTutorial.steps.length === 0) {
        return; // Não há tutorial para esta página
    }
    
    // Cria botão flutuante para iniciar tutorial
    createTutorialButton();
}

// Carrega Intro.js dinamicamente se não estiver disponível
function loadIntroJS() {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/introjs.min.css';
    document.head.appendChild(link);
    
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js';
    script.onload = function() {
        initTutorialSystem();
    };
    document.head.appendChild(script);
}

// Cria botão flutuante para iniciar tutorial
function createTutorialButton() {
    // Remove botão existente se houver
    const existingBtn = document.getElementById('tutorial-start-btn');
    if (existingBtn) {
        existingBtn.remove();
    }
    
    const btn = document.createElement('button');
    btn.id = 'tutorial-start-btn';
    btn.className = 'btn btn-primary btn-sm position-fixed';
    btn.style.cssText = 'bottom: 20px; right: 20px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 50px; padding: 12px 20px;';
    btn.innerHTML = '<i class="ki-duotone ki-question fs-4 me-2"><span class="path1"></span><span class="path2"></span></i> Iniciar Tutorial';
    
    btn.addEventListener('click', function() {
        startTutorial();
    });
    
    document.body.appendChild(btn);
}

// Inicia o tutorial
function startTutorial() {
    const pageTutorial = window.pageTutorial;
    if (!pageTutorial || !pageTutorial.steps) {
        console.error('Tutorial não configurado para esta página');
        return;
    }
    
    // Verifica se o usuário já completou este tutorial
    const tutorialKey = 'tutorial_completed_' + (pageTutorial.pageId || window.location.pathname);
    const completed = localStorage.getItem(tutorialKey);
    
    if (completed === 'true' && pageTutorial.showSkipOption !== false) {
        // Pergunta se quer refazer
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Tutorial já concluído',
                text: 'Deseja refazer o tutorial?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, refazer',
                cancelButtonText: 'Não',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    runTutorial();
                }
            });
        } else {
            if (confirm('Deseja refazer o tutorial?')) {
                runTutorial();
            }
        }
    } else {
        runTutorial();
    }
}

// Executa o tutorial
function runTutorial() {
    const pageTutorial = window.pageTutorial;
    
    // Filtra steps que têm elementos válidos
    const validSteps = pageTutorial.steps.filter(function(step) {
        if (!step.element) {
            return true; // Step sem elemento é válido (tela de boas-vindas)
        }
        
        // Se element é uma string (seletor)
        if (typeof step.element === 'string') {
            const element = document.querySelector(step.element);
            return element !== null;
        }
        
        // Se element é um elemento DOM
        if (step.element instanceof Element) {
            return document.body.contains(step.element);
        }
        
        return false;
    });
    
    if (validSteps.length === 0) {
        console.warn('Nenhum passo válido encontrado para o tutorial');
        return;
    }
    
    const intro = introJs();
    
    // Detecta tema atual
    const isDarkTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ||
                        document.body.classList.contains('dark-mode') ||
                        (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    // Configurações globais
    intro.setOptions({
        steps: validSteps,
        showProgress: true,
        showBullets: true,
        exitOnOverlayClick: false,
        exitOnEsc: true,
        keyboardNavigation: true,
        prevLabel: '← Anterior',
        nextLabel: 'Próximo →',
        skipLabel: 'Pular Tutorial',
        doneLabel: 'Concluir',
        tooltipPosition: 'auto',
        tooltipClass: 'customTooltip',
        highlightClass: 'customHighlight',
        scrollToElement: true,
        scrollPadding: 20,
        disableInteraction: false
    });
    
    // Aplica tema após iniciar
    intro.onchange(function(targetElement) {
        // Força atualização do tema
        setTimeout(function() {
            const tooltip = document.querySelector('.introjs-tooltip');
            if (tooltip) {
                const currentTheme = document.documentElement.getAttribute('data-bs-theme');
                if (currentTheme === 'dark') {
                    tooltip.setAttribute('data-bs-theme', 'dark');
                } else {
                    tooltip.removeAttribute('data-bs-theme');
                }
                
                // Garante que o conteúdo não ultrapasse
                const content = tooltip.querySelector('.introjs-tooltip-content');
                if (content) {
                    content.style.maxWidth = '100%';
                    content.style.overflow = 'hidden';
                    content.style.wordWrap = 'break-word';
                }
            }
        }, 100);
    });
    
    // Evento ao completar
    intro.oncomplete(function() {
        const tutorialKey = 'tutorial_completed_' + (pageTutorial.pageId || window.location.pathname);
        localStorage.setItem(tutorialKey, 'true');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: 'Tutorial concluído! Você pode refazê-lo a qualquer momento clicando no botão de ajuda.',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        }
    });
    
    // Evento ao sair
    intro.onexit(function() {
        // Não marca como completo se sair antes de terminar
    });
    
    // Inicia o tutorial
    intro.start();
}

// Adiciona estilos customizados
function addTutorialStyles() {
    if (document.getElementById('tutorial-custom-styles')) {
        return; // Já existe
    }
    
    const style = document.createElement('style');
    style.id = 'tutorial-custom-styles';
    style.textContent = `
        /* Tooltip principal - adapta ao tema */
        .introjs-tooltip {
            border-radius: 8px !important;
            font-family: 'Inter', sans-serif !important;
            max-width: 400px !important;
            min-width: 300px !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
            overflow: hidden !important;
        }
        
        /* Tema claro */
        [data-bs-theme="light"] .introjs-tooltip,
        body:not([data-bs-theme="dark"]) .introjs-tooltip {
            background-color: #ffffff !important;
            border: 1px solid #E4E6EF !important;
            color: #181C32 !important;
        }
        
        /* Tema escuro */
        [data-bs-theme="dark"] .introjs-tooltip {
            background-color: #1E1E2D !important;
            border: 1px solid #2B2B40 !important;
            color: #E4E6EF !important;
        }
        
        /* Título do tooltip */
        .introjs-tooltip-title {
            font-size: 18px !important;
            font-weight: 600 !important;
            margin-bottom: 12px !important;
            padding: 0 !important;
            line-height: 1.4 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
        }
        
        [data-bs-theme="light"] .introjs-tooltip-title,
        body:not([data-bs-theme="dark"]) .introjs-tooltip-title {
            color: #181C32 !important;
        }
        
        [data-bs-theme="dark"] .introjs-tooltip-title {
            color: #E4E6EF !important;
        }
        
        /* Conteúdo do tooltip */
        .introjs-tooltip-content {
            font-size: 14px !important;
            line-height: 1.6 !important;
            padding: 0 !important;
            margin: 0 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            max-height: none !important;
        }
        
        [data-bs-theme="light"] .introjs-tooltip-content,
        body:not([data-bs-theme="dark"]) .introjs-tooltip-content {
            color: #5E6278 !important;
        }
        
        [data-bs-theme="dark"] .introjs-tooltip-content {
            color: #92929F !important;
        }
        
        /* Container interno do tooltip */
        .introjs-tooltipbuttons {
            border-top: 1px solid !important;
            padding-top: 12px !important;
            margin-top: 16px !important;
        }
        
        [data-bs-theme="light"] .introjs-tooltipbuttons,
        body:not([data-bs-theme="dark"]) .introjs-tooltipbuttons {
            border-top-color: #E4E6EF !important;
        }
        
        [data-bs-theme="dark"] .introjs-tooltipbuttons {
            border-top-color: #2B2B40 !important;
        }
        
        /* Botões */
        .introjs-button {
            border-radius: 6px !important;
            padding: 8px 16px !important;
            font-weight: 500 !important;
            transition: all 0.2s !important;
            border: none !important;
        }
        
        [data-bs-theme="light"] .introjs-button,
        body:not([data-bs-theme="dark"]) .introjs-button {
            background-color: #F1F1F2 !important;
            color: #181C32 !important;
        }
        
        [data-bs-theme="light"] .introjs-button:hover,
        body:not([data-bs-theme="dark"]) .introjs-button:hover {
            background-color: #E4E6EF !important;
        }
        
        [data-bs-theme="dark"] .introjs-button {
            background-color: #2B2B40 !important;
            color: #E4E6EF !important;
        }
        
        [data-bs-theme="dark"] .introjs-button:hover {
            background-color: #3F3F54 !important;
        }
        
        .introjs-button.introjs-nextbutton,
        .introjs-button.introjs-donebutton {
            background-color: #009EF7 !important;
            color: #ffffff !important;
        }
        
        .introjs-button.introjs-nextbutton:hover,
        .introjs-button.introjs-donebutton:hover {
            background-color: #0095E8 !important;
        }
        
        /* Botão pular */
        .introjs-skipbutton {
            background-color: transparent !important;
            color: #009EF7 !important;
            border: 1px solid #009EF7 !important;
        }
        
        .introjs-skipbutton:hover {
            background-color: #009EF7 !important;
            color: #ffffff !important;
        }
        
        /* Progress bar */
        .introjs-progress {
            background-color: #E4E6EF !important;
            border-radius: 4px !important;
            height: 4px !important;
        }
        
        [data-bs-theme="dark"] .introjs-progress {
            background-color: #2B2B40 !important;
        }
        
        .introjs-progressbar {
            background-color: #009EF7 !important;
            border-radius: 4px !important;
        }
        
        /* Bullets de progresso */
        .introjs-bullets ul li a {
            background-color: #E4E6EF !important;
        }
        
        [data-bs-theme="dark"] .introjs-bullets ul li a {
            background-color: #2B2B40 !important;
        }
        
        .introjs-bullets ul li a.active {
            background-color: #009EF7 !important;
        }
        
        /* Highlight do elemento */
        .customHighlight,
        .introjs-helperLayer {
            border-radius: 8px !important;
            box-shadow: 0 0 0 4px rgba(0, 158, 247, 0.2) !important;
        }
        
        /* Padding interno do tooltip */
        .introjs-tooltip {
            padding: 20px !important;
        }
        
        /* Garante que o conteúdo não ultrapasse */
        .introjs-tooltip * {
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Botão flutuante */
        #tutorial-start-btn {
            animation: pulse 2s infinite;
            border-radius: 50px !important;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 4px 12px rgba(0, 158, 247, 0.3);
            }
            50% {
                box-shadow: 0 4px 20px rgba(0, 158, 247, 0.5);
            }
            100% {
                box-shadow: 0 4px 12px rgba(0, 158, 247, 0.3);
            }
        }
        
        #tutorial-start-btn:hover {
            animation: none;
            transform: scale(1.05);
            transition: transform 0.2s;
        }
        
        /* Responsividade */
        @media (max-width: 576px) {
            .introjs-tooltip {
                max-width: calc(100vw - 40px) !important;
                min-width: calc(100vw - 40px) !important;
                margin: 0 20px !important;
            }
        }
        
        /* Ajuste para elementos pequenos */
        .introjs-tooltipReferenceLayer {
            border-radius: 8px !important;
        }
        
        /* Garante quebra de linha adequada */
        .introjs-tooltip-content p {
            margin-bottom: 8px !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            hyphens: auto !important;
        }
        
        .introjs-tooltip-content p:last-child {
            margin-bottom: 0 !important;
        }
        
        /* Garante que o tooltip não ultrapasse a viewport */
        .introjs-tooltip {
            max-height: 90vh !important;
            overflow-y: auto !important;
        }
        
        .introjs-tooltip::-webkit-scrollbar {
            width: 6px !important;
        }
        
        .introjs-tooltip::-webkit-scrollbar-track {
            background: transparent !important;
        }
        
        [data-bs-theme="light"] .introjs-tooltip::-webkit-scrollbar-thumb,
        body:not([data-bs-theme="dark"]) .introjs-tooltip::-webkit-scrollbar-thumb {
            background: #E4E6EF !important;
            border-radius: 3px !important;
        }
        
        [data-bs-theme="dark"] .introjs-tooltip::-webkit-scrollbar-thumb {
            background: #2B2B40 !important;
            border-radius: 3px !important;
        }
        
        .introjs-tooltip::-webkit-scrollbar-thumb:hover {
            background: #009EF7 !important;
        }
    `;
    document.head.appendChild(style);
}

// Observa mudanças de tema
function observeThemeChanges() {
    // Observa mudanças no atributo data-bs-theme
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-bs-theme') {
                // Atualiza tooltip se estiver visível
                const tooltip = document.querySelector('.introjs-tooltip');
                if (tooltip) {
                    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
                    if (currentTheme === 'dark') {
                        tooltip.setAttribute('data-bs-theme', 'dark');
                    } else {
                        tooltip.removeAttribute('data-bs-theme');
                    }
                }
            }
        });
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });
}

// Inicializa quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        addTutorialStyles();
        observeThemeChanges();
        initTutorialSystem();
    });
} else {
    addTutorialStyles();
    observeThemeChanges();
    initTutorialSystem();
}

