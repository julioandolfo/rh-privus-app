/**
 * Widget Flutuante de Chat
 * Para colaboradores
 */

(function() {
    'use strict';
    
    const ChatWidget = {
        panel: null,
        button: null,
        conversas: [],
        pollingInterval: null,
        ultimaMensagemId: 0,
        conversaAtual: null,
        
        init: function() {
            // Verifica se é colaborador
            if (!this.isColaborador()) {
                return;
            }
            
            // Cria elementos
            this.createButton();
            this.createPanel();
            this.createModal();
            
            // Event listeners
            this.attachEvents();
            
            // Carrega conversas
            this.carregarConversas();
            
            // Inicia polling
            this.iniciarPolling();
        },
        
        isColaborador: function() {
            // Verifica se há indicador de colaborador na página
            return document.body.classList.contains('is-colaborador') || 
                   window.userRole === 'COLABORADOR';
        },
        
        createButton: function() {
            this.button = document.createElement('button');
            this.button.className = 'chat-widget-button';
            this.button.innerHTML = '<i class="ki-duotone ki-message-text-2" style="color: white !important;"><span class="path1" style="fill: white !important;"></span><span class="path2" style="fill: white !important;"></span><span class="path3" style="fill: white !important;"></span><span class="path4" style="fill: white !important;"></span></i>';
            this.button.setAttribute('aria-label', 'Abrir chat');
            
            // Badge de notificações
            const badge = document.createElement('span');
            badge.className = 'chat-widget-badge hidden';
            badge.id = 'chat-widget-badge';
            this.button.appendChild(badge);
            
            document.body.appendChild(this.button);
        },
        
        createPanel: function() {
            this.panel = document.createElement('div');
            this.panel.className = 'chat-widget-panel';
            this.panel.innerHTML = `
                <div class="chat-widget-header">
                    <h5>Minhas Conversas</h5>
                    <button class="chat-widget-close" aria-label="Fechar">&times;</button>
                </div>
                <div class="chat-widget-conversas" id="chat-widget-conversas">
                    <div class="chat-widget-loading">Carregando...</div>
                </div>
                <div class="chat-widget-footer">
                    <button class="chat-widget-btn-nova">Nova Conversa</button>
                </div>
            `;
            document.body.appendChild(this.panel);
        },
        
        createModal: function() {
            const modal = document.createElement('div');
            modal.className = 'chat-widget-modal';
            modal.id = 'chat-widget-modal';
            modal.innerHTML = `
                <div class="chat-widget-modal-content">
                    <div class="chat-widget-modal-header">
                        <h4>Nova Conversa</h4>
                        <button class="chat-widget-close" aria-label="Fechar">&times;</button>
                    </div>
                    <form id="chat-widget-form-nova">
                        <div class="chat-widget-form-group">
                            <label>Título *</label>
                            <input type="text" name="titulo" required placeholder="Ex: Dúvida sobre férias">
                        </div>
                        <div class="chat-widget-form-group">
                            <label>Categoria</label>
                            <select name="categoria_id" id="chat-categorias">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="chat-widget-form-group">
                            <label>Prioridade</label>
                            <select name="prioridade">
                                <option value="normal">Normal</option>
                                <option value="alta">Alta</option>
                                <option value="urgente">Urgente</option>
                                <option value="baixa">Baixa</option>
                            </select>
                        </div>
                        <div class="chat-widget-form-group">
                            <label>Mensagem *</label>
                            <textarea name="mensagem" required placeholder="Descreva sua solicitação..."></textarea>
                        </div>
                        <div class="chat-widget-modal-actions">
                            <button type="button" class="chat-widget-btn chat-widget-btn-secondary" onclick="ChatWidget.fecharModal()">Cancelar</button>
                            <button type="submit" class="chat-widget-btn chat-widget-btn-primary">Enviar</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Carrega categorias
            this.carregarCategorias();
        },
        
        attachEvents: function() {
            // Botão abre/fecha painel
            this.button.addEventListener('click', () => {
                this.togglePanel();
            });
            
            // Fechar painel
            this.panel.querySelector('.chat-widget-close').addEventListener('click', () => {
                this.fecharPanel();
            });
            
            // Nova conversa
            this.panel.querySelector('.chat-widget-btn-nova').addEventListener('click', () => {
                this.abrirModal();
            });
            
            // Fechar modal
            const modal = document.getElementById('chat-widget-modal');
            modal.querySelector('.chat-widget-close').addEventListener('click', () => {
                this.fecharModal();
            });
            
            // Submit form
            document.getElementById('chat-widget-form-nova').addEventListener('submit', (e) => {
                e.preventDefault();
                this.criarConversa();
            });
            
            // Fechar ao clicar fora
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.fecharModal();
                }
            });
        },
        
        togglePanel: function() {
            this.panel.classList.toggle('active');
            if (this.panel.classList.contains('active')) {
                this.carregarConversas();
            }
        },
        
        fecharPanel: function() {
            this.panel.classList.remove('active');
        },
        
        abrirModal: function() {
            document.getElementById('chat-widget-modal').classList.add('active');
        },
        
        fecharModal: function() {
            document.getElementById('chat-widget-modal').classList.remove('active');
            document.getElementById('chat-widget-form-nova').reset();
        },
        
        carregarCategorias: function() {
            fetch('../api/chat/categorias/listar.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('chat-categorias');
                        data.data.forEach(cat => {
                            const option = document.createElement('option');
                            option.value = cat.id;
                            option.textContent = cat.nome;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(err => console.error('Erro ao carregar categorias:', err));
        },
        
        carregarConversas: function() {
            const container = document.getElementById('chat-widget-conversas');
            container.innerHTML = '<div class="chat-widget-loading">Carregando...</div>';
            
            // Busca todas as conversas (sem filtro de status para incluir 'nova' e 'em_andamento')
            fetch('../api/chat/conversas/listar.php?limit=20')
                .then(r => {
                    // Verifica se a resposta é JSON válido
                    const contentType = r.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return r.text().then(text => {
                            throw new Error('Resposta não é JSON: ' + text.substring(0, 100));
                        });
                    }
                    return r.json();
                })
                .then(data => {
                    if (data && data.success) {
                        this.conversas = data.data || [];
                        this.renderConversas();
                        this.atualizarBadge();
                    } else {
                        container.innerHTML = '<div class="chat-widget-empty"><div class="chat-widget-empty-icon">💬</div><p>' + (data?.message || 'Erro ao carregar conversas') + '</p></div>';
                    }
                })
                .catch(err => {
                    console.error('Erro ao carregar conversas:', err);
                    container.innerHTML = '<div class="chat-widget-empty"><div class="chat-widget-empty-icon">⚠️</div><p>Erro ao conectar. Tente novamente.</p></div>';
                });
        },
        
        renderConversas: function() {
            const container = document.getElementById('chat-widget-conversas');
            
            if (this.conversas.length === 0) {
                container.innerHTML = '<div class="chat-widget-empty"><div class="chat-widget-empty-icon">💬</div><p>Nenhuma conversa ainda</p><p style="font-size: 12px; margin-top: 8px;">Clique em "Nova Conversa" para começar</p></div>';
                return;
            }
            
            container.innerHTML = this.conversas.map(conv => {
                const nomeColab = conv.colaborador?.nome || 'Colaborador';
                const fotoColab = conv.colaborador?.foto || null;
                const inicial = nomeColab.charAt(0).toUpperCase();
                
                return `
                <div class="chat-widget-conversa-item ${conv.total_mensagens_nao_lidas > 0 ? 'nao-lida' : ''}" 
                     data-conversa-id="${conv.id}">
                    <div class="d-flex align-items-center gap-2">
                        <!-- Avatar -->
                        ${fotoColab ? `<img src="../${fotoColab}" alt="${this.escapeHtml(nomeColab)}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e4e6ef; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : ''}
                        <div class="chat-avatar-inicial rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" 
                             data-inicial="${inicial}"
                             style="width: 40px; height: 40px; font-size: 16px; border: 2px solid #e4e6ef; flex-shrink: 0; ${fotoColab ? 'display: none;' : ''}">
                            ${inicial}
                        </div>
                        <div class="flex-grow-1">
                            <div class="chat-widget-conversa-titulo">
                                ${this.escapeHtml(conv.titulo)}
                                ${conv.total_mensagens_nao_lidas > 0 ? `<span class="chat-widget-conversa-badge">${conv.total_mensagens_nao_lidas}</span>` : ''}
                            </div>
                            <div class="chat-widget-conversa-preview">
                                ${this.formatarData(conv.ultima_mensagem_at)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }).join('');
            
            // Adiciona eventos de clique
            container.querySelectorAll('.chat-widget-conversa-item').forEach(item => {
                item.addEventListener('click', () => {
                    const conversaId = item.dataset.conversaId;
                    window.location.href = `chat_conversa.php?id=${conversaId}`;
                });
            });
        },
        
        criarConversa: function() {
            const form = document.getElementById('chat-widget-form-nova');
            const formData = new FormData(form);
            
            fetch('../api/chat/conversas/criar.php', {
                method: 'POST',
                body: formData
            })
                .then(r => {
                    const contentType = r.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return r.text().then(text => {
                            throw new Error('Resposta não é JSON: ' + text.substring(0, 200));
                        });
                    }
                    return r.json();
                })
                .then(data => {
                    if (data && data.success) {
                        this.fecharModal();
                        this.carregarConversas();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Conversa criada!',
                                text: 'Sua conversa foi criada com sucesso. O RH entrará em contato em breve.',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            alert('Conversa criada com sucesso!');
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: data?.message || 'Erro ao criar conversa'
                            });
                        } else {
                            alert('Erro: ' + (data?.message || 'Erro ao criar conversa'));
                        }
                    }
                })
                .catch(err => {
                    console.error('Erro:', err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao criar conversa. Tente novamente.'
                        });
                    } else {
                        alert('Erro ao criar conversa. Tente novamente.');
                    }
                });
        },
        
        atualizarBadge: function() {
            const total = this.conversas.reduce((sum, conv) => sum + (conv.total_mensagens_nao_lidas || 0), 0);
            const badge = document.getElementById('chat-widget-badge');
            
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : total;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        },
        
        iniciarPolling: function() {
            // Polling a cada 5 segundos
            this.pollingInterval = setInterval(() => {
                if (this.panel.classList.contains('active')) {
                    this.carregarConversas();
                }
            }, 5000);
        },
        
        formatarData: function(data) {
            if (!data) return '';
            const date = new Date(data);
            const agora = new Date();
            const diff = agora - date;
            const minutos = Math.floor(diff / 60000);
            
            if (minutos < 1) return 'Agora';
            if (minutos < 60) return `${minutos} min atrás`;
            if (minutos < 1440) return `${Math.floor(minutos / 60)}h atrás`;
            return date.toLocaleDateString('pt-BR');
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ChatWidget.init());
    } else {
        ChatWidget.init();
    }
    
    // Expõe globalmente
    window.ChatWidget = ChatWidget;
})();

