<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La raíz del sitio no tiene contenido propio: lleva al listado de propuestas,
 * y ese exige haber entrado. Quien llegue de nuevas acaba en la pantalla de
 * acceso, que es lo que comprobamos aquí.
 */
class ExampleTest extends TestCase
{
    public function test_la_raiz_lleva_al_listado_de_propuestas(): void
    {
        $this->get('/')->assertRedirect('/propuestas');
    }

    public function test_y_el_listado_exige_haber_entrado(): void
    {
        $this->get('/propuestas')->assertRedirect('/entrar');
    }
}
