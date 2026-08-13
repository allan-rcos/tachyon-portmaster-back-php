package main

import (
	"context"
	"dagger/codegen/internal/dagger"
	"regexp"
	"strings"
)

// -----------------------------------------------------------------------
// As transformações sobre o PHP que o flatc emite.
//
// Cada uma conserta um defeito conhecido do codegen PHP do FlatBuffers, e
// todas são determinísticas e idempotentes — rodar duas vezes dá o mesmo
// resultado. Editar o gerado à mão não é alternativa: a geração seguinte
// sobrescreve. Acrescentar um defeito novo aqui é o caminho sancionado.
// -----------------------------------------------------------------------

var (
	// 2. O docblock de `create<Table>()`. O flatc anota `@return <Table>` num
	//    método que devolve o offset int da tabela, o que envenena o tipo
	//    inferido de todo chamador. A âncora é o terminador do docblock
	//    imediatamente antes de `public static function create`, de modo que os
	//    helpers de vetor e o getRootAs/init ficam de fora.
	reCreateReturn = regexp.MustCompile(
		`(\*\s*@return\s+)\S+(\s*\n\s*\*/\s*\n\s*public static function create)`)

	// 3. O acessor de tabela filha única. O flatc emite `: 0` para uma filha
	//    ausente — um int onde se espera objeto — e o resultado é uma união
	//    `<Child>|int`. Acessores de vetor já devolvem null, e os escalares
	//    mantêm o próprio default numérico porque não passam por `init`.
	reChildSentinel = regexp.MustCompile(
		`(return \$o != 0 \? \$obj->init\([^;]*?\)) : 0;`)

	// 4. Nomes escalares do FlatBuffers não são tipos PHP, então o PHPStan
	//    reporta cada um como erro de parse de phpDoc e não infere nada.
	reScalarReturn = regexp.MustCompile(
		`(\*\s*@return\s+)(double|float|byte|ubyte|short|ushort|int|uint|long|ulong|bool)\b`)

	// 5. Acessores de string saem sem tipo, o que faz todo chamador ver `mixed`
	//    e obriga um cast em cada proxy. Eles devolvem a string ou null, então
	//    é isso que passam a declarar.
	reStringAccessor = regexp.MustCompile(
		`public function (get\w+)\(\)(\n\s*\{\n\s*\$o = \$this->__offset\(\d+\);\n\s*return \$o != 0 \? \$this->__string\([^;]*?\) : null;)`)
)

// patchFlatc aplica as transformações a um arquivo gerado pelo flatc.
//
// Nota sobre o que NÃO foi portado: o patch-flatbuffers.php tinha mais uma
// substituição, logo antes da 5, cujo padrão capturava o trecho inteiro num
// grupo e o substituía por `$1` — isto é, por ele mesmo. Era um no-op, e a
// prova é que a saída daqui bate byte a byte com a que aquele script produzia.
func patchFlatc(code string) string {
	// 1. Caixa da classe do builder. O upstream declara
	//    `Google\FlatBuffers\FlatbufferBuilder` (b minúsculo) e o flatc
	//    referencia `FlatBufferBuilder` (B maiúsculo), o que quebra o autoload
	//    PSR-4 num sistema de arquivos sensível a caixa. Normalizar aqui evita
	//    um shim no bootstrap.
	code = strings.ReplaceAll(code, "FlatBufferBuilder", "FlatbufferBuilder")

	code = reCreateReturn.ReplaceAllString(code, "${1}int${2}")
	code = reChildSentinel.ReplaceAllString(code, "${1} : null;")

	code = reScalarReturn.ReplaceAllStringFunc(code, func(m string) string {
		g := reScalarReturn.FindStringSubmatch(m)
		var phpType string
		switch g[2] {
		case "double", "float":
			phpType = "float"
		case "bool":
			phpType = "bool"
		default:
			phpType = "int"
		}
		return g[1] + phpType
	})

	return reStringAccessor.ReplaceAllString(code, "public function ${1}(): ?string${2}")
}

// GenerateFbsPhp regenera os bindings PHP e devolve src/API/Fbs.
//
// Duas etapas, e as duas costumavam ser scripts: o flatc emite as tabelas, e as
// transformações de patchFlatc consertam o que ele emite. Rodar só a primeira
// produz código que o PHPStan recusa e que obriga um cast em cada proxy.
func (m *Codegen) GenerateFbsPhp(
	ctx context.Context,
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
) (*dagger.Directory, error) {
	// Debian, porque o withFlatc instala por apt. E, note, sem PHP nenhum: com
	// as transformações em Go, esta etapa passou a precisar só do flatc — o
	// generate-flatbuffers.php exigia um PHP inteiro para invocá-lo.
	generated := withFlatc(dag.Container().From("debian:trixie-slim")).
		WithMountedDirectory("/work", source).
		WithWorkdir("/work").
		WithExec([]string{"sh", "-c",
			"mkdir -p src && flatc --php -o src/ swagger/flatbuffers/schemas/*.fbs"}).
		Directory("/work/src/API/Fbs")

	paths, err := generated.Glob(ctx, "**/*.php")
	if err != nil {
		return nil, err
	}

	out := generated
	for _, p := range paths {
		code, err := generated.File(p).Contents(ctx)
		if err != nil {
			return nil, err
		}
		if !strings.Contains(code, flatcHeader) {
			continue
		}
		if patched := patchFlatc(code); patched != code {
			out = out.WithNewFile(p, patched)
		}
	}
	return out, nil
}
