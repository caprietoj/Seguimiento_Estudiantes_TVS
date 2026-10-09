<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Validated = 'validated';
    case Imported = 'imported';
    case Reverted = 'reverted';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Validated => 'Validado (simulación)',
            self::Imported => 'Importado',
            self::Reverted => 'Revertido',
            self::Failed => 'Falló',
        };
    }
}
