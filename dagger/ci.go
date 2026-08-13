package main

import (
	"context"
	"dagger/back/internal/dagger"
)

// Ci roda o que tem de estar verde antes de um merge.
//
// A ordem é deliberada: a análise estática vem primeiro porque é mais barata
// que a suíte e porque um erro de tipo costuma explicar um teste vermelho,
// enquanto o contrário quase nunca acontece.
//
// A suíte de integração fica de fora, como no ci.yml: é um job à parte, com
// custo e teto de tempo próprios.
func (m *Back) Ci(
	ctx context.Context,
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// +defaultPath="/Dockerfile"
	dockerfile *dagger.File,
) (string, error) {
	out, err := m.Phpstan(ctx, source, dockerfile)
	if err != nil {
		return out, err
	}
	pest, err := m.Pest(ctx, source, dockerfile)
	return out + "\n" + pest, err
}
