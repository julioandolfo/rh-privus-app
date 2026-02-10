# Correções no Fluxo de Aceitar Solicitação de Feedback

## 📋 Resumo

Implementadas correções para garantir que, ao aceitar uma solicitação de feedback, o usuário seja redirecionado automaticamente para a página de envio de feedback com o solicitante já pré-selecionado.

---

## 🔧 Correções Realizadas

### 1. Dropdown de Conta no Header

**Problema:** O dropdown de conta no header não exibia a foto de perfil cadastrada pelo usuário.

**Arquivos Modificados:**
- `includes/header.php`

**Alterações:**

1. **Busca da foto de perfil no banco de dados:**
   ```php
   // Busca foto de perfil do usuário
   require_once __DIR__ . '/upload_foto.php';
   $pdo = getDB();
   $_foto_perfil_path = null;

   if ($usuario['colaborador_id'] ?? null) {
       // Busca foto do colaborador
       $stmt = $pdo->prepare("SELECT foto FROM colaboradores WHERE id = ?");
       $stmt->execute([$usuario['colaborador_id']]);
       $result = $stmt->fetch(PDO::FETCH_ASSOC);
       $_foto_perfil_path = $result['foto'] ?? null;
   } elseif ($usuario['id'] ?? null) {
       // Busca foto do usuário
       $stmt = $pdo->prepare("SELECT foto FROM usuarios WHERE id = ?");
       $stmt->execute([$usuario['id']]);
       $result = $stmt->fetch(PDO::FETCH_ASSOC);
       $_foto_perfil_path = $result['foto'] ?? null;
   }

   $_foto_perfil_url = get_foto_perfil($_foto_perfil_path, $usuario['nome']);
   ```

2. **Exibição da foto no ícone do dropdown:**
   - Substituiu o ícone genérico por:
     - Foto de perfil (quando cadastrada)
     - Círculo com inicial do nome (quando sem foto)

3. **Exibição da foto dentro do menu dropdown:**
   - Mesma lógica aplicada ao avatar dentro do menu

**Resultado:**
- ✅ Foto cadastrada é exibida no ícone do usuário no header
- ✅ Foto cadastrada é exibida no menu dropdown
- ✅ Fallback para inicial do nome quando não há foto

---

### 2. Redirect após Aceitar Solicitação

**Problema:** Ao aceitar uma solicitação de feedback, o JavaScript não processava o redirect, impedindo que o usuário fosse levado automaticamente para a página de envio de feedback.

**Arquivos Modificados:**
- `pages/feedback_solicitacoes.php` (JavaScript)
- `pages/feedback_enviar.php` (Script de pré-seleção)

**Alterações:**

1. **JavaScript - Processamento do Redirect:**
   ```javascript
   // Se tiver redirect (aceitar solicitação), redireciona após o SweetAlert
   if (data.redirect) {
       Swal.fire({
           text: data.message,
           icon: "success",
           buttonsStyling: false,
           confirmButtonText: "Ok, enviar feedback agora",
           customClass: {
               confirmButton: "btn btn-primary"
           }
       }).then(function() {
           window.location.href = data.redirect;
       });
   } else {
       // Apenas recarrega a lista se não tiver redirect (recusar solicitação)
       ...
   }
   ```

2. **Script de Pré-seleção do Destinatário:**
   ```javascript
   // Garante que o destinatário seja selecionado quando vier de uma solicitação aceita
   document.addEventListener('DOMContentLoaded', function() {
       var destinatarioId = '...'; // Valor do PHP
       
       if (destinatarioId) {
           function trySelectDestinatario() {
               // Verifica se jQuery e Select2 estão disponíveis
               if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
                   setTimeout(trySelectDestinatario, 100);
                   return;
               }
               
               var $ = window.jQuery;
               var $select = $('#destinatario_colaborador_id');
               
               // Aguarda o Select2 ser inicializado
               if (!$select.hasClass('select2-hidden-accessible')) {
                   setTimeout(trySelectDestinatario, 100);
                   return;
               }
               
               // Define o valor e dispara eventos para o Select2 atualizar
               $select.val(destinatarioId).trigger('change.select2');
               
               // Scroll suave para o campo de conteúdo após selecionar
               setTimeout(function() {
                   var conteudoField = document.getElementById('feedback_conteudo');
                   if (conteudoField) {
                       conteudoField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                       conteudoField.focus();
                   }
               }, 500);
           }
           
           setTimeout(trySelectDestinatario, 500);
       }
   });
   ```

**Resultado:**
- ✅ Ao aceitar solicitação, exibe modal de confirmação
- ✅ Após confirmar, redireciona automaticamente para `feedback_enviar.php`
- ✅ O destinatário (solicitante) é pré-selecionado automaticamente
- ✅ Faz scroll suave até o campo de conteúdo do feedback
- ✅ Foca automaticamente no campo de conteúdo para facilitar digitação

---

## 🔄 Fluxo Completo

### Fluxo de Aceitar Solicitação:

1. **Usuário recebe solicitação de feedback**
   - Visualiza em "Minhas Solicitações" (aba "Recebidas")

2. **Clica em "Aceitar"**
   - Abre modal de confirmação
   - Pode adicionar mensagem opcional

3. **Confirma aceitação**
   - API `responder_solicitacao.php` processa
   - Atualiza status para "aceita"
   - Adiciona pontos (+20)
   - Envia notificações ao solicitante
   - Retorna `redirect` na resposta

4. **JavaScript processa resposta**
   - Mostra SweetAlert de sucesso
   - Botão: "Ok, enviar feedback agora"
   - Ao confirmar, executa: `window.location.href = data.redirect`

5. **Página de envio carrega**
   - URL: `feedback_enviar.php?solicitacao_id=123&destinatario_id=c_456`
   - PHP captura `destinatario_id` em `$destinatario_pre_selecionado`
   - Renderiza select com colaborador marcado como `selected`

6. **JavaScript pré-seleciona destinatário**
   - Aguarda Select2 ser inicializado
   - Força seleção: `$select.val(destinatarioId).trigger('change.select2')`
   - Faz scroll até campo de conteúdo
   - Foca no campo de conteúdo

7. **Usuário pode enviar feedback**
   - Destinatário já está selecionado
   - Apenas precisa preencher conteúdo e avaliar
   - Envia normalmente

---

## 🧪 Como Testar

### Teste 1: Foto de Perfil no Header
1. Faça login no sistema
2. Acesse "Minha Conta"
3. Faça upload de uma foto de perfil
4. Verifique se a foto aparece:
   - No ícone do usuário (canto superior direito)
   - No dropdown ao clicar no ícone

### Teste 2: Fluxo Completo de Solicitação
1. **Usuário A solicita feedback para Usuário B**
   - Entre como Usuário A
   - Vá em "Solicitar Feedback"
   - Selecione Usuário B
   - Envie solicitação

2. **Usuário B recebe e aceita**
   - Entre como Usuário B
   - Vá em "Minhas Solicitações"
   - Aba "Recebidas"
   - Clique em "Aceitar"
   - Confirme

3. **Verificar redirect automático**
   - ✅ Deve ser redirecionado para página de envio
   - ✅ Usuário A deve estar pré-selecionado no dropdown
   - ✅ Scroll automático até campo de conteúdo
   - ✅ Campo de conteúdo deve estar focado

4. **Enviar feedback**
   - Preencha conteúdo
   - Avalie itens
   - Envie feedback

5. **Verificar feedback enviado**
   - ✅ Feedback deve ser registrado
   - ✅ Usuário A deve receber notificação
   - ✅ Status da solicitação deve ser vinculado ao feedback

---

## 📝 Observações Técnicas

### Formato do ID do Destinatário

O sistema usa um formato especial para IDs:
- `c_123` - Colaborador com ID 123
- `u_456` - Usuário com ID 456

Isso permite diferenciar entre usuários com `colaborador_id` e usuários sem vinculação.

### API Response (responder_solicitacao.php)

Quando ação é "aceitar":
```json
{
  "success": true,
  "message": "Solicitação aceita! Você será redirecionado para enviar o feedback.",
  "redirect": "../pages/feedback_enviar.php?solicitacao_id=123&destinatario_id=c_456",
  "pontos_ganhos": 20,
  "pontos_totais": 1234
}
```

### Select2 Pré-seleção

O Select2 respeita o atributo `selected` do HTML:
```html
<option value="c_123" selected data-foto="...">João Silva</option>
```

Mas para garantir renderização correta, o JavaScript força a seleção após inicialização:
```javascript
$('#destinatario_colaborador_id').val('c_123').trigger('change.select2');
```

---

## ✅ Checklist de Validação

- [x] Foto de perfil aparece no header
- [x] Foto de perfil aparece no dropdown
- [x] Fallback para inicial quando sem foto
- [x] Redirect automático ao aceitar solicitação
- [x] Destinatário pré-selecionado na página de envio
- [x] Select2 renderiza corretamente o valor pré-selecionado
- [x] Scroll automático até campo de conteúdo
- [x] Foco automático no campo de conteúdo
- [x] Feedback pode ser enviado normalmente
- [x] Vinculação entre solicitação e feedback enviado

---

## 🎯 Melhorias Implementadas

1. **UX Melhorada:**
   - Foto pessoal visível no header
   - Fluxo contínuo de aceitar → enviar feedback
   - Menos cliques necessários

2. **Automação:**
   - Pré-seleção automática do destinatário
   - Scroll e foco automáticos
   - Redirect inteligente

3. **Feedback Visual:**
   - Mensagens claras em cada etapa
   - Botão específico: "Ok, enviar feedback agora"
   - Loading indicators

4. **Robustez:**
   - Verificações de disponibilidade de jQuery/Select2
   - Retry automático se componentes ainda não carregaram
   - Logs no console para debug

---

## 📅 Data da Correção

**Data:** 06/02/2026
**Desenvolvedor:** Sistema IA
**Versão:** 1.1

