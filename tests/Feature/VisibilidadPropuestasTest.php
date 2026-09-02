<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La regla que no puede fallar nunca: quién ve qué.
 * Si alguna de estas pruebas se pone en rojo, hay una fuga de información.
 */
class VisibilidadPropuestasTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private int $statusId;

    private User $empleada;

    private User $otroEmpleado;

    private User $miembroComite;

    private User $soporte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->area = Area::firstOrFail();
        $this->statusId = ProposalStatus::idFor(ProposalStatus::NEW);

        $this->empleada = $this->crearUsuario('Marta Ruiz', 'Empleado');
        $this->otroEmpleado = $this->crearUsuario('Carlos Vidal', 'Empleado');
        $this->miembroComite = $this->crearUsuario('Luis Peña', 'Comité');
        $this->soporte = $this->crearUsuario('Jorge Soporte', 'Soporte técnico');
    }

    public function test_un_empleado_ve_las_publicas_de_otros(): void
    {
        $this->crearPropuesta($this->otroEmpleado, Visibility::Public);

        $this->actingAs($this->empleada);

        $this->assertSame(1, Proposal::count());
    }

    public function test_un_empleado_no_ve_las_privadas_de_otros(): void
    {
        $this->crearPropuesta($this->otroEmpleado, Visibility::Private);

        $this->actingAs($this->empleada);

        $this->assertSame(0, Proposal::count());
    }

    public function test_un_empleado_no_ve_las_anonimas_de_otros(): void
    {
        $this->crearPropuesta($this->otroEmpleado, Visibility::Anonymous);

        $this->actingAs($this->empleada);

        $this->assertSame(0, Proposal::count());
    }

    public function test_cada_uno_ve_siempre_las_suyas(): void
    {
        $this->crearPropuesta($this->empleada, Visibility::Private);
        $this->crearPropuesta($this->empleada, Visibility::Anonymous);

        $this->actingAs($this->empleada);

        $this->assertSame(2, Proposal::count());
    }

    public function test_el_comite_ve_todas_las_enviadas(): void
    {
        $this->crearPropuesta($this->empleada, Visibility::Public);
        $this->crearPropuesta($this->empleada, Visibility::Private);
        $this->crearPropuesta($this->otroEmpleado, Visibility::Anonymous);

        $this->actingAs($this->miembroComite);

        $this->assertSame(3, Proposal::count());
    }

    public function test_soporte_tecnico_no_ve_privadas_ni_anonimas(): void
    {
        $this->crearPropuesta($this->empleada, Visibility::Public);
        $this->crearPropuesta($this->empleada, Visibility::Private);
        $this->crearPropuesta($this->empleada, Visibility::Anonymous);

        $this->actingAs($this->soporte);

        $this->assertSame(1, Proposal::count());
    }

    public function test_los_borradores_ajenos_no_los_ve_ni_el_comite(): void
    {
        $this->crearPropuesta($this->empleada, Visibility::Public, enviada: false);

        $this->actingAs($this->miembroComite);

        $this->assertSame(0, Proposal::count());
    }

    public function test_no_se_enseña_quien_firma_una_anonima(): void
    {
        $anonima = $this->crearPropuesta($this->empleada, Visibility::Anonymous);
        $publica = $this->crearPropuesta($this->empleada, Visibility::Public);

        $this->assertSame('Anónima', $anonima->authorName());
        $this->assertSame('Marta Ruiz', $publica->authorName());
    }

    public function test_nadie_puede_destapar_a_quien_firma_una_anonima(): void
    {
        $anonima = $this->crearPropuesta($this->empleada, Visibility::Anonymous);

        $this->assertFalse($this->miembroComite->can('revealAuthor', $anonima));
        $this->assertFalse($this->soporte->can('revealAuthor', $anonima));
        $this->assertFalse($this->empleada->can('revealAuthor', $anonima));
    }

    public function test_la_politica_tambien_esconde_la_propuesta_privada_ajena(): void
    {
        $privada = $this->crearPropuesta($this->otroEmpleado, Visibility::Private);

        $this->assertFalse($this->empleada->can('view', $privada));
        $this->assertTrue($this->miembroComite->can('view', $privada));
        $this->assertTrue($this->otroEmpleado->can('view', $privada));
    }

    // ------------------------------------------------------------------ Ayudas

    private function crearUsuario(string $nombre, string $rol): User
    {
        $user = User::create([
            'name' => $nombre,
            'email' => str()->slug($nombre).'@miempresa.es',
            'password' => 'secreto-de-prueba',
        ]);

        $user->assignRole($rol);

        return $user->fresh();
    }

    private function crearPropuesta(User $autor, Visibility $visibilidad, bool $enviada = true): Proposal
    {
        // forceFill a propósito: status_id no es rellenable en masa, lo mueve
        // la máquina de estados. Aquí colocamos el escenario a mano.
        $proposal = (new Proposal)->forceFill([
            'user_id' => $autor->id,
            'area_id' => $this->area->id,
            'status_id' => $this->statusId,
            'title' => 'Propuesta '.$visibilidad->value,
            'problem' => 'Problema de ejemplo.',
            'proposal' => 'Mejora de ejemplo.',
            'expected_benefit' => 'Beneficio de ejemplo.',
            'visibility' => $visibilidad,
        ]);

        $proposal->save();

        if ($enviada) {
            $proposal->forceFill([
                'submitted_at' => now(),
                'reference' => 'MEJ-26-'.str_pad((string) $proposal->id, 4, '0', STR_PAD_LEFT),
            ])->save();
        }

        return $proposal;
    }
}
