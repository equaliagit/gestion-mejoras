<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Genera la llave de la dirección del cron y la deja escrita en el .env.
 *
 *   php artisan buzon:llave
 *
 * Al terminar enseña la dirección completa, que es justo lo que hay que
 * pasarle al proveedor del alojamiento para que configure la tarea.
 */
class GenerateSchedulerKey extends Command
{
    protected $signature = 'buzon:llave {--forzar : Cambiar la llave aunque ya exista}';

    protected $description = 'Genera la llave secreta de la dirección que llama el cron';

    public function handle(): int
    {
        $actual = (string) config('buzon.scheduler_key');

        if ($actual !== '' && ! $this->option('forzar')) {
            $this->warn('Ya hay una llave configurada. Esta es la dirección:');
            $this->line('  '.route('scheduler.run', $actual));
            $this->newLine();
            $this->line('Para cambiarla: php artisan buzon:llave --forzar');
            $this->warn('Ojo: al cambiarla hay que avisar al proveedor, o el cron dejará de funcionar.');

            return self::SUCCESS;
        }

        $llave = Str::random(48);
        $ruta = base_path('.env');

        if (! is_writable($ruta)) {
            $this->error('No puedo escribir en el .env. Añade esta línea a mano:');
            $this->line("  SCHEDULER_KEY={$llave}");

            return self::FAILURE;
        }

        $contenido = file_get_contents($ruta);

        $contenido = str_contains($contenido, 'SCHEDULER_KEY=')
            ? preg_replace('/^SCHEDULER_KEY=.*$/m', "SCHEDULER_KEY={$llave}", $contenido)
            : rtrim($contenido).PHP_EOL.PHP_EOL."SCHEDULER_KEY={$llave}".PHP_EOL;

        file_put_contents($ruta, $contenido);

        $this->components->info('Llave generada y guardada en el .env');
        $this->newLine();
        $this->line('Pásale esta dirección al proveedor del alojamiento,');
        $this->line('pidiendo que la llame cada 5 minutos:');
        $this->newLine();
        $this->line('  '.config('app.url').'/latido/'.$llave);
        $this->newLine();
        $this->warn('Es un secreto: no la publiques ni la subas al repositorio.');

        return self::SUCCESS;
    }
}
