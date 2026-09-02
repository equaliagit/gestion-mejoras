<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando alguien intenta un cambio de estado que el flujo no permite,
 * o cuando falta algo que ese cambio exige (el motivo de un rechazo, la fecha
 * de revisión de un aplazamiento...).
 *
 * Es una excepción de programación, no de usuario: la interfaz solo enseña los
 * botones de las transiciones válidas, así que si esto salta es que algo se ha
 * saltado la interfaz.
 */
class InvalidTransition extends RuntimeException
{
    public static function between(string $from, string $to): self
    {
        return new self("El flujo no permite pasar de «{$from}» a «{$to}».");
    }

    public static function missingReason(string $to): self
    {
        return new self("Pasar a «{$to}» exige escribir un motivo.");
    }

    public static function missing(string $what, string $to): self
    {
        return new self("Pasar a «{$to}» exige {$what}.");
    }
}
