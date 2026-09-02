<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Events\ProposalStatusChanged;
use App\Exceptions\InvalidTransition;
use App\Models\Area;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * El flujo de una propuesta: qué caminos existen, cuáles no, y qué exige
 * cada paso. Si esto está en verde, ninguna propuesta puede aparecer
 * implantada sin haber pasado por el comité.
 */
class MaquinaDeEstadosTest extends TestCase
{
    use RefreshDatabase;

    private ProposalWorkflow $flujo;

    private User $proponente;

    private User $comite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->flujo = app(ProposalWorkflow::class);
        $this->proponente = $this->crearUsuario('Marta Ruiz', 'Empleado');
        $this->comite = $this->crearUsuario('Luis Peña', 'Comité');
    }

    public function test_el_borrador_no_tiene_numero_ni_historial(): void
    {
        $propuesta = $this->crearBorrador();

        $this->assertTrue($propuesta->isDraft());
        $this->assertNull($propuesta->reference);
        $this->assertSame(0, $propuesta->statusChanges()->count());
    }

    public function test_al_enviar_recibe_numero_y_primera_linea_de_historial(): void
    {
        $propuesta = $this->flujo->submit($this->crearBorrador(), $this->proponente);

        $this->assertFalse($propuesta->isDraft());
        $this->assertSame('MEJ-'.now()->format('y').'-0001', $propuesta->reference);

        $primera = $propuesta->statusChanges()->first();
        $this->assertNull($primera->from_status_id);
        $this->assertSame(Status::NEW, $primera->toStatus->code);
        $this->assertSame($this->proponente->id, $primera->user_id);
    }

    public function test_la_numeracion_es_correlativa(): void
    {
        $this->flujo->submit($this->crearBorrador(), $this->proponente);
        $segunda = $this->flujo->submit($this->crearBorrador(), $this->proponente);

        $this->assertSame('MEJ-'.now()->format('y').'-0002', $segunda->reference);
    }

    public function test_el_recorrido_completo_hasta_implantada(): void
    {
        $propuesta = $this->flujo->submit($this->crearBorrador(), $this->proponente);
        $sesion = CommitteeSession::create(['held_on' => now()->addWeek()]);

        $propuesta = $this->flujo->assignReviewer($propuesta, $this->comite, $this->comite);
        $this->assertSame(Status::IN_REVIEW, $propuesta->status->code);

        $propuesta = $this->flujo->requestInfo($propuesta, $this->comite, '¿Cuántos partes al mes?');
        $this->assertSame(Status::AWAITING_INFO, $propuesta->status->code);

        $propuesta = $this->flujo->infoProvided($propuesta, $this->proponente, 'Unos 90.');
        $this->assertSame(Status::IN_REVIEW, $propuesta->status->code);

        $propuesta = $this->flujo->sendToCommittee($propuesta, $sesion, $this->comite);
        $this->assertSame(Status::IN_COMMITTEE, $propuesta->status->code);
        $this->assertSame($sesion->id, $propuesta->committee_session_id);

        $propuesta = $this->flujo->approve($propuesta, $this->comite, 'Adelante.');
        $this->assertSame(Status::APPROVED, $propuesta->status->code);
        $this->assertNotNull($propuesta->decided_at);

        $propuesta = $this->flujo->planImplementation(
            $propuesta,
            $this->comite,
            now()->addDays(10),
            now()->addDays(40),
        );
        $this->assertNotNull($propuesta->planned_end_on);

        $propuesta = $this->flujo->markImplemented($propuesta, $this->comite, 'Cuatro horas semanales ahorradas.');
        $this->assertSame(Status::IMPLEMENTED, $propuesta->status->code);
        $this->assertNotNull($propuesta->closed_on);

        // Siete pasos, siete líneas de historial.
        $this->assertSame(7, $propuesta->statusChanges()->count());
    }

    public function test_no_se_puede_implantar_sin_pasar_por_el_comite(): void
    {
        $propuesta = $this->flujo->submit($this->crearBorrador(), $this->proponente);

        $this->expectException(InvalidTransition::class);

        $this->flujo->markImplemented($propuesta, $this->comite, 'Hecho por la vía rápida.');
    }

    public function test_no_se_puede_decidir_una_que_no_esta_en_el_comite(): void
    {
        $propuesta = $this->flujo->submit($this->crearBorrador(), $this->proponente);
        $propuesta = $this->flujo->assignReviewer($propuesta, $this->comite, $this->comite);

        $this->expectException(InvalidTransition::class);

        $this->flujo->approve($propuesta, $this->comite);
    }

    public function test_rechazar_exige_motivo(): void
    {
        $propuesta = $this->enComite();

        $this->expectException(InvalidTransition::class);

        $this->flujo->reject($propuesta, $this->comite, '   ');
    }

    public function test_aplazar_guarda_motivo_y_fecha_de_revision(): void
    {
        $propuesta = $this->enComite();

        $propuesta = $this->flujo->postpone(
            $propuesta,
            $this->comite,
            'Buena idea, pero no este año.',
            now()->addMonths(4),
        );

        $this->assertSame(Status::POSTPONED, $propuesta->status->code);
        $this->assertNotNull($propuesta->revisit_on);
        $this->assertSame('Buena idea, pero no este año.', $propuesta->decision_reason);
    }

    public function test_aplazar_no_admite_una_fecha_pasada(): void
    {
        $propuesta = $this->enComite();

        $this->expectException(InvalidTransition::class);

        $this->flujo->postpone($propuesta, $this->comite, 'Para más adelante.', now()->subDay());
    }

    public function test_cerrar_exige_contar_el_resultado(): void
    {
        $propuesta = $this->flujo->approve($this->enComite(), $this->comite);

        $this->expectException(InvalidTransition::class);

        $this->flujo->markImplemented($propuesta, $this->comite, '');
    }

    public function test_una_aplazada_se_puede_reabrir(): void
    {
        $propuesta = $this->flujo->postpone($this->enComite(), $this->comite, 'Ahora no.', now()->addMonth());

        $propuesta = $this->flujo->reopen($propuesta, $this->comite, 'Ya hay presupuesto.');

        $this->assertSame(Status::IN_REVIEW, $propuesta->status->code);
        $this->assertNull($propuesta->revisit_on);
    }

    public function test_una_implantada_es_el_final_del_camino(): void
    {
        $propuesta = $this->flujo->approve($this->enComite(), $this->comite);
        $propuesta = $this->flujo->markImplemented($propuesta, $this->comite, 'Listo.');

        $this->expectException(InvalidTransition::class);

        $this->flujo->reopen($propuesta, $this->comite, 'Quiero retocarla.');
    }

    public function test_cada_cambio_avisa_a_quien_escuche(): void
    {
        Event::fake([ProposalStatusChanged::class]);

        $propuesta = $this->flujo->submit($this->crearBorrador(), $this->proponente);
        $this->flujo->assignReviewer($propuesta, $this->comite, $this->comite);

        Event::assertDispatchedTimes(ProposalStatusChanged::class, 2);
    }

    public function test_el_historial_guarda_quien_y_por_que(): void
    {
        $propuesta = $this->flujo->reject($this->enComite(), $this->comite, 'Ya lo cubre el ERP.');

        $ultima = $propuesta->statusChanges()->get()->last();

        $this->assertSame('Ya lo cubre el ERP.', $ultima->comment);
        $this->assertSame($this->comite->id, $ultima->user_id);
        $this->assertSame(Status::IN_COMMITTEE, $ultima->fromStatus->code);
        $this->assertSame(Status::REJECTED, $ultima->toStatus->code);
    }

    // ------------------------------------------------------------------ Ayudas

    private function crearUsuario(string $nombre, string $rol): User
    {
        $user = User::create([
            'name' => $nombre,
            'email' => str()->slug($nombre).'-'.uniqid().'@miempresa.es',
            'password' => 'secreto-de-prueba',
        ]);

        $user->assignRole($rol);

        return $user->fresh();
    }

    private function crearBorrador(): Proposal
    {
        return $this->flujo->startDraft($this->proponente, [
            'area_id' => Area::firstOrFail()->id,
            'title' => 'Parte de incidencias digital',
            'problem' => 'Se anotan a mano y se pierden.',
            'proposal' => 'Un formulario en la tablet del muelle.',
            'expected_benefit' => 'Cuatro horas semanales.',
            'visibility' => Visibility::Public,
        ]);
    }

    /** Atajo: una propuesta ya puesta encima de la mesa del comité. */
    private function enComite(): Proposal
    {
        $propuesta = $this->flujo->submit($this->crearBorrador(), $this->proponente);
        $propuesta = $this->flujo->assignReviewer($propuesta, $this->comite, $this->comite);

        return $this->flujo->sendToCommittee(
            $propuesta,
            CommitteeSession::create(['held_on' => now()->addWeek()]),
            $this->comite,
        );
    }
}
