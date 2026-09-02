<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\Impact;
use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las pantallas vistas desde fuera: se entra, se lista y se envía una
 * propuesta como lo haría una persona con el navegador.
 */
class PantallaPropuestasTest extends TestCase
{
    use RefreshDatabase;

    private User $empleada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->empleada = User::create([
            'name' => 'Marta Ruiz',
            'email' => 'marta@miempresa.es',
            'password' => 'buzon1234',
            'area_id' => Area::firstOrFail()->id,
        ]);

        $this->empleada->assignRole('Empleado');
    }

    public function test_sin_entrar_te_manda_a_la_pantalla_de_acceso(): void
    {
        $this->get('/propuestas')->assertRedirect('/entrar');
    }

    public function test_se_entra_con_correo_y_contrasena(): void
    {
        $respuesta = $this->post('/entrar', [
            'email' => 'marta@miempresa.es',
            'password' => 'buzon1234',
        ]);

        $respuesta->assertRedirect(route('proposals.index'));
        $this->assertAuthenticatedAs($this->empleada);
    }

    public function test_una_contrasena_mala_no_entra(): void
    {
        $respuesta = $this->from('/entrar')->post('/entrar', [
            'email' => 'marta@miempresa.es',
            'password' => 'me-la-invento',
        ]);

        $respuesta->assertRedirect('/entrar');
        $respuesta->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_una_cuenta_dada_de_baja_no_entra(): void
    {
        $this->empleada->forceFill(['is_active' => false])->save();

        $this->post('/entrar', [
            'email' => 'marta@miempresa.es',
            'password' => 'buzon1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_listado_vacio_invita_a_escribir_la_primera(): void
    {
        $this->actingAs($this->empleada)
            ->get('/propuestas')
            ->assertOk()
            ->assertSee('Todavía no has propuesto nada');
    }

    public function test_el_formulario_muestra_areas_impactos_y_visibilidades(): void
    {
        $this->actingAs($this->empleada)
            ->get('/propuestas/nueva')
            ->assertOk()
            ->assertSee('Operaciones')
            ->assertSee('Calidad')
            ->assertSee('Anónima');
    }

    public function test_enviar_una_propuesta_la_deja_registrada_y_con_numero(): void
    {
        $respuesta = $this->actingAs($this->empleada)
            ->post('/propuestas', $this->datosValidos(['accion' => 'enviar']));

        $respuesta->assertRedirect(route('proposals.index'));

        $propuesta = Proposal::withoutGlobalScopes()->firstOrFail();

        $this->assertFalse($propuesta->isDraft());
        $this->assertSame('MEJ-'.now()->format('y').'-0001', $propuesta->reference);
        $this->assertSame($this->empleada->id, $propuesta->user_id);
        $this->assertSame(Status::NEW, $propuesta->status->code);
        $this->assertSame(2, $propuesta->impacts()->count());
        $this->assertSame(1, $propuesta->statusChanges()->count());
    }

    public function test_guardar_como_borrador_no_gasta_numero(): void
    {
        $this->actingAs($this->empleada)
            ->post('/propuestas', $this->datosValidos(['accion' => 'borrador']));

        $propuesta = Proposal::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($propuesta->isDraft());
        $this->assertNull($propuesta->reference);
        $this->assertSame(0, $propuesta->statusChanges()->count());
    }

    public function test_un_formulario_incompleto_vuelve_con_los_avisos(): void
    {
        $respuesta = $this->actingAs($this->empleada)
            ->from('/propuestas/nueva')
            ->post('/propuestas', $this->datosValidos(['title' => '', 'impacts' => []]));

        $respuesta->assertRedirect('/propuestas/nueva');
        $respuesta->assertSessionHasErrors(['title', 'impacts']);

        $this->assertSame(0, Proposal::withoutGlobalScopes()->count());
    }

    public function test_el_listado_solo_muestra_lo_mio(): void
    {
        $otro = User::create([
            'name' => 'Carlos Vidal',
            'email' => 'carlos@miempresa.es',
            'password' => 'buzon1234',
        ]);
        $otro->assignRole('Empleado');

        $this->actingAs($otro)->post('/propuestas', $this->datosValidos([
            'title' => 'Propuesta de Carlos',
            'accion' => 'enviar',
        ]));

        $this->actingAs($this->empleada)
            ->get('/propuestas')
            ->assertOk()
            ->assertDontSee('Propuesta de Carlos');
    }

    /** @return array<string, mixed> */
    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'area_id' => Area::firstOrFail()->id,
            'title' => 'Digitalizar el parte de incidencias de almacén',
            'problem' => 'Se anotan a mano en papel y se pierden con frecuencia.',
            'proposal' => 'Un formulario en la tablet del muelle con volcado al ERP.',
            'expected_benefit' => 'Unas cuatro horas semanales de repaso de partes.',
            'priority' => 'high',
            'visibility' => Visibility::Public->value,
            'impacts' => Impact::query()->take(2)->pluck('id')->all(),
            'accion' => 'enviar',
        ], $cambios);
    }
}
