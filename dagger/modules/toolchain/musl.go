package main

import "dagger/toolchain/internal/dagger"

// Musl devolve o toolchain Alpine que MONTA o artefato de produção.
//
// A lista de pacotes é a que o release.yml carregava, e três itens dela custam
// uma tarde cada se forem descobertos por build vermelho:
//
//  1. php84-openssl. O php84 do Alpine não o traz por padrão, e sem ele todo
//     download HTTPS de dentro do PHP morre com "Unable to find the socket
//     transport ssl" — que se lê como falha de rede, não como extensão faltando.
//
//  2. bash. Os scripts que rodam aqui são bash por convenção, e o `sh` do
//     Alpine é o ash do busybox, que não entende `set -o pipefail`.
//
//  3. O composer.phar oficial, NUNCA o pacote `composer` do Alpine. O wrapper do
//     pacote chama /usr/bin/php83; o build rodaria silenciosamente na versão
//     errada de PHP, sem erro nenhum, e a divergência só apareceria em produção.
func (m *Toolchain) Musl() *dagger.Container {
	return dag.Container().
		From("alpine:3.22").
		WithExec([]string{
			"apk", "add", "--no-cache",
			"php84", "php84-phar", "php84-openssl", "php84-mbstring", "php84-iconv",
			"php84-tokenizer", "php84-dom", "php84-xml", "php84-xmlwriter",
			"php84-pecl-ds", "php84-curl", "php84-session", "php84-fileinfo",
			"php84-pdo", "php84-pdo_mysql",
			"bash", "git", "curl", "tar", "zstd", "coreutils", "findutils", "gawk",
		}).
		WithExec([]string{"ln", "-sf", "/usr/bin/php84", "/usr/local/bin/php"}).
		WithExec([]string{
			"curl", "-fsSL",
			"https://getcomposer.org/download/" + composerVersion + "/composer.phar",
			"-o", "/usr/local/bin/composer",
		}).
		WithExec([]string{"chmod", "+x", "/usr/local/bin/composer"}).
		WithEnvVariable("COMPOSER_HOME", "/cache/composer").
		WithMountedCache("/cache/composer", dag.CacheVolume("back-composer-musl"))
}
