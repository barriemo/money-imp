<?php

namespace App\Domains\Suppliers\Documents\Parsers;

interface SupplierDocumentParser
{
    public function supports(
        string $supplier
    ): bool;

    public function parse(
        string $text
    ): array;
}
