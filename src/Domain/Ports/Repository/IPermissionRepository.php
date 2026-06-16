<?php

namespace Domain\Ports\Repository;

interface IPermissionRepository
{
    /**
     * Converte o Nome (String) para o Valor (Int) para salvar no banco.
     */
    public function getValueByName(string $name): ?int;

    /**
     * Converte o Valor (Int) para o Nome (String) para enviar ao Front-end.
     */
    public function getNameByValue(int $value): ?string;
}