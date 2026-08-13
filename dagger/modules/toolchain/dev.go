package main

import "dagger/toolchain/internal/dagger"

// Dev devolve o Runtime com o código montado e as dependências de
// desenvolvimento instaladas — o container em que PHPStan e Pest rodam.
//
// As dependências entram ANTES do código: composer.json e composer.lock são
// montados sozinhos, o install roda, e só então o resto da árvore aparece. É o
// que faz uma mudança em src/ não reinstalar o vendor/ (R9).
//
// Nada de --ignore-platform-req aqui, ao contrário do build do artefato: o
// Runtime já traz ext-openswoole e ext-ds de verdade, então o composer.lock é
// honrado como está escrito.
func (m *Toolchain) Dev(
	// A árvore do repositório.
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// +defaultPath="/Dockerfile"
	dockerfile *dagger.File,
) *dagger.Container {
	return m.Runtime(dockerfile).
		// O cache do Composer sobrevive entre execuções e entre funções; sem
		// ele cada `dagger call pest` rebaixaria os pacotes da rede de novo.
		WithEnvVariable("COMPOSER_HOME", "/cache/composer").
		WithMountedCache("/cache/composer", dag.CacheVolume("back-composer")).
		WithWorkdir("/app").
		WithFile("/app/composer.json", source.File("composer.json")).
		WithFile("/app/composer.lock", source.File("composer.lock")).
		WithExec([]string{
			"composer", "install",
			"--no-interaction", "--no-progress", "--prefer-dist", "--no-scripts",
		}).
		WithDirectory("/app", source, dagger.ContainerWithDirectoryOpts{
			// vendor/ acabou de ser instalado no passo acima; deixar a árvore
			// sobrescrevê-lo apagaria o install.
			Exclude: []string{"vendor"},
		}).
		// O autoloader precisa ser regerado: o install rodou quando src/ e
		// tests/ ainda não estavam montados, então o classmap nasceu vazio.
		WithExec([]string{"composer", "dump-autoload", "--no-interaction"})
}
