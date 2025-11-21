# 🔍 Diagnóstico: ERR_FAILED em privus.com.br/rh/

## ❌ Problema

Ao acessar `https://privus.com.br/rh/` aparece `ERR_FAILED`.

## 🔍 Análise

O `index.php` faz:
1. Carrega `functions.php` e `auth.php`
2. Chama `require_login()`
3. Se não estiver logado, redireciona para `get_login_url()`

O problema pode estar em:
- `get_login_url()` retornando URL incorreta
- Erro fatal no PHP antes do redirect
- Problema de SSL/HTTPS
- Problema de configuração do servidor

## ✅ Testes para Fazer

### Teste 1: Acesse diretamente
```
https://privus.com.br/rh/test_index_simples.php
```

Se funcionar = PHP está OK, problema está no `index.php`

### Teste 2: Acesse login diretamente
```
https://privus.com.br/rh/login.php
```

Se funcionar = problema está no redirect do `index.php`

### Teste 3: Verifique logs do servidor
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log
```

### Teste 4: Verifique SSL
Acesse: https://www.ssllabs.com/ssltest/analyze.html?d=privus.com.br

## 🔧 Soluções Possíveis

### Solução 1: Problema de SSL
Se o certificado SSL estiver inválido, pode causar ERR_FAILED.

**Verifique:**
- Certificado SSL válido
- HTTPS configurado corretamente
- Sem erros de certificado no browser

### Solução 2: Problema no get_login_url()
A função pode estar retornando URL incorreta.

**Teste:**
Adicione no início do `index.php`:
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/functions.php';
echo "Login URL: " . get_login_url();
exit;
```

### Solução 3: Problema de Sessão
Sessão pode estar causando problema.

**Teste:**
Verifique se `session_start()` está funcionando corretamente.

### Solução 4: Problema de Permissões
Arquivos podem não ter permissão correta.

**Execute:**
```bash
cd /home/privus/public_html/rh
chmod 644 *.php
chmod 755 .
```

---

**Execute os testes e me diga os resultados!**

