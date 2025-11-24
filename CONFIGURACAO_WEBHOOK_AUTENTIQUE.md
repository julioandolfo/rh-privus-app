# 🔗 Configuração do Webhook Autentique

## 📋 Eventos Recomendados para Ativar

Com base no código do webhook implementado, você deve ativar os seguintes eventos:

### ✅ **Eventos Essenciais (Obrigatórios)**

#### 1. **`document.finished`** ⭐ **MUITO IMPORTANTE**
- **Quando dispara**: Quando o documento é totalmente finalizado (todos assinaram)
- **O que faz**: Atualiza o status do contrato para "assinado"
- **Recomendação**: ✅ **ATIVAR**

#### 2. **`document.signed`** ou **`signer.signed`**
- **Quando dispara**: Quando alguém assina o documento
- **O que faz**: Atualiza o status do signatário e verifica se todos já assinaram
- **Recomendação**: ✅ **ATIVAR** (se disponível)

#### 3. **`document.cancelled`**
- **Quando dispara**: Quando o documento é cancelado
- **O que faz**: Atualiza o status do contrato para "cancelado"
- **Recomendação**: ✅ **ATIVAR**

### 📊 **Eventos Opcionais (Úteis para Logs)**

#### 4. **`document.created`**
- **Quando dispara**: Quando o documento é criado no Autentique
- **O que faz**: Apenas registra no log (não atualiza status)
- **Recomendação**: ⚠️ **OPCIONAL** (útil para auditoria)

#### 5. **`document.updated`**
- **Quando dispara**: Quando o documento é atualizado
- **O que faz**: Apenas registra no log
- **Recomendação**: ⚠️ **OPCIONAL** (útil para auditoria)

#### 6. **`document.viewed`**
- **Quando dispara**: Quando alguém visualiza o documento
- **O que faz**: Apenas registra no log
- **Recomendação**: ⚠️ **OPCIONAL** (útil para saber quem visualizou)

### ❌ **Eventos Não Necessários**

#### 7. **`document.deleted`**
- **Quando dispara**: Quando o documento é deletado
- **O que faz**: Não temos tratamento específico
- **Recomendação**: ❌ **NÃO PRECISA ATIVAR**

---

## 🎯 Configuração Recomendada

### **Mínimo Essencial:**
```
✅ document.finished
✅ document.signed (ou signer.signed)
✅ document.cancelled
```

### **Configuração Completa (Recomendada):**
```
✅ document.created
✅ document.updated
✅ document.finished
✅ document.signed (ou signer.signed)
✅ document.cancelled
✅ document.viewed
```

---

## 📝 Como Configurar no Autentique

### **⚠️ IMPORTANTE: Você precisa criar 2 webhooks separados!**

### **Webhook 1: Eventos de Documento**

1. **Acesse o Dashboard do Autentique**
2. **Vá em Configurações > Webhooks**
3. **Clique em "Adicionar Endpoint"**
4. **Preencha:**
   - **Nome**: Privus RH - Documentos
   - **URL**: `https://privus.com.br/rh/api/contratos/webhook.php`
   - **Formato**: JSON
   - **Tipo do evento**: Documento
5. **Selecione os eventos:**
   - ✅ `document.created`
   - ✅ `document.updated`
   - ✅ `document.finished`
   - ✅ `document.cancelled`
   - ✅ `document.viewed`
6. **Salve e copie o SECRET gerado**
7. **Cole o secret no campo "Secret do Webhook de Documento" no sistema**

### **Webhook 2: Eventos de Assinatura**

1. **Ainda no Dashboard do Autentique**
2. **Clique em "Adicionar Endpoint" novamente**
3. **Preencha:**
   - **Nome**: Privus RH - Assinaturas
   - **URL**: `https://privus.com.br/rh/api/contratos/webhook.php` (pode ser a mesma URL)
   - **Formato**: JSON
   - **Tipo do evento**: Assinatura (ou Signer)
4. **Selecione os eventos:**
   - ✅ `signer.signed`
   - ✅ `document.signed`
5. **Salve e copie o SECRET gerado** (será diferente do primeiro)
6. **Cole o secret no campo "Secret do Webhook de Assinatura" no sistema**

### **Configurar no Sistema**

1. **Acesse**: Configurações > Autentique
2. **Preencha os campos:**
   - **Webhook de Documento URL**: Cole a URL do primeiro webhook
   - **Webhook de Documento Secret**: Cole o secret do primeiro webhook
   - **Webhook de Assinatura URL**: Cole a URL do segundo webhook (pode ser a mesma)
   - **Webhook de Assinatura Secret**: Cole o secret do segundo webhook
3. **Salve**

---

## 🔍 Verificação

Após configurar, você pode verificar se está funcionando:

1. **Crie um contrato de teste**
2. **Envie para assinatura**
3. **Assine o contrato**
4. **Verifique os logs** em `logs/webhook_autentique.log`
5. **Verifique o status** do contrato no sistema

---

## ⚠️ Observações Importantes

### **Eventos que o Sistema Processa:**

O webhook está preparado para processar:

- ✅ `document.signed` - Atualiza signatário e status
- ✅ `signer.signed` - Atualiza signatário e status  
- ✅ `document.cancelled` - Marca contrato como cancelado
- ✅ `document.viewed` - Apenas log (não atualiza status)
- ✅ `document.finished` - Deve marcar como totalmente assinado

### **Eventos que NÃO Processamos:**

- ❌ `document.created` - Apenas log
- ❌ `document.updated` - Apenas log
- ❌ `document.deleted` - Não tratado

---

## 🐛 Troubleshooting

### **Webhook não está recebendo eventos?**

1. Verifique se a URL está correta e acessível
2. Verifique se o servidor aceita requisições POST
3. Verifique os logs em `logs/webhook_autentique.log`
4. Teste a URL manualmente com um POST

### **Eventos não estão atualizando o status?**

1. Verifique se o `document_id` está correto no banco
2. Verifique os logs para ver se o evento está chegando
3. Verifique se o evento está sendo processado corretamente

---

## 📞 Suporte

Se tiver problemas, verifique:
- Logs em `logs/webhook_autentique.log`
- Tabela `contratos_eventos` no banco de dados
- Status HTTP retornado pelo webhook (deve ser 200)

