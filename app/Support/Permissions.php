<?php

namespace App\Support;

/**
 * Los diez permisos de la aplicación y los cuatro roles que los agrupan.
 *
 * Se razona por permisos, no por cargos: así soporte técnico puede mantener
 * la aplicación sin ver ni una propuesta privada, y un miembro del comité
 * puede leerlas todas sin poder tocar la configuración.
 */
final class Permissions
{
    public const CREATE = 'proposals.create';

    public const VIEW_PUBLIC = 'proposals.view_public';

    public const VIEW_RESTRICTED = 'proposals.view_restricted';

    public const REVIEW = 'proposals.review';

    public const DECIDE = 'proposals.decide';

    public const IMPLEMENT = 'proposals.implement';

    public const VIEW_REPORTS = 'reports.view';

    public const MANAGE_CATALOGS = 'catalogs.manage';

    public const MANAGE_USERS = 'users.manage';

    public const VIEW_ACTIVITY = 'activity.view';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::CREATE,
            self::VIEW_PUBLIC,
            self::VIEW_RESTRICTED,
            self::REVIEW,
            self::DECIDE,
            self::IMPLEMENT,
            self::VIEW_REPORTS,
            self::MANAGE_CATALOGS,
            self::MANAGE_USERS,
            self::VIEW_ACTIVITY,
        ];
    }

    /** @return array<string, list<string>> */
    public static function roles(): array
    {
        return [
            'Empleado' => [
                self::CREATE,
                self::VIEW_PUBLIC,
            ],
            'Comité' => [
                self::CREATE,
                self::VIEW_PUBLIC,
                self::VIEW_RESTRICTED,
                self::REVIEW,
                self::DECIDE,
                self::IMPLEMENT,
                self::VIEW_REPORTS,
            ],
            'Administración' => [
                self::CREATE,
                self::VIEW_PUBLIC,
                self::VIEW_REPORTS,
                self::MANAGE_CATALOGS,
                self::MANAGE_USERS,
                self::VIEW_ACTIVITY,
            ],
            // Mantiene la aplicación. Deliberadamente SIN view_restricted.
            'Soporte técnico' => [
                self::CREATE,
                self::VIEW_PUBLIC,
                self::MANAGE_CATALOGS,
                self::MANAGE_USERS,
                self::VIEW_ACTIVITY,
            ],
        ];
    }
}
