package main

import "dagger/back/internal/dagger"

// Docs renderiza a referência de API com o phpDocumentor.
//
//	dagger call docs export --path docs/phpdocumentor
func (m *Back) Docs(
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// Omite o --parseprivate: só a visão de contrato, sem as classes @internal.
	// +optional
	contractOnly bool,
) *dagger.Directory {
	return dag.Docs().API(dagger.DocsAPIOpts{
		Source: source, ContractOnly: contractOnly,
	})
}
