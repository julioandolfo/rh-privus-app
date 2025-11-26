# 🔧 Troubleshooting - Sistema LMS

## Problemas Comuns e Soluções

### 1. Páginas não acessam / Erro 404

**Causa**: Caminhos incorretos ou arquivos não existem

**Solução**:
- Verifique se os arquivos foram criados nas pastas corretas:
  - `pages/lms/portal/meus_cursos.php`
  - `pages/lms/portal/curso_detalhes.php`
  - `pages/lms/portal/player_aula.php`
  - `pages/lms/cursos.php`

- Verifique se o `.htaccess` está configurado corretamente
- Verifique permissões de arquivo (deve ser 644)

### 2. Erro: "Call to undefined function"

**Causa**: Funções não estão sendo carregadas

**Solução**:
- Verifique se `includes/lms_functions.php` existe
- Verifique se o arquivo está sendo incluído corretamente
- Adicione `require_once` antes de usar as funções

### 3. Erro de Banco de Dados

**Causa**: Tabelas não foram criadas

**Solução**:
1. Execute a migração SQL:
   ```sql
   SOURCE migracao_lms_completo.sql;
   ```

2. Verifique se todas as tabelas foram criadas:
   ```sql
   SHOW TABLES LIKE '%lms%';
   SHOW TABLES LIKE '%curso%';
   ```

### 4. Links quebrados entre páginas

**Causa**: Caminhos relativos incorretos

**Solução**:
- Links dentro de `pages/lms/portal/` devem ser relativos:
  - `curso_detalhes.php?id=X` (mesma pasta)
  - `meus_cursos.php` (mesma pasta)
  - `../dashboard.php` (pasta pai)

- Links de `pages/lms/` para `pages/lms/portal/`:
  - `portal/meus_cursos.php`

### 5. Variáveis não definidas

**Causa**: Variáveis não inicializadas

**Solução**:
- Sempre inicialize variáveis como arrays vazios:
  ```php
  $cursos = [];
  $cursos_obrigatorios = [];
  ```

- Use operador null coalescing:
  ```php
  $valor = $array['chave'] ?? 'padrao';
  ```

### 6. Erro ao buscar cursos

**Causa**: Query SQL com erro ou tabelas não existem

**Solução**:
1. Verifique logs de erro do PHP
2. Teste a query diretamente no MySQL
3. Verifique se as tabelas têm dados de teste

### 7. Player não funciona

**Causa**: JavaScript não carregado ou erros no console

**Solução**:
1. Abra o console do navegador (F12)
2. Verifique erros JavaScript
3. Verifique se `lms_player.js` está sendo carregado
4. Verifique se as APIs estão respondendo corretamente

### 8. Permissões negadas

**Causa**: Permissões não configuradas corretamente

**Solução**:
1. Verifique `includes/permissions.php`
2. Verifique se o usuário tem o role correto
3. Verifique se a página está no mapeamento de permissões

## Checklist de Verificação

Antes de reportar problemas, verifique:

- [ ] Migração SQL foi executada?
- [ ] Tabelas existem no banco?
- [ ] Arquivos PHP foram criados?
- [ ] Permissões de arquivo estão corretas?
- [ ] Logs de erro do PHP foram verificados?
- [ ] Console do navegador foi verificado?
- [ ] Usuário está logado?
- [ ] Usuário tem permissão para acessar?

## Como Verificar Logs

### PHP Error Log
```bash
# Windows (XAMPP/Laragon)
C:\laragon\logs\php_error.log

# Linux
/var/log/php/error.log
```

### MySQL Error Log
```bash
# Verificar erros do MySQL
SHOW ERRORS;
```

### Console do Navegador
1. Abra DevTools (F12)
2. Aba "Console"
3. Procure por erros em vermelho

## Teste Básico

1. **Acesse**: `pages/lms/portal/meus_cursos.php`
2. **Verifique**: Se a página carrega sem erros
3. **Verifique**: Se mostra mensagem "Nenhum curso encontrado" (normal se não houver cursos)
4. **Verifique**: Console do navegador para erros JavaScript

## Próximos Passos se Ainda Não Funcionar

1. Ative exibição de erros temporariamente:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

2. Verifique cada arquivo individualmente
3. Teste as funções isoladamente
4. Verifique se o banco de dados está conectado

## Suporte

Se os problemas persistirem, forneça:
- Mensagem de erro completa
- Stack trace (se disponível)
- Arquivo onde ocorre o erro
- Linha do erro
- Logs do PHP e MySQL

