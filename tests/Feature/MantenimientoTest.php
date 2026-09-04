<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las tareas de despliegue desde el navegador.
 *
 * Es la pieza con más poder de la aplicación —toca la base de datos sin que
 * nadie inicie sesión—, así que lo que se prueba aquí es sobre todo que esté
 * bien cerrada.
 */
class MantenimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'buzon.maintenance_key' => 'llave-de-mantenimiento-larga',
            'buzon.maintenance_enabled' => true,
        ]);
    }

    public function test_con_el_interruptor_apagado_no_existe(): void
    {
        config(['buzon.maintenance_enabled' => false]);

        $this->get('/mantenimiento/llave-de-mantenimiento-larga/estado')->assertNotFound();
    }

    public function test_sin_la_llave_correcta_no_existe(): void
    {
        $this->get('/mantenimiento/llave-equivocada/estado')->assertNotFound();
    }

    public function test_sin_llave_configurada_no_existe(): void
    {
        config(['buzon.maintenance_key' => '']);

        $this->get('/mantenimiento//estado')->assertNotFound();
    }

    public function test_solo_admite_las_tareas_de_la_lista(): void
    {
        $this->get('/mantenimiento/llave-de-mantenimiento-larga/borrar-todo')->assertNotFound();
        $this->get('/mantenimiento/llave-de-mantenimiento-larga/tinker')->assertNotFound();
    }

    public function test_la_llave_del_cron_no_sirve_para_mantenimiento(): void
    {
        config(['buzon.scheduler_key' => 'llave-del-cron']);

        // Son dos llaves distintas a propósito: la del cron se le entrega al
        // proveedor del alojamiento, y no debe abrir la base de datos.
        $this->get('/mantenimiento/llave-del-cron/migrar')->assertNotFound();
    }

    public function test_consultar_el_estado_de_las_migraciones(): void
    {
        $this->get('/mantenimiento/llave-de-mantenimiento-larga/estado')
            ->assertOk()
            ->assertJson(['ok' => true, 'tarea' => 'estado']);
    }

    public function test_sembrar_deja_los_catalogos_y_los_roles_puestos(): void
    {
        $this->get('/mantenimiento/llave-de-mantenimiento-larga/sembrar')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('areas', ['name' => 'Operaciones']);
        $this->assertDatabaseHas('proposal_statuses', ['code' => 'in_committee']);
        $this->assertDatabaseHas('roles', ['name' => 'Comité']);
    }

    public function test_asciende_al_primer_administrador(): void
    {
        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $jorge = User::create([
            'name' => 'Jorge',
            'email' => 'jorge@equalia.es',
            'password' => 'buzon1234',
        ]);

        $this->get('/mantenimiento/llave-de-mantenimiento-larga/administrador?correo=JORGE@equalia.es')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue($jorge->fresh()->hasRole('Administración'));
    }

    public function test_no_inventa_usuarios_que_no_existen(): void
    {
        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->get('/mantenimiento/llave-de-mantenimiento-larga/administrador?correo=fantasma@equalia.es')
            ->assertOk()
            ->assertJsonFragment(['salida' => 'No hay nadie con el correo fantasma@equalia.es. Entra primero con esa cuenta y vuelve a lanzar esta tarea.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_la_respuesta_recuerda_apagar_el_interruptor(): void
    {
        $this->get('/mantenimiento/llave-de-mantenimiento-larga/limpiar')
            ->assertOk()
            ->assertJsonFragment(['recuerda' => 'Cuando termines, pon MAINTENANCE_ENABLED=false en el .env.']);
    }
}
