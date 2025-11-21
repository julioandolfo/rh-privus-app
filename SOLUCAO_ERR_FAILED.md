# 🔧 Solução: ERR_FAILED ao acessar privus.com.br/rh/

## ❌ Problema

Ao acessar `https://privus.com.br/rh/` aparece:
```
ERR_FAILED
Não é possível acessar esse site
```

## 🔍 Possíveis Causas

### 1. Problema de SSL/HTTPS
- Certificado SSL inválido ou expirado
- Configuração incorreta de HTTPS

### 2. Erro Fatal no PHP
- O `index.php` pode estar gerando um erro fatal
- `require_login()` pode estar causando problema

### 3. Problema de Configuração do Servidor
- Apache/Nginx não configurado corretamente
- Permissões de arquivo incorretas

### 4. Redirect Infinito
- Algum redirect causando loop

## ✅ Soluções

### Solução 1: Verificar Logs de Erro

No servidor, verifique os logs:
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log
```

### Solução 2: Testar Diretamente

Tente acessar:
- `https://privus.com.br/rh/index.php`
- `https://privus.com.br/rh/login.php`
- `https://privus.com.br/rh/manifest.php`

### Solução 3: Verificar SSL

Teste o certificado SSL:
```bash
openssl s_client -connect privus.com.br:443
```

### Solução 4: Verificar Permissões

```bash
cd /home/privus/public_html/rh
chmod 644 index.php
chmod 755 .
```

### Solução 5: Criar index.php Simples para Teste

Crie um arquivo `test_index.php`:

```php
<?php
phpinfo();
```

Acesse: `https://privus.com.br/rh/test_index.php`

Se funcionar, o problema está no `index.php` original.

## 🧪 Debug Rápido

Adicione no início do `index.php`:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Teste 1: PHP está funcionando<br>";
require_once __DIR__ . '/includes/functions.php';
echo "Teste 2: functions.php carregado<br>";
require_once __DIR__ . '/includes/auth.php';
echo "Teste 3: auth.php carregado<br>";
// ... resto do código
```

Isso vai mostrar onde está travando.

---

**Me diga qual teste funcionou para identificar o problema exato!**

