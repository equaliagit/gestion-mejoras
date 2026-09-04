<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Prepara el paquete que se sube al servidor.
 *
 *   php artisan buzon:paquete
 *
 * Deja un ZIP en el escritorio con el proyecto entero, las librerías dentro y
 * sin nada de desarrollo. Así el servidor no necesita ejecutar Composer —que
 * allí tiene las funciones capadas— ni tener consola: basta con descomprimir.
 *
 * También genera las dos llaves secretas y escribe una plantilla de .env con
 * todo lo que hay que rellenar, para no dejarse ningún ajuste.
 */
class PrepareDeployment extends Command
{
    protected $signature = 'buzon:paquete {--salida= : Dónde dejar el ZIP (por defecto, el escritorio)}';

    protected $description = 'Prepara el ZIP con todo lo que hay que subir al servidor';

    /** Lo que NO viaja al servidor. */
    private const EXCLUIDO = [
        '.git', '.github', 'node_modules', 'tests', 'storage/logs', 'storage/framework/cache/data',
        'storage/framework/sessions', 'storage/framework/views', '.env', '.phpunit.result.cache',
        'docs', '.vscode', '.idea',
    ];

    public function handle(): int
    {
        $destino = rtrim($this->option('salida') ?: (getenv('USERPROFILE') ?: getenv('HOME')).'/Desktop', '/\\');
        $sello = now()->format('Y-m-d-Hi');
        $zip = "{$destino}/buzon-mejora-{$sello}.zip";
        $plantilla = "{$destino}/env-para-el-servidor-{$sello}.txt";

        $this->components->info('Preparando el paquete de despliegue');

        if (! $this->instalarDependenciasDeProduccion()) {
            return self::FAILURE;
        }

        $this->comprimir($zip);
        $this->escribirPlantillaDeEnv($plantilla);

        $this->newLine();
        $this->components->info('Paquete listo');
        $this->line('  ZIP:       '.$zip.'  ('.$this->tamaño($zip).')');
        $this->line('  Ajustes:   '.$plantilla);
        $this->newLine();
        $this->warn('Ese archivo de ajustes lleva las llaves secretas: no lo subas al repositorio.');
        $this->newLine();
        $this->line('Y en tu equipo, para volver a trabajar con las herramientas de desarrollo:');
        $this->line('  composer install');

        return self::SUCCESS;
    }

    private function instalarDependenciasDeProduccion(): bool
    {
        $this->line('  Instalando librerías sin las de desarrollo…');

        $proceso = Process::fromShellCommandline(
            'composer install --no-dev --optimize-autoloader --no-interaction',
            base_path(),
            timeout: 600,
        );

        $proceso->run();

        if (! $proceso->isSuccessful()) {
            $this->error('Composer ha fallado:');
            $this->line($proceso->getErrorOutput() ?: $proceso->getOutput());

            return false;
        }

        return true;
    }

    private function comprimir(string $destino): void
    {
        $this->line('  Comprimiendo…');

        @unlink($destino);

        $zip = new ZipArchive;
        $zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $raiz = str_replace('\\', '/', base_path());
        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($archivos as $archivo) {
            $ruta = str_replace('\\', '/', $archivo->getPathname());
            $relativa = ltrim(Str::after($ruta, $raiz), '/');

            if ($this->excluido($relativa)) {
                continue;
            }

            $archivo->isDir()
                ? $zip->addEmptyDir($relativa)
                : $zip->addFile($ruta, $relativa);
        }

        $zip->close();
    }

    private function excluido(string $relativa): bool
    {
        foreach (self::EXCLUIDO as $patron) {
            if ($relativa === $patron || str_starts_with($relativa, $patron.'/')) {
                return true;
            }
        }

        return false;
    }

    private function escribirPlantillaDeEnv(string $destino): void
    {
        $plantilla = <<<'ENV'
        # ---------------------------------------------------------------
        #  Ajustes del servidor. Este contenido va en el archivo .env
        #  de la carpeta del proyecto, y NO se sube nunca al repositorio.
        #
        #  Lo que pone RELLENAR hay que completarlo antes de nada.
        # ---------------------------------------------------------------

        APP_NAME="Buzón de Mejora"
        APP_ENV=production
        APP_KEY={{APP_KEY}}
        APP_DEBUG=false
        APP_URL=https://RELLENAR.equalia.es
        APP_TIMEZONE=Europe/Madrid
        APP_LOCALE=es
        APP_FALLBACK_LOCALE=en
        APP_FAKER_LOCALE=es_ES

        LOG_CHANNEL=stack
        LOG_LEVEL=error

        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=RELLENAR
        DB_USERNAME=RELLENAR
        DB_PASSWORD=RELLENAR

        SESSION_DRIVER=database
        SESSION_LIFETIME=480
        QUEUE_CONNECTION=database
        CACHE_STORE=database

        # Correo saliente. Pídele los datos al proveedor o usa el SMTP de Microsoft 365.
        MAIL_MAILER=smtp
        MAIL_HOST=RELLENAR
        MAIL_PORT=587
        MAIL_USERNAME=RELLENAR
        MAIL_PASSWORD=RELLENAR
        MAIL_SCHEME=tls
        MAIL_FROM_ADDRESS="buzon-mejora@equalia.es"
        MAIL_FROM_NAME="${APP_NAME}"

        # Entrar con Microsoft. Los tres primeros los da el administrador de 365.
        AZURE_CLIENT_ID=RELLENAR
        AZURE_CLIENT_SECRET=RELLENAR
        AZURE_TENANT_ID=RELLENAR
        AZURE_REDIRECT_URI=https://RELLENAR.equalia.es/entrar/microsoft/callback

        # La dirección que llamará el cron del proveedor, cada 5 minutos:
        #   https://RELLENAR.equalia.es/latido/{{SCHEDULER_KEY}}
        SCHEDULER_KEY={{SCHEDULER_KEY}}

        # Tareas de despliegue desde el navegador. Se enciende solo mientras
        # dura una actualización, y se vuelve a apagar al terminar:
        #   https://RELLENAR.equalia.es/mantenimiento/{{MAINTENANCE_KEY}}/migrar
        MAINTENANCE_KEY={{MAINTENANCE_KEY}}
        MAINTENANCE_ENABLED=false
        ENV;

        $contenido = str_replace(
            ['{{APP_KEY}}', '{{SCHEDULER_KEY}}', '{{MAINTENANCE_KEY}}'],
            ['base64:'.base64_encode(random_bytes(32)), Str::random(48), Str::random(48)],
            $plantilla,
        );

        file_put_contents($destino, $contenido);
    }

    private function tamaño(string $ruta): string
    {
        return number_format(filesize($ruta) / 1048576, 1).' MB';
    }
}
