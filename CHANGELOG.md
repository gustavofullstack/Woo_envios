## [3.1.31] - 2025-12-23


## [3.1.34] - 2025-12-23

48b8a8e - Fix: Permitir múltiplos métodos de envio simultaneamente (Flash + Correios)
256925e - docs: update CHANGELOG.md for v3.1.33 [skip ci]

---


## [3.1.33] - 2025-12-23

0c61d66 - feat: add initial WooCommerce shipping plugin
945bb1a - docs: update CHANGELOG.md for v3.1.32 [skip ci]

---


## [3.1.32] - 2025-12-23

4bf3d38 - test: dispara workflow para testar changelog AI
75b73cb - fix: corrige erros YAML no workflow (heredoc/escape)
5990e64 - feat: melhora prompt DeepSeek para changelogs únicos e específicos
939dd6d - docs: update CHANGELOG.md for v3.1.31 [skip ci]

---

- fix: corrige carregamento duplicado do Geocoder e métodos Logger
- feat: integra DeepSeek AI para gerar changelogs inteligentes


# Changelog

Todas as mudanças notáveis deste projeto são documentadas aqui.

## [3.1.26] - 2024-12-20

### ✨ Novidades
- **Método de Entrega SuperFrete** - Agora aparece nas zonas de entrega do WooCommerce
  - Adicione "SuperFrete (PAC/SEDEX/Mini)" em Configurações → Entrega
  - Funciona igual ao método "Flash" mas para clientes fora do raio

### 🐛 Correções
- Corrigido prazo de entrega mostrando "?" no debug
- Campo `deadline` mapeado corretamente
- Método `calculate_debug()` para testes sem WooCommerce package

---

## [3.1.21] - 2024-12-20

### ✨ Novidades
- **UI Admin Modernizada** - Design minimalista com cards, sombras e animações
- **SuperFrete Integrado** - Cotações PAC/SEDEX/Mini em tempo real

### 🐛 Correções
- **Bug Crítico** - Checkboxes de serviços agora salvam corretamente
- Função `sanitize_superfrete_services()` processa arrays

### 📦 Melhorias
- Release notes automáticas do CHANGELOG.md
- Arquivos de teste excluídos do build

---

## [3.1.20] - 2024-12-20

### ✨ Novidades
- **Nova UI Admin** - Design moderno e minimalista
  - Cards com sombras suaves e hover effects
  - Checkboxes de serviços em grid estilizado
  - Variáveis CSS (design tokens)
  - Alertas com gradientes
  - 100% responsivo

### 🐛 Correções
- **Bug dos Serviços** - Checkboxes de PAC/SEDEX/Mini Envios agora salvam corretamente
- Adicionado `sanitize_superfrete_services()` para processar arrays

### 🔧 Mudanças Técnicas
- Removido código legado do Melhor Envio
- Removida integração direta com API Correios
- Agora usa exclusivamente **SuperFrete** para cotações
- CSS admin reescrito de 82 para 380 linhas

---

## [3.1.19] - 2024-12-20

### ✨ Novidades
- **Integração SuperFrete** - Cotações de frete em tempo real
  - PAC, SEDEX, Mini Envios
  - Cache de 12 horas
  - Suporte a margem de lucro

### 🔧 Mudanças
- Simplificação do código de frete
- Remoção de dependências antigas

---

## [3.1.18] - 2024-12-19

### 🐛 Correções
- Correção de constantes PHP causando erro crítico
- Tabela de cache criada automaticamente

---

## [3.0.0] - 2024-12-18

### ✨ Lançamento Inicial
- Frete por raio escalonado
- Integração Google Maps
- Precificação dinâmica
- Condições climáticas
