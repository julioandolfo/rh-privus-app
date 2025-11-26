# 📚 Sistema LMS - Escola Privus

Sistema completo de Learning Management System (LMS) integrado ao RH Privus.

## 🎯 Funcionalidades Principais

### Para Colaboradores
- ✅ Catálogo de cursos disponíveis
- ✅ Cursos obrigatórios com alertas
- ✅ Player seguro de aulas (vídeo YouTube, vídeo upload, PDF, texto)
- ✅ Acompanhamento de progresso
- ✅ Certificados automáticos
- ✅ Badges e conquistas

### Para Administradores/RH
- ✅ Gestão completa de cursos
- ✅ Criação de aulas (múltiplos formatos)
- ✅ Sistema de cursos obrigatórios
- ✅ Atribuição automática por regras
- ✅ Relatórios e analytics
- ✅ Gestão de certificados

## 🔒 Sistema de Segurança Anti-Fraude

O sistema possui múltiplas camadas de segurança:

1. **Validação de Tempo Real**: Calcula tempo realmente assistido baseado em eventos
2. **Validação de Interação**: Monitora cliques, scrolls e interações
3. **Validação de Visibilidade**: Detecta se janela está ativa
4. **Validação de Velocidade**: Bloqueia velocidade acima de 2x
5. **Detecção de Anomalias**: Score de risco calculado automaticamente
6. **Auditoria Completa**: Todos os eventos são registrados

## 📋 Instalação

### 1. Executar Migração do Banco de Dados

Execute o arquivo SQL no seu banco de dados:

```bash
mysql -u usuario -p nome_banco < migracao_lms_completo.sql
```

Ou execute diretamente no MySQL:

```sql
SOURCE migracao_lms_completo.sql;
```

### 2. Configurar Cron Job

Configure o cron job para processar alertas de cursos obrigatórios:

```bash
# Editar crontab
crontab -e

# Adicionar linha (executa a cada hora)
0 * * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_alertas_lms.php

# Ou a cada 6 horas
0 */6 * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_alertas_lms.php
```

### 3. Criar Diretórios de Upload

Certifique-se de que os diretórios existem e têm permissões corretas:

```bash
mkdir -p uploads/lms/videos
mkdir -p uploads/lms/pdfs
mkdir -p uploads/lms/imagens
mkdir -p uploads/lms/certificados

chmod -R 755 uploads/lms/
```

## 📁 Estrutura de Arquivos

```
rh-privus/
├── api/lms/                    # APIs do LMS
│   ├── iniciar_aula.php
│   ├── registrar_evento.php
│   ├── validar_conclusao.php
│   ├── salvar_progresso.php
│   ├── get_cursos.php
│   ├── get_aulas.php
│   └── cursos_obrigatorios.php
│
├── assets/js/
│   └── lms_player.js          # Player JavaScript seguro
│
├── includes/
│   ├── lms_functions.php      # Funções principais
│   ├── lms_security.php       # Sistema de segurança
│   └── lms_obrigatorios.php   # Cursos obrigatórios
│
├── pages/lms/
│   ├── cursos.php             # Gestão de cursos (admin)
│   ├── portal/
│   │   ├── meus_cursos.php    # Portal colaborador
│   │   ├── curso_detalhes.php # Detalhes do curso
│   │   └── player_aula.php    # Player de aula
│   └── ...
│
├── cron/
│   └── processar_alertas_lms.php
│
└── migracao_lms_completo.sql
```

## 🚀 Como Usar

### Criar um Curso

1. Acesse **Escola Privus > Gestão de Cursos**
2. Clique em **Novo Curso**
3. Preencha informações básicas
4. Adicione aulas (YouTube, Upload, PDF ou Texto)
5. Configure como obrigatório (se necessário)
6. Publique o curso

### Atribuir Curso Obrigatório

1. Acesse o curso
2. Marque como **Obrigatório**
3. Configure prazo e alertas
4. Atribua manualmente ou configure regras automáticas

### Colaborador Acessar Curso

1. Acesse **Escola Privus > Meus Cursos**
2. Selecione um curso
3. Clique em **Iniciar Curso**
4. Assista as aulas sequencialmente
5. Sistema valida automaticamente antes de permitir conclusão

## ⚙️ Configurações de Segurança

As configurações de segurança podem ser ajustadas por tipo de conteúdo em `lms_configuracoes_seguranca`:

- **Tempo mínimo**: Percentual mínimo que deve ser assistido (padrão: 80%)
- **Velocidade máxima**: Velocidade de reprodução permitida (padrão: 2x)
- **Interações mínimas**: Número mínimo de interações requeridas
- **Validar janela ativa**: Se deve validar se janela está em foco
- **Bloquear pular**: Se deve bloquear pular para o final

## 📊 Relatórios

Acesse **Escola Privus > Relatórios** para ver:

- Taxa de conclusão por curso
- Colaboradores com cursos pendentes
- Cursos mais acessados
- Tempo médio de conclusão
- Alertas de segurança

## 🔔 Sistema de Alertas

Os alertas são enviados automaticamente:

- **Notificação inicial**: Ao atribuir curso obrigatório
- **Lembretes**: X dias antes do prazo (configurável)
- **Vencimento próximo**: 1 dia antes
- **Vencido**: Após data limite (frequência configurável)

Canais de alerta:
- ✅ Email
- ✅ Push Notification
- ✅ Notificação no Sistema

## 🛡️ Sistema Anti-Fraude

O sistema detecta automaticamente:

- ✅ Conclusão muito rápida
- ✅ Velocidade anormal de reprodução
- ✅ Falta de interação
- ✅ Janela inativa por muito tempo
- ✅ Tentativas de pular conteúdo
- ✅ Padrões suspeitos de automação

**Ações em caso de fraude:**
- Bloquear conclusão
- Alertar RH/Gestor
- Requerer aprovação manual
- Registrar na auditoria

## 📝 Próximos Passos

Para completar a implementação, ainda é necessário criar:

1. **Páginas administrativas**:
   - `lms/curso_add.php` - Criar curso
   - `lms/curso_edit.php` - Editar curso
   - `lms/curso_view.php` - Visualizar curso
   - `lms/aulas.php` - Gerenciar aulas
   - `lms/aula_add.php` - Criar aula
   - `lms/categorias_cursos.php` - Gerenciar categorias
   - `lms/cursos_obrigatorios.php` - Gestão de obrigatórios
   - `lms/relatorios_lms.php` - Relatórios

2. **Páginas do portal**:
   - `lms/portal/meu_progresso.php` - Dashboard de progresso
   - `lms/portal/meus_certificados.php` - Certificados
   - `lms/portal/minhas_conquistas.php` - Badges

3. **Melhorias**:
   - Sistema de avaliações/quizzes
   - Comentários em aulas
   - Favoritos
   - Recomendações inteligentes

## 🐛 Troubleshooting

### Alertas não estão sendo enviados

1. Verifique se o cron job está configurado e executando
2. Verifique logs em `logs/` (se existir)
3. Verifique configurações de email/push no sistema

### Player não registra eventos

1. Verifique console do navegador para erros
2. Verifique se `lms_player.js` está carregado
3. Verifique permissões de API

### Conclusão bloqueada incorretamente

1. Verifique configurações de segurança do curso/aula
2. Revise eventos registrados na auditoria
3. RH pode aprovar manualmente se necessário

## 📞 Suporte

Para dúvidas ou problemas, consulte a documentação do sistema ou entre em contato com o suporte técnico.

---

**Versão**: 1.0.0  
**Data**: <?= date('Y-m-d') ?>  
**Sistema**: RH Privus - Escola Privus LMS

