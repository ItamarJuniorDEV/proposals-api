# Proposals API

![CI](https://github.com/ItamarJuniorDEV/proposals-api/actions/workflows/ci.yml/badge.svg)
![Security](https://github.com/ItamarJuniorDEV/proposals-api/actions/workflows/security.yml/badge.svg)
![License](https://img.shields.io/badge/License-MIT-green)

API REST em PHP para clientes, propostas comerciais e contratos. O projeto não usa framework e concentra a parte mais importante no domínio: transições de estado, revisões versionadas, cálculo monetário, persistência com PDO e consistência transacional.

## O que o projeto cobre

- cadastro de clientes;
- propostas em rascunho com itens, desconto e validade;
- envio, aprovação e rejeição;
- revisão por nova versão, preservando a proposta anterior;
- contrato criado somente a partir de proposta aprovada;
- rollback quando uma operação de negócio não pode ser concluída por inteiro;
- autenticação Bearer nas rotas de negócio.

## Stack

| Área | Tecnologia |
|---|---|
| Backend | PHP 8.3+ |
| Banco | PostgreSQL 15, PDO |
| Testes | PHPUnit 11 |
| Qualidade | Pint, PHPStan nível 6, Rector |
| Segurança | Composer Audit, Gitleaks |
| CI | GitHub Actions |

A aplicação separa HTTP, services de domínio, entidades, contratos de repositório e persistência PostgreSQL. As interfaces de repositório existem porque os services coordenam regras e transações que não devem depender de SQL espalhado pelos controllers.

## Ciclo de vida

```text
draft -> sent
sent  -> approved
sent  -> rejected
sent/rejected -> nova revisão em draft
```

Uma proposta enviada só pode ser aprovada dentro de `valid_until`. A revisão cria uma nova proposta com `version + 1` e `parent_id` apontando para a versão revisada. Cada versão pode gerar apenas uma revisão direta.

As mutações de uma proposta bloqueiam a linha correspondente durante a transação. Isso evita que duas requisições concorrentes decidam o estado da mesma proposta usando uma leitura antiga. Aprovação e criação do contrato também são confirmadas na mesma transação.

## Como rodar

Pré-requisitos: PHP 8.3+, Composer, Docker e um cliente PostgreSQL.

```bash
git clone https://github.com/ItamarJuniorDEV/proposals-api.git
cd proposals-api
composer install
cp .env.example .env
docker compose up -d
```

Gere um token local com pelo menos 32 caracteres:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Coloque o valor em `API_TOKEN` no `.env`.

Execute as migrations na ordem:

```bash
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/001_create_clients_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/002_create_proposals_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/003_create_proposal_items_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/004_create_contracts_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/005_add_domain_constraints.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/006_add_revision_constraint.sql
```

Inicie a API:

```bash
php -S localhost:8000 -t public
```

`GET /` funciona como health check público. As demais rotas exigem o token:

```bash
curl -H "Authorization: Bearer SEU_TOKEN" http://localhost:8000/proposals
```

## Rotas principais

| Método | Rota | Ação |
|---|---|---|
| GET / POST | `/clients` | listar e criar clientes |
| GET / PUT / DELETE | `/clients/{id}` | consultar, atualizar e remover cliente |
| GET / POST | `/proposals` | listar e criar propostas |
| GET / PUT / DELETE | `/proposals/{id}` | consultar, atualizar e remover proposta |
| POST | `/proposals/{id}/send` | enviar proposta |
| POST | `/proposals/{id}/approve` | aprovar e gerar contrato |
| POST | `/proposals/{id}/reject` | rejeitar proposta |
| POST | `/proposals/{id}/revise` | criar nova revisão |
| POST | `/proposals/{id}/items` | adicionar item |
| PUT / DELETE | `/proposals/{id}/items/{itemId}` | atualizar ou remover item |
| GET | `/contracts` | listar contratos |
| GET | `/contracts/{id}` | consultar contrato |

Os IDs recebidos nas rotas são validados como UUID. O corpo JSON é limitado a 1 MB e respostas de erro interno não expõem exceções ou detalhes do banco.

## Integridade dos dados

O PostgreSQL mantém restrições para:

- status válidos;
- desconto entre 0 e 100%;
- quantidade e preço positivos;
- apenas um contrato por proposta;
- total de contrato não negativo;
- uma única revisão direta por versão.

Valores monetários são persistidos em `DECIMAL`. Os totais são calculados em centavos inteiros antes da conversão para o valor final, evitando acumular erro de ponto flutuante durante soma e desconto.

## Testes e qualidade

```bash
composer test
composer format:check
composer analyse
composer rector
```

Além dos testes unitários, o CI sobe PostgreSQL e executa testes de integração contra as migrations e os repositórios reais. A suíte valida inclusive rollback de aprovação quando a criação do contrato falha.

O pipeline principal roda PHP 8.3 e 8.4, Pint, PHPStan nível 6 sem baseline, Rector em dry-run e a integração com PostgreSQL. O workflow de segurança executa `composer audit` e Gitleaks.

## Licença

MIT
