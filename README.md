# Proposals API

![CI](https://github.com/ItamarJuniorDEV/proposal-api-php/actions/workflows/ci.yml/badge.svg)
![License](https://img.shields.io/badge/License-MIT-green)

> API REST de propostas comerciais em PHP puro, sem framework: máquina de estados no ciclo de vida, versionamento de revisão e geração de contrato na aprovação.

Fiz este projeto pra exercitar arquitetura em camadas e regra de negócio em PHP puro, sem o apoio de um framework. O domínio de propostas comerciais é bom pra isso porque tem estado: uma proposta nasce como rascunho, é enviada, e só então pode ser aprovada ou rejeitada, e cada transição tem regra. Quis modelar isso de forma explícita, com a máquina de estados num enum e a lógica isolada em services testáveis.

## Índice

- [Sobre](#sobre)
- [Funcionalidades](#funcionalidades)
- [Stack](#stack)
- [Arquitetura](#arquitetura)
- [Como rodar](#como-rodar)
- [Modelo de dados](#modelo-de-dados)
- [Documentação da API](#documentação-da-api)
- [Testes](#testes)
- [Decisões técnicas](#decisões-técnicas)
- [Licença](#licença)

## Sobre

A API gerencia clientes, propostas e contratos. Uma proposta tem itens, percentual de desconto e validade; passa por estados controlados (rascunho, enviada, aprovada, rejeitada); ao ser aprovada dentro da validade, gera um contrato com o total calculado. Revisar uma proposta cria uma nova versão ligada à anterior, preservando o histórico.

## Funcionalidades

- Máquina de estados do ciclo de vida da proposta (rascunho, enviada, aprovada, rejeitada)
- Versionamento de revisão: revisar gera uma nova versão apontando para a anterior
- Geração de contrato na aprovação, com o total já calculado
- Cálculo de totais com desconto percentual
- CRUD de clientes, propostas e itens
- Validação de expiração: proposta vencida não pode ser aprovada

## Stack

| Camada | Tecnologia |
|--------|------------|
| Linguagem | PHP 8.3, sem framework |
| Banco | PostgreSQL 15 (PDO) |
| Testes | PHPUnit 11 |
| Estilo | Laravel Pint |
| Infra | Docker |

## Arquitetura

Camadas com responsabilidade separada, sem framework:

```
src/
├── Http/Routes/        roteador simples (método + caminho)
├── Controllers/        recebem a requisição e devolvem a resposta
├── Services/           regra de negócio e validação (ProposalService, etc.)
├── Domain/
│   ├── Entities/       objetos de domínio (Proposal, Client, ...)
│   ├── Enums/          ProposalStatus com as transições permitidas
│   └── Repositories/   interfaces (contrato de persistência)
└── Infrastructure/
    ├── Database/        conexão PDO
    └── Persistence/     repositórios concretos com SQL
```

O fluxo de uma requisição: `public/index.php` monta as dependências e resolve a rota no `Router`, que chama o `Controller`, que chama o `Service` (regra), que usa os `Repository` (dados).

### Ciclo de vida da proposta

- `draft` pode ser editada e enviada
- `sent` pode ser aprovada, rejeitada ou revisada
- `rejected` pode ser revisada
- aprovar gera um `Contract`; revisar cria uma nova proposta `draft` com `version + 1` e `parent_id` apontando para a anterior

As transições ficam no enum `ProposalStatus` (`canSend`, `canApprove`, `canReject`, `canRevise`, `canEdit`), então a regra de estado vive num lugar só.

## Como rodar

Pré-requisitos: PHP 8.3+, Composer, PostgreSQL 15 (ou Docker).

```bash
git clone https://github.com/ItamarJuniorDEV/proposal-api-php
cd proposal-api-php
composer install
cp .env.example .env
docker compose up -d
psql -U postgres -d proposals -f database/migrations/001_create_clients_table.sql
psql -U postgres -d proposals -f database/migrations/002_create_proposals_table.sql
psql -U postgres -d proposals -f database/migrations/003_create_proposal_items_table.sql
psql -U postgres -d proposals -f database/migrations/004_create_contracts_table.sql
php -S localhost:8000 -t public
```

API em `http://localhost:8000`.

## Modelo de dados

- `clients` tem muitas `proposals`
- `proposals` pertence a um `client`, tem `version`, `parent_id` (a revisão anterior), `status`, `discount_percent` e `valid_until`
- `proposal_items` pertence a uma `proposal` e guarda `quantity` e `unit_price`
- `contracts` pertence a uma `proposal` (gerado na aprovação) com o `total_amount`

Os IDs são UUID. A revisão usa `parent_id` para encadear versões da mesma proposta.

## Documentação da API

Respostas em JSON. As rotas de ação seguem o padrão `POST /proposals/{id}/<ação>`.

| Método | Rota | Descrição |
|--------|------|-----------|
| GET/POST | `/clients` | Lista e cria clientes |
| GET/PUT/DELETE | `/clients/{id}` | Detalha, atualiza e remove cliente |
| GET/POST | `/proposals` | Lista e cria propostas |
| GET/PUT/DELETE | `/proposals/{id}` | Detalha (com itens e totais), atualiza e remove (só em rascunho) |
| POST | `/proposals/{id}/send` | Envia a proposta |
| POST | `/proposals/{id}/approve` | Aprova e gera o contrato |
| POST | `/proposals/{id}/reject` | Rejeita a proposta |
| POST | `/proposals/{id}/revise` | Cria uma nova versão |
| POST/PUT/DELETE | `/proposals/{id}/items` | Gerencia os itens |
| GET | `/contracts` `/contracts/{id}` | Lista e detalha contratos |

### Formato de resposta

Detalhe de proposta vem com itens e totais:

```json
{
  "proposal": { "id": "uuid", "status": "draft", "discount_percent": 10 },
  "items": [ { "description": "Desenvolvimento", "quantity": 1, "unit_price": 15000 } ],
  "totals": { "subtotal": 15000.00, "discount": 1500.00, "total": 13500.00 }
}
```

Erro de regra de negócio:

```json
{ "error": "Proposta não pode ser editada" }
```

## Testes

```bash
composer test
```

Testes unitários em PHPUnit cobrindo as entidades, o enum de status e os services (criação, envio, aprovação com geração de contrato, rejeição, revisão e itens), com os repositórios mockados.

## Decisões técnicas

- **Máquina de estados no enum.** `ProposalStatus` concentra as transições permitidas (`canSend`, `canApprove`, etc.). O service pergunta ao estado se a ação é válida, em vez de espalhar `if` de status pelo código.

- **Revisão como nova versão.** Revisar não edita a proposta enviada: cria uma nova `draft` com `version + 1` e `parent_id` apontando para a anterior, copiando os itens. Preserva o histórico do que foi proposto.

- **Revisão em transação.** A criação da nova versão e a cópia dos itens rodam dentro de uma transação PDO. Se a cópia de um item falha, o `rollBack` evita uma revisão pela metade.

- **Contrato gerado na aprovação.** Aprovar dentro da validade muda o status e cria o `Contract` com o total calculado no momento, num passo só.

- **Camadas com interface no repositório.** Os services dependem de `RepositoryInterface`, não da implementação. Isso mantém a regra de negócio testável com mocks, sem subir banco.

## Licença

MIT
