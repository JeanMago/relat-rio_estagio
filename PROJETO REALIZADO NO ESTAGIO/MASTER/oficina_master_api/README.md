# FK Oficina Master API

Backend hub para integracoes entre multiplos projetos (ex.: `oficina_api`) via token tecnico.

## Setup rapido

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8010
```

## Seeder de usuario master

Configure no `.env` antes de rodar seed:

```env
MASTER_USER_NAME=Master Admin
MASTER_USER_EMAIL=Master@fkoficina.local

MASTER_USER_PASSWORD=123456
```

## Auth endpoints

- `POST /api/auth/login`
- `GET /api/auth/me` (auth:sanctum)
- `POST /api/auth/logout` (auth:sanctum)

## Projetos de integracao (URL + token)

- `GET /api/projetos-integracao`
- `POST /api/projetos-integracao`
- `GET /api/projetos-integracao/{id}`
- `PUT /api/projetos-integracao/{id}`
- `DELETE /api/projetos-integracao/{id}`

Payload base:

```json
{
  "nome": "FK Oficina Producao",
  "slug": "fk-oficina-producao",
  "url_base": "https://seu-dominio.com/api",
  "token_integracao": "TOKEN_TECNICO_AQUI",
  "tipo": "fk_oficina",
  "timeout_ms": 15000,
  "ativo": true
}
```

`token_integracao` e salvo criptografado no banco.

## Sincronizacao com projeto externo

- `POST /api/sincronizacao/projetos/{projeto}/executar`
- `GET /api/sincronizacao/projetos/{projeto}/registros`

Recursos padrao sincronizados:
- `tenants`
- `empresas`
- `modelos_impressao`

## IBPT

- `GET /api/ibpt/projetos/{projeto}/versoes`
- `GET /api/ibpt/projetos/{projeto}/itens`
- `POST /api/ibpt/projetos/{projeto}/enviar`
- `POST /api/ibpt/projetos/{projeto}/propagar`

Comando para acionar copia para bancos tenants no projeto remoto:

```bash
php artisan integracao:ibpt-propagar-tenants {projeto_id} {versao} --tenantUuid=UUID --chunk=1000
```
