package main

import "dagger/back-php/internal/dagger"

// GenerateFbsGo regenera os bindings Go da suíte de integração.
//
//	dagger call generate-fbs-go export --path tests/integration/internal/fbs
func (m *BackPhp) GenerateFbsGo(
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
) *dagger.Directory {
	return dag.Codegen().GenerateFbsGo(dagger.CodegenGenerateFbsGoOpts{Source: source})
}
