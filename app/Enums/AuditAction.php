<?php

namespace App\Enums;

enum AuditAction: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Login = 'login';
    case Import = 'import';
    case Revert = 'revert';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Consulta',
            self::Create => 'Creación',
            self::Update => 'Edición',
            self::Delete => 'Eliminación',
            self::Login => 'Inicio de sesión',
            self::Import => 'Importación',
            self::Revert => 'Reversión',
        };
    }
}
