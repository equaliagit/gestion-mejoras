<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Propuestas de la empresa»: el escaparate común.
 *
 * Es la pantalla donde más fácil sería filtrar información sin querer, así que
 * cada visibilidad tiene aquí su prueba.
 */
class PantallaPropuestasEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private ProposalWorkflow $flujo;

    private User $marta;

    private User $carlos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->flujo = app(ProposalWorkflow::class);
        $this->marta = $this->crearEmpleado('Marta Ruiz', 'marta@miempresa.es');
        $this->carlos = $this->crearEmpleado('Carlos Vidal', 'carlos@miempresa.es');
    }

    public function test_un_companero_ve_las_publicas_de_otro(): void
    {
        $this->enviar($this->marta, Visibility::Public, 'Taquilla automática de EPIs');

        $this->actingAs($this->carlos)
            ->get('/propuestas/empresa')
            ->assertOk()
            ->assertSee('Taquilla automática de EPIs')
            ->assertSee('Marta Ruiz');
    }

    public function test_las_privadas_de_otro_no_salen(): void
    {
        $this->enviar($this->marta, Visibility::Private, 'Asunto reservado');

        $this->actingAs($this->carlos)
            ->get('/propuestas/empresa')
            ->assertOk()
            ->assertDontSee('Asunto reservado');
    }

    public function test_las_anonimas_no_salen_ni_con_su_contenido_ni_con_su_autor(): void
    {
        $this->enviar($this->marta, Visibility::Anonymous, 'Queja anónima');

        $this->actingAs($this->carlos)
            ->get('/propuestas/empresa')
            ->assertOk()
            ->assertDontSee('Queja anónima')
            ->assertDontSee('Marta Ruiz');
    }

    public function test_mis_propias_privadas_tampoco_salen_aqui(): void
    {
        $this->enviar($this->marta, Visibility::Private, 'Mi asunto reservado');

        // Marta sí la ve en «Mis propuestas», pero el escaparate común
        // es solo para lo público: si no, se confundiría lo que ven los demás.
        $this->actingAs($this->marta)
            ->get('/propuestas/empresa')
            ->assertOk()
            ->assertDontSee('Mi asunto reservado');

        $this->actingAs($this->marta)
            ->get('/propuestas')
            ->assertOk()
            ->assertSee('Mi asunto reservado');
    }

    public function test_los_borradores_publicos_no_salen_hasta_que_se_envian(): void
    {
        $borrador = $this->flujo->startDraft($this->marta, $this->datos('Idea a medio escribir', Visibility::Public));

        $this->actingAs($this->carlos)
            ->get('/propuestas/empresa')
            ->assertOk()
            ->assertDontSee('Idea a medio escribir');

        $this->flujo->submit($borrador, $this->marta);

        $this->actingAs($this->carlos)
            ->get('/propuestas/empresa')
            ->assertSee('Idea a medio escribir');
    }

    public function test_al_implantarse_se_cuenta_el_resultado_a_todos(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public, 'Checklist del primer día');
        $propuesta = $this->flujo->assignReviewer($propuesta, $this->marta, $this->marta);

        // Atajo hasta el comité sin montar la sesión: lo que se prueba aquí
        // es la pantalla, no el flujo, que ya tiene sus propias pruebas.
        $propuesta->status_id = Status::idFor(Status::IN_COMMITTEE);
        $propuesta->save();

        $propuesta = $this->flujo->approve($propuesta, $this->marta);
        $this->flujo->markImplemented($propuesta, $this->marta, 'Dos horas menos por incorporación.');

        $this->actingAs($this->carlos)
            ->get('/propuestas/empresa')
            ->assertOk()
            ->assertSee('Dos horas menos por incorporación.');
    }

    // ------------------------------------------------------------------ Ayudas

    private function crearEmpleado(string $nombre, string $correo): User
    {
        $user = User::create([
            'name' => $nombre,
            'email' => $correo,
            'password' => 'buzon1234',
            'area_id' => Area::firstOrFail()->id,
        ]);

        $user->assignRole('Empleado');

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function datos(string $titulo, Visibility $visibilidad): array
    {
        return [
            'area_id' => Area::firstOrFail()->id,
            'title' => $titulo,
            'problem' => 'Un problema de ejemplo suficientemente descrito.',
            'proposal' => 'Una mejora de ejemplo suficientemente descrita.',
            'expected_benefit' => 'Un beneficio de ejemplo.',
            'visibility' => $visibilidad,
        ];
    }

    private function enviar(User $autor, Visibility $visibilidad, string $titulo): Proposal
    {
        return $this->flujo->submit(
            $this->flujo->startDraft($autor, $this->datos($titulo, $visibilidad)),
            $autor,
        );
    }
}
