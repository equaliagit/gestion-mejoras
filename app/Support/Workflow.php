<?php

namespace App\Support;

use App\Models\ProposalStatus as Status;

/**
 * El mapa del flujo: desde cada estado, a cuáles se puede pasar.
 *
 * Está aquí y en ningún otro sitio. Si mañana el comité quiere un camino
 * nuevo, se añade una entrada a este array y el resto de la aplicación se
 * entera sola: los botones que se pintan, lo que acepta el servicio y lo que
 * comprueban las pruebas salen todos de aquí.
 */
final class Workflow
{
    /** @return array<string, list<string>> */
    public static function map(): array
    {
        return [
            Status::NEW => [
                Status::IN_REVIEW,
            ],
            Status::IN_REVIEW => [
                Status::AWAITING_INFO,
                Status::IN_COMMITTEE,
            ],
            Status::AWAITING_INFO => [
                Status::IN_REVIEW,
            ],
            Status::IN_COMMITTEE => [
                Status::APPROVED,
                Status::REJECTED,
                Status::POSTPONED,
            ],
            Status::APPROVED => [
                Status::IMPLEMENTED,
            ],
            // Reapertura: el comité puede volver a poner en revisión algo
            // que rechazó o aplazó. Aplazada + fecha de revisión es el caso
            // habitual; rechazada, cuando cambian las circunstancias.
            Status::REJECTED => [
                Status::IN_REVIEW,
            ],
            Status::POSTPONED => [
                Status::IN_REVIEW,
            ],
            // Implantada es final: si hay que retocar algo, se propone de nuevo.
            Status::IMPLEMENTED => [],
        ];
    }

    public static function allows(string $from, string $to): bool
    {
        return in_array($to, self::map()[$from] ?? [], strict: true);
    }

    /** @return list<string> */
    public static function nextFrom(string $from): array
    {
        return self::map()[$from] ?? [];
    }

    public static function isFinal(string $code): bool
    {
        return self::nextFrom($code) === [];
    }
}
