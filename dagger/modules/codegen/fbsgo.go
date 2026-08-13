package main

import "dagger/codegen/internal/dagger"

// GenerateFbsGo regenera os bindings Go da suíte de integração e devolve
// tests/integration/internal/fbs.
//
// Os dois `perl -i` parecem cosméticos e não são: o flatc não reescreve as
// referências cruzadas quando `--go-namespace` colapsa os namespaces, e deixa
// pendurados os imports `API__Fbs__X "API/Fbs/X"` e os prefixos
// correspondentes. Sem os dois o pacote não compila, e a mensagem fala de
// import não usado — nunca de namespace.
//
// Continuam sendo perl, e não Go, porque rodam sobre arquivos que o flatc
// acabou de escrever DENTRO do container, num passo só: trazer quarenta
// arquivos para o lado do engine para aplicar duas substituições e devolvê-los
// custaria mais do que o passo inteiro.
func (m *Codegen) GenerateFbsGo(
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
) *dagger.Directory {
	// A imagem do Go traz gofmt e perl; o flatc vem por cima. É o mesmo Go da
	// suíte de integração — ver `go-version` em .github/workflows/ci.yml.
	return withFlatc(dag.Container().From("golang:1.25")).
		WithMountedDirectory("/work", source).
		WithWorkdir("/work").
		WithExec([]string{"sh", "-c", `
			set -e
			out=tests/integration/internal/fbs
			rm -rf "$out" && mkdir -p "$out"

			# --go-namespace colapsa o namespace de todos os schemas num pacote
			# Go so, para a suite importar um fbs e nada mais.
			flatc --go --go-namespace fbs \
			      -o tests/integration/internal \
			      swagger/flatbuffers/schemas/*.fbs

			perl -i -ne 'print unless /^\s*API__Fbs__\w+ "API\/Fbs\/\w+"$/' "$out"/*.go
			perl -i -pe 's/API__Fbs__\w+\.//g' "$out"/*.go
			gofmt -w "$out"
		`}).
		Directory("/work/tests/integration/internal/fbs")
}
