# ⚡ Solução Rápida: ERR_FAILED

## 🎯 Problema

Ao acessar `https://privus.com.br/rh/` aparece `ERR_FAILED`.

## ✅ Soluções Rápidas

### 1. Teste se PHP está funcionando

Acesse:
```
https://privus.com.br/rh/test_index_simples.php
```

**Se funcionar:**
- PHP está OK
- Problema está no `index.php` ou redirect

**Se não funcionar:**
- Problema de configuração do servidor
- Verifique logs de erro

### 2. Teste login diretamente

Acesse:
```
https://privus.com.br/rh/login.php
```

**Se funcionar:**
- Problema está no `index.php`
- Pode ser redirect infinito ou erro fatal

### 3. Verifique SSL

O erro `ERR_FAILED` pode ser causado por:
- Certificado SSL inválido
- Problema de HTTPS
- Mixed content (HTTP/HTTPS misturado)

**Teste:**
- Tente acessar via HTTP: `http://privus.com.br/rh/` (sem S)
- Se funcionar = problema de SSL

### 4. Verifique Logs

No servidor, execute:
```bash
tail -50 /var/log/apache2/error.log
# ou
tail -50 /var/log/nginx/error.log
```

Procure por erros relacionados a `/rh/` ou `index.php`.

### 5. Problema Comum: Redirect Infinito

Se `get_login_url()` retornar URL incorreta, pode causar loop.

**Teste:**
Adicione no início do `index.php`:
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/functions.php';
echo "Login URL seria: " . get_login_url();
exit;
```

---

## 🔧 Solução Temporária

Se precisar acessar urgentemente, tente:
- `https://privus.com.br/rh/login.php` (direto)
- `https://privus.com.br/rh/pages/dashboard.php` (se logado)

---

**Execute os testes e me diga o resultado!**

