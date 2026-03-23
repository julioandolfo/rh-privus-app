<?php
/**
 * Criação automática do termo de distrato ao desligar colaborador (template padrão + Autentique).
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/contratos_functions.php';

if (!function_exists('log_contrato')) {
    function log_contrato($message) {
        $logFile = __DIR__ . '/../logs/contratos.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Garante colunas padrao_distrato (templates) e demissao_id (contratos).
 */
function garantir_schema_contratos_distrato(PDO $pdo) {
    static $feito = false;
    if ($feito) {
        return;
    }
    try {
        $r = $pdo->query("SHOW COLUMNS FROM contratos_templates LIKE 'padrao_distrato'");
        if ($r && !$r->fetch()) {
            $pdo->exec("ALTER TABLE contratos_templates ADD COLUMN padrao_distrato TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Template usado no distrato automático ao desligar' AFTER ativo");
        }
    } catch (Exception $e) {
        error_log('garantir_schema_contratos_distrato (templates): ' . $e->getMessage());
    }
    try {
        $r = $pdo->query("SHOW COLUMNS FROM contratos LIKE 'demissao_id'");
        if ($r && !$r->fetch()) {
            $pdo->exec("ALTER TABLE contratos ADD COLUMN demissao_id INT NULL COMMENT 'Demissão que originou distrato automático' AFTER colaborador_id");
        }
    } catch (Exception $e) {
        error_log('garantir_schema_contratos_distrato (contratos): ' . $e->getMessage());
    }
    $feito = true;
}

/**
 * Se não existir template marcado como padrão de distrato, insere um modelo editável.
 */
function seed_template_distrato_padrao_se_ausente(PDO $pdo) {
    garantir_schema_contratos_distrato($pdo);
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM contratos_templates WHERE padrao_distrato = 1")->fetchColumn();
        if ($n > 0) {
            return;
        }
    } catch (Exception $e) {
        return;
    }

    $uid = 1;
    try {
        $uid = (int)$pdo->query('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1')->fetchColumn();
    } catch (Exception $e) {
        // mantém 1
    }
    if ($uid < 1) {
        $uid = 1;
    }

    $html = <<<'HTML'
<h2 style="text-align:center;font-size:14pt;">DISTRATO AO CONTRATO DE {{colaborador.categoria_contrato_titulo}}</h2>
<p style="text-align:justify;line-height:1.45;">Pelo presente instrumento particular de distrato, as partes:</p>
<p style="text-align:justify;line-height:1.45;"><strong>CONTRATANTE:</strong> {{empresa.razao_social}}, pessoa jurídica de direito privado, inscrita no CNPJ/MF sob nº {{empresa.cnpj}}, com sede no endereço {{empresa.endereco_completo}}.</p>
<p style="text-align:justify;line-height:1.45;"><strong>CONTRATADA:</strong> {{colaborador.nome_completo}}, brasileiro(a), {{colaborador.estado_civil_label}}, {{colaborador.qualificacao_contratual}}, nascido(a) em {{colaborador.data_nascimento}}, com RG nº {{colaborador.rg}} e inscrito(a) no CPF/MF sob nº {{colaborador.cpf}}, residente e domiciliado(a) em {{colaborador.endereco_completo}}.</p>
<p style="text-align:justify;line-height:1.45;"><strong>CONSIDERANDO QUE:</strong></p>
<p style="text-align:justify;line-height:1.45;margin-left:1em;">a) As partes firmaram contrato em {{colaborador.data_admissao}};</p>
<p style="text-align:justify;line-height:1.45;margin-left:1em;">b) As partes, de comum acordo, resolveram rescindir o referido contrato em relação à CONTRATADA <strong>{{colaborador.nome_completo}}</strong>;</p>
<p style="text-align:justify;line-height:1.45;margin-left:1em;">c) A CONTRATANTE declara que efetuou o pagamento de todos os valores devidos à CONTRATADA <strong>{{colaborador.nome_completo}}</strong> até a presente data;</p>
<p style="text-align:justify;line-height:1.45;margin-left:1em;">d) Registra-se o tipo de desligamento: <strong>{{demissao.tipo_label}}</strong>, e o motivo informado: {{demissao.motivo}}.</p>
<p style="text-align:justify;line-height:1.45;"><strong>RESOLVEM</strong>, de comum acordo, o presente DISTRATO, mediante as seguintes cláusulas:</p>
<p style="text-align:justify;line-height:1.45;"><strong>CLÁUSULA PRIMEIRA:</strong> O contrato firmado entre as partes em {{colaborador.data_admissao}} fica, por este instrumento, rescindido em relação à CONTRATADA <strong>{{colaborador.nome_completo}}</strong> para todos os fins e efeitos de direito.</p>
<p style="text-align:justify;line-height:1.45;"><strong>CLÁUSULA SEGUNDA:</strong> A data de saída da CONTRATADA <strong>{{colaborador.nome_completo}}</strong> será <strong>{{demissao.data}}</strong>.</p>
<p style="text-align:justify;line-height:1.45;"><strong>CLÁUSULA TERCEIRA:</strong> A CONTRATANTE declara que realizou o pagamento integral de todos os valores devidos à CONTRATADA em razão dos serviços prestados até a data de saída especificada na Cláusula Segunda.</p>
<p style="text-align:justify;line-height:1.45;"><strong>CLÁUSULA QUARTA:</strong> As partes declaram que, com a assinatura deste distrato, dão-se plena, geral, irrevogável e irretratável quitação de todos os direitos e obrigações decorrentes do contrato original, nada mais tendo a reclamar, a que título for, em tempo algum.</p>
<p style="text-align:justify;line-height:1.45;"><strong>CLÁUSULA QUINTA:</strong> A CONTRATADA compromete-se a manter o sigilo das informações confidenciais a que teve acesso em razão do contrato original, nos termos da cláusula de confidencialidade do referido contrato e da legislação aplicável.</p>
<p style="text-align:justify;line-height:1.45;"><strong>CLÁUSULA SEXTA:</strong> Fica eleito o foro da Comarca de {{empresa.cidade}}/{{empresa.estado}} para dirimir quaisquer controvérsias oriundas deste Distrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja.</p>
<p style="text-align:justify;line-height:1.45;margin-top:1.5em;">E, por estarem justas e de acordo, as partes firmam o presente distrato em 02 (duas) vias de igual teor e forma, na presença das testemunhas abaixo qualificadas ou por assinatura eletrônica.</p>
<p style="margin-top:2em;">{{empresa.cidade}}/{{empresa.estado}}, {{data_formatada}}.</p>
<table style="width:100%;margin-top:2em;border-collapse:collapse;"><tr><td style="width:50%;vertical-align:top;padding:8px;"><strong>{{empresa.razao_social}}</strong><br/>CONTRATANTE</td><td style="width:50%;vertical-align:top;padding:8px;"><strong>{{colaborador.nome_completo}}</strong><br/>CONTRATADA</td></tr></table>
<p style="margin-top:2em;"><strong>Testemunhas:</strong></p>
<table style="width:100%;border-collapse:collapse;"><tr><td style="width:48%;vertical-align:top;padding:6px;">1) ________________________________<br/><br/>Nome: _____________________________<br/>CPF: ______________________________</td><td style="width:4%;"></td><td style="width:48%;vertical-align:top;padding:6px;">2) ________________________________<br/><br/>Nome: _____________________________<br/>CPF: ______________________________</td></tr></table>
HTML;

    $vars = json_encode(extrair_variaveis_template($html));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO contratos_templates (nome, descricao, conteudo_html, variaveis_disponiveis, ativo, padrao_distrato, criado_por_usuario_id)
            VALUES (?, ?, ?, ?, 1, 1, ?)
        ");
        $stmt->execute([
            'Distrato (modelo referência — prestação de serviços / CLT)',
            'Texto alinhado a modelo de distrato com cláusulas tipo CONTRATANTE/CONTRATADA, considerandos e testemunhas. Revise com o jurídico. O título usa TRABALHO se o colaborador for CLT e PRESTAÇÃO DE SERVIÇOS nos demais casos.',
            $html,
            $vars,
            $uid,
        ]);
        log_contrato('seed_template_distrato_padrao_se_ausente: template padrão de distrato inserido (id automático).');
    } catch (Exception $e) {
        error_log('seed_template_distrato_padrao_se_ausente: ' . $e->getMessage());
    }
}

/**
 * @return array{created:bool,enviado_autentique:bool,contrato_id:?int,message:string}
 */
function criar_contrato_distrato_automatico(PDO $pdo, int $colaborador_id, int $demissao_id, array $usuario) {
    garantir_schema_contratos_distrato($pdo);
    seed_template_distrato_padrao_se_ausente($pdo);

    $vazio = ['created' => false, 'enviado_autentique' => false, 'contrato_id' => null, 'message' => ''];

    try {
        $chk = $pdo->prepare('SELECT id FROM contratos WHERE demissao_id = ? LIMIT 1');
        $chk->execute([$demissao_id]);
        if ($chk->fetch()) {
            return $vazio + ['message' => 'Já existe distrato vinculado a esta demissão.'];
        }
    } catch (Exception $e) {
        // coluna pode não existir em bases muito antigas sem migração
    }

    $stmt = $pdo->prepare('SELECT * FROM contratos_templates WHERE padrao_distrato = 1 AND ativo = 1 ORDER BY id DESC LIMIT 1');
    $stmt->execute();
    $template = $stmt->fetch();
    if (!$template) {
        return $vazio + ['message' => 'Nenhum template ativo marcado como padrão de distrato. Defina um em Contratos > Templates.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM demissoes WHERE id = ? AND colaborador_id = ?');
    $stmt->execute([$demissao_id, $colaborador_id]);
    $demissao = $stmt->fetch();
    if (!$demissao) {
        return $vazio + ['message' => 'Registro de demissão não encontrado.'];
    }

    $colaborador = buscar_dados_colaborador_completos($colaborador_id);
    if (!$colaborador) {
        return $vazio + ['message' => 'Colaborador não encontrado.'];
    }
    if (empty($colaborador['data_admissao']) && !empty($colaborador['data_inicio'])) {
        $colaborador['data_admissao'] = $colaborador['data_inicio'];
    }

    $tipo = $demissao['tipo_demissao'] ?? 'outro';
    $contrato_data = [
        'titulo' => 'Termo de distrato — ' . ($colaborador['nome_completo'] ?? 'Colaborador'),
        'descricao_funcao' => '',
        'data_criacao' => date('Y-m-d'),
        'data_vencimento' => null,
        'observacoes' => 'Gerado automaticamente ao desligamento do colaborador.',
        'demissao_data' => $demissao['data_demissao'] ?? '',
        'demissao_tipo' => $tipo,
        'demissao_tipo_label' => label_tipo_demissao($tipo),
        'demissao_motivo' => $demissao['motivo'] ?? '',
    ];

    $conteudo_final = substituir_variaveis_contrato_com_manuais($template['conteudo_html'], $colaborador, $contrato_data, []);
    $titulo = $contrato_data['titulo'];
    $template_id = (int)$template['id'];
    $user_id = (int)($usuario['id'] ?? 0);
    if ($user_id < 1) {
        $user_id = 1;
    }

    $autentique_ok = false;
    $autentique_config = null;
    try {
        $ac = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
        $autentique_config = $ac ? $ac->fetch() : null;
        $autentique_ok = ($autentique_config !== false && $autentique_config !== null);
    } catch (Exception $e) {
        $autentique_ok = false;
    }

    $colab_email = trim($colaborador['email_pessoal'] ?? '');
    $representante = [
        'nome' => $autentique_config['representante_nome'] ?? '',
        'email' => trim($autentique_config['representante_email'] ?? ''),
        'cpf' => $autentique_config['representante_cpf'] ?? '',
    ];
    $incluir_representante = $autentique_ok && !empty($representante['email']);

    $pdf_path = null;
    $contrato_id = null;

    try {
        $pdo->beginTransaction();

        $pdf_path = gerar_pdf_contrato($conteudo_final, $titulo);

        $stmt = $pdo->prepare("
            INSERT INTO contratos (
                colaborador_id, demissao_id, template_id, titulo, descricao_funcao,
                conteudo_final_html, pdf_path, status, criado_por_usuario_id,
                data_criacao, data_vencimento, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $status_inicial = 'rascunho';
        $stmt->execute([
            $colaborador_id,
            $demissao_id,
            $template_id,
            $titulo,
            '',
            $conteudo_final,
            $pdf_path,
            $status_inicial,
            $user_id,
            $contrato_data['data_criacao'],
            null,
            $contrato_data['observacoes'],
        ]);
        $contrato_id = (int)$pdo->lastInsertId();

        $ordem = 0;
        $stmt = $pdo->prepare("
            INSERT INTO contratos_signatarios (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
            VALUES (?, 'colaborador', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $contrato_id,
            $colaborador['nome_completo'] ?? 'Colaborador',
            $colab_email,
            function_exists('formatar_cpf') ? formatar_cpf($colaborador['cpf'] ?? '') : ($colaborador['cpf'] ?? ''),
            $ordem++,
        ]);

        if ($incluir_representante) {
            $stmt = $pdo->prepare("
                INSERT INTO contratos_signatarios (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                VALUES (?, 'representante', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $contrato_id,
                $representante['nome'] ?? '',
                $representante['email'],
                function_exists('formatar_cpf') ? formatar_cpf($representante['cpf'] ?? '') : ($representante['cpf'] ?? ''),
                $ordem++,
            ]);
        }

        if (!$autentique_ok) {
            $pdo->commit();
            return [
                'created' => true,
                'enviado_autentique' => false,
                'contrato_id' => $contrato_id,
                'message' => 'Distrato criado em rascunho (Autentique não configurado). Envie manualmente em Contratos.',
            ];
        }

        if ($colab_email === '' || !filter_var($colab_email, FILTER_VALIDATE_EMAIL)) {
            $pdo->commit();
            return [
                'created' => true,
                'enviado_autentique' => false,
                'contrato_id' => $contrato_id,
                'message' => 'Distrato criado em rascunho: e-mail do colaborador ausente ou inválido para assinatura eletrônica.',
            ];
        }

        require_once __DIR__ . '/autentique_service.php';
        $service = new AutentiqueService();
        $pdf_base64 = pdf_para_base64($pdf_path);

        $signatarios = [['email' => $colab_email, 'x' => 100, 'y' => 100]];
        if ($incluir_representante) {
            $signatarios[] = ['email' => $representante['email'], 'x' => 100, 'y' => 250];
        }

        $resultado = $service->criarDocumento($titulo, $pdf_base64, $signatarios);
        $signatures = $resultado['signatures'] ?? [];

        if ($resultado && !empty($signatures)) {
            $stmt = $pdo->prepare('UPDATE contratos SET autentique_document_id = ?, status = ? WHERE id = ?');
            $stmt->execute([$resultado['id'], 'enviado', $contrato_id]);

            $pdo->prepare('DELETE FROM contratos_signatarios WHERE contrato_id = ?')->execute([$contrato_id]);
            $sig_map = [];
            foreach ($signatures as $sig) {
                $em = strtolower($sig['email'] ?? '');
                if ($em) {
                    $sig_map[$em] = $sig;
                }
            }
            $ordem = 0;

            $colab_email_key = strtolower($colab_email);
            $signer = $sig_map[$colab_email_key] ?? null;
            $stmt = $pdo->prepare("
                INSERT INTO contratos_signatarios
                (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura, link_publico)
                VALUES (?, 'colaborador', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $contrato_id,
                $colaborador['nome_completo'],
                $colab_email,
                function_exists('formatar_cpf') ? formatar_cpf($colaborador['cpf'] ?? '') : '',
                $signer['public_id'] ?? null,
                $ordem++,
                $signer['link']['short_link'] ?? null,
            ]);

            if ($incluir_representante) {
                $rep_email = strtolower($representante['email']);
                $signer = $sig_map[$rep_email] ?? null;
                $stmt = $pdo->prepare("
                    INSERT INTO contratos_signatarios
                    (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura, link_publico)
                    VALUES (?, 'representante', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $contrato_id,
                    $representante['nome'] ?? '',
                    $representante['email'],
                    function_exists('formatar_cpf') ? formatar_cpf($representante['cpf'] ?? '') : '',
                    $signer['public_id'] ?? null,
                    $ordem++,
                    $signer['link']['short_link'] ?? null,
                ]);
            }
        } else {
            if ($resultado && !empty($resultado['id'])) {
                $pdo->prepare('UPDATE contratos SET autentique_document_id = ?, status = ? WHERE id = ?')->execute([$resultado['id'], 'enviado', $contrato_id]);
            } else {
                $pdo->prepare("UPDATE contratos SET status = 'enviado' WHERE id = ?")->execute([$contrato_id]);
            }
            $pdo->prepare('DELETE FROM contratos_signatarios WHERE contrato_id = ?')->execute([$contrato_id]);
            $ordem = 0;
            $stmt = $pdo->prepare("
                INSERT INTO contratos_signatarios (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                VALUES (?, 'colaborador', ?, ?, ?, ?)
            ");
            $stmt->execute([$contrato_id, $colaborador['nome_completo'] ?? '', $colab_email, function_exists('formatar_cpf') ? formatar_cpf($colaborador['cpf'] ?? '') : '', $ordem++]);
            if ($incluir_representante) {
                $stmt = $pdo->prepare("
                    INSERT INTO contratos_signatarios (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                    VALUES (?, 'representante', ?, ?, ?, ?)
                ");
                $stmt->execute([$contrato_id, $representante['nome'] ?? '', $representante['email'], function_exists('formatar_cpf') ? formatar_cpf($representante['cpf'] ?? '') : '', $ordem++]);
            }
        }

        $pdo->commit();

        return [
            'created' => true,
            'enviado_autentique' => true,
            'contrato_id' => $contrato_id,
            'message' => 'Termo de distrato enviado para assinatura no Autentique.',
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($pdf_path) {
            $full = __DIR__ . '/../' . $pdf_path;
            if (is_file($full)) {
                @unlink($full);
            }
        }
        log_contrato('criar_contrato_distrato_automatico ERRO: ' . $e->getMessage());
        return [
            'created' => false,
            'enviado_autentique' => false,
            'contrato_id' => null,
            'message' => 'Falha ao gerar distrato: ' . $e->getMessage(),
        ];
    }
}
