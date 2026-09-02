<?php

/*
| Mensajes de validación en español.
|
| Solo están las reglas que usamos y las más habituales. Si algún día se usa
| una regla que no esté aquí, Laravel recurre al idioma de reserva (inglés)
| en vez de enseñar un código raro: por eso APP_FALLBACK_LOCALE vale «en».
|
| Los mensajes concretos de cada formulario se escriben en su FormRequest,
| que manda sobre lo que haya aquí. Esto es la red de seguridad.
*/

return [
    'accepted' => 'Tienes que aceptar :attribute.',
    'after' => ':attribute tiene que ser posterior a :date.',
    'after_or_equal' => ':attribute no puede ser anterior a :date.',
    'array' => ':attribute tiene que ser una lista.',
    'before' => ':attribute tiene que ser anterior a :date.',
    'before_or_equal' => ':attribute no puede ser posterior a :date.',
    'between' => [
        'array' => ':attribute tiene que tener entre :min y :max elementos.',
        'file' => ':attribute tiene que ocupar entre :min y :max kilobytes.',
        'numeric' => ':attribute tiene que estar entre :min y :max.',
        'string' => ':attribute tiene que tener entre :min y :max caracteres.',
    ],
    'boolean' => ':attribute solo puede ser sí o no.',
    'confirmed' => ':attribute no coincide con la confirmación.',
    'date' => ':attribute no es una fecha válida.',
    'declined' => 'Tienes que rechazar :attribute.',
    'different' => ':attribute y :other tienen que ser distintos.',
    'email' => ':attribute no parece un correo válido.',
    'exists' => 'La opción elegida en :attribute no es válida.',
    'file' => ':attribute tiene que ser un archivo.',
    'filled' => ':attribute no puede quedarse vacío.',
    'image' => ':attribute tiene que ser una imagen.',
    'in' => 'La opción elegida en :attribute no es válida.',
    'integer' => ':attribute tiene que ser un número entero.',
    'max' => [
        'array' => ':attribute no puede tener más de :max elementos.',
        'file' => ':attribute no puede ocupar más de :max kilobytes.',
        'numeric' => ':attribute no puede ser mayor que :max.',
        'string' => ':attribute no puede tener más de :max caracteres.',
    ],
    'mimes' => ':attribute tiene que ser un archivo de tipo: :values.',
    'min' => [
        'array' => ':attribute tiene que tener al menos :min elementos.',
        'file' => ':attribute tiene que ocupar al menos :min kilobytes.',
        'numeric' => ':attribute tiene que ser al menos :min.',
        'string' => ':attribute tiene que tener al menos :min caracteres.',
    ],
    'not_in' => 'La opción elegida en :attribute no es válida.',
    'numeric' => ':attribute tiene que ser un número.',
    'present' => 'Falta :attribute.',
    'prohibited' => ':attribute no está permitido.',
    'required' => 'Falta rellenar :attribute.',
    'required_if' => 'Falta rellenar :attribute cuando :other vale :value.',
    'required_unless' => 'Falta rellenar :attribute.',
    'required_with' => 'Falta rellenar :attribute.',
    'required_without' => 'Falta rellenar :attribute.',
    'same' => ':attribute y :other tienen que coincidir.',
    'size' => [
        'array' => ':attribute tiene que tener :size elementos.',
        'file' => ':attribute tiene que ocupar :size kilobytes.',
        'numeric' => ':attribute tiene que valer :size.',
        'string' => ':attribute tiene que tener :size caracteres.',
    ],
    'string' => ':attribute tiene que ser texto.',
    'unique' => ':attribute ya está en uso.',
    'uploaded' => 'No se ha podido subir :attribute.',
    'url' => ':attribute no es una dirección válida.',

    /*
    | Y aquí, cómo se llama cada campo cuando aparece dentro de un mensaje.
    | Sin esto saldría «Falta rellenar area_id», que no se lo merece nadie.
    */
    'attributes' => [
        'area_id' => 'el área',
        'title' => 'el título',
        'problem' => 'el problema actual',
        'proposal' => 'la propuesta',
        'expected_benefit' => 'el beneficio esperado',
        'priority' => 'la prioridad',
        'visibility' => 'la visibilidad',
        'impacts' => 'el impacto',
        'email' => 'el correo',
        'password' => 'la contraseña',
        'cuerpo' => 'el mensaje',
        'pregunta' => 'la pregunta',
        'motivo' => 'el motivo',
        'decision' => 'la decisión',
        'revisar_el' => 'la fecha de revisión',
        'responsable' => 'el responsable',
        'inicio' => 'la fecha de inicio',
        'fin' => 'la fecha de fin',
        'resultado' => 'el resultado',
        'fecha' => 'la fecha',
        'cerrada_el' => 'la fecha de cierre',
    ],
];
