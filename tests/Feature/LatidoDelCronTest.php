<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\CommitteeSession;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\DeadlineReminder;
use App\Services\ProposalWorkflow;
use App\Services\ScheduledReminders;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Los avisos que dispara el calendario y la dirección que llama el cron.
 */
class LatidoDelCronTest extends TestCase
{
    use RefreshDatabase;

    private ProposalWorkflow $flujo;

    private ScheduledReminders $avisos;

    private User $marta;

    private User $luis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->flujo = app(ProposalWorkflow::class);
        $this->avisos = app(ScheduledReminders::class);
        $this->marta = $this->crear('Marta Ruiz', 'marta@miempresa.es', 'Empleado');
        $this->luis = $this->crear('Luis Peña', 'luis@miempresa.es', 'Comité');

        config(['buzon.scheduler_key' => 'llave-de-prueba-muy-larga']);
    }

    // ------------------------------------------------------- La puerta secreta

    public function test_sin_la_llave_correcta_la_direccion_no_existe(): void
    {
        $this->get('/latido/una-llave-inventada')->assertNotFound();
    }

    public function test_si_no_hay_llave_configurada_la_puerta_no_existe(): void
    {
        config(['buzon.scheduler_key' => '']);

        $this->get('/latido/')->assertNotFound();
        $this->get('/latido/cualquier-cosa')->assertNotFound();
    }

    public function test_con_la_llave_correcta_responde_y_no_pide_iniciar_sesion(): void
    {
        $this->get('/latido/llave-de-prueba-muy-larga')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_los_avisos_diarios_solo_se_lanzan_una_vez_al_dia(): void
    {
        $primera = $this->get('/latido/llave-de-prueba-muy-larga');
        $segunda = $this->get('/latido/llave-de-prueba-muy-larga');

        $this->assertIsArray($primera->json('tareas_diarias'));
        $this->assertSame('ya se hicieron hoy', $segunda->json('tareas_diarias'));
    }

    // ------------------------------------------------------ Los tres recordatorios

    public function test_avisa_cuando_se_pasa_la_fecha_de_fin_prevista(): void
    {
        $propuesta = $this->flujo->approve($this->enComite(), $this->luis);
        $this->flujo->planImplementation($propuesta, $this->luis, now()->subMonth(), now()->subWeek());

        Notification::fake();

        $resultado = $this->avisos->run();

        $this->assertSame(1, $resultado['plazos_vencidos']);
        Notification::assertSentTo($this->luis, DeadlineReminder::class);
    }

    public function test_no_avisa_si_la_propuesta_ya_se_cerro(): void
    {
        $propuesta = $this->flujo->approve($this->enComite(), $this->luis);
        $this->flujo->planImplementation($propuesta, $this->luis, now()->subMonth(), now()->subWeek());
        $this->flujo->markImplemented($propuesta, $this->luis, 'Terminada a tiempo.');

        Notification::fake();

        $this->assertSame(0, $this->avisos->run()['plazos_vencidos']);
    }

    public function test_avisa_cuando_una_aplazada_llega_a_su_fecha(): void
    {
        $propuesta = $this->flujo->postpone($this->enComite(), $this->luis, 'Este año no toca.', now()->addMonths(3));
        $propuesta->forceFill(['revisit_on' => today()])->save();

        Notification::fake();

        $this->assertSame(1, $this->avisos->run()['aplazadas_a_revisar']);
        Notification::assertSentTo($this->luis, DeadlineReminder::class);
    }

    public function test_la_aplazada_no_vuelve_a_avisar_al_dia_siguiente(): void
    {
        $propuesta = $this->flujo->postpone($this->enComite(), $this->luis, 'Ahora no.', now()->addMonths(3));
        $propuesta->forceFill(['revisit_on' => today()])->save();

        $this->avisos->run();

        $this->assertNull($propuesta->refresh()->revisit_on);
        $this->assertSame(0, $this->avisos->run()['aplazadas_a_revisar']);
    }

    public function test_recuerda_los_borradores_de_dos_meses_y_borra_los_de_tres(): void
    {
        $olvidado = $this->borrador();
        $olvidado->forceFill(['updated_at' => now()->subDays(70)])->save();

        $muyViejo = $this->borrador();
        $muyViejo->forceFill(['updated_at' => now()->subDays(100)])->save();

        $reciente = $this->borrador();

        Notification::fake();

        $this->assertSame(1, $this->avisos->run()['borradores_olvidados']);

        // El de 100 días desaparece de la aplicación, pero queda recuperable
        // en la base de datos: el borrado es lógico, no definitivo.
        $this->assertNotNull(Proposal::withoutGlobalScope(VisibilityScope::class)->find($olvidado->id));
        $this->assertNull(Proposal::withoutGlobalScope(VisibilityScope::class)->find($muyViejo->id));
        $this->assertSoftDeleted('proposals', ['id' => $muyViejo->id]);
        $this->assertNotNull(Proposal::withoutGlobalScope(VisibilityScope::class)->find($reciente->id));
    }

    public function test_una_propuesta_enviada_nunca_se_borra_por_antigua(): void
    {
        $propuesta = $this->enviar();
        $propuesta->forceFill(['updated_at' => now()->subYears(3)])->save();

        $this->avisos->run();

        $this->assertNotNull(Proposal::withoutGlobalScope(VisibilityScope::class)->find($propuesta->id));
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

    private function borrador(): Proposal
    {
        return $this->flujo->startDraft($this->marta, [
            'area_id' => Area::firstOrFail()->id,
            'title' => 'Propuesta a medio escribir',
            'problem' => 'Un problema de ejemplo suficientemente descrito.',
            'proposal' => 'Una mejora de ejemplo suficientemente descrita.',
            'expected_benefit' => 'Un beneficio de ejemplo.',
            'visibility' => Visibility::Public,
        ]);
    }

    private function enviar(): Proposal
    {
        return $this->flujo->submit($this->borrador(), $this->marta);
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
