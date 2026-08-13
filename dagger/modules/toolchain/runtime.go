package main

import "dagger/toolchain/internal/dagger"

// Runtime devolve o ambiente PHP em que a API roda: o estágio `ext` do
// Dockerfile deste repositório.
//
// Recebe apenas o Dockerfile, nunca a árvore inteira (R10). O estágio `ext` não
// copia nada do contexto, então amarrar o cache ao arquivo único mantém o
// ambiente construído uma vez só e reaproveitado por todas as outras funções,
// por mais que o código da aplicação mude.
func (m *Toolchain) Runtime(
	// O Dockerfile do repositório, de onde sai o estágio `ext`.
	// +defaultPath="/Dockerfile"
	dockerfile *dagger.File,
) *dagger.Container {
	return dag.Directory().
		WithFile("Dockerfile", dockerfile).
		DockerBuild(dagger.DirectoryDockerBuildOpts{Target: "ext"})
}
