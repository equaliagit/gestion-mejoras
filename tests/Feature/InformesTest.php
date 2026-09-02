<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalReport;
use App\Services\ProposalWorkflow;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los informes. Como los números salen del historial y no de nadie tecleando,
 * lo que hay que comprobar es que las cuentas cuadran con lo que pasó.
 */
class InformesTest extends TestCase
{
    use RefreshDatabase;

    private ProposalWorkflow $flujo;

    private User $marta;

    private User $carlos;

    private User $luis;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->flujo = app(ProposalWorkflow::class);
        $this->marta = $this->crear('Marta Ruiz', 'marta@miempresa.es', 'Empleado');
        $this->carlos = $this->crear('Carlos Vidal', 'carlos@miempresa.es', 'Empleado');
        $this->luis = $this->crear('Luis Peña', 'luis@miempresa.es', 'Comité');
        $this->admin = $this->crear('Ana Admin', 'ana@miempresa.es', 'Administración');
    }

    public function test_el_embudo_cuenta_por_donde_paso_cada_propuesta(): void
    {
        // Tres enviadas: una llega a implantada, otra se queda en el comité,
        // la tercera ni se revisa.
        $completa = $this->flujo->approve($this->enComite($this->marta), $this->luis);
        $this->flujo->markImplemented($completa, $this->luis, 'Hecho y funcionando.');

        $this->enComite($this->carlos);
        $this->enviar($this->marta);

        $embudo = (new ProposalReport((int) now()->year))->embudo();

        $this->assertSame(3, $embudo['Registradas']);
        $this->assertSame(2, $embudo['Revisadas']);
        $this->assertSame(2, $embudo['Al comité']);
        $this->assertSame(1, $embudo['Aprobadas']);
        $this->assertSame(1, $embudo['Implantadas']);
    }

    public function test_una_propuesta_cuenta_en_el_embudo_aunque_ya_no_este_ahi(): void
    {
        // Aprobada e implantada: sigue contando como «llegó al comité»,
        // aunque hoy su estado sea otro. Eso es lo que mide un embudo.
        $propuesta = $this->flujo->approve($this->enComite($this->marta), $this->luis);
        $this->flujo->markImplemented($propuesta, $this->luis, 'Cerrada.');

        $embudo = (new ProposalReport((int) now()->year))->embudo();

        $this->assertSame(1, $embudo['Al comité']);
        $this->assertSame(1, $embudo['Implantadas']);
    }

    public function test_el_porcentaje_implantado_y_la_participacion(): void
    {
        $propuesta = $this->flujo->approve($this->enComite($this->marta), $this->luis);
        $this->flujo->markImplemented($propuesta, $this->luis, 'Cerrada.');
        $this->enviar($this->carlos);
        $this->enviar($this->carlos);

        $informe = new ProposalReport((int) now()->year);

        $this->assertSame(33, $informe->porcentajeImplantadas());
        $this->assertSame(2, $informe->participacion()['personas']);
        $this->assertSame(4, $informe->participacion()['plantilla']);
    }

    public function test_las_areas_salen_ordenadas_de_mas_a_menos(): void
    {
        $operaciones = Area::where('name', 'Operaciones')->firstOrFail();
        $rrhh = Area::where('name', 'RRHH')->firstOrFail();

        $this->enviar($this->marta, $operaciones);
        $this->enviar($this->carlos, $operaciones);
        $this->enviar($this->carlos, $rrhh);

        $areas = (new ProposalReport((int) now()->year))->porArea();

        $this->assertSame('Operaciones', $areas[0]['area']);
        $this->assertSame(2, $areas[0]['total']);
        $this->assertSame('RRHH', $areas[1]['area']);
    }

    public function test_los_borradores_no_entran_en_los_informes(): void
    {
        $this->enviar($this->marta);
        $this->flujo->startDraft($this->marta, $this->datos(Area::firstOrFail()));

        $this->assertSame(1, (new ProposalReport((int) now()->year))->total());
    }

    public function test_solo_cuentan_las_del_año_consultado(): void
    {
        $vieja = $this->enviar($this->marta);
        $vieja->forceFill(['submitted_at' => now()->subYears(2)])->save();

        $this->enviar($this->carlos);

        $this->assertSame(1, (new ProposalReport((int) now()->year))->total());
        $this->assertSame(1, (new ProposalReport((int) now()->subYears(2)->year))->total());
    }

    public function test_la_pantalla_es_solo_para_quien_tiene_el_permiso(): void
    {
        $this->actingAs($this->marta)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->luis)->get(route('reports.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk();
    }

    public function test_la_pantalla_pinta_los_numeros(): void
    {
        $this->enviar($this->marta);

        $this->actingAs($this->luis)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Dónde se quedan por el camino')
            ->assertSee('Participación');
    }

    public function test_sin_datos_lo_dice_en_vez_de_pintar_graficos_vacios(): void
    {
        $this->actingAs($this->luis)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Todavía no hay datos');
    }

    public function test_la_descarga_no_desvela_a_quien_firma_una_anonima(): void
    {
        $this->enviar($this->marta, null, Visibility::Anonymous);

        $respuesta = $this->actingAs($this->luis)->get(route('reports.export', ['anio' => now()->year]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('Anónima', $contenido);
        $this->assertStringNotContainsString('Marta Ruiz', $contenido);
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

    /** @return array<string, mixed> */
    private function datos(Area $area, Visibility $visibilidad = Visibility::Public): array
    {
        return [
            'area_id' => $area->id,
            'title' => 'Propuesta de ejemplo',
            'problem' => 'Un problema de ejemplo suficientemente descrito.',
            'proposal' => 'Una mejora de ejemplo suficientemente descrita.',
            'expected_benefit' => 'Un beneficio de ejemplo.',
            'visibility' => $visibilidad,
        ];
    }

    private function enviar(User $autor, ?Area $area = null, Visibility $visibilidad = Visibility::Public): Proposal
    {
        return $this->flujo->submit(
            $this->flujo->startDraft($autor, $this->datos($area ?? Area::firstOrFail(), $visibilidad)),
            $autor,
        );
    }

    private function enComite(User $autor): Proposal
    {
        $propuesta = $this->flujo->assignReviewer($this->enviar($autor), $this->luis, $this->luis);

        return $this->flujo->sendToCommittee(
            $propuesta,
            CommitteeSession::firstOrCreate(['held_on' => now()->addWeek()]),
            $this->luis,
        );
    }
}
