package main

import (
	"context"
	"dagger/codegen/internal/dagger"
	"encoding/json"
	"fmt"
	"sort"
	"strings"
)

// A configuração usada para ANALISAR, sem a baseline incluída — do contrário os
// achados que ela já cobre desapareceriam da análise e a baseline nova sairia
// vazia.
//
// É a mesma substituição que o script fazia, com uma diferença: o script
// salvava o phpstan.neon, sobrescrevia e restaurava, e um crash no meio deixava
// o arquivo do repositório corrompido. Aqui a troca acontece na cópia dentro do
// container, e o arquivo do host nunca é tocado.
const phpstanAnalyseConfig = `parameters:
    inferPrivatePropertyTypeFromConstructor: true
    level: 9
    paths:
        - src
`

// phpstanReport é o recorte do --error-format=json que interessa.
type phpstanReport struct {
	Files map[string]struct {
		Messages []struct {
			Message string `json:"message"`
		} `json:"messages"`
	} `json:"files"`
}

// isGenerated decide o que entra na baseline.
//
// Um achado em arquivo escrito à mão é para CORRIGIR, não para silenciar — é
// por isso que existe esta função em vez de um `phpstan --generate-baseline`,
// que congelaria tudo que estivesse vermelho no dia. Proxies e contratos são
// escritos à mão mesmo vivendo sob src/API/Fbs.
func isGenerated(path string) bool {
	return strings.Contains(path, "/src/API/Fbs/") &&
		!strings.HasSuffix(path, "Proxy.php") &&
		!strings.Contains(path, "/Contracts/")
}

// pregQuote replica o preg_quote do PHP.
//
// NÃO é o regexp.QuoteMeta do Go, e a diferença quebra a baseline em silêncio:
// o QuoteMeta escapa `\.+*?()|[]{}^$` e para aí, enquanto o preg_quote escapa
// também `= ! < > : - # /`. Uma mensagem do PHPStan que contenha dois pontos —
// e muitas contêm — sairia escapada de um jeito pelo gerador e esperada de
// outro pelo PHPStan, e a entrada simplesmente não casaria.
//
// O delimitador usado no script era `#`, que já está na lista abaixo.
func pregQuote(s string) string {
	const special = `.\+*?[^]$(){}=!<>|:-#/`
	var b strings.Builder
	for _, r := range s {
		if r < 128 && strings.ContainsRune(special, r) {
			b.WriteByte('\\')
		}
		b.WriteRune(r)
	}
	return b.String()
}

// GeneratePhpstanBaseline reconstrói phpstan-generated-baseline.neon.
//
//	dagger call generate-phpstan-baseline export --path phpstan-generated-baseline.neon
//
// A análise roda no container; o filtro, o agrupamento e a emissão do NEON são
// Go. O script equivalente somava 58 linhas efetivas de PHP.
func (m *Codegen) GeneratePhpstanBaseline(
	ctx context.Context,
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// +defaultPath="/Dockerfile"
	dockerfile *dagger.File,
) (*dagger.File, error) {
	out, err := dag.Toolchain().Dev(dagger.ToolchainDevOpts{
		Source: source, Dockerfile: dockerfile,
	}).
		WithNewFile("/app/phpstan.neon", phpstanAnalyseConfig).
		// O PHPStan sai com código 1 quando encontra erros, e encontrar erros é
		// exatamente o objetivo aqui. Sem o Expect, o Dagger trataria a análise
		// bem-sucedida como falha.
		WithExec([]string{
			"vendor/bin/phpstan", "analyse",
			"--no-progress", "--error-format=json", "--memory-limit=1G",
		}, dagger.ContainerWithExecOpts{Expect: dagger.ReturnTypeAny}).
		Stdout(ctx)
	if err != nil {
		return nil, err
	}

	var report phpstanReport
	if err := json.Unmarshal([]byte(out), &report); err != nil {
		return nil, fmt.Errorf("não consegui interpretar o relatório do PHPStan: %w", err)
	}

	// mensagem -> caminho relativo -> quantas vezes
	entries := map[string]map[string]int{}
	kept, skipped := 0, 0

	for path, file := range report.Files {
		relative := strings.TrimPrefix(strings.TrimPrefix(path, "/app"), "/")
		for _, msg := range file.Messages {
			if !isGenerated(path) {
				skipped++
				continue
			}
			if entries[msg.Message] == nil {
				entries[msg.Message] = map[string]int{}
			}
			entries[msg.Message][relative]++
			kept++
		}
	}

	// A ordenação é o que torna a baseline estável entre execuções: sem ela, a
	// iteração sobre o mapa reordena o arquivo a cada geração e todo diff fica
	// ilegível.
	messages := make([]string, 0, len(entries))
	for msg := range entries {
		messages = append(messages, msg)
	}
	sort.Strings(messages)

	lines := []string{"parameters:", "\tignoreErrors:"}
	for _, msg := range messages {
		paths := make([]string, 0, len(entries[msg]))
		for p := range entries[msg] {
			paths = append(paths, p)
		}
		sort.Strings(paths)

		for _, p := range paths {
			lines = append(lines,
				"\t\t-",
				"\t\t\tmessage: '#^"+pregQuote(msg)+"$#'",
				fmt.Sprintf("\t\t\tcount: %d", entries[msg][p]),
				"\t\t\tpath: "+p,
			)
		}
	}

	fmt.Printf("baseline: %d achados de código gerado; %d de código escrito à mão seguem visíveis\n",
		kept, skipped)

	return dag.Directory().
		WithNewFile("phpstan-generated-baseline.neon", strings.Join(lines, "\n")+"\n").
		File("phpstan-generated-baseline.neon"), nil
}
