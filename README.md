# Woo Envios - Plugin de Frete por Distância

Plugin para WooCommerce que calcula frete baseado na distância real entre a loja e o cliente.

## ✨ Recursos

- **Cálculo de distância real** via Google Distance Matrix API
- **Precificação dinâmica** (horários de pico, fim de semana, clima)
- **Curva regressiva de preços** (mais justo para distâncias longas)
- **Cache inteligente** para economizar requisições à API
- **Circuit breaker** para proteção contra falhas
- **Logs detalhados** para debug

## 🚀 Instalação

1. Faça upload da pasta `woo-envios` para `/wp-content/plugins/`
2. Ative o plugin em WordPress → Plugins
3. Configure em WooCommerce → Woo Envios

## ⚙️ Configuração Inicial

1. **Google Maps API Key**: Obtenha em [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. **Coordenadas da Loja**: Arraste o marcador no mapa para sua localização
3. **Faixas de Preço**: Configure ou use as faixas padrão

### Aplicar Nova Tabela de Preços

As faixas padrão já estão no código com a **curva regressiva otimizada**.

Para aplicar:
1. Acesse **WooCommerce → Woo Envios**
2. **Delete todas as faixas** clicando no × de cada uma
3. Clique em **Salvar Alterações**
4. Recarregue a página (F5)
5. As novas faixas aparecerão automaticamente ✨

## 📊 Estrutura de Preços (Curva Regressiva)

| Distância | Preço | Incremento |
|-----------|-------|------------|
| 1-3 km | R$ 7,90 | Tarifa mínima |
| 4-6 km | R$ 8,90-10,90 | +R$ 1,00/km |
| 7-12 km | R$ 11,50-14,50 | +R$ 0,60/km |
| 13-30 km | R$ 15,00-23,50 | +R$ 0,50/km |

**Vantagens**:
- Tarifa mínima cobre custo real do entregador
- Preços competitivos em distâncias longas
- Economia de até R$ 10,50 vs modelo linear

## 🔧 Requisitos

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Google Maps API Key (opcional mas recomendado)

## 📝 Licença

GPL v2 ou posterior
