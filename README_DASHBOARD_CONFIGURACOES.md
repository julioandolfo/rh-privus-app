# 🎨 Configurações Personalizadas do Dashboard

## 📋 Visão Geral

Sistema completo de personalização do dashboard com opções avançadas para ajustar margem, altura, densidade e tema visual.

## ✨ Funcionalidades

### 1. **Margem entre Cards**
- 📏 Ajuste de **0px a 48px** (incrementos de 4px)
- Slider interativo com preview em tempo real
- Valor padrão: **16px**

### 2. **Altura das Células**
- 📐 Ajuste de **50px a 120px** (incrementos de 10px)
- Controla o tamanho vertical das células do grid
- Valor padrão: **70px**

### 3. **Densidade do Layout**
Três predefinições para diferentes necessidades:

| Densidade | Margem | Altura | Descrição |
|-----------|--------|--------|-----------|
| **Compacto** | 8px | 60px | Máximo aproveitamento de espaço |
| **Padrão** | 16px | 70px | Equilíbrio entre espaço e conforto visual |
| **Espaçado** | 24px | 80px | Mais respiração entre elementos |

### 4. **Tema do Grid** (Modo Edição)
Personaliza as cores visuais durante a edição:

- 🔵 **Azul** (padrão)
- 🟢 **Verde**
- 🟣 **Roxo**
- 🟠 **Laranja**

### 5. **Animações**
- Toggle para habilitar/desabilitar animações suaves
- Melhora a experiência visual ao reorganizar cards

## 🚀 Como Usar

### Acessar Configurações

1. Clique em **"Personalizar Dashboard"**
2. Clique no botão **"Configurações"** ⚙️
3. Ajuste as opções desejadas
4. Clique em **"Aplicar Configurações"**

### Restaurar Padrão

Dentro do modal de configurações:
- Clique em **"Restaurar Padrão"** na seção de alerta amarelo
- Depois clique em **"Aplicar Configurações"** para salvar

## 🗄️ Estrutura de Banco de Dados

### Nova Tabela: `dashboard_preferences`

```sql
CREATE TABLE dashboard_preferences (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT(11) NOT NULL,
    configuracao_chave VARCHAR(100) NOT NULL,
    configuracao_valor TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_chave (usuario_id, configuracao_chave),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
```

### Estrutura de Dados

As configurações são armazenadas em JSON:

```json
{
  "margin": 16,
  "cellHeight": 70,
  "densidade": "padrao",
  "temaGrid": "azul",
  "animate": true
}
```

## 📦 Instalação

1. **Execute a migração SQL:**
```bash
mysql -u usuario -p nome_banco < migracao_dashboard_preferences.sql
```

2. **Reinicie o sistema** (se necessário)

3. **Acesse o dashboard** e comece a personalizar!

## 🎯 Casos de Uso

### Dashboard Compacto (Muitas Informações)
```
- Densidade: Compacto
- Margem: 8px
- Altura: 60px
```

### Dashboard Confortável (Visualização)
```
- Densidade: Espaçado
- Margem: 24px
- Altura: 80px
```

### Dashboard Corporativo
```
- Densidade: Padrão
- Tema: Azul
- Animações: Desabilitadas
```

## 🔧 API Endpoints

### Carregar Configurações
```
GET /api/dashboard/carregar_config.php
```

Retorna:
```json
{
  "success": true,
  "cards": [...],
  "config": {
    "margin": 16,
    "cellHeight": 70,
    "densidade": "padrao",
    "temaGrid": "azul",
    "animate": true
  }
}
```

### Salvar Configurações
```
POST /api/dashboard/salvar_config.php
Content-Type: application/json
```

Body:
```json
{
  "cards": [...],
  "config": {
    "margin": 16,
    "cellHeight": 70,
    "densidade": "padrao",
    "temaGrid": "azul",
    "animate": true
  }
}
```

## 🎨 Personalização de Temas

Os temas definem:
- **primary**: Cor da borda no modo edição
- **bg**: Cor de fundo dos cards no modo edição
- **bgHover**: Cor de fundo ao passar o mouse

### Adicionar Novo Tema

No JavaScript do dashboard:

```javascript
const temasCores = {
    // ... temas existentes ...
    vermelho: { 
        primary: '#dc3545', 
        bg: 'rgba(220, 53, 69, 0.05)', 
        bgHover: 'rgba(220, 53, 69, 0.1)' 
    }
};
```

No HTML do modal:

```html
<div class="col-6">
    <input type="radio" class="btn-check" name="tema_grid" id="tema_vermelho" value="vermelho">
    <label class="btn btn-outline btn-outline-dashed btn-outline-danger w-100 p-4" for="tema_vermelho">
        <span class="d-block fw-bold mb-2">Vermelho</span>
        <span class="d-block" style="height: 4px; background: #dc3545;"></span>
    </label>
</div>
```

## 📱 Responsividade

As configurações se aplicam a todos os tamanhos de tela, mas considere:

- **Desktop**: Todas as densidades funcionam bem
- **Tablet**: Densidade Padrão ou Compacto recomendados
- **Mobile**: GridStack não é ideal para mobile (layout Bootstrap é mantido)

## ⚠️ Observações Importantes

1. **Modo Edição**: As configurações só podem ser alteradas no modo de edição
2. **Persistência**: As configurações são salvas automaticamente ao aplicar
3. **Por Usuário**: Cada usuário tem suas próprias preferências
4. **Performance**: Animações podem ser desabilitadas em computadores mais lentos

## 🐛 Troubleshooting

### Cards não se ajustam após mudanças
**Solução**: Saia do modo edição e entre novamente

### Configurações não salvam
**Solução**: Verifique se a tabela `dashboard_preferences` foi criada

### Temas não aplicam
**Solução**: Limpe o cache do navegador (Ctrl+F5)

## 📈 Melhorias Futuras

- [ ] Preview ao vivo das configurações antes de aplicar
- [ ] Mais opções de tema (escuro, claro, alto contraste)
- [ ] Densidade personalizada (valores manuais)
- [ ] Exportar/Importar configurações
- [ ] Configurações por dispositivo
- [ ] Atalhos de teclado para ajustes rápidos

## 📝 Changelog

### v1.0.0 (2025-01-23)
- ✅ Implementação inicial
- ✅ 5 configurações principais
- ✅ 4 temas de cores
- ✅ 3 predefinições de densidade
- ✅ Persistência no banco de dados
- ✅ Interface intuitiva com sliders

---

**Desenvolvido para RH Privus** 🚀

