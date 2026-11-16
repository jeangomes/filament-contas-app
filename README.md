# Contas App

Projeto pessoal, feito para estudo e uso particular, de controle de despesas
compartilhadas entre duas pessoas.

## O que faz

- Importa faturas de cartão de crédito (CSV) e registra lançamentos avulsos.
- Classifica despesas como comuns ou individuais.
- Gera um fechamento mensal com o rateio, o acerto entre os participantes e o
  custo de vida de cada um.
- Acompanha compras parceladas em andamento.

## Stack

- PHP 8.2+ / Laravel 13
- Filament 5 (painel administrativo, sem build de front-end)
- MySQL
- Docker Compose

A regra de negócio fica isolada em uma camada de domínio pura
(`app/Domain`), separada dos adapters de infraestrutura (`app/Infra`) e da
interface (`app/Filament`).

## Como rodar

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Fechamento mensal pela linha de comando:

```bash
docker compose exec app php artisan monthly:calculate 2026-07
docker compose exec app php artisan monthly:calculate --all
```

## Testes

```bash
docker compose exec app php artisan test
```

Os testes usam SQLite em memória.
