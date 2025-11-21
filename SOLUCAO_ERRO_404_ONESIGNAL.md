# 🔧 Solução: Erro 404 na API OneSignal

## ❌ Problema

Erro no console:
```
Failed to load resource: the server responded with a status of 404
onesignal-init.js:74 Erro ao inicializar OneSignal: Error: Erro ao buscar configurações
```

## ✅ Soluções

### Solução 1: Verificar se tabelas existem

Execute primeiro:
```
http://localhost/rh-privus/criar_tabelas_onesignal.php
```

Ou:
```
http://localhost/rh-privus/executar_migracao_onesignal.php
```

### Solução 2: Testar caminho da API

Acesse diretamente no browser:
```
http://localhost/rh-privus/api/onesignal/config.php
```

**Se retornar JSON** = caminho está correto ✅
**Se retornar 404** = problema de configuração do servidor

### Solução 3: Verificar configuração do Laragon

1. Verifique se o projeto está em: `C:\laragon\www\rh-privus\`
2. Acesse: `http://localhost/rh-privus/`
3. Verifique se outras APIs funcionam: `http://localhost/rh-privus/api/get_colaboradores.php`

### Solução 4: Usar caminho relativo

O código JavaScript já foi atualizado para detectar automaticamente:
- Se está em `/rh-privus/pages/` → usa `../api/onesignal/config.php`
- Se está em `/rh-privus/` → usa `/rh-privus/api/onesignal/config.php`

## 🧪 Teste Rápido

1. Abra o console do browser (F12)
2. Procure por: `Buscando configurações em:`
3. Veja qual caminho está sendo usado
4. Teste esse caminho diretamente no browser

## 📝 Checklist

- [ ] Tabelas criadas no banco
- [ ] Arquivo `api/onesignal/config.php` existe
- [ ] Caminho no console está correto
- [ ] API retorna JSON quando acessada diretamente

---

**Se ainda não funcionar, verifique o console do browser para ver o caminho exato que está sendo usado!**

