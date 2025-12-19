-- =====================================================
-- ATUALIZAÇÃO DIRETA DE FAIXAS DE PREÇO
-- Woo Envios - Curva Regressiva
-- =====================================================
-- 
-- COMO USAR:
-- 1. Acesse phpMyAdmin
-- 2. Selecione seu banco de dados WordPress
-- 3. Vá em SQL
-- 4. Cole este código e execute
-- 
-- OU via terminal:
-- wp db query < update_pricing.sql
-- =====================================================

-- Backup das faixas antigas
INSERT INTO wp_options (option_name, option_value, autoload)
SELECT 
    CONCAT('woo_envios_tiers_backup_', UNIX_TIMESTAMP()) as option_name,
    option_value,
    'no'
FROM wp_options 
WHERE option_name = 'woo_envios_tiers';

-- Atualizar com nova estrutura de preços (curva regressiva)
UPDATE wp_options 
SET option_value = 'a:30:{i:0;a:3:{s:5:"label";s:12:"Raio 1.0 km";s:8:"distance";d:1;s:5:"price";d:7.9;}i:1;a:3:{s:5:"label";s:12:"Raio 2.0 km";s:8:"distance";d:2;s:5:"price";d:7.9;}i:2;a:3:{s:5:"label";s:12:"Raio 3.0 km";s:8:"distance";d:3;s:5:"price";d:7.9;}i:3;a:3:{s:5:"label";s:12:"Raio 4.0 km";s:8:"distance";d:4;s:5:"price";d:8.9;}i:4;a:3:{s:5:"label";s:12:"Raio 5.0 km";s:8:"distance";d:5;s:5:"price";d:9.9;}i:5;a:3:{s:5:"label";s:12:"Raio 6.0 km";s:8:"distance";d:6;s:5:"price";d:10.9;}i:6;a:3:{s:5:"label";s:12:"Raio 7.0 km";s:8:"distance";d:7;s:5:"price";d:11.5;}i:7;a:3:{s:5:"label";s:12:"Raio 8.0 km";s:8:"distance";d:8;s:5:"price";d:12.1;}i:8;a:3:{s:5:"label";s:12:"Raio 9.0 km";s:8:"distance";d:9;s:5:"price";d:12.7;}i:9;a:3:{s:5:"label";s:13:"Raio 10.0 km";s:8:"distance";d:10;s:5:"price";d:13.3;}i:10;a:3:{s:5:"label";s:13:"Raio 11.0 km";s:8:"distance";d:11;s:5:"price";d:13.9;}i:11;a:3:{s:5:"label";s:13:"Raio 12.0 km";s:8:"distance";d:12;s:5:"price";d:14.5;}i:12;a:3:{s:5:"label";s:13:"Raio 13.0 km";s:8:"distance";d:13;s:5:"price";d:15;}i:13;a:3:{s:5:"label";s:13:"Raio 14.0 km";s:8:"distance";d:14;s:5:"price";d:15.5;}i:14;a:3:{s:5:"label";s:13:"Raio 15.0 km";s:8:"distance";d:15;s:5:"price";d:16;}i:15;a:3:{s:5:"label";s:13:"Raio 16.0 km";s:8:"distance";d:16;s:5:"price";d:16.5;}i:16;a:3:{s:5:"label";s:13:"Raio 17.0 km";s:8:"distance";d:17;s:5:"price";d:17;}i:17;a:3:{s:5:"label";s:13:"Raio 18.0 km";s:8:"distance";d:18;s:5:"price";d:17.5;}i:18;a:3:{s:5:"label";s:13:"Raio 19.0 km";s:8:"distance";d:19;s:5:"price";d:18;}i:19;a:3:{s:5:"label";s:13:"Raio 20.0 km";s:8:"distance";d:20;s:5:"price";d:18.5;}i:20;a:3:{s:5:"label";s:13:"Raio 21.0 km";s:8:"distance";d:21;s:5:"price";d:19;}i:21;a:3:{s:5:"label";s:13:"Raio 22.0 km";s:8:"distance";d:22;s:5:"price";d:19.5;}i:22;a:3:{s:5:"label";s:13:"Raio 23.0 km";s:8:"distance";d:23;s:5:"price";d:20;}i:23;a:3:{s:5:"label";s:13:"Raio 24.0 km";s:8:"distance";d:24;s:5:"price";d:20.5;}i:24;a:3:{s:5:"label";s:13:"Raio 25.0 km";s:8:"distance";d:25;s:5:"price";d:21;}i:25;a:3:{s:5:"label";s:13:"Raio 26.0 km";s:8:"distance";d:26;s:5:"price";d:21.5;}i:26;a:3:{s:5:"label";s:13:"Raio 27.0 km";s:8:"distance";d:27;s:5:"price";d:22;}i:27;a:3:{s:5:"label";s:13:"Raio 28.0 km";s:8:"distance";d:28;s:5:"price";d:22.5;}i:28;a:3:{s:5:"label";s:13:"Raio 29.0 km";s:8:"distance";d:29;s:5:"price";d:23;}i:29;a:3:{s:5:"label";s:13:"Raio 30.0 km";s:8:"distance";d:30;s:5:"price";d:23.5;}}'
WHERE option_name = 'woo_envios_tiers';

-- Verificar atualização
SELECT 
    'ATUALIZAÇÃO CONCLUÍDA!' as status,
    COUNT(*) as total_backups
FROM wp_options 
WHERE option_name LIKE 'woo_envios_tiers_backup_%';
