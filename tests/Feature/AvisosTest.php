<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalUpdate;
use App\Services\ProposalWorkflow;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Quién se entera de qué. Es la matriz de avisos del documento, comprobada.
 */
class AvisosTest extends TestCase
{
    use RefreshDatabase;

    private ProposalWorkflow $flujo;

    private User $marta;

    private User $carlos;

    private User $luis;

    private User $nuria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->flujo = app(ProposalWorkflow::class);
        $this->marta = $this->crear('Marta Ruiz', 'marta@miempresa.es', 'Empleado');
        $this->carlos = $this->crear('Carlos Vidal', 'carlos@miempresa.es', 'Empleado');
        $this->luis = $this->crear('Luis Peña', 'luis@miempresa.es', 'Comité');
        $this->nuria = $this->crear('Nuria Sanz', 'nuria@miempresa.es', 'Comité');
    }

    public function test_al_registrar_se_avisa_a_quien_propone_y_al_comite(): void
    {
        Notification::fake();

        $this->enviar($this->marta, Visibility::Public);

        // Acuse de recibo para ella, aunque el movimiento lo haya hecho ella.
        Notification::assertSentTo($this->marta, ProposalUpdate::class);
        Notification::assertSentTo($this->luis, ProposalUpdate::class);
        Notification::assertSentTo($this->nuria, ProposalUpdate::class);
        Notification::assertNotSentTo($this->carlos, ProposalUpdate::class);
    }

    public function test_nadie_recibe_avisos_de_sus_propios_movimientos(): void
    {
        $propuesta = $this->enviar($this->marta, Visibility::Public);

        Notification::fake();

        $this->flujo->assignReviewer($propuesta, $this->luis, $this->luis);

        // A Marta sí, que es la proponente; a Luis no, que lo ha hecho él.
        Notification::assertSentTo($this->marta, ProposalUpdate::class);
        Notification::assertNotSentTo($this->luis, ProposalUpdate::class);
    }

    public function test_la_pregunta_llega_con_su_texto_dentro(): void
    {
        $propuesta = $this->flujo->assignReviewer($this->enviar($this->marta, Visibility::Public), $this->luis, $this->luis);

        Notification::fake();

        $this->flujo->requestInfo($propuesta, $this->luis, '¿Cuántos partes se generan al mes?');

        Notification::assertSentTo($this->marta, ProposalUpdate::class, function (ProposalUpdate $aviso) {
            $correo = $aviso->toMail($this->marta);

            return str_contains(implode(' ', $correo->introLines), '¿Cuántos partes se generan al mes?');
        });
    }

    public function test_cuando_quien_propone_contesta_se_avisa_al_revisor(): void
    {
        $propuesta = $this->flujo->assignReviewer($this->enviar($this->marta, Visibility::Public), $this->luis, $this->luis);
        $propuesta = $this->flujo->requestInfo($propuesta, $this->luis, '¿Cuántos partes al mes?');

        Notification::fake();

        $this->actingAs($this->marta)->post(route('comments.store', $propuesta), [
            'cuerpo' => 'Unos noventa al mes.',
        ]);

        Notification::assertSentTo($this->luis, ProposalUpdate::class, function (ProposalUpdate $aviso) {
            return str_contains($aviso->toMail($this->luis)->subject, 'Te han contestado');
        });
    }

    public function test_al_implantar_una_publica_se_entera_toda_la_plantilla(): void
    {
        $propuesta = $this->flujo->approve($this->enComite($this->marta, Visibility::Public), $this->luis);

        Notification::fake();

        $this->flujo->markImplemented($propuesta, $this->luis, 'Cuatro horas semanales ahorradas.');

        Notification::assertSentTo($this->marta, ProposalUpdate::class);
        Notification::assertSentTo($this->carlos, ProposalUpdate::class);
        Notification::assertSentTo($this->nuria, ProposalUpdate::class);
    }

    public function test_al_implantar_una_privada_no_se_entera_la_plantilla(): void
    {
        $propuesta = $this->flujo->approve($this->enComite($this->marta, Visibility::Private), $this->luis);

        Notification::fake();

        $this->flujo->markImplemented($propuesta, $this->luis, 'Resuelto con discreción.');

        Notification::assertSentTo($this->marta, ProposalUpdate::class);
        Notification::assertNotSentTo($this->carlos, ProposalUpdate::class);
    }

    public function test_el_aviso_se_guarda_tambien_en_el_buzon_de_la_aplicacion(): void
    {
        $this->enviar($this->marta, Visibility::Public);

        $this->assertGreaterThan(0, $this->marta->fresh()->unreadNotifications()->count());

        $this->actingAs($this->marta)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Hemos recibido tu propuesta');
    }

    public function test_se_pueden_marcar_todos_como_leidos(): void
    {
        $this->enviar($this->marta, Visibility::Public);

        $this->actingAs($this->marta)
            ->post(route('notifications.read'))
            ->assertRedirect();

        $this->assertSame(0, $this->marta->fresh()->unreadNotifications()->count());
    }

    public function test_el_menu_lleva_la_cuenta_de_los_avisos_sin_leer(): void
    {
        $this->enviar($this->marta, Visibility::Public);

        $this->actingAs($this->marta)
            ->get(route('proposals.index'))
            ->assertOk()
            ->assertSee('sin-leer');
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

    private function enviar(User $autor, Visibility $visibilidad): Proposal
    {
        return $this->flujo->submit($this->flujo->startDraft($autor, [
            'area_id' => Area::firstOrFail()->id,
            'title' => 'Parte de incidencias digital',
            'problem' => 'Un problema de ejemplo suficientemente descrito.',
            'proposal' => 'Una mejora de ejemplo suficientemente descrita.',
            'expected_benefit' => 'Un beneficio de ejemplo.',
            'visibility' => $visibilidad,
        ]), $autor);
    }

    private function enComite(User $autor, Visibility $visibilidad): Proposal
    {
        $propuesta = $this->flujo->assignReviewer($this->enviar($autor, $visibilidad), $this->luis, $this->luis);

        return $this->flujo->sendToCommittee(
            $propuesta,
            CommitteeSession::create(['held_on' => now()->addWeek()]),
            $this->luis,
        );
    }
}
