-- ============================================================
-- CLUBE SDM - Seed: Super Admin + Clube Demo
-- ============================================================

-- Super Admin (senha: clubesdm2026)
-- Hash gerado com: password_hash('clubesdm2026', PASSWORD_DEFAULT)
INSERT INTO users (nome, email, password_hash, role, club_id)
VALUES ('Wladimir Borsato', 'admin@clubesdm.com', '$2y$10$placeholder', 'SUPER_ADMIN', NULL)
ON CONFLICT (email) DO NOTHING;

-- O hash real sera gerado pelo PHP na inicializacao
