<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as CuentaDeMicrosoft;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * La entrada con la cuenta de Microsoft de la empresa.
 *
 * Microsoft no participa en las pruebas: se sustituye por un doble que
 * devuelve lo que devolvería él. Lo que se comprueba es lo nuestro — qué
 * hacemos con esa respuesta.
 */
class EntrarConMicrosoftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CatalogSeeder::class, RoleSeeder::class]);

        config([
            'services.azure.client_id' => 'id-de-prueba',
            'services.azure.client_secret' => 'secreto-de-prueba',
            'services.azure.redirect' => 'http://localhost/entrar/microsoft/callback',
            'services.azure.tenant' => 'tenant-de-prueba',
        ]);
    }

    public function test_quien_entra_por_primera_vez_se_da_de_alta_como_empleado(): void
    {
        $this->fingirCuenta('marta@equalia.es', 'Marta Ruiz', 'ms-0001');

        $this->get('/entrar/microsoft/callback')->assertRedirect(route('proposals.index'));

        $usuario = User::where('email', 'marta@equalia.es')->firstOrFail();

        $this->assertAuthenticatedAs($usuario);
        $this->assertTrue($usuario->hasRole('Empleado'));
        $this->assertSame('ms-0001', $usuario->microsoft_id);
        $this->assertNull($usuario->password);
        $this->assertNotNull($usuario->last_login_at);

        // Sin área: adivinarla sería peor que dejarla vacía, porque el
        // formulario la traería preseleccionada y nadie lo notaría.
        $this->assertNull($usuario->area_id);
    }

    public function test_el_formulario_no_preselecciona_area_a_quien_no_la_tiene(): void
    {
        $this->fingirCuenta('nuevo@equalia.es', 'Persona Nueva', 'ms-0009');
        $this->get('/entrar/microsoft/callback');

        $this->get('/propuestas/nueva')
            ->assertOk()
            ->assertSee('Elige un área…', escape: false);
    }

    public function test_quien_ya_tenia_cuenta_conserva_su_rol_y_no_se_duplica(): void
    {
        $luis = User::create([
            'name' => 'Luis Peña',
            'email' => 'luis@equalia.es',
            'password' => 'buzon1234',
            'area_id' => Area::firstOrFail()->id,
        ]);
        $luis->assignRole('Comité');

        $this->fingirCuenta('luis@equalia.es', 'Luis Peña', 'ms-0002');

        $this->get('/entrar/microsoft/callback')->assertRedirect(route('proposals.index'));

        $this->assertSame(1, User::where('email', 'luis@equalia.es')->count());
        $this->assertTrue($luis->fresh()->hasRole('Comité'));
        $this->assertSame('ms-0002', $luis->fresh()->microsoft_id);
    }

    public function test_el_correo_no_distingue_mayusculas(): void
    {
        $ana = User::create([
            'name' => 'Ana Admin',
            'email' => 'ana@equalia.es',
            'password' => 'buzon1234',
        ]);
        $ana->assignRole('Administración');

        $this->fingirCuenta('ANA@Equalia.es', 'Ana Admin', 'ms-0003');

        $this->get('/entrar/microsoft/callback');

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['ana@equalia.es'])->count());
        $this->assertAuthenticatedAs($ana->fresh());
    }

    public function test_una_cuenta_dada_de_baja_no_entra_aunque_microsoft_diga_que_si(): void
    {
        $baja = User::create([
            'name' => 'Ex Empleado',
            'email' => 'ex@equalia.es',
            'password' => 'buzon1234',
            'is_active' => false,
        ]);
        $baja->assignRole('Empleado');

        $this->fingirCuenta('ex@equalia.es', 'Ex Empleado', 'ms-0004');

        $this->get('/entrar/microsoft/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_si_microsoft_falla_se_avisa_en_vez_de_reventar(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new \RuntimeException('token caducado'));

        $this->get('/entrar/microsoft/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sin_configurar_el_boton_no_aparece_y_la_ruta_no_lleva_a_ninguna_parte(): void
    {
        config(['services.azure.client_id' => null]);

        $this->get('/entrar')->assertOk()->assertDontSee('Entrar con Microsoft');
        $this->get('/entrar/microsoft')->assertRedirect(route('login'));
    }

    public function test_configurado_el_boton_si_aparece(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Entrar con Microsoft');
    }

    /** Sustituye a Microsoft por un doble que devuelve estos datos. */
    private function fingirCuenta(string $correo, string $nombre, string $id): void
    {
        $cuenta = Mockery::mock(CuentaDeMicrosoft::class);
        $cuenta->shouldReceive('getEmail')->andReturn($correo);
        $cuenta->shouldReceive('getName')->andReturn($nombre);
        $cuenta->shouldReceive('getId')->andReturn($id);
        $cuenta->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver->user')->andReturn($cuenta);
    }
}
