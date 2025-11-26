<?php
/**
 * Criar/Editar Avaliação
 */

$page_title = 'Nova Avaliação';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('lms_avaliacao_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$avaliacao_id = (int)($_GET['id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

$avaliacao = null;
if ($avaliacao_id) {
    $stmt = $pdo->prepare("SELECT * FROM avaliacoes WHERE id = ?");
    $stmt->execute([$avaliacao_id]);
    $avaliacao = $stmt->fetch();
    if ($avaliacao) {
        $curso_id = $avaliacao['curso_id'];
        $page_title = 'Editar Avaliação';
    }
}

if (!$curso_id) {
    redirect('lms_avaliacoes.php', 'Curso não encontrado', 'error');
}

// Busca curso
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    redirect('lms_avaliacoes.php', 'Curso não encontrado', 'error');
}

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descricao = sanitize($_POST['descricao'] ?? '');
    $tipo = $_POST['tipo'] ?? 'quiz';
    $pontuacao_minima = !empty($_POST['pontuacao_minima']) ? (float)$_POST['pontuacao_minima'] : 70.00;
    $tentativas_maximas = !empty($_POST['tentativas_maximas']) ? (int)$_POST['tentativas_maximas'] : 3;
    $obrigatoria = isset($_POST['obrigatoria']) && $_POST['obrigatoria'] == '1' ? 1 : 0;
    $aula_id = !empty($_POST['aula_id']) ? (int)$_POST['aula_id'] : null;
    
    // Processa questões
    $questoes = [];
    if (isset($_POST['questoes']) && is_array($_POST['questoes'])) {
        foreach ($_POST['questoes'] as $q) {
            if (!empty($q['pergunta'])) {
                $questoes[] = [
                    'pergunta' => sanitize($q['pergunta']),
                    'tipo' => $q['tipo'] ?? 'multipla_escolha',
                    'opcoes' => isset($q['opcoes']) ? array_map('sanitize', $q['opcoes']) : [],
                    'resposta_correta' => $q['resposta_correta'] ?? null,
                    'pontos' => !empty($q['pontos']) ? (float)$q['pontos'] : 1.0
                ];
            }
        }
    }
    
    if (empty($titulo)) {
        redirect('lms_avaliacao_add.php?curso_id=' . $curso_id, 'Preencha o título da avaliação!', 'error');
    }
    
    if (empty($questoes)) {
        redirect('lms_avaliacao_add.php?curso_id=' . $curso_id, 'Adicione pelo menos uma questão!', 'error');
    }
    
    $configuracao = json_encode([
        'questoes' => $questoes
    ]);
    
    try {
        if ($avaliacao_id && $avaliacao) {
            // Editar
            $stmt = $pdo->prepare("
                UPDATE avaliacoes 
                SET titulo = ?, descricao = ?, tipo = ?, pontuacao_minima = ?, 
                    tentativas_maximas = ?, obrigatoria = ?, aula_id = ?, configuracao = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $titulo, $descricao ?: null, $tipo, $pontuacao_minima, 
                $tentativas_maximas, $obrigatoria, $aula_id, $configuracao, $avaliacao_id
            ]);
            redirect('lms_avaliacoes.php?curso_id=' . $curso_id, 'Avaliação atualizada com sucesso!', 'success');
        } else {
            // Criar
            $stmt = $pdo->prepare("
                INSERT INTO avaliacoes 
                (curso_id, aula_id, titulo, descricao, tipo, pontuacao_minima, tentativas_maximas, obrigatoria, configuracao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $curso_id, $aula_id, $titulo, $descricao ?: null, $tipo, 
                $pontuacao_minima, $tentativas_maximas, $obrigatoria, $configuracao
            ]);
            redirect('lms_avaliacoes.php?curso_id=' . $curso_id, 'Avaliação criada com sucesso!', 'success');
        }
    } catch (PDOException $e) {
        error_log("Erro ao salvar avaliação: " . $e->getMessage());
        redirect('lms_avaliacao_add.php?curso_id=' . $curso_id, 'Erro ao salvar avaliação.', 'error');
    }
}

// Busca aulas do curso
$stmt = $pdo->prepare("SELECT id, titulo FROM aulas WHERE curso_id = ? ORDER BY ordem ASC");
$stmt->execute([$curso_id]);
$aulas = $stmt->fetchAll();

// Carrega dados da avaliação se estiver editando
$config = [];
if ($avaliacao && $avaliacao['configuracao']) {
    $config = json_decode($avaliacao['configuracao'], true) ?: [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0"><?= $avaliacao_id ? 'Editar' : 'Nova' ?> Avaliação</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="lms_cursos.php" class="text-muted text-hover-primary">Escola Privus</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900"><?= $avaliacao_id ? 'Editar' : 'Nova' ?> Avaliação</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
            <a href="lms_avaliacoes.php?curso_id=<?= $curso_id ?>" class="btn btn-light">Voltar</a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="card">
            <div class="card-body pt-0">
                <form method="POST" class="row g-5" id="formAvaliacao">
                    
                    <div class="col-md-12">
                        <label class="form-label">Título da Avaliação *</label>
                        <input type="text" name="titulo" class="form-control" required value="<?= htmlspecialchars($avaliacao['titulo'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($avaliacao['descricao'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="quiz" <?= ($avaliacao['tipo'] ?? 'quiz') == 'quiz' ? 'selected' : '' ?>>Quiz</option>
                            <option value="questionario" <?= ($avaliacao['tipo'] ?? '') == 'questionario' ? 'selected' : '' ?>>Questionário</option>
                            <option value="projeto" <?= ($avaliacao['tipo'] ?? '') == 'projeto' ? 'selected' : '' ?>>Projeto</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Pontuação Mínima (%)</label>
                        <input type="number" name="pontuacao_minima" class="form-control" min="0" max="100" step="0.1" value="<?= $avaliacao['pontuacao_minima'] ?? 70 ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Tentativas Máximas</label>
                        <input type="number" name="tentativas_maximas" class="form-control" min="1" value="<?= $avaliacao['tentativas_maximas'] ?? 3 ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Aula Específica (opcional)</label>
                        <select name="aula_id" class="form-select">
                            <option value="">Avaliação do curso completo</option>
                            <?php foreach ($aulas as $aula): ?>
                            <option value="<?= $aula['id'] ?>" <?= ($avaliacao['aula_id'] ?? null) == $aula['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($aula['titulo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Obrigatória</label>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="obrigatoria" value="1" id="obrigatoriaCheck" <?= ($avaliacao['obrigatoria'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="obrigatoriaCheck">Avaliação obrigatória</label>
                        </div>
                    </div>
                    
                    <!-- Questões -->
                    <div class="col-12">
                        <hr class="my-5">
                        <h3 class="mb-4">Questões</h3>
                        <div id="questoesContainer">
                            <!-- Questões serão adicionadas aqui via JavaScript -->
                        </div>
                        <button type="button" class="btn btn-light-primary" onclick="adicionarQuestao()">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Adicionar Questão
                        </button>
                    </div>
                    
                    <div class="col-12">
                        <hr class="my-5">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="lms_avaliacoes.php?curso_id=<?= $curso_id ?>" class="btn btn-light">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar Avaliação</button>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
let questaoIndex = 0;
const questoesExistentes = <?= json_encode($config['questoes'] ?? []) ?>;

function adicionarQuestao(questao = null) {
    const container = document.getElementById('questoesContainer');
    const index = questaoIndex++;
    
    const questaoDiv = document.createElement('div');
    questaoDiv.className = 'card mb-5';
    questaoDiv.id = `questao_${index}`;
    questaoDiv.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Questão ${index + 1}</h5>
                <button type="button" class="btn btn-sm btn-danger" onclick="removerQuestao(${index})">
                    <i class="ki-duotone ki-trash fs-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </button>
            </div>
            <div class="mb-3">
                <label class="form-label">Pergunta *</label>
                <input type="text" name="questoes[${index}][pergunta]" class="form-control" required value="${questao?.pergunta || ''}">
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Tipo</label>
                    <select name="questoes[${index}][tipo]" class="form-select" onchange="atualizarTipoQuestao(${index}, this.value)">
                        <option value="multipla_escolha" ${questao?.tipo === 'multipla_escolha' ? 'selected' : ''}>Múltipla Escolha</option>
                        <option value="verdadeiro_falso" ${questao?.tipo === 'verdadeiro_falso' ? 'selected' : ''}>Verdadeiro/Falso</option>
                        <option value="texto" ${questao?.tipo === 'texto' ? 'selected' : ''}>Texto Livre</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pontos</label>
                    <input type="number" name="questoes[${index}][pontos]" class="form-control" min="0" step="0.1" value="${questao?.pontos || 1}">
                </div>
            </div>
            <div id="opcoes_${index}">
                ${gerarOpcoes(index, questao)}
            </div>
        </div>
    `;
    container.appendChild(questaoDiv);
}

function gerarOpcoes(index, questao) {
    if (questao?.tipo === 'verdadeiro_falso') {
        return `
            <div class="mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="questoes[${index}][resposta_correta]" value="verdadeiro" ${questao?.resposta_correta === 'verdadeiro' ? 'checked' : ''}>
                    <label class="form-check-label">Verdadeiro</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="questoes[${index}][resposta_correta]" value="falso" ${questao?.resposta_correta === 'falso' ? 'checked' : ''}>
                    <label class="form-check-label">Falso</label>
                </div>
            </div>
        `;
    } else if (questao?.tipo === 'texto') {
        return '<div class="text-muted">Resposta em texto livre</div>';
    } else {
        let html = '<label class="form-label">Opções de Resposta</label>';
        const opcoes = questao?.opcoes || ['', '', '', ''];
        opcoes.forEach((opcao, i) => {
            html += `
                <div class="input-group mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="questoes[${index}][resposta_correta]" value="${i}" ${questao?.resposta_correta == i ? 'checked' : ''}>
                    </div>
                    <input type="text" name="questoes[${index}][opcoes][]" class="form-control" placeholder="Opção ${i + 1}" value="${opcao}">
                </div>
            `;
        });
        return html;
    }
}

function atualizarTipoQuestao(index, tipo) {
    const opcoesDiv = document.getElementById(`opcoes_${index}`);
    const questao = questoesExistentes[index] || {};
    questao.tipo = tipo;
    opcoesDiv.innerHTML = gerarOpcoes(index, questao);
}

function removerQuestao(index) {
    document.getElementById(`questao_${index}`).remove();
}

// Carrega questões existentes ao editar
if (questoesExistentes.length > 0) {
    questoesExistentes.forEach((q, i) => {
        questaoIndex = i;
        adicionarQuestao(q);
    });
} else {
    // Adiciona uma questão vazia por padrão
    adicionarQuestao();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

