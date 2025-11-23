/**
 * Sistema de Gravação de Áudio para Chat
 */

const ChatAudio = {
    mediaRecorder: null,
    audioChunks: [],
    stream: null,
    conversaId: null,
    
    /**
     * Inicia gravação de áudio
     */
    iniciarGravacao: function(conversaId) {
        this.conversaId = conversaId;
        
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Gravação de voz não suportada neste navegador. Use Chrome, Firefox ou Edge.'
            });
            return;
        }
        
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(stream => {
                this.stream = stream;
                this.audioChunks = [];
                
                // Configura MediaRecorder com opções para melhor compatibilidade
                const options = {
                    mimeType: 'audio/webm;codecs=opus'
                };
                
                // Tenta outros formatos se webm não for suportado
                if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                    options.mimeType = 'audio/webm';
                }
                if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                    options.mimeType = 'audio/mp4';
                }
                
                this.mediaRecorder = new MediaRecorder(stream, options);
                
                this.mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        this.audioChunks.push(event.data);
                    }
                };
                
                this.mediaRecorder.onstop = () => {
                    this.processarGravacao();
                };
                
                this.mediaRecorder.onerror = (event) => {
                    console.error('Erro na gravação:', event);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao gravar áudio. Tente novamente.'
                    });
                    this.pararGravacao();
                };
                
                // Inicia gravação
                this.mediaRecorder.start();
                
                // Mostra interface de gravação
                this.mostrarInterfaceGravacao();
                
            })
            .catch(err => {
                console.error('Erro ao acessar microfone:', err);
                let mensagem = 'Erro ao acessar microfone.';
                
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    mensagem = 'Permissão de microfone negada. Por favor, permita o acesso ao microfone nas configurações do navegador.';
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    mensagem = 'Nenhum microfone encontrado. Verifique se há um microfone conectado.';
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: mensagem
                });
            });
    },
    
    /**
     * Mostra interface de gravação
     */
    mostrarInterfaceGravacao: function() {
        const tempoInicio = Date.now();
        let tempoDecorrido = 0;
        
        const atualizarTempo = () => {
            tempoDecorrido = Math.floor((Date.now() - tempoInicio) / 1000);
            const minutos = Math.floor(tempoDecorrido / 60);
            const segundos = tempoDecorrido % 60;
            return `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
        };
        
        Swal.fire({
            title: 'Gravando áudio...',
            html: `
                <div class="text-center">
                    <div class="mb-4">
                        <div class="spinner-border text-danger" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Gravando...</span>
                        </div>
                    </div>
                    <div class="fs-2 fw-bold text-danger mb-3" id="tempo-gravacao">00:00</div>
                    <p class="text-muted">Clique em "Parar e Enviar" para finalizar</p>
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button id="btn-parar-enviar" class="btn btn-danger">
                            <i class="ki-duotone ki-cross fs-2"></i>
                            Parar e Enviar
                        </button>
                        <button id="btn-cancelar-gravacao" class="btn btn-light">
                            <i class="ki-duotone ki-cross fs-2"></i>
                            Cancelar
                        </button>
                    </div>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                // Atualiza tempo a cada segundo
                const intervalo = setInterval(() => {
                    const tempoEl = document.getElementById('tempo-gravacao');
                    if (tempoEl) {
                        tempoEl.textContent = atualizarTempo();
                    } else {
                        clearInterval(intervalo);
                    }
                }, 1000);
                
                // Botão parar e enviar
                document.getElementById('btn-parar-enviar').addEventListener('click', () => {
                    clearInterval(intervalo);
                    this.pararGravacao();
                });
                
                // Botão cancelar
                document.getElementById('btn-cancelar-gravacao').addEventListener('click', () => {
                    clearInterval(intervalo);
                    this.cancelarGravacao();
                });
            }
        });
    },
    
    /**
     * Para gravação e processa
     */
    pararGravacao: function() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
        
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        Swal.close();
    },
    
    /**
     * Cancela gravação sem salvar
     */
    cancelarGravacao: function() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
        
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        this.audioChunks = [];
        Swal.close();
    },
    
    /**
     * Processa gravação e envia para servidor
     */
    processarGravacao: function() {
        if (this.audioChunks.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Nenhum áudio gravado.'
            });
            return;
        }
        
        // Cria blob do áudio
        // Detecta o tipo MIME correto baseado no que foi gravado
        let mimeType = 'audio/webm';
        if (this.mediaRecorder && this.mediaRecorder.mimeType) {
            mimeType = this.mediaRecorder.mimeType;
        }
        
        const audioBlob = new Blob(this.audioChunks, { type: mimeType });
        
        // Mostra loading
        Swal.fire({
            title: 'Enviando áudio...',
            html: 'Por favor, aguarde.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Cria FormData
        const formData = new FormData();
        formData.append('conversa_id', this.conversaId);
        
        // Determina extensão baseada no MIME type
        let extensao = 'webm';
        if (mimeType.includes('ogg')) {
            extensao = 'ogg';
        } else if (mimeType.includes('mp4') || mimeType.includes('m4a')) {
            extensao = 'm4a';
        } else if (mimeType.includes('wav')) {
            extensao = 'wav';
        }
        
        formData.append('voz', audioBlob, `gravacao.${extensao}`);
        
        // Envia para servidor
        fetch('../api/chat/mensagens/enviar.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Resposta não é JSON:', text);
                throw new Error('Erro ao processar resposta do servidor. Verifique o console para mais detalhes.');
            }
            
            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Erro ao enviar áudio');
            }
            
            return data;
        })
        .then(data => {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Mensagem de voz enviada com sucesso!',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Aguarda um pouco e verifica novas mensagens em vez de recarregar tudo
            setTimeout(() => {
                // Tenta ChatGestao (RH) primeiro
                if (typeof ChatGestao !== 'undefined' && ChatGestao.verificarNovasMensagens) {
                    ChatGestao.verificarNovasMensagens();
                } else if (typeof ChatGestao !== 'undefined' && ChatGestao.carregarMensagens) {
                    ChatGestao.carregarMensagens();
                } 
                // Tenta ChatConversa (Colaborador)
                else if (typeof ChatConversa !== 'undefined' && ChatConversa.verificarNovasMensagens) {
                    ChatConversa.verificarNovasMensagens();
                } else {
                    // Último recurso: recarrega página
                    location.reload();
                }
            }, 1000);
        })
        .catch(error => {
            console.error('Erro ao enviar áudio:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: error.message || 'Erro ao enviar mensagem de voz. Tente novamente.'
            });
        })
        .finally(() => {
            this.audioChunks = [];
            this.conversaId = null;
        });
    }
};

// Função global para compatibilidade
window.iniciarGravacaoVoz = function(conversaId) {
    // Tenta obter conversa_id do formulário se não fornecido
    if (!conversaId) {
        const form = document.getElementById('chat-form-resposta');
        if (form) {
            const input = form.querySelector('input[name="conversa_id"]');
            if (input) {
                conversaId = input.value;
            }
        }
    }
    
    if (!conversaId) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'ID da conversa não encontrado.'
        });
        return;
    }
    
    ChatAudio.iniciarGravacao(conversaId);
};

