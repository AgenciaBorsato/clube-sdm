# Clube SDM — Plataforma Multi-Tenant de Clubes de Vantagens

Sistema SaaS para criar e gerenciar multiplos clubes de fidelidade/cashback.

## Stack
- **Frontend:** HTML/CSS/JS (SPAs self-contained)
- **Backend:** PHP 8+
- **Banco:** PostgreSQL
- **Deploy:** Railway

## Acesso

| Painel | URL | Roles |
|--------|-----|-------|
| Super Admin | `/index.html` | SUPER_ADMIN |
| Clube | `/club.html` | CLUB_ADMIN, OPERATOR |

**Credenciais padrao do Super Admin:**
- Email: `admin@clubesdm.com`
- Senha: `clubesdm2026`

## Deploy no Railway

1. Crie um projeto no Railway
2. Adicione PostgreSQL (Add Plugin > PostgreSQL)
3. Conecte este repositorio GitHub
4. Adicione a variavel `DATABASE_URL` referenciando o Postgres
5. O banco sera criado automaticamente no primeiro acesso

## Estrutura

```
clube-sdm/
├── index.html           (Super Admin SPA)
├── club.html            (Club Panel SPA)
├── database.sql         (Schema PostgreSQL)
├── includes/
│   ├── db.php           (Conexao + init)
│   ├── auth.php         (Login, roles, CSRF, audit)
│   └── helpers.php      (Cashback, credito)
└── api/
    ├── auth.php          (Login/logout)
    ├── admin/
    │   ├── dashboard.php (KPIs globais)
    │   ├── clubs.php     (CRUD clubes)
    │   └── users.php     (CRUD usuarios)
    ├── clientes.php      (CRUD clientes - club-scoped)
    ├── compras.php       (Compras + relatorios - club-scoped)
    ├── config.php        (Cashback mensal - club-scoped)
    └── resgates.php      (Resgates - club-scoped)
```
