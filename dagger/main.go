// Back — as verificações, o código gerado e o empacotamento da API.
//
// ---------------------------------------------------------------------------
// COMO ESTE DIRETÓRIO É ORGANIZADO
//
// Um arquivo por função, com o nome do comando. Para mexer no que
// `dagger call phpstan` faz, abra phpstan.go — não é preciso caçar dentro de um
// arquivo de trezentas linhas.
//
//	main.go          só o tipo e esta explicação
//	ci.go            dagger call ci
//	phpstan.go       dagger call phpstan
//	pest.go          dagger call pest
//	dist.go          dagger call dist
//	version.go       dagger call version
//	docs.go          dagger call docs
//	integration.go   dagger call integration-test
//	fbsphp.go        dagger call generate-fbs-php
//	fbsgo.go         dagger call generate-fbs-go
//	fbscheck.go      dagger call check-fbs-go
//	baseline.go      dagger call generate-phpstan-baseline
//
// O Go trata todos como um pacote só, então não há import entre eles.
//
// > O arquivo da suíte se chama `integration.go` e NÃO `integration_test.go`:
// > o sufixo `_test.go` é reservado pelo Go para arquivos de teste, que ficam
// > FORA do build normal. Com esse nome a função simplesmente não existiria, e
// > o erro seria "unknown command integration-test".
//
// ---------------------------------------------------------------------------
// O QUE É GERADO E O QUE VOCÊ ESCREVE
//
//	escrito à mão   dagger.json, go.mod, go.sum, *.go, modules/
//	gerado          dagger.gen.go, internal/dagger/, internal/telemetry/
//
// O gerado já está no .gitignore deste diretório e é reconstruído por
// `dagger develop`. Não edite nada dele: a execução seguinte sobrescreve.
//
// ---------------------------------------------------------------------------
// AS REGRAS
//
// R2 — o módulo raiz só compõe, nunca implementa. Nenhum `Container.From()`
// nestes arquivos: o ambiente está em modules/toolchain, o artefato em
// modules/artifact, os geradores em modules/codegen, a documentação em
// modules/docs. A única exceção é integration.go, e o comentário lá explica por
// que o daemon Docker não virou módulo.
//
// R3 — toda função é chamável isolada. Ci() encadeia Phpstan() e Pest(), mas as
// duas funcionam sozinhas — é o análogo do `--tags` do Ansible.
//
// > A lista de `+ignore` se repete em cada arquivo, e não há como evitar: é uma
// > diretiva em comentário, lida pelo introspector antes de o Go compilar, então
// > não pode ser uma constante. Se mudar uma, mude todas — um `+ignore`
// > divergente muda a chave de cache e faz a função reexecutar sozinha.
// ---------------------------------------------------------------------------
package main

type BackPhp struct{}
