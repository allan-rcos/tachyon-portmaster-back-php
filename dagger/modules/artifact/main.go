// Artifact — o artefato de produção da API.
//
// Um arquivo por assunto:
//
//	main.go      o tipo, e as decisões que o build toma
//	build.go     dagger call dist
//	version.go   dagger call version
//
// ---------------------------------------------------------------------------
// Este módulo é o antigo scripts/build-dist.sh, REESCRITO.
//
// As decisões — o que conta como dependência de docblock, o que é podado do
// vendor, como o tar é tornado independente de quem o montou — são os valores
// Go declarados abaixo, cada um com o motivo junto.
//
// O que continua sendo `withExec` são as FERRAMENTAS: `composer`, `php -w`,
// `tar` e `zstd` são programas que rodam dentro do container, e orquestrá-los é
// precisamente o que o Dagger faz. O que saiu foi a lógica que decidia por eles.
//
// O raciocínio por trás da forma do artefato está em
// docs/adr/0008-minified-tarball-as-the-release-artifact.md.
// ---------------------------------------------------------------------------
//
// O módulo se chama `artifact` e NÃO `dist` porque o .gitignore deste
// repositório traz `dist/` — um padrão sem barra inicial, que o git aplica a
// qualquer diretório com esse nome em qualquer profundidade. O Dagger respeita
// o .gitignore ao carregar um módulo local, então um módulo em
// dagger/modules/dist/ seria carregado VAZIO: o main.go nunca chegaria ao
// engine, e o que rodaria seria o esqueleto do `dagger init`, sem erro nenhum
// para explicar por quê.
package main

// O alvo é sempre o mesmo, e entra no nome do arquivo: o tarball é implantado
// numa VM Alpine x86_64, e é por isso que o toolchain que o monta é musl.
const platform = "linux-x86_64"

// Nada em src/ pode depender de docblock.
//
// O `php -w` do passo de minificação é o php_strip_whitespace: ele descarta
// comentários e espaços preservando a semântica. Isso é seguro NESTE projeto
// porque nada aqui é dirigido por anotação, e a guarda reconfirma isso a cada
// build. Se alguém introduzir uma dependência de docblock, o build falha ali —
// em vez de a API subir em produção com um atributo que sumiu em silêncio.
var docblockDependencies = []string{
	"getDocComment",
	"ReflectionClass",
	"ReflectionMethod",
	"ReflectionProperty",
}

// Diretórios que somem do vendor: o que nunca é carregado em runtime.
var prunedDirs = []string{
	"tests", "Tests", "test", "Test",
	"docs", "doc", "examples", "example",
	".github", ".git", "benchmarks",
}

// Arquivos que somem do vendor.
//
// Os LICENSE ficam, e não é descuido: descartar a licença de uma dependência
// não economiza nada que valha e quebra os termos de redistribuição de boa
// parte delas.
var prunedFiles = []string{
	"*.md", "*.rst", "*.dist",
	"phpunit.xml*", "*.neon", "*.neon.dist",
	"psalm*", "Makefile", ".editorconfig",
	".php_cs*", ".php-cs-fixer*", "phpbench.json",
	"*.yml", "*.yaml",
}

type Artifact struct{}
