package main

import "dagger/back/internal/dagger"

// GeneratePhpstanBaseline reconstrói a baseline do PHPStan.
//
//	dagger call generate-phpstan-baseline export --path phpstan-generated-baseline.neon
func (m *Back) GeneratePhpstanBaseline(
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// +defaultPath="/Dockerfile"
	dockerfile *dagger.File,
) *dagger.File {
	return dag.Codegen().GeneratePhpstanBaseline(dagger.CodegenGeneratePhpstanBaselineOpts{
		Source: source, Dockerfile: dockerfile,
	})
}
