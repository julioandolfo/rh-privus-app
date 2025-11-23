/**
 * JavaScript para Chat do Colaborador
 */

(function() {
    'use strict';
    
    const ChatConversa = {
        conversaId: null,
        ultimaMensagemId: 0,
        pollingInterval: null,
        colaboradorId: null,
        
        init: function() {
            // Obtém ID do colaborador logado (deve ser definido na página)
            this.colaboradorId = window.CHAT_COLABORADOR_ID || null;
            
            // Obtém ID da conversa da URL
            const urlParams = new URLSearchParams(window.location.search);
            this.conversaId = urlParams.get('id');
            
            if (this.conversaId) {
                this.conversaId = parseInt(this.conversaId);
                this.setupFormResposta();
                
                // Inicializa ultimaMensagemId com a última mensagem já renderizada
                const container = document.getElementById('chat-mensagens');
                if (container) {
                    const ultimaMsg = container.querySelector('.chat-mensagem-wrapper:last-child');
                    if (ultimaMsg) {
                        const msgId = ultimaMsg.getAttribute('data-msg-id');
                        if (msgId) {
                            this.ultimaMensagemId = parseInt(msgId);
                        }
                    }
                }
                
                // Se não encontrou mensagem, inicializa buscando do servidor
                if (this.ultimaMensagemId === 0) {
                    this.buscarUltimaMensagemId();
                }
                
                this.iniciarPolling();
                this.scrollToBottom();
            }
        },
        
        setupFormResposta: function() {
            const form = document.getElementById('chat-form-resposta');
            if (!form) return;
            
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.enviarMensagem();
            });
        },
        
        enviarMensagem: function() {
            const form = document.getElementById('chat-form-resposta');
            const formData = new FormData(form);
            
            fetch('../api/chat/mensagens/enviar.php', {
                method: 'POST',
                body: formData
            })
                .then(async response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Resposta não é JSON:', text);
                        throw new Error('Resposta inválida do servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        form.reset();
                        // Aguarda um pouco e verifica novas mensagens em vez de recarregar tudo
                        setTimeout(() => {
                            this.verificarNovasMensagens();
                        }, 500);
                    } else {
                        Swal.fire('Erro', data.message || 'Erro ao enviar mensagem', 'error');
                    }
                })
                .catch(err => {
                    console.error('Erro ao enviar mensagem:', err);
                    Swal.fire('Erro', 'Erro ao enviar mensagem', 'error');
                });
        },
        
        buscarUltimaMensagemId: function() {
            // Busca o ID da última mensagem para inicializar o polling
            fetch(`../api/chat/mensagens/listar.php?conversa_id=${this.conversaId}&page=1`)
                .then(async response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return null;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success && data.data.length > 0) {
                        // Pega o ID da última mensagem
                        const ultimaMsg = data.data[data.data.length - 1];
                        this.ultimaMensagemId = ultimaMsg.id;
                    }
                })
                .catch(err => console.error('Erro ao buscar última mensagem:', err));
        },
        
        iniciarPolling: function() {
            // Limpa intervalo anterior se existir
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
            }
            
            // Polling a cada 3 segundos
            this.pollingInterval = setInterval(() => {
                if (this.conversaId && document.visibilityState === 'visible') {
                    this.verificarNovasMensagens();
                }
            }, 3000);
            
            // Verifica quando a página volta a ficar visível
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.conversaId) {
                    // Verifica imediatamente quando volta a ficar visível
                    this.verificarNovasMensagens();
                }
            });
        },
        
        verificarNovasMensagens: function() {
            if (!this.conversaId) return;
            
            fetch(`../api/chat/mensagens/novas.php?conversa_id=${this.conversaId}&ultima_mensagem_id=${this.ultimaMensagemId}`)
                .then(async response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Resposta não é JSON:', text);
                        throw new Error('Resposta inválida do servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.novas_mensagens && data.novas_mensagens.length > 0) {
                        // Adiciona novas mensagens
                        data.novas_mensagens.forEach(msg => {
                            this.adicionarMensagem(msg);
                            this.ultimaMensagemId = Math.max(this.ultimaMensagemId, msg.id);
                        });
                        this.scrollToBottom();
                        
                        // Toca som se configurado
                        this.tocarSomNotificacao();
                    }
                })
                .catch(err => {
                    console.error('Erro ao verificar novas mensagens:', err);
                    // Não interrompe o polling em caso de erro
                });
        },
        
        adicionarMensagem: function(msg) {
            const container = document.getElementById('chat-mensagens');
            if (!container) return;
            
            // Verifica se mensagem já existe (evita duplicação)
            const msgExistente = container.querySelector(`[data-msg-id="${msg.id}"]`);
            if (msgExistente) {
                return; // Mensagem já existe, não adiciona novamente
            }
            
            // Verifica se é minha mensagem (enviada pelo colaborador logado)
            const ehMinhaMensagem = msg.enviado_por_colaborador_id && msg.enviado_por_colaborador_id == ChatConversa.colaboradorId;
            const classeWrapper = ehMinhaMensagem ? 'mensagem-rh' : 'mensagem-colaborador';
            const nomeAutor = msg.autor ? msg.autor.nome : 'Usuário';
            const dataAtual = new Date(msg.created_at).toLocaleDateString('pt-BR');
            const hoje = new Date().toLocaleDateString('pt-BR');
            
            // Verifica se precisa de separador de data
            // Busca a última mensagem renderizada (não o separador)
            const ultimasMensagens = container.querySelectorAll('.chat-mensagem-wrapper');
            let precisaSeparador = false;
            
            if (ultimasMensagens.length === 0) {
                // Primeira mensagem, sempre mostra separador
                precisaSeparador = true;
            } else {
                // Busca a última mensagem renderizada
                const ultimaMensagem = ultimasMensagens[ultimasMensagens.length - 1];
                const ultimaMsgDataAttr = ultimaMensagem.getAttribute('data-msg-date');
                
                if (!ultimaMsgDataAttr) {
                    // Se não tem atributo de data, precisa verificar
                    const ultimoSeparador = container.querySelector('.chat-data-separator:last-child');
                    if (ultimoSeparador) {
                        const textoSeparador = ultimoSeparador.textContent.trim();
                        const textoEsperado = dataAtual === hoje ? 'Hoje' : dataAtual;
                        precisaSeparador = textoSeparador !== textoEsperado;
                    } else {
                        precisaSeparador = false;
                    }
                } else {
                    // Compara datas
                    precisaSeparador = ultimaMsgDataAttr !== dataAtual;
                }
            }
            
            if (precisaSeparador) {
                const separador = document.createElement('div');
                separador.className = 'chat-data-separator';
                separador.innerHTML = `<span>${dataAtual === hoje ? 'Hoje' : dataAtual}</span>`;
                container.appendChild(separador);
            }
            
            let conteudo = '';
            if (msg.voz && msg.voz.caminho) {
                conteudo = `
                    <div class="chat-mensagem-voz">
                        <audio controls>
                            <source src="../${msg.voz.caminho}" type="audio/mpeg">
                        </audio>
                        ${msg.voz.transcricao ? `<div class="chat-voz-transcricao"><small>${this.escapeHtml(msg.voz.transcricao)}</small></div>` : ''}
                    </div>
                `;
            } else if (msg.anexo && msg.anexo.caminho) {
                conteudo = `
                    <div class="chat-mensagem-anexo">
                        <a href="../${msg.anexo.caminho}" target="_blank" class="d-inline-flex align-items-center gap-2 p-2 bg-light rounded text-decoration-none">
                            <i class="ki-duotone ki-file-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                            <span>${this.escapeHtml(msg.anexo.nome || msg.anexo.nome_original || 'Anexo')}</span>
                        </a>
                    </div>
                `;
            } else {
                conteudo = `<div class="chat-mensagem-texto">${this.nl2br(this.escapeHtml(msg.mensagem || ''))}</div>`;
            }
            
            // Obtém avatar do autor (prioriza autor.foto)
            const avatar = msg.autor?.foto || msg.avatar || null;
            const inicial = nomeAutor.charAt(0).toUpperCase();
            const dataHora = new Date(msg.created_at);
            const dataFormatada = dataHora.toLocaleDateString('pt-BR') + ' ' + dataHora.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
            
            let avatarHtml = '';
            if (avatar) {
                avatarHtml = `<img src="../${avatar}" alt="${this.escapeHtml(nomeAutor)}" class="chat-mensagem-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
            }
            avatarHtml += `<div class="chat-mensagem-avatar chat-avatar-inicial d-flex align-items-center justify-content-center fw-bold text-white" data-inicial="${inicial}" style="${avatar ? 'display: none;' : ''}">${inicial}</div>`;
            
            const wrapper = document.createElement('div');
            wrapper.className = `chat-mensagem-wrapper ${classeWrapper}`;
            wrapper.setAttribute('data-msg-id', msg.id);
            wrapper.setAttribute('data-msg-date', dataAtual); // Armazena a data para comparação
            wrapper.innerHTML = `
                ${avatarHtml}
                <div class="chat-mensagem">
                    <div class="chat-mensagem-enviado-por">${ehMinhaMensagem ? 'Enviado por mim' : 'Enviado por: ' + this.escapeHtml(nomeAutor)}</div>
                    <div class="chat-mensagem-conteudo">
                        ${conteudo}
                    </div>
                    <div class="chat-mensagem-timestamp">
                        <span>${dataFormatada}</span>
                    </div>
                </div>
            `;
            container.appendChild(wrapper);
            this.scrollToBottom();
        },
        
        scrollToBottom: function() {
            const container = document.getElementById('chat-mensagens');
            if (container) {
                setTimeout(() => {
                    container.scrollTop = container.scrollHeight;
                }, 100);
            }
        },
        
        tocarSomNotificacao: function() {
            // Verifica preferências e toca som se configurado
            // Implementar áudio se necessário
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        nl2br: function(text) {
            return text.replace(/\n/g, '<br>');
        }
    };
    
    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ChatConversa.init());
    } else {
        ChatConversa.init();
    }
    
    window.ChatConversa = ChatConversa;
})();

