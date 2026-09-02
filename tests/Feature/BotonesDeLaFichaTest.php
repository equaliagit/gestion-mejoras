<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qué botones ofrece la ficha en cada estado.
 *
 * Los botones se calculan desde el mapa de transiciones, así que estas
 * pruebas son la red que avisa si un día se ofrece una acción que no toca.
 */
class BotonesDeLaFichaTest extends TestCase
{
    use RefreshDatabase;

    private ProposalWorkflow $flujo;

    private User $marta;

    private User $luis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->flujo = app(ProposalWorkflow::class);
        $this->marta = $this->crear('Marta Ruiz', 'marta@miempresa.es', 'Empleado');
        $this->luis = $this->crear('Luis Peña', 'luis@miempresa.es', 'Comité');
    }

    public function test_recien_enviada_solo_se_puede_asignar(): void
    {
        $propuesta = $this->enviar();

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertSee('Asignarme esta propuesta')
            ->assertDontSee('Llevar al comité')
            ->assertDontSee('Reabrir');
    }

    public function test_en_revision_se_puede_preguntar_o_llevar_al_comite(): void
    {
        $propuesta = $this->flujo->assignReviewer($this->enviar(), $this->luis, $this->luis);

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertSee('Pedir información')
            ->assertSee('Llevar al comité')
            ->assertDontSee('Reabrir')
            ->assertDontSee('Guardar decisión');
    }

    public function test_esperando_informacion_el_comite_no_tiene_nada_que_hacer(): void
    {
        $propuesta = $this->flujo->assignReviewer($this->enviar(), $this->luis, $this->luis);
        $propuesta = $this->flujo->requestInfo($propuesta, $this->luis, '¿Cuántos partes al mes?');

        // La pelota está en el tejado de quien propuso: no hay nada que
        // reabrir ni que decidir hasta que conteste.
        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertDontSee('Reabrir')
            ->assertDontSee('Guardar decisión');
    }

    public function test_en_el_comite_aparece_el_formulario_de_decision(): void
    {
        $propuesta = $this->enComite();

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertSee('Guardar decisión')
            ->assertDontSee('Pedir información');
    }

    public function test_aprobada_ofrece_planificar_y_cerrar(): void
    {
        $propuesta = $this->flujo->approve($this->enComite(), $this->luis);

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertSee('Planificar implantación')
            ->assertSee('Marcar como implantada');
    }

    public function test_aplazada_y_rechazada_si_se_pueden_reabrir(): void
    {
        $aplazada = $this->flujo->postpone($this->enComite(), $this->luis, 'Este año no toca.', now()->addMonths(3));

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $aplazada))
            ->assertSee('Reabrir');

        $rechazada = $this->flujo->reject($this->enComite(), $this->luis, 'Ya lo cubre el ERP.');

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $rechazada))
            ->assertSee('Reabrir');
    }

    public function test_implantada_no_ofrece_ningun_paso_mas(): void
    {
        $propuesta = $this->flujo->approve($this->enComite(), $this->luis);
        $propuesta = $this->flujo->markImplemented($propuesta, $this->luis, 'Cuatro horas semanales ahorradas.');

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertSee('Esta propuesta está cerrada')
            ->assertDontSee('Reabrir');
    }

    public function test_quien_propone_no_ve_botones_de_gestion(): void
    {
        $propuesta = $this->enComite();

        $this->actingAs($this->marta)
            ->get(route('proposals.show', $propuesta))
            ->assertOk()
            ->assertDontSee('Guardar decisión')
            ->assertDontSee('Asignarme esta propuesta');
    }

    // ------------------------------------------------------------------ Ayudas

    private function crear(string $nombre, string $correo, string $rol): User
    {
        $user = User::create([
            'name' => $nombre,
            'email' => $correo,
            'password' => 'buzon1234',
            'area_id' => Area::firstOrFail()->id,
        ]);

        $user->assignRole($rol);

        return $user->fresh();
    }

    private function enviar(): Proposal
    {
        return $this->flujo->submit($this->flujo->startDraft($this->marta, [
            'area_id' => Area::firstOrFail()->id,
            'title' => 'Parte de incidencias digital',
            'problem' => 'Un problema de ejemplo suficientemente descrito.',
            'proposal' => 'Una mejora de ejemplo suficientemente descrita.',
            'expected_benefit' => 'Un beneficio de ejemplo.',
            'visibility' => Visibility::Public,
        ]), $this->marta);
    }

    private function enComite(): Proposal
    {
        $propuesta = $this->flujo->assignReviewer($this->enviar(), $this->luis, $this->luis);

        return $this->flujo->sendToCommittee(
            $propuesta,
            CommitteeSession::create(['held_on' => now()->addWeek()]),
            $this->luis,
        );
    }
}
