<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * La pantalla de personas: quién entra y con qué rol.
 */
class AdministrarPersonasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $marta;

    private User $luis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        $this->admin = $this->crear('Ana Admin', 'ana@equalia.es', 'Administración');
        $this->marta = $this->crear('Marta Ruiz', 'marta@equalia.es', 'Empleado');
        $this->luis = $this->crear('Luis Peña', 'luis@equalia.es', 'Comité');
    }

    // ------------------------------------------------------------ Quién entra

    public function test_solo_entra_quien_puede_administrar(): void
    {
        $this->actingAs($this->marta)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->luis)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
    }

    public function test_el_comite_no_ve_el_enlace_en_el_menu(): void
    {
        $this->actingAs($this->luis)
            ->get(route('proposals.index'))
            ->assertOk()
            ->assertDontSee(route('users.index'));
    }

    // ---------------------------------------------------------------- Listado

    public function test_el_listado_enseña_roles_y_como_entra_cada_uno(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Marta Ruiz')
            ->assertSee('Comité')
            ->assertSee('Contraseña');
    }

    public function test_se_puede_buscar_por_nombre(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index', ['buscar' => 'Marta']))
            ->assertOk()
            ->assertSee('Marta Ruiz')
            ->assertDontSee('Luis Peña');
    }

    // ------------------------------------------------------------------ Altas

    public function test_se_da_de_alta_a_alguien_nuevo(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Nuria Sanz',
            'email' => 'NURIA@equalia.es',
            'area_id' => Area::firstOrFail()->id,
            'roles' => ['Comité'],
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $nueva = User::where('email', 'nuria@equalia.es')->firstOrFail();

        $this->assertTrue($nueva->hasRole('Comité'));
        $this->assertTrue($nueva->is_active);
        $this->assertNull($nueva->password);
    }

    public function test_no_se_puede_repetir_un_correo(): void
    {
        $this->actingAs($this->admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Otra Marta',
                'email' => 'marta@equalia.es',
                'roles' => ['Empleado'],
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('email');
    }

    // ------------------------------------------------------------------ Roles

    public function test_se_nombra_a_alguien_del_comite(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->marta), [
            'name' => 'Marta Ruiz',
            'email' => 'marta@equalia.es',
            'roles' => ['Empleado', 'Comité'],
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $marta = $this->marta->fresh();

        $this->assertTrue($marta->hasRole('Comité'));
        $this->assertTrue($marta->canSeeRestrictedProposals());
    }

    public function test_al_quitar_el_comite_deja_de_ver_las_privadas(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->luis), [
            'name' => 'Luis Peña',
            'email' => 'luis@equalia.es',
            'roles' => ['Empleado'],
            'is_active' => '1',
        ]);

        $this->assertFalse($this->luis->fresh()->canSeeRestrictedProposals());
    }

    public function test_hay_que_marcar_algun_rol(): void
    {
        $this->actingAs($this->admin)
            ->from(route('users.edit', $this->marta))
            ->put(route('users.update', $this->marta), [
                'name' => 'Marta Ruiz',
                'email' => 'marta@equalia.es',
                'roles' => [],
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('roles');
    }

    // ------------------------------------------------------- La red de seguridad

    public function test_nadie_puede_quitarse_a_si_mismo_la_administracion(): void
    {
        $this->actingAs($this->admin)
            ->from(route('users.edit', $this->admin))
            ->put(route('users.update', $this->admin), [
                'name' => 'Ana Admin',
                'email' => 'ana@equalia.es',
                'roles' => ['Empleado'],
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($this->admin->fresh()->hasRole('Administración'));
    }

    public function test_nadie_puede_darse_de_baja_a_si_mismo(): void
    {
        $this->actingAs($this->admin)
            ->from(route('users.edit', $this->admin))
            ->put(route('users.update', $this->admin), [
                'name' => 'Ana Admin',
                'email' => 'ana@equalia.es',
                'roles' => ['Administración'],
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_si_se_puede_dar_de_baja_a_otra_persona(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->marta), [
            'name' => 'Marta Ruiz',
            'email' => 'marta@equalia.es',
            'roles' => ['Empleado'],
        ])->assertRedirect(route('users.index'));

        $this->assertFalse($this->marta->fresh()->is_active);

        // Y con eso deja de poder entrar. Hay que salir primero: la pantalla
        // de acceso rechaza a quien ya tiene la sesión abierta.
        auth()->logout();

        $this->post('/entrar', ['email' => 'marta@equalia.es', 'password' => 'buzon1234'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // --------------------------------------------------------------- Contraseña

    public function test_dejar_la_contraseña_vacia_no_la_cambia(): void
    {
        $antes = $this->marta->password;

        $this->actingAs($this->admin)->put(route('users.update', $this->marta), [
            'name' => 'Marta Ruiz',
            'email' => 'marta@equalia.es',
            'roles' => ['Empleado'],
            'is_active' => '1',
            'password' => '',
        ]);

        $this->assertSame($antes, $this->marta->fresh()->password);
    }

    public function test_escribir_una_contraseña_la_cambia_y_queda_cifrada(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->marta), [
            'name' => 'Marta Ruiz',
            'email' => 'marta@equalia.es',
            'roles' => ['Empleado'],
            'is_active' => '1',
            'password' => 'contraseña-nueva-larga',
        ]);

        $marta = $this->marta->fresh();

        $this->assertNotSame('contraseña-nueva-larga', $marta->password);
        $this->assertTrue(Hash::check('contraseña-nueva-larga', $marta->password));
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
}
