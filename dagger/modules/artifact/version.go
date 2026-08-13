package main

import (
	"context"
	"dagger/artifact/internal/dagger"
	"encoding/json"
	"fmt"
	"strings"
)

// Version devolve a versão declarada em composer.json — a mesma fonte que a API
// em execução usa para se identificar.
//
// Lê o JSON aqui, em Go, em vez de invocar `php -r` ou `jq` num container: é um
// campo de um arquivo, e subir uma imagem para lê-lo era o tipo de passo que
// esta reescrita existe para eliminar.
func (m *Artifact) Version(
	ctx context.Context,
	// +defaultPath="/composer.json"
	composerJSON *dagger.File,
) (string, error) {
	contents, err := composerJSON.Contents(ctx)
	if err != nil {
		return "", err
	}

	var manifest struct {
		Version string `json:"version"`
	}
	if err := json.Unmarshal([]byte(contents), &manifest); err != nil {
		return "", fmt.Errorf("não consegui interpretar o composer.json: %w", err)
	}
	return strings.TrimSpace(manifest.Version), nil
}
