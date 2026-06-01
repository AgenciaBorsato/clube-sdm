-- ============================================================
-- CLUBE SDM - Plataforma Multi-Tenant de Clubes de Vantagens
-- Schema PostgreSQL
-- ============================================================

-- Clubes (tenants)
CREATE TABLE IF NOT EXISTS clubs (
  id SERIAL PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  segmento VARCHAR(100) NOT NULL DEFAULT 'farmacia',
  logo_url TEXT,
  endereco TEXT,
  cidade VARCHAR(100),
  estado VARCHAR(2),
  telefone VARCHAR(20),
  email VARCHAR(255),
  cor_primaria VARCHAR(7) DEFAULT '#2196f3',
  cor_secundaria VARCHAR(7) DEFAULT '#0a2540',
  expiracao_meses INT NOT NULL DEFAULT 3,
  whatsapp_enabled BOOLEAN DEFAULT FALSE,
  evolution_instance VARCHAR(120),
  evolution_token VARCHAR(120),
  whatsapp_template TEXT,
  ativo BOOLEAN DEFAULT TRUE,
  criado_em TIMESTAMP DEFAULT NOW(),
  atualizado_em TIMESTAMP DEFAULT NOW()
);

-- Usuarios (multi-role)
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'OPERATOR',
  club_id INT REFERENCES clubs(id) ON DELETE SET NULL,
  avatar_url TEXT,
  ativo BOOLEAN DEFAULT TRUE,
  ultimo_login TIMESTAMP,
  criado_em TIMESTAMP DEFAULT NOW(),
  atualizado_em TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_club ON users(club_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Clientes (scoped por clube)
CREATE TABLE IF NOT EXISTS clientes (
  id SERIAL PRIMARY KEY,
  club_id INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
  nome VARCHAR(255) NOT NULL,
  cpf VARCHAR(11) NOT NULL,
  telefone VARCHAR(11) NOT NULL,
  email VARCHAR(255),
  endereco TEXT,
  cidade VARCHAR(100),
  estado VARCHAR(2),
  cep VARCHAR(8),
  data_nascimento DATE,
  observacoes TEXT,
  data_cadastro TIMESTAMP DEFAULT NOW(),
  ativo BOOLEAN DEFAULT TRUE,
  UNIQUE (club_id, cpf),
  UNIQUE (club_id, telefone)
);

CREATE INDEX IF NOT EXISTS idx_clientes_club ON clientes(club_id);
CREATE INDEX IF NOT EXISTS idx_clientes_cpf ON clientes(cpf);
CREATE INDEX IF NOT EXISTS idx_clientes_telefone ON clientes(telefone);

-- Compras
CREATE TABLE IF NOT EXISTS compras (
  id SERIAL PRIMARY KEY,
  club_id INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
  cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  valor DECIMAL(10,2) NOT NULL,
  cashback_percentual DECIMAL(5,2) NOT NULL,
  cashback_valor DECIMAL(10,2) NOT NULL,
  data_compra TIMESTAMP DEFAULT NOW(),
  estornada BOOLEAN DEFAULT FALSE,
  data_estorno TIMESTAMP NULL DEFAULT NULL,
  motivo_estorno VARCHAR(255) NULL DEFAULT NULL,
  registrado_por INT REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_compras_club ON compras(club_id);
CREATE INDEX IF NOT EXISTS idx_compras_cliente ON compras(cliente_id);
CREATE INDEX IF NOT EXISTS idx_compras_data ON compras(data_compra);

-- Resgates
CREATE TABLE IF NOT EXISTS resgates (
  id SERIAL PRIMARY KEY,
  club_id INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
  cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  valor DECIMAL(10,2) NOT NULL,
  data_resgate TIMESTAMP DEFAULT NOW(),
  estornado BOOLEAN DEFAULT FALSE,
  registrado_por INT REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_resgates_club ON resgates(club_id);
CREATE INDEX IF NOT EXISTS idx_resgates_cliente ON resgates(cliente_id);

-- Cashback mensal (por clube)
CREATE TABLE IF NOT EXISTS cashback_mensal (
  id SERIAL PRIMARY KEY,
  club_id INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
  ano INT NOT NULL,
  mes INT NOT NULL,
  percentual DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  criado_em TIMESTAMP DEFAULT NOW(),
  UNIQUE (club_id, ano, mes)
);

CREATE INDEX IF NOT EXISTS idx_cashback_club ON cashback_mensal(club_id);

-- Configuracoes (por clube)
CREATE TABLE IF NOT EXISTS configuracoes (
  id SERIAL PRIMARY KEY,
  club_id INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
  chave VARCHAR(100) NOT NULL,
  valor TEXT NOT NULL,
  atualizado_em TIMESTAMP DEFAULT NOW(),
  UNIQUE (club_id, chave)
);

-- Campanhas de mensagens (disparos em massa por clube)
CREATE TABLE IF NOT EXISTS campanhas (
  id SERIAL PRIMARY KEY,
  club_id INT NOT NULL REFERENCES clubs(id) ON DELETE CASCADE,
  segmento VARCHAR(50),
  mensagem TEXT,
  total INT DEFAULT 0,
  enviados INT DEFAULT 0,
  falhas INT DEFAULT 0,
  criado_por INT REFERENCES users(id),
  criado_em TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_campanhas_club ON campanhas(club_id);

-- Log de auditoria
CREATE TABLE IF NOT EXISTS audit_log (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id),
  club_id INT REFERENCES clubs(id),
  acao VARCHAR(100) NOT NULL,
  tabela VARCHAR(50),
  registro_id INT,
  dados_anteriores JSONB,
  dados_novos JSONB,
  ip VARCHAR(45),
  criado_em TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_club ON audit_log(club_id);
CREATE INDEX IF NOT EXISTS idx_audit_data ON audit_log(criado_em);

-- Tentativas de login (por IP)
CREATE TABLE IF NOT EXISTS login_tentativas (
  id SERIAL PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  tentativa_em TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_ip ON login_tentativas(ip);
CREATE INDEX IF NOT EXISTS idx_login_tempo ON login_tentativas(tentativa_em);
