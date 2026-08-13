package main

import (
	"context"
	"dagger/back/internal/dagger"
)

// Pest roda a suíte unitária.
//
// Só os unitários: tests/integration é uma suíte Go, com testcontainers, e tem
// função própria — ver integration.go.
func (m *Back) Pest(
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
		WithExec([]string{"vendor/bin/pest"}).
		Stdout(ctx)
}
