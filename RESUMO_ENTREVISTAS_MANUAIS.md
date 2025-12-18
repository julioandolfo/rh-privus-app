# Resumo: Implementação de Entrevistas Manuais

## Objetivo
Permitir criar entrevistas manualmente sem necessidade de candidatura cadastrada no sistema, e que essas entrevistas apareçam no kanban de seleção.

## Alterações Realizadas

### 1. Banco de Dados
- **Arquivo**: `migracao_entrevistas_manual.sql`
- Modificada a tabela `entrevistas` para permitir `candidatura_id` NULL
- Adicionados campos para candidato manual:
  - `candidato_nome_manual`
  - `candidato_email_manual`
  - `candidato_telefone_manual`
  - `vaga_id_manual`
  - `coluna_kanban`
- Foreign key para `candidatura_id` agora permite NULL
- Foreign key adicionada para `vaga_id_manual`

### 2. API de Criação de Entrevistas
- **Arquivo**: `api/recrutamento/entrevistas/criar.php`
- Modificada para aceitar entrevistas sem candidatura
- Validação de campos obrigatórios quando é entrevista manual
- Suporte para campos de candidato manual

### 3. Página de Entrevistas
- **Arquivo**: `pages/entrevistas.php`
- Adicionado toggle no modal para escolher entre candidatura existente ou entrevista manual
- Formulário adaptado para campos manuais quando necessário
- Query modificada para incluir entrevistas sem candidatura usando LEFT JOIN
- Filtro de empresa ajustado para considerar entrevistas sem candidatura

### 4. Página de Detalhes da Entrevista
- **Arquivo**: `pages/entrevista_view.php`
- Query modificada para suportar entrevistas sem candidatura
- Exibição de alerta quando é entrevista manual
- Campos adaptados para mostrar dados manuais quando necessário

### 5. Funções de Recrutamento
- **Arquivo**: `includes/recrutamento_functions.php`
- Nova função `buscar_entrevistas_kanban()` para buscar entrevistas sem candidatura
- Função `buscar_candidaturas_kanban()` modificada para incluir entrevistas sem candidatura
- Filtros de permissão ajustados para entrevistas sem candidatura

### 6. Kanban de Seleção
- **Arquivo**: `pages/kanban_selecao.php`
- Cards adaptados para mostrar entrevistas manuais com badge especial
- Links ajustados para apontar para `entrevista_view.php` quando for entrevista manual
- Drag and drop adaptado para detectar entrevistas manuais

### 7. API de Mover no Kanban
- **Arquivo**: `api/recrutamento/kanban/mover.php`
- Suporte para mover entrevistas sem candidatura no kanban
- Detecção automática se é candidatura ou entrevista manual

### 8. API de Listar Vagas
- **Arquivo**: `api/recrutamento/vagas/listar.php` (novo)
- Criada API para listar vagas para uso no formulário de entrevistas manuais

## Como Usar

### Criar Entrevista Manual
1. Acesse o menu **Recrutamento > Entrevistas**
2. Clique em **Nova Entrevista**
3. Marque a opção **"Entrevista Manual (sem candidatura no sistema)"**
4. Preencha os dados do candidato:
   - Nome (obrigatório)
   - Email (obrigatório)
   - Telefone (opcional)
   - Vaga (opcional)
   - Coluna do Kanban
5. Preencha os dados da entrevista normalmente
6. Clique em **Agendar**

### Visualizar no Kanban
- As entrevistas manuais aparecem no kanban com um badge **"Entrevista Manual"**
- Podem ser arrastadas entre colunas normalmente
- Ao clicar, abrem a página de detalhes da entrevista

## Migração

Execute o script de migração:
```bash
php executar_migracao_entrevistas_manual.php
```

Ou execute o SQL diretamente:
```bash
mysql -u usuario -p nome_banco < migracao_entrevistas_manual.sql
```

## Observações

- Entrevistas manuais não têm histórico de candidatura
- Entrevistas manuais não executam automações do kanban (apenas movimentação)
- Entrevistas manuais podem ser convertidas em candidaturas futuramente (não implementado)
- O filtro de empresa funciona corretamente para entrevistas com vaga manual

