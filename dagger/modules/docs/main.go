// Docs — a referência de API renderizada a partir dos docblocks de src/.
//
// R7 (envelopar, nunca reimplementar): a configuração continua sendo o
// phpdoc.dist.xml do repositório, e a imagem continua sendo a oficial do
// phpDocumentor. O que sai daqui é o mesmo HTML que o scripts/generate-docs.sh
// produz — este módulo só o produz sem exigir nada instalado na sua máquina.
//
// ---------------------------------------------------------------------------
// Este módulo apaga um remendo, e vale saber qual.
//
// O generate-docs.sh, quando cai no fallback de Docker, chama
// `docker run --user "$(id -u):$(id -g)"`. Esse --user existe só porque o
// `docker run` grava como root: sem ele, docs/phpdocumentor e build/docs-cache
// nascem pertencendo ao root e a execução SEGUINTE — a que não usa Docker —
// falha ao escrever no próprio cache.
//
// Aqui o container não escreve no host coisa nenhuma (R6). A saída volta como
// Directory e quem grava é o `export`, com o usuário de quem chamou. O remendo
// deixa de ter função.
// ---------------------------------------------------------------------------
package main

import (
	"dagger/docs/internal/dagger"
)

// A mesma imagem que o fallback de scripts/generate-docs.sh usa.
//
// O phpDocumentor é distribuído como PHAR e como imagem, e deliberadamente NÃO
// é dependência do composer: ele traz a própria árvore de Twig, Symfony e
// phpDocumentor/*, que conflita com o que a aplicação e o PHPStan já fixam.
const phpdocImage = "phpdoc/phpdoc:3"

type Docs struct{}

// Api renderiza a referência de API e devolve o diretório docs/phpdocumentor.
//
//	dagger call docs export --path docs/phpdocumentor
//
// A saída é versionada, não gitignorada, porque o GitHub Pages serve o docs/
// deste repositório — a referência só é publicada se estiver na árvore.
//
// O --parseprivate NÃO é opcional, e é o invariante mais fácil de perder aqui.
// Toda classe de implementação sob src/*/Interno/ é marcada @internal, e o
// phpDocumentor filtra elementos @internal do HTML a menos que essa flag seja
// passada. Sem ela a renderização continua terminando com sucesso, só que ~116
// classes — e todas as notas de implementação escritas nelas — desaparecem em
// silêncio. É por isso que a flag é padrão aqui e não algo a lembrar na hora.
func (m *Docs) Api(
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// Renderiza só a visão de contrato, omitindo o --parseprivate e portanto
	// as classes @internal. É a variação que o comentário de
	// scripts/generate-docs.sh descreve; o padrão continua sendo a documentação
	// completa.
	// +optional
	contractOnly bool,
) *dagger.Directory {
	args := []string{"--parseprivate"}
	if contractOnly {
		args = []string{}
	}

	return dag.Container().
		From(phpdocImage).
		WithMountedDirectory("/data", source).
		WithWorkdir("/data").
		// O cache sobrevive entre execuções e torna a renderização incremental
		// muito mais rápida. Fica num CacheVolume em vez de build/docs-cache
		// para não depender de o diretório existir no host.
		WithMountedCache("/data/build/docs-cache", dag.CacheVolume("back-phpdoc-cache")).
		// UseEntrypoint, porque `--parseprivate` é argumento do phpdoc e não um
		// executável: sem isto o Dagger tenta rodá-lo como comando e falha com
		// "executable file not found in $PATH", que não sugere entrypoint
		// nenhum. A imagem do phpDocumentor declara o binário como ENTRYPOINT,
		// e é assim que o docker run do generate-docs.sh também o invoca.
		WithExec(args,
			dagger.ContainerWithExecOpts{UseEntrypoint: true}).
		Directory("/data/docs/phpdocumentor")
}
