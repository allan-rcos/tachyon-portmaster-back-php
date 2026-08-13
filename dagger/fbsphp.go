package main

import "dagger/back/internal/dagger"

// GenerateFbsPhp regenera os bindings PHP — rode depois de mexer num .fbs.
//
//	dagger call generate-fbs-php export --path src/API/Fbs
func (m *Back) GenerateFbsPhp(
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
) *dagger.Directory {
	return dag.Codegen().GenerateFbsPhp(dagger.CodegenGenerateFbsPhpOpts{Source: source})
}
