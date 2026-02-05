/**
 * Gerador de Vagas com IA
 * Sistema completo de geração e refinamento de vagas usando OpenAI
 */

class VagaIAGenerator {
    constructor() {
        this.historico_id = null;
        this.templates = [];
        this.init();
    }

    async init() {
        await this.carregarTemplates();
        await this.verificarLimite();
        this.setupEventListeners();
    }

    async carregarTemplates() {
        try {
            const response = await fetch('../api/vagas/gerar_com_ia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ acao: 'listar_templates' })
            });

            const data = await response.json();

            if (data.success) {
                this.templates = data.templates;
                this.popularSelectTemplates();
            }
        } catch (error) {
            console.error('Erro ao carregar templates:', error);
        }
    }

    popularSelectTemplates() {
        const select = document.getElementById('ia_template_select');
        if (!select) return;

        select.innerHTML = '<option value="">Selecione um tipo...</option>';

        this.templates.forEach(template => {
            const option = document.createElement('option');
            option.value = template.codigo;
            option.textContent = template.nome;
            option.setAttribute('data-descricao', template.descricao);
            option.setAttribute('data-exemplo', template.exemplo);
            select.appendChild(option);
        });
    }

    async verificarLimite() {
        try {
            const response = await fetch('../api/vagas/gerar_com_ia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ acao: 'verificar_limite' })
            });

            const data = await response.json();

            if (data.success) {
                this.atualizarInfoLimite(data);
            }
        } catch (error) {
            console.error('Erro ao verificar limite:', error);
        }
    }

    atualizarInfoLimite(limiteData) {
        const limiteInfo = document.getElementById('ia_limite_info');
        if (!limiteInfo) return;

        limiteInfo.innerHTML = `
            <small class="text-muted">
                <i class="ki-duotone ki-information-5 fs-6 me-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Gerações hoje: ${limiteData.usado}/${limiteData.limite} 
                (${limiteData.restante} restantes)
            </small>
        `;

        if (!limiteData.pode_gerar) {
            const btnGerar = document.getElementById('btnGerarIA');
            if (btnGerar) {
                btnGerar.disabled = true;
                btnGerar.innerHTML = '<i class="ki-duotone ki-cross-circle fs-2"><span class="path1"></span><span class="path2"></span></i> Limite Diário Atingido';
            }
        }
    }

    setupEventListeners() {
        // Botão gerar
        const btnGerar = document.getElementById('btnGerarIA');
        if (btnGerar) {
            btnGerar.addEventListener('click', () => this.gerarVaga());
        }

        // Mostrar exemplo ao selecionar template
        const selectTemplate = document.getElementById('ia_template_select');
        if (selectTemplate) {
            selectTemplate.addEventListener('change', (e) => {
                const option = e.target.selectedOptions[0];
                const exemplo = option.getAttribute('data-exemplo');
                const infoTemplate = document.getElementById('ia_template_info');

                if (exemplo && infoTemplate) {
                    infoTemplate.innerHTML = `
                        <div class="alert alert-info d-flex align-items-center p-3 mt-3">
                            <i class="ki-duotone ki-information-5 fs-2 me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div>
                                <strong>Exemplo:</strong> ${exemplo}
                            </div>
                        </div>
                    `;
                } else {
                    infoTemplate.innerHTML = '';
                }
            });
        }

        // Botões de refinamento
        document.querySelectorAll('.btn-refinar-ia').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const acao = e.currentTarget.getAttribute('data-acao');
                this.refinarVaga(acao);
            });
        });

        // Botão refinar customizado
        const btnRefinarCustom = document.getElementById('btnRefinarCustom');
        if (btnRefinarCustom) {
            btnRefinarCustom.addEventListener('click', () => {
                const instrucao = document.getElementById('ia_instrucao_refinamento').value;
                if (instrucao.trim()) {
                    this.refinarVaga(instrucao);
                }
            });
        }
    }

    async gerarVaga() {
        const empresaId = document.getElementById('empresaSelect')?.value;
        const descricao = document.getElementById('ia_descricao_input')?.value;
        const template = document.getElementById('ia_template_select')?.value || 'vaga_generica';

        // Validações
        if (!empresaId) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'Selecione uma empresa primeiro!',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
            return;
        }

        if (!descricao || descricao.trim().length < 20) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'Descreva a vaga com pelo menos 20 caracteres!',
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
            return;
        }

        const btnGerar = document.getElementById('btnGerarIA');
        const originalHtml = btnGerar.innerHTML;
        btnGerar.disabled = true;
        btnGerar.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2"></span>
            Gerando com IA...
        `;

        // Mostra modal de loading
        this.mostrarModalLoading();

        try {
            const response = await fetch('../api/vagas/gerar_com_ia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    acao: 'gerar',
                    descricao: descricao,
                    empresa_id: empresaId,
                    template: template
                })
            });

            const data = await response.json();

            // Fecha modal de loading
            this.fecharModalLoading();

            if (data.success) {
                this.preencherFormulario(data.data);
                this.mostrarResultadoGeracao(data.meta);
                this.mostrarBotoesRefinamento();

                // Atualiza limite
                await this.verificarLimite();

                Swal.fire({
                    icon: 'success',
                    title: 'Vaga Gerada!',
                    html: `
                        <p>A vaga foi gerada com sucesso!</p>
                        <div class="mt-3">
                            <strong>Qualidade:</strong> 
                            <span class="badge badge-${this.getQualidadeBadge(data.meta.qualidade_score)}">
                                ${data.meta.qualidade_score}/100
                            </span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                Revise os campos e edite conforme necessário antes de salvar.
                            </small>
                        </div>
                    `,
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });

                // Scroll para o formulário
                document.querySelector('#formVaga').scrollIntoView({ behavior: 'smooth', block: 'start' });

            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao Gerar',
                    text: data.message,
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }

        } catch (error) {
            this.fecharModalLoading();
            console.error('Erro:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao gerar vaga: ' + error.message,
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        } finally {
            btnGerar.disabled = false;
            btnGerar.innerHTML = originalHtml;
        }
    }

    preencherFormulario(vagaData) {
        // Remove indicadores de confiança antes de preencher
        const confianca = vagaData._confianca || {};
        delete vagaData._confianca;

        // Título
        if (vagaData.titulo) {
            this.preencherCampo('titulo', vagaData.titulo, confianca.titulo_confianca);
        }

        // Descrição
        if (vagaData.descricao) {
            this.preencherCampo('descricao', vagaData.descricao, confianca.descricao_confianca);
        }

        // Salários
        if (vagaData.salario_min) {
            const salarioMinFormatado = this.formatarMoeda(vagaData.salario_min);
            this.preencherCampo('salario_min', salarioMinFormatado, confianca.salario_min_confianca);
        }

        if (vagaData.salario_max) {
            const salarioMaxFormatado = this.formatarMoeda(vagaData.salario_max);
            this.preencherCampo('salario_max', salarioMaxFormatado, confianca.salario_max_confianca);
        }

        // Tipo de contrato
        if (vagaData.tipo_contrato) {
            this.preencherCampo('tipo_contrato', vagaData.tipo_contrato, confianca.tipo_contrato_confianca);
        }

        // Benefícios
        if (vagaData.beneficios && Array.isArray(vagaData.beneficios)) {
            this.preencherBeneficios(vagaData.beneficios);
        }

        // Requisitos
        if (vagaData.requisitos_obrigatorios) {
            this.preencherCampo('requisitos_obrigatorios', vagaData.requisitos_obrigatorios, confianca.requisitos_obrigatorios_confianca);
        }

        if (vagaData.requisitos_desejaveis) {
            this.preencherCampo('requisitos_desejaveis', vagaData.requisitos_desejaveis, confianca.requisitos_desejaveis_confianca);
        }

        // Competências
        if (vagaData.competencias_tecnicas) {
            this.preencherCampo('competencias_tecnicas', vagaData.competencias_tecnicas, confianca.competencias_tecnicas_confianca);
        }

        if (vagaData.competencias_comportamentais) {
            this.preencherCampo('competencias_comportamentais', vagaData.competencias_comportamentais, confianca.competencias_comportamentais_confianca);
        }

        // Modalidade
        if (vagaData.modalidade) {
            this.preencherCampo('modalidade', vagaData.modalidade, confianca.modalidade_confianca);
        }

        // Localização
        if (vagaData.localizacao) {
            this.preencherCampo('localizacao', vagaData.localizacao, confianca.localizacao_confianca);
        }

        // Horário
        if (vagaData.horario_trabalho) {
            this.preencherCampo('horario_trabalho', vagaData.horario_trabalho, confianca.horario_trabalho_confianca);
        }

        // Dias
        if (vagaData.dias_trabalho) {
            this.preencherCampo('dias_trabalho', vagaData.dias_trabalho, confianca.dias_trabalho_confianca);
        }
    }

    preencherCampo(nome, valor, confianca = 'medium') {
        const campo = document.querySelector(`[name="${nome}"]`);
        if (!campo) return;

        campo.value = valor;

        // Adiciona badge de confiança
        this.adicionarBadgeConfianca(campo, confianca);

        // Trigger change event para campos que precisam
        campo.dispatchEvent(new Event('change', { bubbles: true }));
    }

    preencherBeneficios(beneficios) {
        // Desmarca todos primeiro
        document.querySelectorAll('input[name="beneficios[]"]').forEach(cb => {
            cb.checked = false;
        });

        beneficios.forEach(beneficio => {
            // Procura checkbox existente
            let encontrado = false;
            document.querySelectorAll('input[name="beneficios[]"]').forEach(cb => {
                if (cb.value === beneficio) {
                    cb.checked = true;
                    encontrado = true;
                }
            });

            // Se não encontrou, adiciona como customizado
            if (!encontrado) {
                const container = document.querySelector('.row:has(input[name="beneficios[]"])');
                if (container) {
                    const col = document.createElement('div');
                    col.className = 'col-md-3 mb-2';
                    col.innerHTML = `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="beneficios[]" 
                                   value="${beneficio}" checked>
                            <label class="form-check-label">${beneficio}</label>
                        </div>
                    `;
                    container.appendChild(col);
                }
            }
        });
    }

    adicionarBadgeConfianca(campo, confianca) {
        // Remove badge anterior se existir
        const badgeAnterior = campo.parentElement.querySelector('.badge-confianca-ia');
        if (badgeAnterior) {
            badgeAnterior.remove();
        }

        const badges = {
            'high': { class: 'success', icon: 'check-circle', texto: 'Alta Confiança' },
            'medium': { class: 'warning', icon: 'information-5', texto: 'Revisar' },
            'low': { class: 'danger', icon: 'cross-circle', texto: 'Baixa Confiança' }
        };

        const badgeConfig = badges[confianca] || badges['medium'];

        const badge = document.createElement('span');
        badge.className = `badge badge-${badgeConfig.class} badge-confianca-ia ms-2`;
        badge.innerHTML = `
            <i class="ki-duotone ki-${badgeConfig.icon} fs-6 me-1">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            ${badgeConfig.texto}
        `;

        // Adiciona após o campo
        if (campo.parentElement.querySelector('label')) {
            campo.parentElement.querySelector('label').appendChild(badge);
        }
    }

    formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(valor);
    }

    getQualidadeBadge(score) {
        if (score >= 80) return 'success';
        if (score >= 60) return 'warning';
        return 'danger';
    }

    mostrarResultadoGeracao(meta) {
        const resultadoDiv = document.getElementById('ia_resultado_geracao');
        if (!resultadoDiv) return;

        resultadoDiv.innerHTML = `
            <div class="alert alert-info d-flex align-items-center p-5 mt-5">
                <i class="ki-duotone ki-robot fs-2hx text-info me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="flex-grow-1">
                    <h4 class="mb-1 text-info">Vaga Gerada com IA</h4>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Qualidade</small>
                            <span class="badge badge-${this.getQualidadeBadge(meta.qualidade_score)} fs-6">
                                ${meta.qualidade_score}/100
                            </span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Modelo</small>
                            <span class="fw-bold">${meta.modelo_usado}</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Tempo</small>
                            <span class="fw-bold">${(meta.tempo_geracao_ms / 1000).toFixed(1)}s</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Custo</small>
                            <span class="fw-bold">$${meta.custo_estimado.toFixed(4)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    mostrarBotoesRefinamento() {
        const botoesDiv = document.getElementById('ia_botoes_refinamento');
        if (!botoesDiv) return;

        botoesDiv.classList.remove('d-none');
    }

    async refinarVaga(instrucao) {
        // Coleta dados atuais do formulário
        const vagaAtual = this.coletarDadosFormulario();
        const empresaId = document.getElementById('empresaSelect')?.value;

        const btnRefinar = event.target;
        const originalHtml = btnRefinar.innerHTML;
        btnRefinar.disabled = true;
        btnRefinar.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const response = await fetch('../api/vagas/gerar_com_ia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    acao: 'refinar',
                    vaga_atual: vagaAtual,
                    instrucao: instrucao,
                    empresa_id: empresaId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.preencherFormulario(data.data);

                Swal.fire({
                    icon: 'success',
                    title: 'Vaga Refinada!',
                    text: 'A vaga foi refinada com sucesso!',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao Refinar',
                    text: data.message,
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }

        } catch (error) {
            console.error('Erro:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao refinar vaga: ' + error.message,
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        } finally {
            btnRefinar.disabled = false;
            btnRefinar.innerHTML = originalHtml;
        }
    }

    coletarDadosFormulario() {
        return {
            titulo: document.querySelector('[name="titulo"]')?.value || '',
            descricao: document.querySelector('[name="descricao"]')?.value || '',
            salario_min: this.parseValorMoeda(document.querySelector('[name="salario_min"]')?.value),
            salario_max: this.parseValorMoeda(document.querySelector('[name="salario_max"]')?.value),
            tipo_contrato: document.querySelector('[name="tipo_contrato"]')?.value || '',
            beneficios: Array.from(document.querySelectorAll('input[name="beneficios[]"]:checked')).map(cb => cb.value),
            requisitos_obrigatorios: document.querySelector('[name="requisitos_obrigatorios"]')?.value || '',
            requisitos_desejaveis: document.querySelector('[name="requisitos_desejaveis"]')?.value || '',
            competencias_tecnicas: document.querySelector('[name="competencias_tecnicas"]')?.value || '',
            competencias_comportamentais: document.querySelector('[name="competencias_comportamentais"]')?.value || '',
            modalidade: document.querySelector('[name="modalidade"]')?.value || '',
            localizacao: document.querySelector('[name="localizacao"]')?.value || '',
            horario_trabalho: document.querySelector('[name="horario_trabalho"]')?.value || '',
            dias_trabalho: document.querySelector('[name="dias_trabalho"]')?.value || ''
        };
    }

    parseValorMoeda(valor) {
        if (!valor) return null;
        return parseFloat(valor.replace(/\./g, '').replace(',', '.')) || null;
    }

    mostrarModalLoading() {
        const modalHtml = `
            <div class="modal fade" id="modalLoadingIA" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body text-center p-10">
                            <div class="spinner-border text-primary mb-5" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Gerando...</span>
                            </div>
                            <h3 class="mb-3">Gerando Vaga com IA...</h3>
                            <p class="text-muted">
                                A inteligência artificial está analisando sua descrição e criando uma vaga completa.
                                <br>Isso pode levar alguns segundos.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('modalLoadingIA'));
        modal.show();
    }

    fecharModalLoading() {
        const modalEl = document.getElementById('modalLoadingIA');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
            setTimeout(() => modalEl.remove(), 500);
        }
    }
}

// Inicializa quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ia_card_gerador')) {
        window.vagaIAGenerator = new VagaIAGenerator();
    }
});
