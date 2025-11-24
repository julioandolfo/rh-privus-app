# 📋 Como Funcionam os Templates de Contrato

## 🎯 Visão Geral

Os templates de contrato são modelos reutilizáveis que permitem criar contratos rapidamente, preenchendo automaticamente dados do colaborador e informações do contrato.

---

## 📝 Criando um Template

### **Passo 1: Acessar a Criação**
1. Vá em **Colaboradores > Contratos**
2. Clique em **Templates de Contrato** (ou acesse diretamente)
3. Clique em **Adicionar Template**

### **Passo 2: Preencher Informações**
- **Nome do Template**: Ex: "Contrato de Trabalho CLT"
- **Descrição**: Explicação breve do template
- **Conteúdo**: Use o editor TinyMCE para criar o template

### **Passo 3: Usar Variáveis Dinâmicas**

No conteúdo do template, você pode usar variáveis que serão substituídas automaticamente quando criar um contrato:

#### **Dados do Colaborador**
```
{{colaborador.nome_completo}}      → João Silva
{{colaborador.cpf}}                 → 123.456.789-00
{{colaborador.rg}}                  → 12.345.678-9
{{colaborador.email_pessoal}}       → joao@email.com
{{colaborador.telefone}}            → (11) 98765-4321
{{colaborador.data_nascimento}}     → 15/03/1990
{{colaborador.endereco_completo}}   → Rua Exemplo, 123
{{colaborador.cidade}}              → São Paulo
{{colaborador.estado}}              → SP
{{colaborador.cep}}                 → 01234-567
```

#### **Dados da Empresa**
```
{{colaborador.empresa_nome}}        → Empresa XYZ Ltda
{{colaborador.setor_nome}}          → Recursos Humanos
{{colaborador.cargo_nome}}          → Analista de RH
{{colaborador.salario}}             → R$ 5.000,00
{{colaborador.data_admissao}}       → 01/01/2024
```

#### **Dados do Contrato**
```
{{contrato.titulo}}                 → Título informado ao criar
{{contrato.descricao_funcao}}       → Descrição da função
{{contrato.data_criacao}}           → Data de criação
{{contrato.data_vencimento}}        → Data de vencimento
{{contrato.observacoes}}            → Observações adicionais
```

#### **Data/Hora Atual**
```
{{data_atual}}                      → 15/03/2024
{{hora_atual}}                      → 14:30
{{data_formatada}}                  → 15 de março de 2024
```

### **Passo 4: Exemplo de Template**

```html
<h1>CONTRATO DE TRABALHO</h1>

<p>
    <strong>CONTRATANTE:</strong> {{colaborador.empresa_nome}}<br>
    <strong>CONTRATADO:</strong> {{colaborador.nome_completo}}, 
    CPF {{colaborador.cpf}}, RG {{colaborador.rg}}, 
    residente e domiciliado em {{colaborador.endereco_completo}}, 
    {{colaborador.cidade}}/{{colaborador.estado}}, CEP {{colaborador.cep}}.
</p>

<h2>CLÁUSULA PRIMEIRA - DO OBJETO</h2>
<p>
    O CONTRATADO será admitido para exercer a função de 
    <strong>{{colaborador.cargo_nome}}</strong> no setor 
    <strong>{{colaborador.setor_nome}}</strong>, 
    com as seguintes atribuições:
</p>

<p>{{contrato.descricao_funcao}}</p>

<h2>CLÁUSULA SEGUNDA - DA REMUNERAÇÃO</h2>
<p>
    O CONTRATADO receberá salário mensal de 
    <strong>{{colaborador.salario}}</strong>, 
    com vencimento no dia 5 de cada mês.
</p>

<h2>CLÁUSULA TERCEIRA - DA VIGÊNCIA</h2>
<p>
    Este contrato tem início em {{contrato.data_criacao}} e 
    <?php if (!empty($contrato_data['data_vencimento'])): ?>
    término em {{contrato.data_vencimento}}.
    <?php else: ?>
    prazo indeterminado.
    <?php endif; ?>
</p>

<p>
    São Paulo, {{data_formatada}}.
</p>

<p>
    _________________________<br>
    {{colaborador.nome_completo}}<br>
    CPF: {{colaborador.cpf}}
</p>
```

---

## 🚀 Usando um Template

### **Passo 1: Criar Novo Contrato**
1. Vá em **Colaboradores > Contratos**
2. Clique em **Novo Contrato**

### **Passo 2: Selecionar Colaborador**
- Use o campo de busca para encontrar o colaborador
- O sistema carregará automaticamente os dados dele

### **Passo 3: Selecionar Template (Opcional)**
- Escolha um template na lista
- OU deixe em branco para criar conteúdo customizado

### **Passo 4: Preencher Dados do Contrato**
- **Título**: Nome do contrato
- **Descrição da Função**: Obrigatório - descreva as funções do colaborador
- **Data de Criação**: Data do contrato
- **Data de Vencimento**: (opcional) Quando o contrato expira
- **Observações**: (opcional) Informações adicionais

### **Passo 5: Visualizar Preview**
- Clique em **Atualizar Preview** para ver como ficará
- As variáveis serão substituídas automaticamente
- Revise antes de enviar

### **Passo 6: Enviar para Assinatura**
- **Salvar como Rascunho**: Salva sem enviar
- **Enviar para Assinatura**: Cria PDF e envia para Autentique

---

## 🔄 Fluxo Completo

```
1. Criar Template
   ↓
2. Definir variáveis no template (ex: {{colaborador.nome_completo}})
   ↓
3. Salvar template
   ↓
4. Criar novo contrato
   ↓
5. Selecionar colaborador
   ↓
6. Selecionar template
   ↓
7. Preencher dados do contrato (título, descrição da função, etc)
   ↓
8. Visualizar preview (variáveis são substituídas automaticamente)
   ↓
9. Adicionar testemunhas (opcional)
   ↓
10. Enviar para assinatura
    ↓
11. Sistema gera PDF
    ↓
12. Envia para Autentique
    ↓
13. Colaborador recebe link de assinatura
    ↓
14. Testemunhas recebem links públicos
    ↓
15. Quando todos assinam, contrato fica completo
```

---

## 💡 Dicas e Boas Práticas

### **1. Organize seus Templates**
- Use nomes descritivos: "Contrato CLT", "Contrato Estágio", etc.
- Adicione descrições para facilitar identificação

### **2. Use Variáveis Consistentemente**
- Sempre use as variáveis ao invés de texto fixo
- Isso garante que os dados sempre estarão atualizados

### **3. Teste o Preview**
- Sempre visualize o preview antes de enviar
- Verifique se todas as variáveis foram substituídas

### **4. Templates Ativos vs Inativos**
- Templates **ativos** aparecem na lista ao criar contrato
- Templates **inativos** ficam ocultos mas não são excluídos
- Use isso para arquivar templates antigos

### **5. Conteúdo Customizado**
- Se não usar template, pode criar conteúdo do zero
- Ainda pode usar variáveis mesmo sem template
- Útil para contratos únicos ou específicos

---

## ❓ Perguntas Frequentes

### **Posso editar um template depois de criar contratos com ele?**
Sim! Mas os contratos já criados não serão alterados. Apenas novos contratos usarão a versão atualizada.

### **Posso excluir um template?**
Sim, mas apenas se nenhum contrato estiver usando ele. O sistema avisa antes de excluir.

### **As variáveis são obrigatórias?**
Não! Você pode criar templates sem variáveis, mas elas facilitam muito o trabalho.

### **Posso usar HTML no template?**
Sim! O editor TinyMCE permite formatação completa (negrito, listas, tabelas, etc).

### **O que acontece se uma variável não tiver valor?**
A variável será substituída por uma string vazia (""). Por exemplo, se o colaborador não tiver RG cadastrado, `{{colaborador.rg}}` ficará vazio.

---

## 🎨 Exemplo Prático Completo

### **Template Criado:**
```html
<h1>TERMO DE COMPROMISSO</h1>

<p>
    Eu, <strong>{{colaborador.nome_completo}}</strong>, 
    CPF {{colaborador.cpf}}, comprometo-me a:
</p>

<ul>
    <li>Exercer a função de {{colaborador.cargo_nome}}</li>
    <li>Respeitar os horários estabelecidos</li>
    <li>Cumprir com as responsabilidades do cargo</li>
</ul>

<p>
    <strong>Funções específicas:</strong><br>
    {{contrato.descricao_funcao}}
</p>

<p>
    Data: {{data_formatada}}<br>
    Assinatura: _________________________
</p>
```

### **Ao Criar Contrato:**
- **Colaborador**: João Silva (CPF: 123.456.789-00, Cargo: Analista)
- **Descrição da Função**: "Analisar processos de RH e elaborar relatórios"

### **Resultado Final:**
```html
<h1>TERMO DE COMPROMISSO</h1>

<p>
    Eu, <strong>João Silva</strong>, 
    CPF 123.456.789-00, comprometo-me a:
</p>

<ul>
    <li>Exercer a função de Analista de RH</li>
    <li>Respeitar os horários estabelecidos</li>
    <li>Cumprir com as responsabilidades do cargo</li>
</ul>

<p>
    <strong>Funções específicas:</strong><br>
    Analisar processos de RH e elaborar relatórios
</p>

<p>
    Data: 15 de março de 2024<br>
    Assinatura: _________________________
</p>
```

---

**Pronto! Agora você sabe como usar os templates de contrato! 🎉**

