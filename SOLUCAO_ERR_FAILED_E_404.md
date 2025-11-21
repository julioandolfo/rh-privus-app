# 🔧 Solução: ERR_FAILED e Erro 404 em Arquivos da Raiz

## ❌ Problemas Identificados

1. **ERR_FAILED** ao acessar `https://privus.com.br/rh/`
2. **Erro 404** ao acessar arquivos diretamente na raiz (ex: `test_enviar_push.php`)

## ✅ Soluções Implementadas

### 1. Arquivo `.htaccess` Criado

Foi criado um arquivo `.htaccess` na raiz do projeto que:
- Configura o roteamento para que `/rh/` redirecione para `/rh/index.php`
- Permite acesso direto a arquivos PHP
- Configura segurança básica
- Configura cache para assets estáticos

**Arquivo criado:** `.htaccess`

### 2. Melhorias no `index.php`

O arquivo `index.php` foi melhorado com:
- Tratamento de erros robusto
- Captura de exceções e erros fatais
- Redirecionamento seguro para login em caso de erro
- Evita que erros causem `ERR_FAILED` no navegador

**Arquivo modificado:** `index.php`

### 3. Arquivo de Teste Criado

Foi criado um arquivo `test_php_simples.php` para diagnóstico:
- Verifica se PHP está funcionando
- Mostra informações do servidor
- Testa se consegue carregar includes
- Fornece links para testar outros arquivos

**Arquivo criado:** `test_php_simples.php`

## 🧪 Como Testar

### Passo 1: Teste Básico do PHP

Acesse no navegador:
```
https://privus.com.br/rh/test_php_simples.php
```

Se funcionar, você verá uma página com informações do PHP e do servidor.

### Passo 2: Teste do Index

Acesse:
```
https://privus.com.br/rh/
```

Deve redirecionar para:
- Se não estiver logado: `https://privus.com.br/rh/login.php`
- Se estiver logado: `https://privus.com.br/rh/pages/dashboard.php`

### Passo 3: Teste de Arquivos Diretos

Acesse:
```
https://privus.com.br/rh/test_enviar_push.php
```

Deve carregar normalmente (se estiver logado).

## 🔍 Se Ainda Não Funcionar

### Verificação 1: Permissões de Arquivo

No servidor, execute:
```bash
cd /home/privus/public_html/rh
chmod 644 .htaccess
chmod 644 *.php
chmod 755 .
```

### Verificação 2: Verificar se Apache Suporta .htaccess

Verifique se o Apache está configurado para permitir `.htaccess`:

```bash
# Verifique o arquivo de configuração do Apache
grep -i "AllowOverride" /etc/apache2/sites-available/000-default.conf
# ou
grep -i "AllowOverride" /etc/apache2/apache2.conf
```

Deve conter algo como:
```apache
<Directory /home/privus/public_html/rh>
    AllowOverride All
</Directory>
```

### Verificação 3: Verificar Logs de Erro

```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log
```

Acesse a página e veja se aparecem erros nos logs.

### Verificação 4: Testar SSL

Se o problema for SSL, teste:
```bash
openssl s_client -connect privus.com.br:443 -servername privus.com.br
```

## 🚨 Problemas Comuns

### Problema: Arquivo .htaccess não está sendo lido

**Solução:** Verifique se o Apache tem `AllowOverride All` configurado para o diretório.

### Problema: Ainda dá ERR_FAILED

**Solução:** 
1. Verifique os logs do servidor
2. Teste com `test_php_simples.php` primeiro
3. Verifique se há erros de PHP nos logs

### Problema: Arquivos PHP retornam 404

**Solução:**
1. Verifique se o módulo `mod_rewrite` está ativo no Apache
2. Verifique permissões dos arquivos
3. Verifique se o caminho está correto no servidor

## 📋 Checklist de Verificação

- [ ] Arquivo `.htaccess` existe na raiz do projeto
- [ ] Arquivo `test_php_simples.php` funciona
- [ ] Arquivo `index.php` redireciona corretamente
- [ ] Arquivos PHP na raiz são acessíveis diretamente
- [ ] Logs do servidor não mostram erros
- [ ] Permissões de arquivo estão corretas (644 para arquivos, 755 para diretórios)

## 📝 Notas Importantes

1. **Se estiver usando Nginx** ao invés de Apache, o `.htaccess` não funcionará. Nesse caso, você precisa configurar o Nginx diretamente.

2. **Se o problema persistir**, pode ser necessário verificar:
   - Configuração do Virtual Host
   - Configuração de SSL/HTTPS
   - Firewall bloqueando requisições
   - Problemas de DNS

3. **Para produção**, considere desabilitar `display_errors` no PHP (já está configurado no `index.php`).

---

**Execute os testes acima e me informe os resultados!**

