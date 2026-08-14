package main

import "dagger/back-php/internal/dagger"

// Dist monta o artefato de produção e devolve o diretório dist/.
//
// Use com `export`: o Dagger não escreve no host por conta própria (R6).
//
//	dagger call dist --version 1.2.0 export --path dist
func (m *BackPhp) Dist(
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// +optional
	version string,
) *dagger.Directory {
	return dag.Artifact().Build(dagger.ArtifactBuildOpts{
		Source: source, Version: version,
	})
}
