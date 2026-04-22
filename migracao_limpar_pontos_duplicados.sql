-- Limpeza de pontos duplicados causados pelo bug curtir/descurtir/curtir
-- Remove entradas repetidas em pontos_historico mantendo apenas a primeira (menor id)
-- por combinação (usuario_id, colaborador_id, acao, referencia_id, referencia_tipo)
-- e recalcula pontos_total a partir do histórico limpo.

-- 1. Remove duplicatas do histórico (mantém o registro mais antigo de cada combinação)
DELETE h1 FROM pontos_historico h1
INNER JOIN pontos_historico h2
    ON h1.acao = h2.acao
    AND h1.referencia_id = h2.referencia_id
    AND h1.referencia_tipo = h2.referencia_tipo
    AND (
        (h1.usuario_id = h2.usuario_id AND h1.usuario_id IS NOT NULL)
        OR (h1.colaborador_id = h2.colaborador_id AND h1.colaborador_id IS NOT NULL)
    )
    AND h1.id > h2.id
WHERE h1.referencia_id IS NOT NULL
  AND h1.referencia_tipo IS NOT NULL
  AND h1.acao NOT IN ('acesso_diario', 'ajuste_manual_credito', 'ajuste_manual_debito');

-- 2. Recalcula pontos_total a partir do histórico (para usuários)
UPDATE pontos_total pt
LEFT JOIN (
    SELECT
        usuario_id,
        SUM(pontos) AS total,
        SUM(CASE WHEN MONTH(data_registro) = MONTH(CURDATE()) AND YEAR(data_registro) = YEAR(CURDATE()) THEN pontos ELSE 0 END) AS mes,
        SUM(CASE WHEN YEARWEEK(data_registro) = YEARWEEK(CURDATE()) THEN pontos ELSE 0 END) AS semana,
        SUM(CASE WHEN data_registro = CURDATE() THEN pontos ELSE 0 END) AS dia
    FROM pontos_historico
    WHERE usuario_id IS NOT NULL
    GROUP BY usuario_id
) h ON pt.usuario_id = h.usuario_id
SET
    pt.pontos_totais = COALESCE(h.total, 0),
    pt.pontos_mes = COALESCE(h.mes, 0),
    pt.pontos_semana = COALESCE(h.semana, 0),
    pt.pontos_dia = COALESCE(h.dia, 0)
WHERE pt.usuario_id IS NOT NULL;

-- 3. Recalcula pontos_total a partir do histórico (para colaboradores)
UPDATE pontos_total pt
LEFT JOIN (
    SELECT
        colaborador_id,
        SUM(pontos) AS total,
        SUM(CASE WHEN MONTH(data_registro) = MONTH(CURDATE()) AND YEAR(data_registro) = YEAR(CURDATE()) THEN pontos ELSE 0 END) AS mes,
        SUM(CASE WHEN YEARWEEK(data_registro) = YEARWEEK(CURDATE()) THEN pontos ELSE 0 END) AS semana,
        SUM(CASE WHEN data_registro = CURDATE() THEN pontos ELSE 0 END) AS dia
    FROM pontos_historico
    WHERE colaborador_id IS NOT NULL
    GROUP BY colaborador_id
) h ON pt.colaborador_id = h.colaborador_id
SET
    pt.pontos_totais = COALESCE(h.total, 0),
    pt.pontos_mes = COALESCE(h.mes, 0),
    pt.pontos_semana = COALESCE(h.semana, 0),
    pt.pontos_dia = COALESCE(h.dia, 0)
WHERE pt.colaborador_id IS NOT NULL;
