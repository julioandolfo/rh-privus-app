# 🔗 Guia: Configuração de Múltiplos Webhooks Autentique

## 🎯 Por que 2 Webhooks?

O Autentique permite criar webhooks separados para diferentes tipos de eventos. Isso oferece:
- ✅ **Melhor organização**: Separa eventos de documento e assinatura
- ✅ **Segurança**: Cada webhook tem seu próprio secret único
- ✅ **Flexibilidade**: Pode configurar URLs diferentes se necessário

---

## 📋 Passo a Passo Completo

### **1️⃣ Criar Webhook de Documento no Autentique**

1. Acesse: https://app.autentique.com.br
2. Vá em **Configurações > Webhooks**
3. Clique em **"Adicionar Endpoint"**
4. Preencha:
   ```
   Nome: Privus RH - Documentos
   URL: https://privus.com.br/rh/api/contratos/webhook.php
   Formato: JSON
   Tipo do evento: Documento
   ```
5. Marque os eventos:
   - ✅ `document.created`
   - ✅ `document.updated`
   - ✅ `document.finished` ⭐ **MUITO IMPORTANTE**
   - ✅ `document.cancelled`
   - ✅ `document.viewed` (opcional)
6. Clique em **Salvar**
7. **COPIE O SECRET** que aparece após salvar (ex: `whsec_abc123xyz...`)

### **2️⃣ Criar Webhook de Assinatura no Autentique**

1. Ainda na mesma página, clique em **"Adicionar Endpoint"** novamente
2. Preencha:
   ```
   Nome: Privus RH - Assinaturas
   URL: https://privus.com.br/rh/api/contratos/webhook.php
   Formato: JSON
   Tipo do evento: Assinatura (ou Signer)
   ```
3. Marque os eventos:
   - ✅ `signer.signed` ⭐ **MUITO IMPORTANTE**
   - ✅ `document.signed`
4. Clique em **Salvar**
5. **COPIE O SECRET** que aparece após salvar (será diferente do primeiro)

### **3️⃣ Configurar no Sistema**

1. Acesse: **Configurações > Autentique**
2. Preencha a **API Key** e configure **Sandbox/Produção**
3. Na seção **Webhook de Documento**:
   - Cole a URL: `https://privus.com.br/rh/api/contratos/webhook.php`
   - Cole o **Secret do primeiro webhook** (Documentos)
4. Na seção **Webhook de Assinatura**:
   - Cole a URL: `https://privus.com.br/rh/api/contratos/webhook.php` (pode ser a mesma)
   - Cole o **Secret do segundo webhook** (Assinaturas)
5. Clique em **Salvar Configurações**

---

## 🔐 Como Funciona a Validação

O sistema identifica automaticamente qual secret usar baseado no tipo de evento:

- **Eventos `document.*`** → Usa `webhook_documento_secret`
- **Eventos `signer.*` ou `document.signed`** → Usa `webhook_assinatura_secret`

Se o secret não corresponder, o webhook será rejeitado por segurança.

---

## ✅ Verificação

Após configurar:

1. **Crie um contrato de teste**
2. **Envie para assinatura**
3. **Assine o contrato**
4. **Verifique os logs** em `logs/webhook_autentique.log`
5. **Verifique o status** do contrato no sistema

Se tudo estiver funcionando, você verá nos logs:
```
[2024-03-15 14:30:00] Secret validado com sucesso para evento: document.created
[2024-03-15 14:31:00] Secret validado com sucesso para evento: signer.signed
```

---

## 🐛 Troubleshooting

### **Erro: "Secret inválido"**

- Verifique se copiou o secret correto de cada webhook
- Verifique se não há espaços extras ao colar
- Verifique se salvou as configurações no sistema

### **Webhook não está recebendo eventos**

- Verifique se a URL está correta e acessível
- Verifique se os eventos estão marcados no Autentique
- Verifique os logs em `logs/webhook_autentique.log`

### **Eventos não estão atualizando status**

- Verifique se o `document_id` está correto no banco
- Verifique se o evento está sendo processado (veja logs)
- Verifique se o tipo de evento está correto

---

## 📝 Notas Importantes

- ✅ **Pode usar a mesma URL** para ambos os webhooks
- ✅ **Cada webhook tem seu próprio secret** (obrigatório)
- ✅ **O sistema identifica automaticamente** qual secret usar
- ✅ **Se não configurar secret**, o webhook ainda funciona (menos seguro)

---

## 🔄 Migração de Webhook Único

Se você já tinha um webhook configurado:

1. Execute a migração SQL: `migracao_webhooks_multiplos.sql`
2. Os dados antigos serão migrados automaticamente
3. Configure os novos webhooks no Autentique
4. Atualize os secrets no sistema

