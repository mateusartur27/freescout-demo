# FreeScout Helpdesk — Demonstração

Sistema de help desk open-source para demonstração acadêmica.

## Como Rodar

### Pré-requisitos
- Docker Desktop (com WSL2 habilitado)
- Cloudflared ou localtunnel (para acesso externo)

### 1. Subir os containers

```bash
docker compose up -d
```

Aguarde ~3-5 minutos para o first-boot (criação do schema, migrations, etc.).

Verifique os logs:
```bash
docker compose logs -f freescout-app
```

### 2. Acessar localmente

- **Landing page:** http://localhost/demo.html
- **Login direto:** http://localhost/login

### 3. Expor online (acesso externo)

Com localtunnel:
```bash
npx localtunnel --port 80
```

Ou com cloudflared:
```bash
cloudflared tunnel --url http://localhost:80
```

Copie a URL gerada e compartilhe com os colegas. Acesse `/demo.html` na URL.

## Credenciais

| Perfil         | Email                    | Senha       |
|----------------|--------------------------|-------------|
| Administrador  | admin@freescout.demo     | Admin@2026  |
| Usuário/Agente | usuario@freescout.demo   | User@2026   |

## Como limpar os dados

Para remover tudo (containers + dados do banco):
```bash
docker compose down -v
```

Para limpar apenas os dados do banco (mantendo os containers):
```bash
docker compose exec freescout-db psql -U freescout -f /dev/stdin < cleanup.sql
```

## Stack

- **FreeScout** — Help desk open-source (PHP/Laravel)
- **PostgreSQL 15** — Banco de dados
- **Docker** — Containerização
- **Cloudflare Tunnel / Localtunnel** — Exposição online gratuita

## Estrutura

```
FreeScout/
├── docker-compose.yml          # Orquestração dos containers
├── assets/
│   └── custom/
│       └── public/
│           └── demo.html       # Landing page com botões Admin/Usuário
├── cleanup.sql                 # Script SQL para limpar dados
├── README.md                   # Este arquivo
└── .gitignore
```
