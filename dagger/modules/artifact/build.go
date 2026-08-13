package main

import (
	"context"
	"dagger/artifact/internal/dagger"
	"fmt"
	"strings"
)

// Build monta os dois tarballs de produção e devolve o diretório dist/.
//
// Sobre `+ignore`: o .git NÃO entra, e isso é um invariante, não economia. O
// .git do submódulo é um gitlink apontando para ../.git/modules/back, que não
// existe dentro do container; o Composer tenta detectar VCS, não encontra o
// repositório e aborta com "not a git repository".
//
// vendor/ também fica de fora, e por outro motivo: as dependências de produção
// são instaladas num stage próprio, de propósito, para não destruir o vendor/ de
// desenvolvimento de quem rodou — o `composer install --no-dev` ingênuo, na
// raiz, apaga PHPStan, Pest e Mockery da cópia de trabalho.
func (m *Artifact) Build(
	ctx context.Context,
	// A árvore do repositório.
	// +defaultPath="/"
	// +ignore=["vendor", "dist", "docs", "build", ".git", ".github", "node_modules", "**/.git"]
	source *dagger.Directory,
	// A versão do artefato. Vazio lê de composer.json, que é onde ela é
	// declarada — o mesmo campo que a API em execução reporta.
	// +optional
	version string,
) (*dagger.Directory, error) {
	if version == "" {
		v, err := m.Version(ctx, source.File("composer.json"))
		if err != nil {
			return nil, err
		}
		version = v
	}
	version = strings.TrimPrefix(version, "v")
	if version == "" {
		return nil, fmt.Errorf("composer.json não declara version, e nenhuma foi passada")
	}

	apiName := fmt.Sprintf("portmaster-api-%s-%s", version, platform)
	migName := fmt.Sprintf("portmaster-migrations-%s", version)
	stage := "/stage/" + apiName

	base := dag.Toolchain().Musl().
		WithMountedDirectory("/work", source).
		WithWorkdir("/work")

	return base.
		// 1. A guarda de docblock.
		//
		// Roda como um grep só, e não como leitura arquivo a arquivo do lado do
		// engine, porque a pergunta é um booleano sobre ~335 arquivos: trazer
		// todos para cá custaria centenas de idas e voltas para responder
		// "algum casa?". O que importa que seja Go é a LISTA, em main.go.
		WithExec([]string{"sh", "-c", fmt.Sprintf(
			`if grep -rEl '%s' src/ 2>/dev/null | grep -q .; then
				echo "ERRO: src/ passou a usar reflexao sobre docblocks; o php -w deixou de ser seguro." >&2
				grep -rEn '%s' src/ >&2
				exit 1
			fi`,
			strings.Join(docblockDependencies, "|"),
			strings.Join(docblockDependencies, "|"))}).
		// 2. O stage, e as dependências de produção instaladas DENTRO dele.
		//
		// O composer.lock acompanha o composer.json: sem ele o Composer resolve
		// do zero e o artefato deixa de ser reprodutível.
		//
		// O --ignore-platform-req=ext-openswoole é deliberado: openswoole é
		// requisito de RUNTIME, e a máquina que monta o artefato não roda a API.
		// Honrá-lo significaria compilar a extensão só para o Composer riscar
		// uma linha do composer.json.
		WithExec([]string{"mkdir", "-p", stage, "/stage/" + migName}).
		WithExec([]string{"cp", "-a", "src", stage + "/src"}).
		WithExec([]string{"cp", "composer.json", "composer.lock", stage + "/"}).
		WithExec([]string{
			"composer", "install",
			"--working-dir=" + stage,
			"--no-dev", "--prefer-dist", "--no-scripts",
			"--no-interaction", "--no-progress",
			"--classmap-authoritative",
			"--ignore-platform-req=ext-openswoole",
		}).
		// 3. A poda do vendor.
		WithExec([]string{"sh", "-c", fmt.Sprintf(
			`find %s/vendor -type d \( %s \) -prune -exec rm -rf {} + 2>/dev/null || true
			 find %s/vendor -type f \( %s \) -delete 2>/dev/null || true`,
			stage, findNameClause(prunedDirs),
			stage, findNameClause(prunedFiles))}).
		// 4. A minificação.
		//
		// vendor/bin fica de fora: são executáveis com shebang, e nenhum deles é
		// carregado pela API em runtime. O `-s` confere que a saída não é vazia
		// antes de substituir — um php -w que falhe não pode zerar o arquivo.
		WithExec([]string{"sh", "-c", fmt.Sprintf(
			`find %s/src %s/vendor -type f -name '*.php' -not -path '*/vendor/bin/*' -print0 |
			 while IFS= read -r -d '' f; do
				if php -w "$f" > "$f.min" 2>/dev/null && [ -s "$f.min" ]; then
					mv "$f.min" "$f"
				else
					rm -f "$f.min"
				fi
			 done`, stage, stage)}).
		// 5. As migrations, no pacote delas.
		//
		// Separadas de propósito: quem as aplica é a máquina de quem desenvolve
		// falando com o banco, não o servidor que roda a API. Enviá-las dentro
		// do tarball da API poria uma mudança de schema na máquina menos
		// autorizada a fazer uma.
		WithExec([]string{"cp", "-a", "db", "/stage/" + migName + "/db"}).
		// 6. Os tarballs, e os checksums.
		WithExec([]string{"mkdir", "-p", "/out"}).
		WithExec(tarArgs(apiName)).
		WithExec(tarArgs(migName)).
		WithExec([]string{"sh", "-c",
			`cd /out && for f in *.tar.zst; do sha256sum "$f" > "$f.sha256"; done`}).
		Directory("/out"), nil
}

// findNameClause monta a cláusula `-name a -o -name b …` a partir de uma lista
// Go, para que os padrões podados sejam declarados uma vez, em main.go, e não
// escondidos dentro de um comando.
func findNameClause(patterns []string) string {
	quoted := make([]string, len(patterns))
	for i, p := range patterns {
		quoted[i] = "-name '" + p + "'"
	}
	return strings.Join(quoted, " -o ")
}

// tarArgs monta o comando de empacotamento de um dos dois pacotes.
//
// --owner/--group/--numeric-owner mantêm o arquivo independente de quem o
// montou. O que eles NÃO normalizam é a data: o `composer install` carimba cada
// arquivo do vendor com a hora da instalação, então duas execuções produzem o
// mesmo conteúdo em tarballs de bytes diferentes. Um `--mtime` fixo resolveria,
// ao custo de mudar os bytes de tudo que já foi publicado.
func tarArgs(name string) []string {
	return []string{
		"tar", "--use-compress-program=zstd -19 -T0",
		"--owner=0", "--group=0", "--numeric-owner",
		"-C", "/stage", "-cf", "/out/" + name + ".tar.zst", name,
	}
}
