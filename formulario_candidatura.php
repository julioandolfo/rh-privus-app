<?php
/**
 * Formulário de Candidatura (Incluído nas páginas)
 */
$vaga_id = $vaga_id_form ?? ($vaga_id ?? ($_GET['id'] ?? 0));
?>
<form id="formCandidatura" class="row g-3" enctype="multipart/form-data">
    <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
    <div class="col-md-6">
        <label class="form-label">Nome Completo *</label>
        <input type="text" name="nome_completo" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Telefone</label>
        <input type="tel" name="telefone" class="form-control" placeholder="(00) 00000-0000">
    </div>
    <div class="col-md-6">
        <label class="form-label">LinkedIn</label>
        <input type="url" name="linkedin" class="form-control" placeholder="https://linkedin.com/in/seu-perfil">
    </div>
    <div class="col-md-6">
        <label class="form-label">Portfolio/Site</label>
        <input type="url" name="portfolio" class="form-control" placeholder="https://seuportfolio.com">
    </div>
    <div class="col-12">
        <label class="form-label">Currículo (PDF, DOC, DOCX) *</label>
        <input type="file" name="curriculo" class="form-control" accept=".pdf,.doc,.docx" required>
        <small class="form-text text-muted">Tamanho máximo: 10MB</small>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-send"></i> Enviar Candidatura
        </button>
    </div>
</form>

<script>
document.getElementById('formCandidatura')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    
    try {
        const response = await fetch('<?= get_base_url() ?>/api/recrutamento/candidaturas/criar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Candidatura enviada com sucesso!');
            if (data.link_acompanhamento) {
                if (confirm('Deseja acompanhar o status da sua candidatura?')) {
                    window.location.href = data.link_acompanhamento;
                }
            }
            this.reset();
        } else {
            alert('❌ Erro: ' + data.message);
        }
    } catch (error) {
        alert('❌ Erro ao enviar candidatura. Tente novamente.');
        console.error(error);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send"></i> Enviar Candidatura';
    }
});
</script>

