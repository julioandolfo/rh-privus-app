-- ============================================
-- SCRIPT PARA DIAGNOSTICAR E RESOLVER LOCKS NO MYSQL
-- ============================================

-- ============================================
-- 1. VER PROCESSOS ATIVOS E LOCKS
-- ============================================
-- Mostra todos os processos ativos no MySQL
SELECT 
    ID,
    USER,
    HOST,
    DB,
    COMMAND,
    TIME as 'Tempo (segundos)',
    STATE,
    INFO as 'Query'
FROM information_schema.PROCESSLIST
WHERE COMMAND != 'Sleep'
ORDER BY TIME DESC;

-- ============================================
-- 2. VER LOCKS ESPECÍFICOS DO INNODB
-- ============================================
-- Mostra locks ativos no InnoDB
SELECT 
    r.trx_id waiting_trx_id,
    r.trx_mysql_thread_id waiting_thread,
    r.trx_query waiting_query,
    b.trx_id blocking_trx_id,
    b.trx_mysql_thread_id blocking_thread,
    b.trx_query blocking_query
FROM information_schema.innodb_lock_waits w
INNER JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
INNER JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id;

-- ============================================
-- 3. VER TRANSAÇÕES ATIVAS
-- ============================================
-- Mostra todas as transações ativas
SELECT 
    trx_id,
    trx_state,
    trx_started,
    trx_mysql_thread_id,
    trx_query,
    trx_tables_locked,
    trx_rows_locked,
    TIMESTAMPDIFF(SECOND, trx_started, NOW()) as 'Tempo (segundos)'
FROM information_schema.innodb_trx
ORDER BY trx_started ASC;

-- ============================================
-- 4. VER PROCESSOS BLOQUEADOS (WAITING)
-- ============================================
-- Mostra processos que estão esperando por locks
SELECT 
    p.ID,
    p.USER,
    p.HOST,
    p.DB,
    p.COMMAND,
    p.TIME as 'Tempo Esperando (segundos)',
    p.STATE,
    p.INFO as 'Query',
    CASE 
        WHEN p.TIME > 60 THEN 'CRÍTICO - Mais de 1 minuto'
        WHEN p.TIME > 30 THEN 'ALTO - Mais de 30 segundos'
        WHEN p.TIME > 10 THEN 'MÉDIO - Mais de 10 segundos'
        ELSE 'BAIXO'
    END as 'Prioridade'
FROM information_schema.PROCESSLIST p
WHERE p.COMMAND != 'Sleep'
AND p.STATE LIKE '%lock%'
OR p.TIME > 5
ORDER BY p.TIME DESC;

-- ============================================
-- 5. MATAR PROCESSO ESPECÍFICO
-- ============================================
-- Substitua PROCESS_ID pelo ID do processo que você quer matar
-- Exemplo: KILL 12345;
-- 
-- Para matar múltiplos processos de uma vez, use:
-- KILL 12345, 12346, 12347;

-- ============================================
-- 6. MATAR TODOS OS PROCESSOS DE UM USUÁRIO ESPECÍFICO
-- ============================================
-- CUIDADO: Isso mata TODOS os processos do usuário!
-- Substitua 'usuario' pelo nome do usuário
-- 
-- SELECT CONCAT('KILL ', ID, ';') as kill_command
-- FROM information_schema.PROCESSLIST
-- WHERE USER = 'usuario'
-- AND ID != CONNECTION_ID();

-- ============================================
-- 7. MATAR PROCESSOS COM MAIS DE X SEGUNDOS
-- ============================================
-- Gera comandos KILL para processos que estão rodando há mais de 30 segundos
-- Execute os comandos gerados manualmente
SELECT 
    CONCAT('KILL ', ID, ';') as kill_command,
    ID,
    USER,
    TIME as 'Tempo (segundos)',
    STATE,
    LEFT(INFO, 100) as 'Query Preview'
FROM information_schema.PROCESSLIST
WHERE COMMAND != 'Sleep'
AND TIME > 30
AND ID != CONNECTION_ID()
ORDER BY TIME DESC;

-- ============================================
-- 8. VER LOCKS POR TABELA
-- ============================================
-- Mostra quais tabelas estão com locks
SELECT 
    OBJECT_SCHEMA,
    OBJECT_NAME,
    LOCK_TYPE,
    LOCK_MODE,
    LOCK_STATUS,
    LOCK_DATA
FROM performance_schema.data_locks
WHERE OBJECT_SCHEMA = DATABASE()
ORDER BY OBJECT_NAME;

-- ============================================
-- 9. VERIFICAR CONFIGURAÇÕES DE TIMEOUT
-- ============================================
-- Mostra configurações atuais de timeout
SHOW VARIABLES LIKE '%timeout%';
SHOW VARIABLES LIKE 'innodb_lock_wait_timeout';
SHOW VARIABLES LIKE 'lock_wait_timeout';

-- ============================================
-- 10. AUMENTAR TIMEOUT (TEMPORÁRIO)
-- ============================================
-- Aumenta o timeout de lock para 60 segundos (sessão atual)
-- SET SESSION innodb_lock_wait_timeout = 60;
-- SET SESSION lock_wait_timeout = 60;

-- ============================================
-- 11. LIMPAR LOCKS DE TABELAS ESPECÍFICAS
-- ============================================
-- Se você sabe qual tabela está bloqueada, pode tentar:
-- UNLOCK TABLES;  -- Desbloqueia todas as tabelas da sessão atual

-- ============================================
-- 12. VER PROCESSOS RELACIONADOS A VAGAS
-- ============================================
-- Mostra processos que estão trabalhando com a tabela vagas
SELECT 
    p.ID,
    p.USER,
    p.TIME as 'Tempo (segundos)',
    p.STATE,
    p.INFO as 'Query'
FROM information_schema.PROCESSLIST p
WHERE p.INFO LIKE '%vagas%'
OR p.INFO LIKE '%vagas_etapas%'
OR p.INFO LIKE '%vagas_landing_pages%'
ORDER BY p.TIME DESC;

