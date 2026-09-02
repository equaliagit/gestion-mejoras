<?php

namespace App\Enums;

enum Visibility: string
{
    /** La ve toda la plantilla, con el nombre de quien la propone. */
    case Public = 'public';

    /** Solo su autor y el comité. */
    case Private = 'private';

    /** El comité ve el contenido, nadie ve quién la escribió. */
    case Anonymous = 'anonymous';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Pública',
            self::Private => 'Privada',
            self::Anonymous => 'Anónima',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Aparece en el listado común de la empresa, con tu nombre.',
            self::Private => 'Solo la veis tú y el comité.',
            self::Anonymous => 'El comité la lee sin saber quién la ha escrito.',
        };
    }

    /** Si al implantarla se comunica el resultado a toda la plantilla. */
    public function isAnnounceable(): bool
    {
        return $this === self::Public;
    }
}
