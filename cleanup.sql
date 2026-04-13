-- =============================================
-- FreeScout Cleanup Script
-- Uso: Remove TODAS as tabelas do FreeScout
-- Executar contra o banco 'freescout' (PostgreSQL)
-- =============================================

-- Dropar todo o schema public e recriar vazio
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO freescout;
GRANT ALL ON SCHEMA public TO public;

-- Após executar, o FreeScout recriará as tabelas no próximo boot do container.
-- Para limpar completamente: docker-compose down -v
