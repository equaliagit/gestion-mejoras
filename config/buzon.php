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

    /*
    | Las tareas de despliegue desde el navegador, para un servidor sin consola.
    |
    | Llave propia, distinta de la del cron: la del cron se le entrega al
    | proveedor del alojamiento, y no queremos que esa misma llave sirva para
    | tocar la base de datos.
    |
    | El interruptor viene apagado. Se enciende el rato que dura una
    | actualización y se apaga al terminar; con él apagado, la dirección
    | responde «no existe» aunque se acierte la llave.
    */
    'maintenance_key' => env('MAINTENANCE_KEY', ''),
    'maintenance_enabled' => (bool) env('MAINTENANCE_ENABLED', false),

];
