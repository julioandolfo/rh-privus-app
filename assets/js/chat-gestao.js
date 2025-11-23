/**
 * JavaScript para Gestão de Chat - RH
 */

(function() {
    'use strict';
    
    const ChatGestao = {
        conversaId: null,
        ultimaMensagemId: 0,
        pollingInterval: null,
        gravacaoVoz: null,
        mediaRecorder: null,
        usuarioId: null,
        
        init: function() {
            // Obtém ID do usuário logado (deve ser definido na página)
            this.usuarioId = window.CHAT_USUARIO_ID || null;
            
            // Verifica se há conversa aberta
            const urlParams = new URLSearchParams(window.location.search);
            this.conversaId = urlParams.get('conversa');
            
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
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        form.reset();
                        this.carregarMensagens();
                        this.scrollToBottom();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: data.message || 'Erro ao enviar mensagem'
                        });
                    }
                })
                .catch(err => {
                    console.error('Erro:', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao enviar mensagem. Tente novamente.'
                    });
                });
        },
        
        carregarMensagens: function() {
            if (!this.conversaId) return;
            
            fetch(`../api/chat/mensagens/listar.php?conversa_id=${this.conversaId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.renderMensagens(data.data);
                        if (data.data.length > 0) {
                            this.ultimaMensagemId = data.data[data.data.length - 1].id;
                        }
                    }
                })
                .catch(err => console.error('Erro ao carregar mensagens:', err));
        },
        
        renderMensagens: function(mensagens) {
            const container = document.getElementById('chat-mensagens');
            if (!container) return;
            
            container.innerHTML = mensagens.map(msg => {
                const autor = msg.autor.tipo === 'rh' ? msg.autor.nome : msg.autor.nome;
                // Verifica se é minha mensagem (enviada pelo usuário logado)
                const ehMinhaMensagem = msg.enviado_por_usuario_id && msg.enviado_por_usuario_id == ChatGestao.usuarioId;
                const classe = ehMinhaMensagem ? 'mensagem-rh' : 'mensagem-colaborador';
                const data = new Date(msg.created_at).toLocaleString('pt-BR');
                
                let conteudo = '';
                if (msg.voz) {
                    conteudo = `
                        <div class="chat-mensagem-voz">
                            <audio controls>
                                <source src="../${msg.voz.caminho}" type="audio/mpeg">
                            </audio>
                            ${msg.voz.transcricao ? `<div class="chat-voz-transcricao"><small class="text-muted">Transcrição: ${this.escapeHtml(msg.voz.transcricao)}</small></div>` : ''}
                        </div>
                    `;
                } else if (msg.anexo) {
                    conteudo = `
                        <div class="chat-mensagem-anexo">
                            <a href="../${msg.anexo.caminho}" target="_blank" class="btn btn-sm btn-light">
                                <i class="ki-duotone ki-file-down"><span class="path1"></span><span class="path2"></span></i>
                                ${this.escapeHtml(msg.anexo.nome)}
                            </a>
                        </div>
                    `;
                } else {
                    conteudo = `<div class="chat-mensagem-texto">${this.nl2br(this.escapeHtml(msg.mensagem))}</div>`;
                }
                
                return `
                    <div class="chat-mensagem ${classe}">
                        <div class="chat-mensagem-header">
                            <strong>${this.escapeHtml(autor)}</strong>
                            <span class="text-muted small ms-2">${data}</span>
                        </div>
                        <div class="chat-mensagem-conteudo">
                            ${conteudo}
                        </div>
                    </div>
                `;
            }).join('');
        },
        
        iniciarPolling: function() {
            // Polling a cada 3 segundos
            this.pollingInterval = setInterval(() => {
                if (this.conversaId) {
                    this.verificarNovasMensagens();
                }
            }, 3000);
        },
        
        verificarNovasMensagens: function() {
            fetch(`../api/chat/mensagens/novas.php?conversa_id=${this.conversaId}&ultima_mensagem_id=${this.ultimaMensagemId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.novas_mensagens.length > 0) {
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
                .catch(err => console.error('Erro ao verificar novas mensagens:', err));
        },
        
        adicionarMensagem: function(msg) {
            const container = document.getElementById('chat-mensagens');
            if (!container) return;
            
            // Verifica se mensagem já existe (evita duplicação)
            const msgExistente = container.querySelector(`[data-msg-id="${msg.id}"]`);
            if (msgExistente) {
                return; // Mensagem já existe, não adiciona novamente
            }
            
            // Verifica se é minha mensagem (enviada pelo usuário logado)
            const ehMinhaMensagem = msg.enviado_por_usuario_id && msg.enviado_por_usuario_id == ChatGestao.usuarioId;
            const classeWrapper = ehMinhaMensagem ? 'mensagem-rh' : 'mensagem-colaborador';
            const nomeAutor = msg.autor ? msg.autor.nome : 'Usuário';
            const dataAtual = new Date(msg.created_at).toLocaleDateString('pt-BR');
            
            // Verifica se precisa de separador de data
            const ultimaData = container.querySelector('.chat-data-separator:last-child');
            const hoje = new Date().toLocaleDateString('pt-BR');
            const precisaSeparador = !ultimaData || ultimaData.textContent.trim() !== (dataAtual === hoje ? 'Hoje' : dataAtual);
            
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
                        <audio controls style="max-width: 250px;">
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
            
            const avatar = msg.avatar || msg.autor?.avatar || null;
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
            wrapper.innerHTML = `
                ${avatarHtml}
                <div class="chat-mensagem">
                    <div class="chat-mensagem-enviado-por">Enviado por: ${this.escapeHtml(nomeAutor)}</div>
                    <div class="chat-mensagem-conteudo">
                        ${conteudo}
                    </div>
                    <div class="chat-mensagem-timestamp">
                        <span>${dataFormatada}</span>
                        ${ehRh && msg.lida ? '<i class="ki-duotone ki-check fs-8 text-primary"><span class="path1"></span><span class="path2"></span></i>' : ''}
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
            // Verifica preferências
            fetch('../api/chat/preferencias/salvar.php')
                .then(r => r.json())
                .then(data => {
                    // Toca som se ativado (implementar áudio)
                })
                .catch(() => {});
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
    
    // Funções globais
    window.atribuirConversa = function(conversaId) {
        Swal.fire({
            title: 'Atribuir Conversa',
            html: '<select id="swal-usuario" class="swal2-input"><option value="">Selecione...</option></select>',
            showCancelButton: true,
            confirmButtonText: 'Atribuir',
            preConfirm: () => {
                const usuarioId = document.getElementById('swal-usuario').value;
                if (!usuarioId) {
                    Swal.showValidationMessage('Selecione um usuário');
                    return false;
                }
                return usuarioId;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('conversa_id', conversaId);
                formData.append('usuario_id', result.value);
                
                fetch('../api/chat/conversas/atribuir.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Conversa atribuída!', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    });
            }
        });
    };
    
    window.gerarResumoIA = function(conversaId) {
        Swal.fire({
            title: 'Gerando Resumo...',
            text: 'Aguarde enquanto a IA processa a conversa',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const formData = new FormData();
        formData.append('conversa_id', conversaId);
        
        fetch('../api/chat/ia/gerar_resumo.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Resumo Gerado',
                        html: `<div style="text-align: left; max-height: 400px; overflow-y: auto;">${data.resumo.replace(/\n/g, '<br>')}</div>`,
                        width: '600px',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message || 'Erro ao gerar resumo', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Erro', 'Erro ao gerar resumo', 'error');
            });
    };
    
    window.fecharConversa = function(conversaId) {
        Swal.fire({
            title: 'Fechar Conversa',
            input: 'text',
            inputLabel: 'Motivo (opcional)',
            inputPlaceholder: 'Digite o motivo do fechamento...',
            showCancelButton: true,
            confirmButtonText: 'Fechar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('conversa_id', conversaId);
                if (result.value) {
                    formData.append('motivo', result.value);
                }
                
                fetch('../api/chat/conversas/fechar.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Sucesso', 'Conversa fechada!', 'success').then(() => {
                                window.location.href = 'chat_gestao.php';
                            });
                        } else {
                            Swal.fire('Erro', data.message, 'error');
                        }
                    });
            }
        });
    };
    
    // Função de gravação movida para chat-audio.js
    // Mantida aqui apenas para compatibilidade, mas será sobrescrita pelo chat-audio.js
    window.iniciarGravacaoVoz = window.iniciarGravacaoVoz || function(conversaId) {
        if (typeof ChatAudio !== 'undefined') {
            ChatAudio.iniciarGravacao(conversaId);
        } else {
            Swal.fire('Erro', 'Sistema de áudio não carregado. Recarregue a página.', 'error');
        }
    };
    
    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ChatGestao.init());
    } else {
        ChatGestao.init();
    }
    
    window.ChatGestao = ChatGestao;
})();

