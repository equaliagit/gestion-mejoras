<?php

namespace App\Http\Controllers;

use App\Services\ScheduledReminders;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * El «latido» de la aplicación: la dirección que llama el cron del servidor.
 *
 * Existe porque en este alojamiento las tareas programadas las configura el
 * proveedor y solo aceptan una URL, no un comando. Cada llamada hace dos cosas:
 *
 *   1. Vacía la cola: manda los correos que estén pendientes.
 *   2. Una vez al día, lanza los avisos de plazos.
 *
 * Todo ocurre dentro de esta misma petición, sin lanzar subprocesos, porque el
 * servidor tiene `proc_open` deshabilitado.
 */
class SchedulerController extends Controller
{
    /** Margen de seguridad: la petición muere a los 90 segundos en el servidor. */
    private const SEGUNDOS_MAXIMOS = 50;

    public function __invoke(string $llave, ScheduledReminders $avisos): JsonResponse
    {
        $esperada = (string) config('buzon.scheduler_key');

        // Sin llave configurada, la puerta no existe. Y la comparación es en
        // tiempo constante para no filtrar la llave a base de cronometrar.
        if ($esperada === '' || ! hash_equals($esperada, $llave)) {
            throw new NotFoundHttpException;
        }

        $empezado = microtime(true);

        $correos = $this->vaciarLaCola();
        $diarios = $this->tareasDelDia($avisos);

        $resumen = [
            'ok' => true,
            'correos_enviados' => $correos,
            'tareas_diarias' => $diarios,
            'segundos' => round(microtime(true) - $empezado, 2),
        ];

        Log::info('Latido del programador', $resumen);

        return response()->json($resumen);
    }

    /**
     * Envía los avisos que estén esperando en la cola, con un tope de tiempo
     * para no agotar el límite de la petición. Lo que no dé tiempo a enviar
     * se queda para la llamada siguiente.
     */
    private function vaciarLaCola(): int
    {
        $enviados = 0;
        $limite = microtime(true) + self::SEGUNDOS_MAXIMOS;

        while (microtime(true) < $limite) {
            $trabajo = Queue::pop();

            if ($trabajo === null) {
                break;
            }

            try {
                $trabajo->fire();
                $enviados++;
            } catch (\Throwable $e) {
                $trabajo->fail($e);
                Log::error('Falló un aviso en la cola: '.$e->getMessage());
            }
        }

        return $enviados;
    }

    /**
     * Los avisos de plazos, una sola vez al día por mucho que el cron llame
     * cada cinco minutos. El candado dura 23 horas.
     *
     * @return array<string, int>|string
     */
    private function tareasDelDia(ScheduledReminders $avisos): array|string
    {
        $hoy = today()->toDateString();

        if (! Cache::add("avisos-diarios:{$hoy}", true, now()->addHours(23))) {
            return 'ya se hicieron hoy';
        }

        return $avisos->run();
    }
}
