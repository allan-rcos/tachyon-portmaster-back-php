# Portmaster

**API de pátio de contêineres construída em PHP 8.4 sobre OpenSwoole, falando FlatBuffers binário sobre HTTP.**

Produtos são catalogados, contêineres são registrados, carga é embarcada e desembarcada contra um manifesto, e o contêiner é selado e despachado quando atinge sua capacidade. Tudo isso sem servidor web na frente: o processo PHP *é* o servidor, com os workers mantendo o grafo de objetos e o pool de conexões vivos entre requisições.

O projeto é uma **base de ecossistema para APIs de alto desempenho** — arquitetura em cinco camadas com dependências de mão única, autorização declarada pelo próprio caso de uso, erros como valores em vez de exceções, e um formato de fio binário gerado a partir de schemas versionados.

[![CI](https://img.shields.io/github/actions/workflow/status/allan-rcos/tachyon-portmaster-back-php/ci.yml?branch=main&style=for-the-badge&logo=githubactions&logoColor=white&label=CI)](https://github.com/allan-rcos/tachyon-portmaster-back-php/actions/workflows/ci.yml)

![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![OpenSwoole](https://img.shields.io/badge/OpenSwoole-1F6FEB?style=for-the-badge&logo=php&logoColor=white)
![FlatBuffers](https://img.shields.io/badge/FlatBuffers-4285F4?style=for-the-badge&logo=google&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![Docker](https://img.shields.io/badge/docker-%230db7ed.svg?style=for-the-badge&logo=docker&logoColor=white)
![Go](https://img.shields.io/badge/go-%2300ADD8.svg?style=for-the-badge&logo=go&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-black?style=for-the-badge&logo=jsonwebtokens&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan_level_9-2C3E50?style=for-the-badge&logo=php&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-8B5CF6?style=for-the-badge&logo=pest&logoColor=white)
![Testcontainers](https://img.shields.io/badge/Testcontainers-291A3F?style=for-the-badge&logo=docker&logoColor=white)
![OpenAPI](https://img.shields.io/badge/OpenAPI-6BA539?style=for-the-badge&logo=openapiinitiative&logoColor=white)
![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-2088FF?style=for-the-badge&logo=githubactions&logoColor=white)
![License](https://img.shields.io/badge/MIT-green?style=for-the-badge)

-----

## ✨ Destaques

* **Runtime persistente.** PHP 8.4 CLI sob OpenSwoole, sem Nginx/Apache. O grafo de objetos inteiro é montado uma vez em `WorkerStart` — depois do fork — e cada worker é dono das suas instâncias pelo tempo de vida do processo.
* **FlatBuffers como formato de fio.** Requisições e respostas são tabelas geradas por `flatc` a partir dos schemas em `swagger/` (submódulo). Sem parsing, sem alocação por campo — e JSON continua disponível por negociação de `Accept`/`Content-Type`.
* **Cinco camadas, dependências de mão única.** `API → App → Infra → Domain`, com `Shared` transversal. `Domain` não depende de nada além de `Shared`.
* **Composição explícita, sem container de DI.** Cada camada tem seu provider, que constrói os próprios objetos e memoiza. O grafo é legível de cima a baixo, sem reflexão nem resolução em runtime.
* **Erros como valores.** Toda operação falível devolve `Result` — sucesso com valor, ou falha com um id de erro que indexa mensagem, detalhe e status HTTP. Falha é valor justamente para que o caso de uso consiga fazer *rollback* antes de retornar, algo que uma exceção desempilhando ignoraria.
* **Autorização junto do caso de uso.** Cada caso de uso protegido declara sua própria permissão no construtor e a exige na primeira linha de `execute()`. O catálogo de permissões é o próprio código — `POST /setup` concede ao primeiro papel toda permissão registrada, então uma permissão nova nasce concedida sem lista para manter.
* **Regras em um só lugar.** Validação vive nos *Table Modules* do domínio, que se recusam a construir um modelo inválido. Controller não valida, repositório não valida.
* **Ids opacos na borda.** Snowflake, NanoID e ULID gerados pela aplicação, `BIGINT UNSIGNED` no banco, Base62 na API — por isso as rotas casam `[A-Za-z0-9]+`, nunca `\d+`.
* **Leituras e escritas assimétricas.** Escrita atravessa `Command → UseCase → TableModule → Repository` dentro de uma transação; leitura atravessa `Query → UseCase → DQL → View` e devolve exatamente as colunas do endpoint, sem reconstituir modelo de domínio.
* **Qualidade verificada.** PHPStan nível 9, Pest para regras de domínio e fluxo transacional, e uma suíte de integração em Go que sobe MariaDB + API reais via testcontainers e exercita a API como um cliente faz.

-----

## 🏗️ Arquitetura

```
src/
├── API/       HTTP: rotas, middlewares, controllers, formato de fio
├── App/       casos de uso, commands, queries, autorização
├── Domain/    modelos, table modules, geração de ids, hashing
├── Infra/     banco, repositórios, queries de leitura, logging
└── Shared/    Result e o registro de erros
```

```
API ──► App ──► Infra ──► Domain
                  │          ▲
                  └──────────┘
        Shared é usado por todas
```

Cada camada publica contratos e esconde implementações: `IProductRepository` no topo do diretório, `Interno/SqlProductRepository` um nível abaixo. Nada fora da camada nomeia uma classe `Interno` exceto o provider que a constrói — é isso que permite trocar o repositório SQL por um dublê em memória sem que nenhum chamador perceba.

**Caminho de uma requisição:** `main.php` → `RecovererMiddleware` → `RequestIdMiddleware` → `LoggingMiddleware` → `FlatBufferNegotiationMiddleware` → `AuthenticationMiddleware` → `RouteDispatchMiddleware` → Controller → UseCase → Repository/DQL → `ResponseEmitter`.

O desenho completo, com os porquês, está em [`docs/architecture.md`](docs/architecture.md) e nos [ADRs](docs/adr/).

-----

## 🌐 Endpoints

| Domínio | Rotas |
|---|---|
| **Servidor** | `GET /info` |
| **Bootstrap** | `POST /setup` — única porta de entrada num sistema sem usuários; responde 409 depois do primeiro |
| **Autenticação** | `POST /auth/login` · `POST /auth/refresh` · `POST /auth/logout` |
| **Conta** | `GET /account` · `PUT /account` · `PUT /account/password` |
| **Produtos** | `GET` `POST` `/products` · `GET` `PUT` `DELETE` `/products/{id}` |
| **Contêineres** | `GET` `POST` `/containers` · `GET /containers/summary` · `GET` `PUT` `DELETE` `/containers/{id}` · `POST /containers/{id}/seal` · `POST /containers/{id}/dispatch` |
| **Manifestos** | `POST /manifests/load-item` · `POST /manifests/unload-item` |
| **Usuários (admin)** | `GET` `POST` `/users` · `GET` `PUT` `DELETE` `/users/{id}` · `PUT /users/{id}/password` · `PUT /users/{id}/roles` |
| **Papéis (admin)** | `GET` `POST` `/roles` · `PUT /roles/{id}/permissions` |
| **Metadados do sistema** | `GET /metadata/permissions` — catálogo preenchido em código no *WorkerStart*; sem paginação, filtrável por `?search=` |
| **Métricas** | `GET /metrics` |

A sessão trafega em cookies `HttpOnly`: um JWT HS256 de curta duração e um *refresh token* opaco (NanoID) revogável por *marker*.

-----

## 🛠️ Instalação

### Requisitos

| | |
|---|---|
| **Docker + Compose** | caminho recomendado; é tudo que a stack de desenvolvimento precisa |
| **PHP 8.4** com `openswoole`, `ds`, `pdo_mysql`, `mbstring`, `iconv` | para rodar fora de container e para a análise estática |
| **Composer 2** | dependências PHP |
| **Go 1.25+** | apenas para a suíte de integração |
| **flatc 25.12+** | apenas para regerar os bindings FlatBuffers |

### 1. Clone com submódulos

Os schemas FlatBuffers vivem no submódulo `swagger/` — sem ele não há formato de fio para gerar.

```bash
git clone --recurse-submodules git@github.com:allan-rcos/tachyon-portmaster-back-php.git portmaster
cd portmaster
```

Já clonou sem eles? `git submodule update --init --recursive`.

### 2. Suba a stack

```bash
docker compose up -d
```

O Compose orquestra a ordem inteira: `db` (MariaDB 11 com `--event-scheduler=ON`) → `migrate` (golang-migrate) → `seed` (`db/seeds/dev.sql`, idempotente) → `app` (a API na porta `8000`).

> A primeira subida inicializa um volume novo do MariaDB e pode levar alguns minutos; o healthcheck tem `start_period` de 180s justamente para não estourar o orçamento de tentativas nesse intervalo.

### 3. Instalação local (opcional)

Para rodar a análise estática, os testes unitários ou o servidor fora do container:

```bash
composer install
```

A configuração é **inteiramente por variáveis de ambiente** — a mesma imagem serve a stack de desenvolvimento e o pool de testes, cada um apontado para seu banco. O `.env` do repositório traz os padrões de desenvolvimento:

| Variável | Papel |
|---|---|
| `APP_HOST`, `APP_PORT` | endereço de escuta |
| `APP_WORKER_NUM` | processos worker (4 em dev) |
| `APP_DB_HOST`, `APP_DB_PORT`, `APP_DB_NAME`, `APP_DB_USER`, `APP_DB_PASSWORD` | banco |
| `APP_JWT_SECRET` | chave de assinatura HS256 — **mínimo de 32 bytes**, o boot recusa menos |
| `APP_JWT_TTL`, `APP_REFRESH_TTL` | validade do token e do refresh |
| `APP_JWT_COOKIE_SECURE` | `false` em HTTP local, `true` atrás de HTTPS |

> **Em produção**, troque `APP_JWT_SECRET` por um valor aleatório forte e ligue `APP_JWT_COOKIE_SECURE`.

```bash
php src/API/main.php
```

-----

## 🚀 Uso

### 1. Faça o bootstrap do sistema

Não existe usuário semeado — o primeiro administrador nasce de uma chamada explícita, que se recusa com `409` a partir da segunda. O papel criado aí recebe **todas** as permissões registradas pelos casos de uso.

```bash
curl -X POST localhost:8000/setup -H 'Content-Type: application/json' \
     -d '{"name":"Admin","email":"admin@portmaster.local","password":"Portmaster1"}'
```

### 2. Autentique-se

O login devolve os cookies de sessão; guarde-os num *cookie jar*.

```bash
curl -c jar.txt -X POST localhost:8000/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"email":"admin@portmaster.local","password":"Portmaster1"}'
```

### 3. Consuma a API

```bash
# JSON, por negociação de conteúdo
curl -b jar.txt localhost:8000/products -H 'Accept: application/json'

# FlatBuffers binário — o formato nativo
curl -b jar.txt localhost:8000/products -H 'Accept: application/octet-stream' --output products.fb

curl localhost:8000/info
docker compose logs -f app
```

Ciclo de vida de um contêiner: crie-o, embarque itens com `POST /manifests/load-item` (o peso corrente é mantido na mesma transação da escrita do item), sele com `POST /containers/{id}/seal` e despache com `POST /containers/{id}/dispatch`.

-----

## ✅ Qualidade

| Comando | O que faz |
|---|---|
| `composer phpstan` | análise estática, **nível 9** (com `--memory-limit=2G`) |
| `composer pest` | testes unitários — regras de domínio e espinha transacional |
| `scripts/integration-test.sh` | suíte de integração em Go (precisa de Docker) |
| `composer flatbuffers` | regera e normaliza as classes PHP a partir dos `.fbs` |
| `scripts/generate-flatbuffers-go.sh` | regera os bindings Go da suíte de testes |
| `composer phpdoc` | renderiza a documentação de API em `docs/phpdocumentor` (versionada; GitHub Pages) |

**A linha divisória entre as suítes:** se um comportamento é observável por uma requisição e uma resposta, é integração; se é uma regra ou um desvio, é unitário. Os testes unitários batem direto nos *table modules* — é onde as regras existem — e nos casos de uso, verificando *commit* no caminho felizes, *rollback* em qualquer falha e o guarda de `403`. A suíte de integração é escrita como **histórias** (sessão, administração, pátio) que sobem MariaDB em tmpfs e um pool de APIs reais via testcontainers-go.

O CI ([GitHub Actions](.github/workflows/ci.yml)) roda três jobs independentes: PHPStan nível 9, Pest, e a suíte Go — esta última falhando se os bindings FlatBuffers comitados estiverem defasados.

-----

## 📚 Documentação

| | |
|---|---|
| [`docs/architecture.md`](docs/architecture.md) | as cinco camadas, o caminho da requisição e as convenções |
| [`docs/guides/new-feature.md`](docs/guides/new-feature.md) | uma feature de ponta a ponta, com um arquivo para espelhar em cada passo |
| [`docs/database.md`](docs/database.md) | schema, migrações, seeds, transações, as tabelas MEMORY |
| [`docs/testing.md`](docs/testing.md) | o que é teste unitário e o que é história de integração |
| [`docs/infrastructure.md`](docs/infrastructure.md) | Docker, configuração, flatc, scripts, CI |
| [`docs/documentation.md`](docs/documentation.md) | o formato de PHPDoc e como os docs são renderizados |
| [`docs/adr/`](docs/adr/) | **por que** as coisas são como são |

-----

## ✏️ Contribuir

Contribuições são bem-vindas. Antes de abrir um PR:

1. `composer phpstan` e `composer pest` precisam passar; para mudanças na borda da API, `scripts/integration-test.sh` também.
2. Siga a convenção de contrato/implementação (`IFoo` no topo, `Interno/BarFoo` abaixo) e mantenha a direção das dependências.
3. Regra nova vive no *table module*, não no controller nem no repositório.
4. Mudou schema `.fbs`? Regere os dois lados e comite o resultado.
5. Decisão estrutural merece um ADR em [`docs/adr/`](docs/adr/).

O guia [`docs/guides/new-feature.md`](docs/guides/new-feature.md) percorre a implementação completa de uma feature, arquivo por arquivo.

-----

## 🔓 Licença

[![MIT](https://img.shields.io/badge/MIT-green?style=for-the-badge)](#)
