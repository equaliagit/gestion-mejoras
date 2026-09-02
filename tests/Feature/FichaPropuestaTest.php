<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La ficha y las acciones del comité, recorridas desde el navegador.
 */
class FichaPropuestaTest extends TestCase
{
    use RefreshDatabase;

    private ProposalWorkflow $flujo;

    private User $marta;

    private User $carlos;

    private User $luis;

    private User $soporte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->flujo = app(ProposalWorkflow::class);
        $this->marta = $this->crear('Marta Ruiz', 'marta@miempresa.es', 'Empleado');
        $this->carlos = $this->crear('Carlos Vidal', 'carlos@miempresa.es', 'Empleado');
        $this->luis = $this->crear('Luis Peña', 'luis@miempresa.es', 'Comité');
        $this->soporte = $this->crear('Jorge Soporte', 'jorge@miempresa.es', 'Soporte técnico');
    }

    public function test_quien_propone_ve_su_ficha(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Private);

        $this->actingAs($this->marta)
            ->get(route('proposals.show', $propuesta))
            ->assertOk()
            ->assertSee('Problema actual')
            ->assertSee('Historial');
    }

    public function test_una_privada_ajena_da_404_no_403(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Private);

        // 404 y no 403 a propósito: quien no puede verla ni siquiera debe
        // enterarse de que existe esa referencia.
        $this->actingAs($this->carlos)
            ->get(route('proposals.show', $propuesta))
            ->assertNotFound();
    }

    public function test_soporte_tecnico_tampoco_entra_en_una_privada(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Private);

        $this->actingAs($this->soporte)
            ->get(route('proposals.show', $propuesta))
            ->assertNotFound();
    }

    public function test_la_ficha_de_una_anonima_no_enseña_el_nombre_ni_al_comite(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Anonymous);

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertOk()
            ->assertSee('Anónima')
            ->assertDontSee('Marta Ruiz');
    }

    public function test_las_notas_internas_no_las_ve_quien_propuso(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);

        $this->actingAs($this->luis)->post(route('comments.store', $propuesta), [
            'cuerpo' => 'Encaja con el objetivo de digitalización del almacén.',
            'interno' => '1',
        ])->assertRedirect();

        $this->actingAs($this->marta)
            ->get(route('proposals.show', $propuesta))
            ->assertOk()
            ->assertDontSee('Encaja con el objetivo');

        $this->actingAs($this->luis)
            ->get(route('proposals.show', $propuesta))
            ->assertSee('Encaja con el objetivo');
    }

    public function test_un_empleado_no_puede_escribir_notas_internas(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);

        $this->actingAs($this->marta)->post(route('comments.store', $propuesta), [
            'cuerpo' => 'Intento colar una nota interna.',
            'interno' => '1',
        ])->assertForbidden();
    }

    public function test_el_comite_se_asigna_la_propuesta_desde_la_ficha(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);

        $this->actingAs($this->luis)
            ->post(route('proposals.assign', $propuesta))
            ->assertRedirect(route('proposals.show', $propuesta));

        $propuesta->refresh();

        $this->assertSame($this->luis->id, $propuesta->reviewer_id);
        $this->assertSame(Status::IN_REVIEW, $propuesta->status->code);
    }

    public function test_un_empleado_no_puede_asignarse_nada(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);

        // 403 y no 404: la propuesta es pública, Carlos puede verla.
        // Lo que no puede es actuar sobre ella.
        $this->actingAs($this->carlos)
            ->post(route('proposals.assign', $propuesta))
            ->assertForbidden();
    }

    public function test_quien_propone_lee_la_pregunta_que_le_hacen(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);
        $propuesta = $this->flujo->assignReviewer($propuesta, $this->luis, $this->luis);
        $this->flujo->requestInfo($propuesta, $this->luis, '¿Cuántos partes se generan al mes?');

        // La pregunta tiene que estar en el hilo visible, no solo en el
        // historial: si no, la propuesta se queda parada y nadie sabe por qué.
        $this->actingAs($this->marta)
            ->get(route('proposals.show', $propuesta))
            ->assertOk()
            ->assertSee('¿Cuántos partes se generan al mes?', escape: false);
    }

    public function test_el_listado_avisa_a_quien_propone_de_que_le_toca_mover(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);
        $propuesta = $this->flujo->assignReviewer($propuesta, $this->luis, $this->luis);
        $this->flujo->requestInfo($propuesta, $this->luis, '¿Cuántos partes se generan al mes?');

        $this->actingAs($this->marta)
            ->get(route('proposals.index'))
            ->assertOk()
            ->assertSee('contesta en la ficha');
    }

    public function test_contestar_una_peticion_de_info_devuelve_la_propuesta_a_revision(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);
        $propuesta = $this->flujo->assignReviewer($propuesta, $this->luis, $this->luis);
        $this->flujo->requestInfo($propuesta, $this->luis, '¿Cuántos partes se generan al mes?');

        $this->actingAs($this->marta)->post(route('comments.store', $propuesta), [
            'cuerpo' => 'Unos noventa al mes.',
        ])->assertRedirect();

        $this->assertSame(Status::IN_REVIEW, $propuesta->refresh()->status->code);
    }

    public function test_rechazar_sin_motivo_no_pasa_la_validacion(): void
    {
        $propuesta = $this->enComite();

        $this->actingAs($this->luis)
            ->from(route('proposals.show', $propuesta))
            ->post(route('proposals.decide', $propuesta), ['decision' => 'rechazar', 'motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(Status::IN_COMMITTEE, $propuesta->refresh()->status->code);
    }

    public function test_el_recorrido_del_comite_desde_la_ficha(): void
    {
        $propuesta = $this->enComite();

        $this->actingAs($this->luis)->post(route('proposals.decide', $propuesta), [
            'decision' => 'aprobar',
            'motivo' => 'Adelante con ello.',
        ])->assertRedirect();

        $this->assertSame(Status::APPROVED, $propuesta->refresh()->status->code);

        $this->actingAs($this->luis)->post(route('proposals.plan', $propuesta), [
            'responsable' => $this->luis->id,
            'inicio' => now()->addDays(3)->format('Y-m-d'),
            'fin' => now()->addDays(30)->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertNotNull($propuesta->refresh()->planned_end_on);

        $this->actingAs($this->luis)->post(route('proposals.implemented', $propuesta), [
            'resultado' => 'Cuatro horas semanales ahorradas en el repaso de partes.',
        ])->assertRedirect();

        $this->assertSame(Status::IMPLEMENTED, $propuesta->refresh()->status->code);
    }

    public function test_no_se_puede_decidir_dos_veces_la_misma_propuesta(): void
    {
        $propuesta = $this->enComite();

        $this->actingAs($this->luis)->post(route('proposals.decide', $propuesta), [
            'decision' => 'aprobar',
        ])->assertRedirect();

        // El segundo intento ni siquiera llega al flujo: la política lo corta,
        // porque la propuesta ya no está en el comité. La red de seguridad de
        // la máquina de estados queda cubierta en MaquinaDeEstadosTest.
        $this->actingAs($this->luis)
            ->post(route('proposals.decide', $propuesta), ['decision' => 'rechazar', 'motivo' => 'Me lo he repensado.'])
            ->assertForbidden();

        $this->assertSame(Status::APPROVED, $propuesta->refresh()->status->code);
    }

    public function test_la_bandeja_del_comite_es_solo_del_comite(): void
    {
        $this->actingAs($this->carlos)->get(route('committee.inbox'))->assertForbidden();
        $this->actingAs($this->soporte)->get(route('committee.inbox'))->assertForbidden();
        $this->actingAs($this->luis)->get(route('committee.inbox'))->assertOk();
    }

    public function test_la_bandeja_lista_privadas_y_anonimas_sin_desvelar_autores(): void
    {
        $this->enviar($this->marta, Visibility::Private, 'Asunto reservado');
        $this->enviar($this->marta, Visibility::Anonymous, 'Queja sin firma');

        $this->actingAs($this->luis)
            ->get(route('committee.inbox'))
            ->assertOk()
            ->assertSee('Asunto reservado')
            ->assertSee('Queja sin firma')
            ->assertSee('Anónima');
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

    private function enviar(User $autor, Visibility $visibilidad, string $titulo = 'Parte de incidencias digital'): Proposal
    {
        $borrador = $this->flujo->startDraft($autor, [
            'area_id' => Area::firstOrFail()->id,
            'title' => $titulo,
            'problem' => 'Un problema de ejemplo suficientemente descrito.',
            'proposal' => 'Una mejora de ejemplo suficientemente descrita.',
            'expected_benefit' => 'Un beneficio de ejemplo.',
            'visibility' => $visibilidad,
        ]);

        return $this->flujo->submit($borrador, $autor);
    }

    private function enComite(): Proposal
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);
        $propuesta = $this->flujo->assignReviewer($propuesta, $this->luis, $this->luis);

        return $this->flujo->sendToCommittee(
            $propuesta,
            CommitteeSession::create(['held_on' => now()->addWeek()]),
            $this->luis,
        );
    }
}
