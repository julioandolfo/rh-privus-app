<?php
/**
 * Tab: Meus Cursos - Meu Perfil
 */
?>

<div class="row mb-5">
    <!-- Estatísticas de Cursos -->
    <div class="col-md-3">
        <div class="card bg-light-primary">
            <div class="card-body text-center">
                <i class="ki-duotone ki-book fs-3x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="fw-bold fs-2 text-primary"><?= (int)($lms_stats['cursos_concluidos'] ?? 0) ?></div>
                <div class="text-gray-700">Cursos Concluídos</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-light-warning">
            <div class="card-body text-center">
                <i class="ki-duotone ki-chart-line-up fs-3x text-warning mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="fw-bold fs-2 text-warning"><?= (int)($lms_stats['cursos_em_andamento'] ?? 0) ?></div>
                <div class="text-gray-700">Em Andamento</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-light-success">
            <div class="card-body text-center">
                <i class="ki-duotone ki-award fs-3x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="fw-bold fs-2 text-success"><?= (int)($lms_stats['total_certificados'] ?? 0) ?></div>
                <div class="text-gray-700">Certificados</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-light-info">
            <div class="card-body text-center">
                <i class="ki-duotone ki-time fs-3x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <?php
                $tempo_total_horas = floor(($lms_stats['tempo_total_assistido_segundos'] ?? 0) / 3600);
                ?>
                <div class="fw-bold fs-2 text-info"><?= $tempo_total_horas ?>h</div>
                <div class="text-gray-700">Tempo Estudado</div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Cursos em Andamento -->
<div class="card">
    <div class="card-header border-0 pt-6">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">Cursos em Andamento</span>
            <span class="text-muted fw-semibold fs-7">Continue seus estudos</span>
        </h3>
        <div class="card-toolbar">
            <a href="lms_meus_cursos.php" class="btn btn-sm btn-primary">
                Ver Todos os Cursos
                <i class="ki-duotone ki-arrow-right fs-3 ms-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($cursos_progresso)): ?>
            <div class="text-center py-10">
                <i class="ki-duotone ki-book-open fs-5x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="fw-bold fs-4 text-gray-700">Nenhum curso em andamento</div>
                <div class="text-muted mb-5">Comece um novo curso agora!</div>
                <a href="lms_meus_cursos.php" class="btn btn-primary">
                    <i class="ki-duotone ki-book fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    Ver Cursos Disponíveis
                </a>
            </div>
        <?php else: ?>
            <div class="row g-5">
                <?php foreach ($cursos_progresso as $curso): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border border-dashed border-gray-300 hover-elevate-up">
                        <?php if (!empty($curso['imagem_capa'])): ?>
                            <img src="../<?= htmlspecialchars($curso['imagem_capa']) ?>" class="card-img-top" alt="<?= htmlspecialchars($curso['titulo']) ?>" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top bg-light-primary d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="ki-duotone ki-book fs-5x text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <div class="mb-3">
                                <h3 class="fw-bold text-gray-900 mb-2">
                                    <?= htmlspecialchars($curso['titulo']) ?>
                                </h3>
                                <?php if (!empty($curso['categoria_nome'])): ?>
                                    <span class="badge" style="background-color: <?= htmlspecialchars($curso['categoria_cor'] ?? '#667eea') ?>;">
                                        <?= htmlspecialchars($curso['categoria_nome']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php
                            $total_aulas = (int)($curso['total_aulas'] ?? 0);
                            $aulas_concluidas = (int)($curso['aulas_concluidas'] ?? 0);
                            $percentual = $total_aulas > 0 ? ($aulas_concluidas / $total_aulas) * 100 : 0;
                            $status_curso = $curso['status_curso'] ?? 'nao_iniciado';
                            ?>

                            <div class="mb-5">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted fs-7">Progresso</span>
                                    <span class="fw-bold fs-7"><?= $aulas_concluidas ?> de <?= $total_aulas ?> aulas</span>
                                </div>
                                <div class="progress h-8px bg-light-primary">
                                    <div class="progress-bar bg-primary" role="progressbar" 
                                         style="width: <?= $percentual ?>%" 
                                         aria-valuenow="<?= $percentual ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <div class="text-center mt-1">
                                    <span class="fw-bold text-primary"><?= number_format($percentual, 1) ?>%</span>
                                </div>
                            </div>

                            <div class="text-center">
                                <?php if ($status_curso === 'concluido'): ?>
                                    <span class="badge badge-success fs-7">
                                        <i class="ki-duotone ki-check-circle fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Concluído
                                    </span>
                                <?php else: ?>
                                    <a href="lms_curso.php?id=<?= $curso['id'] ?>" class="btn btn-sm btn-primary w-100">
                                        <i class="ki-duotone ki-play-circle fs-3 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Continuar Curso
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
