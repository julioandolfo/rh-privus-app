/**
 * Player Seguro do LMS - Sistema Anti-Fraude
 */

class SecureLMSPlayer {
    constructor(config) {
        this.progressoId = config.progressoId;
        this.sessaoId = config.sessaoId;
        this.aulaId = config.aulaId;
        this.cursoId = config.cursoId;
        this.tipoConteudo = config.tipoConteudo;
        this.duracaoTotal = config.duracaoTotal || 0;
        
        this.tempoAssistido = 0;
        this.tempoInativo = 0;
        this.interacoes = 0;
        this.eventos = [];
        this.ultimaPosicao = 0;
        this.ultimoEvento = null;
        this.visibilidadeAtiva = true;
        this.playerFocado = true;
        this.velocidadeAtual = 1.0;
        this.emReproducao = false;
        this.tempoUltimoPlay = null;
        
        this.intervaloSalvar = null;
        this.intervaloEventos = null;
        
        this.init();
    }
    
    init() {
        // Monitora visibilidade da página
        document.addEventListener('visibilitychange', () => {
            this.visibilidadeAtiva = !document.hidden;
            this.registrarEvento('visibilitychange', {
                hidden: document.hidden
            });
        });
        
        // Monitora foco da janela
        window.addEventListener('focus', () => {
            this.playerFocado = true;
            this.registrarEvento('focus');
        });
        
        window.addEventListener('blur', () => {
            this.playerFocado = false;
            this.registrarEvento('blur');
        });
        
        // Monitora interações
        document.addEventListener('click', () => {
            this.interacoes++;
            this.registrarEvento('interaction', { tipo: 'click' });
        });
        
        document.addEventListener('scroll', () => {
            this.interacoes++;
            this.registrarEvento('interaction', { tipo: 'scroll' });
        });
        
        // Inicializa player baseado no tipo
        switch (this.tipoConteudo) {
            case 'video_youtube':
                this.initYouTubePlayer();
                break;
            case 'video_upload':
                this.initVideoPlayer();
                break;
            case 'pdf':
                this.initPDFViewer();
                break;
            case 'texto':
                this.initTextoContent();
                break;
        }
        
        // Salva progresso a cada 10 segundos
        this.intervaloSalvar = setInterval(() => {
            this.salvarProgresso();
        }, 10000);
        
        // Registra eventos a cada 5 segundos durante reprodução
        this.intervaloEventos = setInterval(() => {
            if (this.emReproducao) {
                this.registrarTimeUpdate();
            }
        }, 5000);
    }
    
    initYouTubePlayer() {
        // YouTube API já carregada
        if (typeof YT !== 'undefined' && YT.Player) {
            this.ytPlayer = new YT.Player('youtube-player', {
                events: {
                    'onStateChange': (event) => {
                        this.handleYouTubeStateChange(event);
                    },
                    'onReady': () => {
                        this.ytPlayerReady = true;
                    }
                }
            });
        }
    }
    
    handleYouTubeStateChange(event) {
        const state = event.data;
        const posicao = this.ytPlayer ? this.ytPlayer.getCurrentTime() : 0;
        
        switch (state) {
            case YT.PlayerState.PLAYING:
                this.emReproducao = true;
                this.tempoUltimoPlay = Date.now();
                this.registrarEvento('play', posicao);
                break;
            case YT.PlayerState.PAUSED:
                this.emReproducao = false;
                if (this.tempoUltimoPlay) {
                    const tempoSessao = (Date.now() - this.tempoUltimoPlay) / 1000;
                    this.tempoAssistido += tempoSessao;
                }
                this.registrarEvento('pause', posicao);
                break;
            case YT.PlayerState.ENDED:
                this.emReproducao = false;
                if (this.tempoUltimoPlay) {
                    const tempoSessao = (Date.now() - this.tempoUltimoPlay) / 1000;
                    this.tempoAssistido += tempoSessao;
                }
                this.registrarEvento('ended', posicao);
                break;
        }
        
        this.ultimaPosicao = posicao;
    }
    
    initVideoPlayer() {
        const video = document.getElementById('video-player');
        if (!video) return;
        
        // Carrega última posição
        if (video.dataset.ultimaPosicao) {
            video.currentTime = parseFloat(video.dataset.ultimaPosicao);
        }
        
        video.addEventListener('play', () => {
            this.emReproducao = true;
            this.tempoUltimoPlay = Date.now();
            this.registrarEvento('play', video.currentTime);
        });
        
        video.addEventListener('pause', () => {
            this.emReproducao = false;
            if (this.tempoUltimoPlay) {
                const tempoSessao = (Date.now() - this.tempoUltimoPlay) / 1000;
                this.tempoAssistido += tempoSessao;
            }
            this.registrarEvento('pause', video.currentTime);
        });
        
        video.addEventListener('ended', () => {
            this.emReproducao = false;
            if (this.tempoUltimoPlay) {
                const tempoSessao = (Date.now() - this.tempoUltimoPlay) / 1000;
                this.tempoAssistido += tempoSessao;
            }
            this.registrarEvento('ended', video.duration);
        });
        
        video.addEventListener('timeupdate', () => {
            this.ultimaPosicao = video.currentTime;
        });
        
        video.addEventListener('seeked', (e) => {
            this.registrarEvento('seek', video.currentTime, {
                posicao_anterior: this.ultimaPosicao,
                posicao_nova: video.currentTime
            });
        });
        
        // Monitora velocidade de reprodução
        video.addEventListener('ratechange', () => {
            this.velocidadeAtual = video.playbackRate;
            this.registrarEvento('ratechange', video.currentTime, {
                velocidade: video.playbackRate
            });
        });
    }
    
    initPDFViewer() {
        const iframe = document.querySelector('#pdf-viewer iframe');
        if (!iframe) return;
        
        // Para PDF, monitora scroll e tempo na página
        let tempoInicio = Date.now();
        let ultimoScroll = 0;
        
        // Monitora scroll do iframe (se possível)
        window.addEventListener('scroll', () => {
            this.interacoes++;
            const agora = Date.now();
            if (agora - ultimoScroll > 2000) { // A cada 2 segundos
                this.registrarEvento('interaction', {
                    tipo: 'scroll',
                    tempo_na_pagina: (agora - tempoInicio) / 1000
                });
                ultimoScroll = agora;
            }
        });
        
        // Calcula tempo baseado em tempo na página e interações
        setInterval(() => {
            if (this.visibilidadeAtiva && this.playerFocado) {
                const tempoNaPagina = (Date.now() - tempoInicio) / 1000;
                // Estimativa: considera tempo na página como tempo assistido
                // (ajustar conforme necessário)
                this.tempoAssistido = Math.min(tempoNaPagina * 0.7, this.duracaoTotal);
            }
        }, 5000);
    }
    
    initTextoContent() {
        // Para conteúdo de texto, monitora preenchimento de campos e tempo na página
        let tempoInicio = Date.now();
        
        const form = document.getElementById('form-campos-personalizados');
        if (form) {
            form.addEventListener('input', () => {
                this.interacoes++;
                this.registrarEvento('interaction', {
                    tipo: 'input',
                    tempo_na_pagina: (Date.now() - tempoInicio) / 1000
                });
            });
        }
        
        // Calcula tempo baseado em tempo na página e interações
        setInterval(() => {
            if (this.visibilidadeAtiva && this.playerFocado) {
                const tempoNaPagina = (Date.now() - tempoInicio) / 1000;
                // Estimativa conservadora
                this.tempoAssistido = Math.min(tempoNaPagina * 0.6, this.duracaoTotal);
            }
        }, 5000);
    }
    
    registrarEvento(tipo, posicao = 0, dadosAdicionais = {}) {
        const evento = {
            tipo: tipo,
            posicao: posicao,
            timestamp: new Date().toISOString(),
            visibilidade: this.visibilidadeAtiva,
            foco: this.playerFocado,
            velocidade: this.velocidadeAtual,
            interacoes: this.interacoes,
            tempo_assistido: this.tempoAssistido,
            ...dadosAdicionais
        };
        
        this.eventos.push(evento);
        this.ultimoEvento = evento;
        
        // Envia evento para backend
        const baseUrl = window.location.origin + window.location.pathname.split('/pages')[0];
        fetch(baseUrl + '/api/lms/registrar_evento.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                sessao_id: this.sessaoId,
                progresso_id: this.progressoId,
                aula_id: this.aulaId,
                tipo_evento: tipo,
                posicao_video: posicao,
                dados_adicionais: JSON.stringify(dadosAdicionais)
            })
        }).catch(err => {
            console.error('Erro ao registrar evento:', err);
        });
    }
    
    registrarTimeUpdate() {
        let posicao = 0;
        
        if (this.tipoConteudo == 'video_youtube' && this.ytPlayer) {
            posicao = this.ytPlayer.getCurrentTime();
        } else if (this.tipoConteudo == 'video_upload') {
            const video = document.getElementById('video-player');
            if (video) posicao = video.currentTime;
        }
        
        this.registrarEvento('timeupdate', posicao);
    }
    
    salvarProgresso() {
        let posicao = 0;
        let percentual = 0;
        
        if (this.tipoConteudo == 'video_youtube' && this.ytPlayer) {
            posicao = this.ytPlayer.getCurrentTime();
            if (this.ytPlayer.getDuration() > 0) {
                percentual = (posicao / this.ytPlayer.getDuration()) * 100;
            }
        } else if (this.tipoConteudo == 'video_upload') {
            const video = document.getElementById('video-player');
            if (video) {
                posicao = video.currentTime;
                if (video.duration > 0) {
                    percentual = (posicao / video.duration) * 100;
                }
            }
        } else if (this.duracaoTotal > 0) {
            percentual = (this.tempoAssistido / this.duracaoTotal) * 100;
        }
        
        // Atualiza UI
        const barraProgresso = document.getElementById('barra-progresso');
        const percentualElement = document.getElementById('percentual-progresso');
        
        if (barraProgresso) {
            barraProgresso.style.width = percentual + '%';
        }
        if (percentualElement) {
            percentualElement.textContent = Math.round(percentual) + '%';
        }
        
        // Salva no backend
        const baseUrl = window.location.origin + window.location.pathname.split('/pages')[0];
        fetch(baseUrl + '/api/lms/salvar_progresso.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                progresso_id: this.progressoId,
                sessao_id: this.sessaoId,
                posicao: posicao,
                percentual: percentual
            })
        }).catch(err => {
            console.error('Erro ao salvar progresso:', err);
        });
    }
    
    async validarConclusao() {
        const btn = document.getElementById('btn-concluir-aula');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validando...';
        }
        
        try {
            const baseUrl = window.location.origin + window.location.pathname.split('/pages')[0];
            const response = await fetch(baseUrl + '/api/lms/validar_conclusao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    progresso_id: this.progressoId,
                    aula_id: this.aulaId,
                    curso_id: this.cursoId
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.pode_concluir) {
                // Sucesso
                Swal.fire({
                    icon: 'success',
                    title: 'Aula Concluída!',
                    text: 'Parabéns! Você concluiu esta aula.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Recarrega página ou avança para próxima
                    window.location.reload();
                });
            } else {
                // Não pode concluir
                Swal.fire({
                    icon: 'warning',
                    title: 'Não é possível concluir',
                    text: data.motivo || 'Você precisa assistir mais conteúdo para concluir esta aula.',
                    confirmButtonText: 'Entendi'
                });
                
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ki-duotone ki-check fs-2"><span class="path1"></span><span class="path2"></span></i> Marcar como Concluído';
                }
            }
        } catch (error) {
            console.error('Erro ao validar conclusão:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Ocorreu um erro ao validar a conclusão. Tente novamente.',
                confirmButtonText: 'OK'
            });
            
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ki-duotone ki-check fs-2"><span class="path1"></span><span class="path2"></span></i> Marcar como Concluído';
            }
        }
    }
    
    destroy() {
        if (this.intervaloSalvar) {
            clearInterval(this.intervaloSalvar);
        }
        if (this.intervaloEventos) {
            clearInterval(this.intervaloEventos);
        }
    }
}

// Inicializa player quando a página carregar
let lmsPlayer = null;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof PROGRESSO_ID !== 'undefined') {
        lmsPlayer = new SecureLMSPlayer({
            progressoId: PROGRESSO_ID,
            sessaoId: SESSAO_ID,
            aulaId: AULA_ID,
            cursoId: CURSO_ID,
            tipoConteudo: TIPO_CONTEUDO,
            duracaoTotal: DURACAO_TOTAL
        });
        
        // Botão de concluir
        const btnConcluir = document.getElementById('btn-concluir-aula');
        if (btnConcluir) {
            btnConcluir.addEventListener('click', () => {
                lmsPlayer.validarConclusao();
            });
        }
    }
});

// Limpa ao sair da página
window.addEventListener('beforeunload', () => {
    if (lmsPlayer) {
        lmsPlayer.salvarProgresso();
        lmsPlayer.destroy();
    }
});

