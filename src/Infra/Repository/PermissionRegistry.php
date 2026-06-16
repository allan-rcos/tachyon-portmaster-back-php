<?php

namespace Infra\Repository;

use Domain\Enums\Permissions;
use Domain\Ports\Repository\IPermissionRepository;
use OpenSwoole\Table;

final class PermissionRegistry implements IPermissionRepository
{
    // Usaremos duas tabelas para busca direta por chave primária em ambas as direções
    private Table $nameToValueTable;
    private Table $valueToNameTable;

    public function __construct()
    {
        $cases = Permissions::cases();
        $count = count($cases);

        // Instancia as tabelas calculando o tamanho necessário (potência de 2)
        $this->nameToValueTable = new Table($count);
        $this->nameToValueTable->column('value', Table::TYPE_INT);
        $this->nameToValueTable->create();

        $this->valueToNameTable = new Table($count);
        // Ajuste o tamanho da string se o nome dos seus Enums for maior que 32 caracteres
        $this->valueToNameTable->column('name', Table::TYPE_STRING, 32);
        $this->valueToNameTable->create();

        $this->populate($cases);
    }

    private function populate(array $cases): void
    {
        foreach ($cases as $case) {
            // Tabela 1: Chave é o Nome (String) -> Guarda o Valor (Int)
            $this->nameToValueTable->set($case->name,
                ['value' => $case->value]);

            // Tabela 2: Chave é o Valor convertido para String -> Guarda o Nome (String)
            $this->valueToNameTable->set((string) $case->value,
                ['name' => str_replace("_", ":", strtolower($case->name))]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getValueByName(string $name): ?int
    {
        $row = $this->nameToValueTable->get($name);
        return $row ? $row['value'] : null;
    }

    /**
     * @inheritDoc
     */
    public function getNameByValue(int $value): ?string
    {
        $row = $this->valueToNameTable->get((string) $value);
        return $row ? $row['name'] : null;
    }
}