# dagger/

**As verificações, o código gerado e o empacotamento da API — no mesmo PHP em que ela roda em produção, e sem um único script de build no repositório.**

Antes disto, "qual PHP este projeto usa" estava escrito em três lugares: o `Dockerfile`, os dois jobs de `ci.yml` (via `setup-php`) e o `release.yml` (via `apk`). O custo apareceu como o pin `openswoole-26.2.0` escrito à mão nos workflows, com um comentário de oito linhas explicando que o padrão do `setup-php` era 25.2.0 enquanto o `composer.lock` exigia ≥ 26.2.0.

O `Dockerfile` ganhou um estágio `ext` — extensões e Composer, sem nada da aplicação — e `modules/toolchain` constrói **esse mesmo target**. Não há o que sincronizar, e o pin foi embora.

-----

## 🗺️ O diretório

```
dagger/
├── dagger.json              SDK Go e as dependências locais
├── main.go                  só compõe — nenhum Container.From() aqui
└── modules/
    ├── toolchain/           em que PHP este repositório roda
    ├── artifact/            o tarball de produção
    ├── codegen/             FlatBuffers e a baseline do PHPStan
    └── docs/                a referência de API
```

**São dois ambientes, e a diferença é deliberada.** `toolchain.runtime()` é o estágio `ext` do Dockerfile — glibc, `ds` e `openswoole` via PECL — e é onde PHPStan e Pest rodam, porque é onde a API roda. `toolchain.musl()` é Alpine, e existe só para **montar** o artefato: o tarball é implantado numa VM Alpine, e um build em glibc produziria um `vendor/` que não corresponde ao alvo.

> O módulo se chama `artifact` e não `dist` porque o `.gitignore` traz `dist/` — sem barra inicial, o que casa com qualquer diretório desse nome em qualquer profundidade. Um módulo em `modules/dist/` seria carregado **vazio**, e rodaria o esqueleto do `dagger init` sem erro nenhum explicando por quê.

-----

## 🚀 O que você vai rodar

De dentro de `dagger/`:

| Comando | Precisa instalado |
|---|---|
| `dagger call ci` | — |
| `dagger call phpstan` | — |
| `dagger call pest` | — |
| `dagger call dist --version X export --path ../dist` | — |
| `dagger call generate-fbs-php export --path ../src/API/Fbs` | — |
| `dagger call generate-fbs-go export --path ../tests/integration/internal/fbs` | — |
| `dagger call check-fbs-go` | — |
| `dagger call generate-phpstan-baseline export --path ../phpstan-generated-baseline.neon` | — |
| `dagger call docs export --path ../docs/phpdocumentor` | — |
| `dagger call integration-test` | — |

**A coluna da direita é o ponto.** A tabela equivalente pedia `flatc`, `perl`, `gofmt`, `php` com extensões, `composer`, `zstd` e `phpdoc`. Agora pede Docker.

-----

## 📦 O que era `scripts/`

O diretório `back/scripts/` não existe mais. Os sete scripts foram portados, e o que segue é onde cada um foi parar e o que prova que a tradução foi fiel.

| Era | Virou | Prova de equivalência |
|---|---|---|
| `generate-flatbuffers.php` + `patch-flatbuffers.php` | `modules/codegen` — o flatc é uma chamada, e as cinco transformações são Go | `dagger call generate-fbs-php` produz **exatamente** o `src/API/Fbs` commitado: `diff -r` com **zero** linhas, 42 arquivos |
| `generate-phpstan-baseline.php` | `modules/codegen` — análise no container, filtro e emissão do NEON em Go | O `.neon` gerado é **byte a byte** igual ao commitado: 82.878 bytes, 411 entradas, `diff` vazio |
| `build-dist.sh` | `modules/artifact` — as decisões são valores Go, as ferramentas continuam sendo `withExec` | Tarball com os **mesmos 1433 arquivos**, mesmos modos, donos, tamanhos e caminhos. Zero diferenças ignorando a data |
| `generate-flatbuffers-go.sh` | `modules/codegen` — os dois `perl -i` transcritos literalmente | `dagger call check-fbs-go` diz "em dia": o gerado bate com o commitado |
| `generate-docs.sh` | `modules/docs` — a imagem oficial, direto | 293 classes, idênticas às do script |
| `integration-test.sh` | inline em `main.go` | `ok portmaster/tests/integration` |

**O que virou Go e o que continua sendo `withExec`, e por quê.** As transformações de texto — as cinco do flatc, o filtro e a emissão da baseline, a leitura da versão — são substituição de string e parsing de JSON: rodam em Go, sobre o conteúdo do `Directory`, sem subir container. Já `composer`, `php -w`, `tar`, `zstd` e `flatc` são programas; orquestrá-los é o que o Dagger faz, e chamá-los não é resíduo de shell. O que saiu foi a lógica que decidia por eles — a lista de dependências de docblock, os padrões de poda do `vendor`, as flags que tornam o tar independente de quem o montou. Tudo isso está declarado no topo de `modules/artifact/main.go`, com o motivo junto.

**Uma coisa não foi transcrita, e é de propósito.** O `patch-flatbuffers.php` tinha uma sexta substituição cujo padrão capturava o trecho inteiro num grupo e o substituía por `$1` — por ele mesmo. Era um no-op. Está registrado no comentário de `patchFlatc`.

**Um `preg_quote` teve de ser reimplementado.** O `regexp.QuoteMeta` do Go escapa `\.+*?()|[]{}^$` e para aí; o `preg_quote` do PHP escapa também `= ! < > : - # /`. Uma mensagem do PHPStan que contenha dois pontos — e muitas contêm — sairia escapada de um jeito e esperada de outro, e a entrada da baseline simplesmente não casaria. Em silêncio.

-----

## 📋 Os métodos

Onze funções. Nenhuma pede nada instalado além do Docker; `dagger call <nome> --help` lista os argumentos.

| Comando | Devolve | Arquivo | Para quê |
|---|---|---|---|
| `ci` | `String` | `ci.go` | `phpstan` e depois `pest` — o que tem de estar verde antes de um merge |
| `phpstan` | `String` | `phpstan.go` | Análise estática nível 9 sobre `src/` |
| `pest` | `String` | `pest.go` | A suíte unitária |
| `dist` | `Directory` | `dist.go` | Os dois tarballs de produção |
| `version` | `String` | `version.go` | A versão de `composer.json` |
| `docs` | `Directory` | `docs.go` | A referência de API (phpDocumentor) |
| `generate-fbs-php` | `Directory` | `fbsphp.go` | Regera `src/API/Fbs` a partir dos schemas |
| `generate-fbs-go` | `Directory` | `fbsgo.go` | Regera os bindings Go da suíte de integração |
| `check-fbs-go` | `String` | `fbscheck.go` | Falha se os bindings commitados estiverem defasados |
| `generate-phpstan-baseline` | `File` | `baseline.go` | Reconstrói a baseline do código gerado |
| `integration-test` | `String` | `integration.go` | A suíte Go, com o daemon Docker junto |

**As que devolvem `Directory` ou `File` pedem `export`** — nenhuma escreve no repositório por conta própria (R6.1):

```bash
dagger call generate-fbs-php export --path ../src/API/Fbs
dagger call generate-fbs-go  export --path ../tests/integration/internal/fbs
dagger call generate-phpstan-baseline export --path ../phpstan-generated-baseline.neon
dagger call docs export --path ../docs/phpdocumentor
dagger call dist --version 1.2.0 export --path ../dist
```

### Argumentos

Quase todas recebem só `--source`, que tem `defaultPath` para a raiz do repositório — na prática você não passa nada. As exceções:

| Comando | Argumento | Padrão | |
|---|---|---|---|
| `ci` `phpstan` `pest` `generate-phpstan-baseline` | `--dockerfile` | `/Dockerfile` | De onde sai o estágio `ext` |
| `dist` | `--version` | de `composer.json` | Sobrepor para uma beta |
| `version` | `--composer-json` | `/composer.json` | |
| `docs` | `--contract-only` | desligado | Omite o `--parseprivate`: só a visão de contrato |
| `integration-test` | `--args` | — | Repassado ao `go test`: `--args -run,TestYardStory` |
| `integration-test` | `--pool-size` | GOMAXPROCS | Quantos ambientes {API + banco} em paralelo |

-----

## ➕ Como acrescentar um método

1. **Decida onde.** Compõe outras funções? Um arquivo na raiz de `dagger/`. Constrói ambiente ou implementa lógica? Um módulo — a R2 diz que a raiz não tem `Container.From()`. `integration.go` é a única exceção, e o comentário lá explica por quê.

2. **Um arquivo, com o nome do comando.** `dagger call pest` vive em `pest.go`.

3. **Escreva a função.** A primeira linha do comentário vira a descrição no `dagger functions`:

   ```go
   package main

   import (
   	"context"
   	"dagger/back-php/internal/dagger"
   )

   // Lint roda o PHP_CodeSniffer sobre src/.
   func (m *BackPhp) Lint(
   	ctx context.Context,
   	// +defaultPath="/"
   	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
   	source *dagger.Directory,
   	// +defaultPath="/Dockerfile"
   	dockerfile *dagger.File,
   ) (string, error) {
   	return dag.Toolchain().Dev(dagger.ToolchainDevOpts{
   		Source: source, Dockerfile: dockerfile,
   	}).
   		WithExec([]string{"vendor/bin/phpcs", "src"}).
   		Stdout(ctx)
   }
   ```

4. **`dagger develop`** no módulo alterado, e depois na raiz se ela depende dele.

5. **`dagger functions`** para confirmar que apareceu.

### As armadilhas na hora de nomear

> **Nunca termine um arquivo em `_test.go`.** O sufixo é reservado pelo Go: o arquivo fica fora do build e a função não existe, com `unknown command` como único sintoma. É por isso que a suíte de integração está em `integration.go`.

> **Um módulo não pode se chamar `dist`, `build` nem `docs`.** O `.gitignore` deste repositório traz `dist/` e `build/` sem barra inicial, e o Dagger respeita o `.gitignore` — o módulo é carregado **vazio** e roda o esqueleto do `dagger init`, sem erro que explique. Confira com `git check-ignore -v dagger/modules/<nome>`.

> **`Api` vira `API` no binding gerado**, e `Json` vira `JSON`. Se o compilador reclamar de campo inexistente num `…Opts`, é isso.

> **`+ignore` idêntico ao das vizinhas.** Um divergente muda a chave de cache e faz a função reexecutar sozinha, sem erro nenhum.

### E se a função substitui um script

A regra é reimplementar, não envelopar — e **provar equivalência antes de apagar o original**, com `diff` da saída. As provas dos sete scripts que já saíram daqui estão na seção acima.

-----

## 🐢 A exceção: `integration-test`

É a única função que **não** se espera rápida, e a única que o CI não usa.

A suíte usa testcontainers-go: ela mesma constrói a imagem da API, sobe o MariaDB em tmpfs e um pool de ambientes. Isso exige um daemon Docker, e o daemon roda **dentro do mesmo container** que os testes.

**As duas alternativas mais óbvias falham, e vale saber como:**

* *Montar o socket do host* — os containers que o testcontainers subisse seriam irmãos no host, e um container Dagger não alcança o host. A suíte morre ao conectar nas portas mapeadas.
* *dockerd como serviço Dagger à parte, via `DOCKER_HOST`* — chega bem mais perto e falha no fim: o testcontainers pede ao daemon uma porta publicada e recebe algo como `32768`, que é uma porta do **host do daemon**. Do container de teste, `127.0.0.1:32768` é outro lugar. O erro é `dial tcp 127.0.0.1:32768: connect: connection refused`, **depois de doze minutos construindo a imagem**.

Com daemon e testes no mesmo namespace de rede, o endereço que o daemon informa é o que vale. Medido: **21 minutos**, verde.

```bash
dagger call integration-test
dagger call integration-test --args -run,TestYardStory
dagger call integration-test --pool-size 2
```

> O `ci.yml` roda `go test` direto, e a repetição de uma linha entre lá e aqui é deliberada: o runner já tem daemon, e fazê-lo passar por esta função aninharia um segundo daemon dentro do engine por nada. Esta função existe para a sua máquina, onde não há runner de quem herdar.

-----

## ♻️ A regra do `CACHED`

Duas execuções seguidas e idênticas: a segunda sai inteiramente `CACHED`. Medido: `dagger call phpstan` leva ~16 s na primeira e **2,3 s** na segunda.

Um passo que reexecuta significa entrada não determinística. O sintoma parece inofensivo e o custo é cache inútil para sempre, sem erro aparecer.

-----

## ⚠️ As armadilhas

> **O Dagger lê a sua árvore de trabalho; o CI lê um checkout novo.** Se o seu clone tiver CRLF onde o `.gitattributes` manda LF, `dagger call phpstan` acusa centenas de `phpDoc.parseError` em `src/API/Fbs/` — arquivos que ninguém editou. É exatamente o modo de falha que o [`../.gitattributes`](../.gitattributes) documenta no item 2. `git add --renormalize .` resolve.

> **O `--parseprivate` do `docs` não é opcional.** Toda classe sob `src/*/Interno/` é `@internal`, e o phpDocumentor as filtra do HTML sem essa flag. A renderização continua terminando com sucesso — só que com bem menos classes, em silêncio. `--contract-only` é a variação deliberada.

> **Editar o estágio `ext` do Dockerfile muda o ambiente das verificações.** É o objetivo: um só lugar. Mas quer dizer que uma mudança de extensão ali afeta PHPStan, Pest e a produção de uma vez.

> **O artefato não é reprodutível byte a byte entre execuções, e nunca foi.** O `--owner=0 --group=0 --numeric-owner` do tar normaliza quem é dono, mas nada normaliza a data: o `composer install` carimba cada arquivo do `vendor` com a hora da instalação. Duas execuções produzem o mesmo conteúdo e tarballs com bytes diferentes. Um `--mtime` fixo resolveria, ao custo de mudar os bytes de tudo que já foi publicado.

-----

## 📚 Relacionado

* [`../../dagger/README.md`](../../dagger/README.md) — as regras de arquitetura e a regra de decisão, na infraestrutura.
* [`../Dockerfile`](../Dockerfile) — os estágios `ext` e `base`.
* [`../docs/adr/0008-minified-tarball-as-the-release-artifact.md`](../docs/adr/0008-minified-tarball-as-the-release-artifact.md) — por que o artefato tem a forma que tem.
