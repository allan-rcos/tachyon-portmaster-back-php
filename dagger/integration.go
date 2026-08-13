package main

import (
	"context"
	"dagger/back/internal/dagger"
	"strings"
)

// IntegrationTest roda a suíte Go de ponta a ponta.
//
//	dagger call integration-test
//	dagger call integration-test --args -run,TestYardStory
//	dagger call integration-test --pool-size 2
//
// ---------------------------------------------------------------------------
// Esta é a única função que não se espera rápida, e a única que constrói
// container aqui em vez de num módulo — o daemon Docker não é um "assunto" que
// se reaproveite, é uma condição desta suíte e de mais nada.
//
// O daemon e os testes rodam no MESMO container, e essa é a decisão que faz a
// coisa funcionar. A tentativa natural — dockerd como serviço Dagger à parte,
// alcançado por DOCKER_HOST — chega perto e falha no fim: o testcontainers pede
// ao daemon uma porta publicada e recebe algo como 32768, que é uma porta do
// HOST DO DAEMON. Do container de teste, `127.0.0.1:32768` é outro lugar, e a
// suíte morre com "dial tcp 127.0.0.1:32768: connect: connection refused"
// depois de doze minutos construindo a imagem. Com os dois no mesmo namespace
// de rede, o endereço que o daemon informa é o endereço que vale.
//
// A base é o docker:dind (Alpine, traz dockerd, containerd e o CLI) com o
// toolchain Go montado por cima, e não o contrário: mover /usr/local/go é
// copiar um diretório, enquanto trazer o daemon para a imagem do Go seria
// perseguir dockerd, containerd, runc e iptables um a um.
// ---------------------------------------------------------------------------
func (m *Back) IntegrationTest(
	ctx context.Context,
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// Argumentos extras repassados ao `go test`, para recortar a execução:
	//
	//	dagger call integration-test --args -run,TestYardStory
	//
	// +optional
	args []string,
	// Quantos ambientes {API + banco} sobem em paralelo. Vazio deixa a suíte
	// decidir pelo GOMAXPROCS.
	// +optional
	poolSize string,
) (string, error) {
	// Cada extra é aspado, para que um `-run 'TestX|TestY'` chegue inteiro em
	// vez de virar duas palavras.
	//
	// O timeout de 20 minutos não é folga: a suíte constrói a imagem da API e
	// reinicia um container por teste. O mesmo comando aparece no job
	// `go-integration` de .github/workflows/ci.yml, e a repetição de uma linha é
	// deliberada — o runner já tem um daemon Docker, e fazê-lo passar por aqui
	// aninharia um segundo daemon dentro do engine por nada.
	testCmd := "exec go test ./... -count=1 -timeout 20m"
	for _, a := range args {
		testCmd += " '" + strings.ReplaceAll(a, "'", `'\''`) + "'"
	}

	ctr := dag.Container().
		From("docker:28-dind").
		// `git` porque o `go test` pode precisar resolver módulo, e o cache nem
		// sempre está quente.
		WithExec([]string{"apk", "add", "--no-cache", "git"}).
		WithDirectory("/usr/local/go",
			dag.Container().From("golang:1.25-alpine").Directory("/usr/local/go")).
		WithEnvVariable("PATH", "/usr/local/go/bin:/go/bin:${PATH}",
			dagger.ContainerWithEnvVariableOpts{Expand: true}).
		WithEnvVariable("GOPATH", "/go").
		WithMountedCache("/var/lib/docker", dag.CacheVolume("back-dind")).
		WithMountedCache("/go/pkg/mod", dag.CacheVolume("back-go-mod")).
		WithMountedCache("/root/.cache/go-build", dag.CacheVolume("back-go-build")).
		// O Ryuk é o container que o testcontainers usa para limpar os outros
		// quando o processo morre. Aqui não tem o que vigiar — o daemon inteiro
		// é descartado no fim — e subi-lo custa tempo e às vezes falha por
		// permissão.
		WithEnvVariable("TESTCONTAINERS_RYUK_DISABLED", "true").
		WithMountedDirectory("/work", source).
		WithWorkdir("/work")

	if poolSize != "" {
		ctr = ctr.WithEnvVariable("INTEGRATION_POOL_SIZE", poolSize)
	}

	return ctr.
		WithExec([]string{"sh", "-c", `
			set -e
			dockerd --host=unix:///var/run/docker.sock \
			        --storage-driver=overlay2 >/tmp/dockerd.log 2>&1 &
			for i in $(seq 1 60); do
				docker info >/dev/null 2>&1 && break
				sleep 1
			done
			docker info >/dev/null 2>&1 || { echo "dockerd nao subiu"; cat /tmp/dockerd.log; exit 1; }
			cd tests/integration
			` + testCmd + `
		`}, dagger.ContainerWithExecOpts{
			// dockerd precisa de capacidades que um container Dagger comum não
			// tem. É o preço de um daemon aninhado, e a razão de esta função não
			// ser o caminho padrão no CI.
			InsecureRootCapabilities: true,
		}).
		Stdout(ctx)
}
