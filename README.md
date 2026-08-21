# Proposals API

![CI](https://github.com/ItamarJuniorDEV/proposals-api/actions/workflows/ci.yml/badge.svg)
![License](https://img.shields.io/badge/License-MIT-green)

API REST em PHP para gerenciamento de clientes, propostas comerciais e contratos. O domínio controla o ciclo de vida da proposta, revisões versionadas, validade e geração de contrato após aprovação.

## Funcionalidades

- cadastro de clientes;
- criação e edição de propostas em rascunho;
- itens com quantidade e preço unitário;
- desconto percentual e cálculo de totais;
- envio, aprovação e rejeição de propostas;
- revisão com criação de uma nova versão e cópia dos itens;
- bloqueio de aprovação de propostas vencidas;
- geração de contrato a partir de uma proposta aprovada.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.3+ |
| Banco | PostgreSQL 15, PDO |
| Testes | PHPUnit 11 |
| Infra | Docker, GitHub Actions |

O projeto não utiliza framework. A separação principal fica entre `Controllers`, `Services`, objetos de domínio, interfaces de repositório e implementações de persistência com PDO.

## Ciclo de vida da proposta

As transições permitidas são definidas por `ProposalStatus`:

```text
draft -> sent
sent  -> approved
sent  -> rejected
sent/rejected -> nova revisão em draft
```

Uma proposta enviada só pode ser aprovada enquanto estiver dentro de `valid_until`. A revisão não altera a versão anterior: uma nova proposta é criada com `version + 1` e `parent_id` apontando para a proposta revisada.

## Como rodar

Pré-requisitos: PHP 8.3+, Composer, Docker e cliente `psql`.

```bash
git clone https://github.com/ItamarJuniorDEV/proposals-api.git
cd proposals-api
composer install
cp .env.example .env
docker compose up -d
```

O `.env.example` já usa os dados do PostgreSQL definido no `docker-compose.yml`.

Execute as migrations na ordem:

```bash
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/001_create_clients_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/002_create_proposals_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/003_create_proposal_items_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/004_create_contracts_table.sql
psql -h 127.0.0.1 -U postgres -d proposal -f database/migrations/005_add_domain_constraints.sql
```

Depois inicie a API:

```bash
php -S localhost:8000 -t public
```

A API fica disponível em `http://localhost:8000`.

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

## Integridade dos dados

A aprovação atualiza o status da proposta e cria o contrato dentro da mesma transação PDO. Se a persistência do contrato falhar, a alteração de status também é revertida.

A criação de uma revisão e a cópia de seus itens também utilizam uma única transação.

Valores persistidos usam colunas `DECIMAL` no PostgreSQL. Os totais são calculados em centavos inteiros antes de serem convertidos para o formato monetário, evitando acumular imprecisões de ponto flutuante durante soma e aplicação do desconto.

O banco mantém restrições para status válidos, percentual de desconto entre 0 e 100, quantidade positiva, preço unitário positivo e total de contrato não negativo.

## Testes

```bash
composer test
```

Os testes cobrem entidades, transições de estado e regras dos services, incluindo envio, aprovação, rejeição, revisão, expiração, validações de itens, cálculo monetário e rollback das operações transacionais.

O CI executa a suíte em PHP 8.3 e 8.4. O workflow de segurança verifica o `composer.lock` com `composer audit` e executa Gitleaks sobre o repositório.

## Decisões técnicas

- **Estados explícitos:** `ProposalStatus` concentra as transições permitidas da proposta.
- **Revisões imutáveis:** uma revisão cria uma nova versão em vez de sobrescrever o conteúdo enviado anteriormente.
- **Aprovação atômica:** mudança de status e criação do contrato são confirmadas ou revertidas juntas.
- **Persistência isolada:** os services dependem de interfaces de repositório e a implementação SQL fica na camada de infraestrutura.

## Licença

MIT
