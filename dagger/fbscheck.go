package main

import (
	"context"
	"dagger/back-php/internal/dagger"
)

// CheckFbsGo falha se os bindings Go commitados estiverem defasados.
func (m *BackPhp) CheckFbsGo(
	ctx context.Context,
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
) (string, error) {
	return dag.Codegen().CheckFbsGo(ctx, dagger.CodegenCheckFbsGoOpts{Source: source})
}
