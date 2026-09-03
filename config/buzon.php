<?php

/*
| Ajustes propios de la aplicación.
|
| Todo lo que cambia entre tu equipo y el servidor vive en el .env; aquí solo
| se le pone nombre y un valor por defecto.
*/

return [

    /*
    | La llave de la dirección que llama el cron del servidor.
    |
    | Es un secreto: va en el .env, que no se sube al repositorio. Si está
    | vacía, la dirección responde «no existe» y no ejecuta nada — así, un
    | despliegue mal configurado falla del lado seguro en vez de dejar una
    | puerta abierta.
    |
    | Para generar una nueva: php artisan buzon:llave
    */
    'scheduler_key' => env('SCHEDULER_KEY', ''),

];
