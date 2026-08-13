// Toolchain — em que PHP este repositório roda.
//
// Um arquivo por ambiente:
//
//	main.go     o tipo e a versão do Composer
//	runtime.go  o PHP da produção — onde PHPStan e Pest rodam
//	dev.go      o runtime com o código e as dependências de desenvolvimento
//	musl.go     o Alpine que MONTA o artefato
//
// ---------------------------------------------------------------------------
// São DOIS ambientes, e a diferença não é acidente.
//
// Runtime() é php:8.4-cli sobre glibc, com ds e openswoole via PECL — o estágio
// `ext` do Dockerfile, literalmente o mesmo target e não uma cópia da lista. É
// onde a API roda em produção, e por isso é onde as verificações rodam.
//
// Musl() é alpine:3.22 com php84, sobre musl. Existe só para MONTAR o artefato:
// o tarball é implantado numa VM Alpine, e um build feito em glibc produziria
// um vendor/ que não corresponde ao alvo. Ver o comentário de release.yml:
// "Alpine, to match the musl of the target the tarball is deployed to."
//
// A razão de Runtime() sair do Dockerfile em vez de repetir a lista de
// extensões é a R8. Antes desta migração a mesma informação vivia em três
// lugares — o Dockerfile, os dois jobs de ci.yml (via setup-php) e o release.yml
// (via apk) — e o custo apareceu como o pin `openswoole-26.2.0` escrito à mão,
// porque o padrão do setup-php era 25.2.0 enquanto o composer.lock exigia
// >= 26.2.0. Agora Runtime() constrói o alvo `ext`, e não há o que sincronizar.
// ---------------------------------------------------------------------------
package main

// A versão do Composer é fixada porque o artefato tem de ser reprodutível: um
// Composer diferente pode resolver o mesmo composer.lock para um autoloader
// diferente. É a mesma versão que .github/workflows/release.yml usava.
const composerVersion = "2.8.10"

type Toolchain struct{}
