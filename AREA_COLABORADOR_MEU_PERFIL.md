# 👤 Área do Colaborador - Meu Perfil

## 🎯 Objetivo

Criar uma área completa onde o colaborador pode visualizar todas as suas informações de forma intuitiva e amigável, similar ao que RH/ADMIN vê em `colaborador_view.php`, mas adaptado para visualização própria.

---

## ✨ O Que Foi Criado

### **1. Página Principal: `meu_perfil.php`**

Uma página moderna e responsiva com:
- ✅ **Card de Perfil Destaque** com foto e informações principais
- ✅ **4 Cards de Estatísticas** (Banco de Horas, Horas Extras, Cursos, Bônus)
- ✅ **Sistema de Tabs** para organizar informações
- ✅ **Design responsivo** e amigável
- ✅ **Cores e ícones** intuitivos

### **2. Tabs de Informações**

Criamos 6 tabs completas com todas as informações:

#### **Tab 1: Dados Pessoais** (`includes/meu_perfil_tabs/dados_pessoais.php`)
- 📋 Informações profissionais (cargo, setor, empresa)
- 👤 Informações pessoais (CPF, RG, telefone, e-mail)
- 👔 Líder direto (se houver)
- 📍 Endereço completo

#### **Tab 2: Banco de Horas** (`includes/meu_perfil_tabs/banco_horas.php`)
- ⏰ Saldo atual em destaque
- 📊 Histórico completo de movimentações
- 🎨 Cores indicativas (verde para positivo, vermelho para negativo)
- 📋 Detalhes de cada movimentação (data, tipo, origem, quantidade)

#### **Tab 3: Horas Extras** (`includes/meu_perfil_tabs/horas_extras.php`)
- 📅 Lista de todas as horas extras
- 💰 Valores calculados (quando aplicável)
- 🏦 Distinção entre pagamento em dinheiro e banco de horas
- 📊 Totalizadores (total de horas, valor em dinheiro, horas no banco)

#### **Tab 4: Ocorrências** (`includes/meu_perfil_tabs/ocorrencias.php`)
- 📝 Histórico de ocorrências registradas
- 🎨 Badges coloridos por severidade e status
- 💵 Informações sobre descontos
- 📊 Resumo com totais (pendentes, aprovadas, com desconto)

#### **Tab 5: Bônus** (`includes/meu_perfil_tabs/bonus.php`)
- 🎁 Lista de bônus ativos
- 💰 Valores de cada bônus
- 📅 Períodos de validade
- 📊 Total de bônus e valor acumulado

#### **Tab 6: Meus Cursos** (`includes/meu_perfil_tabs/cursos.php`)
- 📚 Estatísticas gerais (concluídos, em andamento, certificados)
- 🎓 Lista de cursos em andamento com progresso visual
- 📈 Percentual de conclusão de cada curso
- 🔗 Link direto para continuar estudando

---

## 📁 Estrutura de Arquivos Criados

```
pages/
  └── meu_perfil.php                    # Página principal

includes/
  ├── menu.php                          # Atualizado com link "Meu Perfil"
  ├── permissions.php                   # Atualizado com permissões
  └── meu_perfil_tabs/                  # Pasta com as tabs
      ├── dados_pessoais.php
      ├── banco_horas.php
      ├── horas_extras.php
      ├── ocorrencias.php
      ├── bonus.php
      └── cursos.php
```

---

## 🎨 Design e UX

### **Elementos Visuais:**

1. **Card de Perfil com Gradiente**
   - Fundo em gradiente roxo/azul
   - Foto circular destacada
   - Informações principais em branco

2. **Cards de Estatísticas**
   - Hover com elevação
   - Ícones coloridos
   - Números grandes e legíveis

3. **Sistema de Tabs**
   - Ícones em cada tab
   - Cores consistentes
   - Navegação intuitiva

4. **Badges e Indicadores**
   - Cores semânticas (verde = positivo, vermelho = negativo)
   - Tamanhos adequados
   - Informações claras

### **Responsividade:**
- ✅ Mobile First
- ✅ Grid system Bootstrap
- ✅ Cards adaptáveis
- ✅ Tabelas responsivas

---

## 🔐 Permissões e Segurança

### **Controle de Acesso:**
- ✅ Apenas colaboradores podem acessar
- ✅ Cada colaborador vê apenas suas próprias informações
- ✅ Sem opções de edição (somente visualização)
- ✅ Permissão definida em `permissions.php`

### **Validações:**
```php
// Verifica se é colaborador
if (!is_colaborador()) {
    redirect('dashboard.php', 'Acesso negado', 'error');
}

// Pega ID do colaborador logado
$colaborador_id = $usuario['colaborador_id'] ?? null;
```

---

## 🔗 Navegação

### **Como Acessar:**

1. **Pelo Menu Lateral:**
   - Dashboard → **Meu Perfil**
   - Ícone: `ki-profile-circle`
   - Posição: Logo abaixo do Dashboard

2. **URL Direta:**
   - `pages/meu_perfil.php`

### **Menu:**
```php
<?php if (is_colaborador() && can_access_page('meu_perfil.php')): ?>
<div class="menu-item">
    <a class="menu-link" href="meu_perfil.php">
        <span class="menu-icon">
            <i class="ki-duotone ki-profile-circle fs-2">
                ...
            </i>
        </span>
        <span class="menu-title">Meu Perfil</span>
    </a>
</div>
<?php endif; ?>
```

---

## 📊 Informações Exibidas

### **O que o colaborador pode ver:**

| Categoria | Informações |
|-----------|-------------|
| **Dados Pessoais** | Nome, CPF, RG, Telefone, E-mail, Endereço, Data de Nascimento |
| **Profissionais** | Cargo, Setor, Empresa, Nível Hierárquico, Líder, Contrato, Data de Início |
| **Banco de Horas** | Saldo atual, Histórico completo, Movimentações detalhadas |
| **Horas Extras** | Lista completa, Valores, Tipo de pagamento, Totalizadores |
| **Ocorrências** | Histórico, Status, Severidade, Descontos aplicados |
| **Bônus** | Bônus ativos, Valores, Períodos, Observações |
| **Cursos** | Em andamento, Concluídos, Progresso, Certificados |

### **O que o colaborador NÃO pode fazer:**

- ❌ Editar informações pessoais
- ❌ Deletar ocorrências
- ❌ Adicionar/remover horas extras
- ❌ Modificar bônus
- ❌ Alterar banco de horas

---

## 🎯 Benefícios

### **Para o Colaborador:**
- ✅ Acesso fácil às suas informações
- ✅ Transparência sobre ocorrências e descontos
- ✅ Acompanhamento do banco de horas
- ✅ Visualização de progresso em cursos
- ✅ Interface amigável e intuitiva

### **Para a Empresa:**
- ✅ Reduz perguntas ao RH
- ✅ Aumenta transparência
- ✅ Melhora engajamento
- ✅ Facilita autogestão

---

## 🚀 Como Usar

### **Colaborador:**

1. **Fazer Login** no sistema
2. **Clicar em "Meu Perfil"** no menu lateral
3. **Navegar pelas tabs** para ver diferentes informações
4. **Visualizar** dados, históricos e estatísticas

### **RH/ADMIN:**

Não há ações necessárias. A página funciona automaticamente para colaboradores que:
- ✅ Têm login no sistema
- ✅ Estão com role `COLABORADOR`
- ✅ Possuem `colaborador_id` vinculado

---

## 🔧 Manutenção

### **Adicionar Nova Tab:**

1. Criar arquivo em `includes/meu_perfil_tabs/nova_tab.php`
2. Adicionar tab no HTML principal (`meu_perfil.php`):

```php
<!-- No menu de tabs -->
<li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#tab_nova">
        <i class="ki-duotone ki-icon fs-2 me-2">
            ...
        </i>
        Nova Tab
    </a>
</li>

<!-- No conteúdo -->
<div class="tab-pane fade" id="tab_nova">
    <?php require __DIR__ . '/../includes/meu_perfil_tabs/nova_tab.php'; ?>
</div>
```

### **Customizar Cards:**

Os cards de estatísticas podem ser facilmente customizados editando a seção de "Cards de Estatísticas" em `meu_perfil.php`.

---

## 📝 Notas Técnicas

### **Dependências:**
- Bootstrap 5
- Metronic Theme (ícones e estilos)
- jQuery (para tabs)
- SweetAlert2 (alertas, se necessário)

### **Compatibilidade:**
- ✅ Browsers modernos (Chrome, Firefox, Safari, Edge)
- ✅ Mobile e Tablet
- ✅ IE11+ (com limitações de CSS)

### **Performance:**
- Carrega apenas dados do colaborador logado
- Queries otimizadas com índices
- Limit de 6 meses no histórico por padrão

---

## 🎉 Resultado Final

Uma área completa e moderna onde o colaborador pode:
- 👀 **Visualizar** todas as suas informações
- 📊 **Acompanhar** seu progresso e históricos
- 💰 **Entender** descontos e bônus
- 🎓 **Continuar** seus estudos
- ⏰ **Monitorar** banco de horas

Tudo isso de forma **segura**, **intuitiva** e **profissional**!

---

**Última atualização:** Janeiro 2025
**Versão:** 1.0
