package main

import (
	"context"
	"dagger/back/internal/dagger"
)

// Phpstan roda a análise estática em nível 9 sobre src/.
//
// Roda no MESMO PHP da produção — o estágio `ext` do Dockerfile. É o que
// dispensa o pin de openswoole que o ci.yml carregava: a extensão é a que a
// imagem instala, não uma escolhida por uma action à parte.
func (m *Back) Phpstan(
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
		WithExec([]string{"vendor/bin/phpstan", "analyse", "--memory-limit=2G"}).
		Stdout(ctx)
}
