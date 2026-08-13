package main

import (
	"context"
	"dagger/back-php/internal/dagger"
)

// Version devolve a versão declarada em composer.json — a mesma que a API em
// execução reporta.
func (m *BackPhp) Version(
	ctx context.Context,
	// +defaultPath="/composer.json"
	composerJSON *dagger.File,
) (string, error) {
	return dag.Artifact().Version(ctx, dagger.ArtifactVersionOpts{ComposerJSON: composerJSON})
}
