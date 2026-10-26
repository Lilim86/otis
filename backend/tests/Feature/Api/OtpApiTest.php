<?php

namespace Tests\Feature\Api;

use App\Mail\OtpCodigoMail;
use App\Models\OtpCodigo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpApiTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'http://127.0.0.1:5173';

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.requerido' => true]); // el código por correo es lo normal; solo se apaga en desarrollo
    }

    private function usuario(array $extra = []): User
    {
        return User::create($extra + ['name' => 'Admin', 'username' => 'admin', 'email' => 'administrador@otis.com', 'password' => 'secret123']);
    }

    /** Pide el código y lo devuelve leyéndolo del correo enviado. */
    private function pedirCodigo(string $username = 'admin', string $password = 'secret123'): string
    {
        $this->postJson('/api/auth/otp/solicitar', ['username' => $username, 'password' => $password])->assertOk();

        $codigo = null;
        Mail::assertSent(OtpCodigoMail::class, function ($mail) use (&$codigo) {
            $codigo = $mail->codigo;

            return true;
        });

        return $codigo;
    }

    public function test_solicitar_envia_un_codigo_de_seis_digitos_por_correo(): void
    {
        Mail::fake();
        $this->usuario();

        $this->postJson('/api/auth/otp/solicitar', ['username' => 'admin', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('correo', 'ad***********@otis.com')
            ->assertJsonPath('expira_en_segundos', 300);

        Mail::assertSent(OtpCodigoMail::class, fn ($m) => $m->hasTo('administrador@otis.com') && preg_match('/^\d{6}$/', $m->codigo));
    }

    public function test_el_codigo_no_se_guarda_en_claro(): void
    {
        Mail::fake();
        $this->usuario();
        $codigo = $this->pedirCodigo();

        $guardado = OtpCodigo::first();
        $this->assertNotSame($codigo, $guardado->codigo_hash);
        $this->assertStringNotContainsString($codigo, $guardado->codigo_hash);
    }

    public function test_credenciales_incorrectas_no_envian_codigo(): void
    {
        Mail::fake();
        $this->usuario();

        $this->postJson('/api/auth/otp/solicitar', ['username' => 'admin', 'password' => 'mala'])->assertUnprocessable();
        $this->postJson('/api/auth/otp/solicitar', ['username' => 'noexiste', 'password' => 'x'])->assertUnprocessable();
        $this->postJson('/api/auth/otp/solicitar', [])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);

        Mail::assertNothingSent();
    }

    public function test_un_usuario_inactivo_no_recibe_codigo(): void
    {
        Mail::fake();
        $this->usuario(['estado' => false]);

        $this->postJson('/api/auth/otp/solicitar', ['username' => 'admin', 'password' => 'secret123'])->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_verificar_devuelve_un_token_para_clientes_sin_sesion(): void
    {
        Mail::fake();
        $this->usuario();
        $codigo = $this->pedirCodigo();

        $token = $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $codigo, 'device_name' => 'postman'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.username', 'admin')
            ->json('token');

        $this->withToken($token)->getJson('/api/paises')->assertOk();
    }

    public function test_verificar_inicia_la_sesion_del_frontend_de_vue(): void
    {
        Mail::fake();
        $this->usuario();
        $codigo = $this->pedirCodigo();
        $cabeceras = ['Origin' => self::FRONTEND, 'Referer' => self::FRONTEND.'/'];

        $this->withHeaders($cabeceras)->getJson('/api/user')->assertUnauthorized();

        $this->withHeaders($cabeceras)->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $codigo])
            ->assertOk()->assertJsonPath('user.username', 'admin')->assertJsonMissingPath('token');

        $this->withHeaders($cabeceras)->getJson('/api/user')->assertOk()->assertJsonPath('username', 'admin');
    }

    public function test_un_codigo_incorrecto_se_rechaza(): void
    {
        Mail::fake();
        $this->usuario();
        $codigo = $this->pedirCodigo();
        $malo = $codigo === '000000' ? '111111' : '000000';

        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $malo])
            ->assertUnprocessable()->assertJsonPath('errors.codigo.0', 'El código es incorrecto o ya venció.');
        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => '12'])
            ->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->postJson('/api/auth/otp/verificar', ['username' => 'noexiste', 'codigo' => $codigo])->assertUnprocessable();
    }

    public function test_el_codigo_es_de_un_solo_uso(): void
    {
        Mail::fake();
        $this->usuario();
        $codigo = $this->pedirCodigo();

        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $codigo])->assertOk();
        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $codigo])->assertUnprocessable();
    }

    public function test_el_codigo_vence(): void
    {
        Mail::fake();
        $this->usuario();
        $codigo = $this->pedirCodigo();

        $this->travel(6)->minutes();

        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $codigo])->assertUnprocessable();
    }

    public function test_se_bloquea_tras_demasiados_intentos_fallidos(): void
    {
        Mail::fake();
        $this->usuario();
        $codigo = $this->pedirCodigo();
        $malo = $codigo === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $malo])->assertUnprocessable();
        }

        // Ni el código correcto sirve ya: hay que pedir uno nuevo.
        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $codigo])->assertUnprocessable();
    }

    public function test_pedir_un_codigo_nuevo_anula_el_anterior(): void
    {
        Mail::fake();
        $this->usuario();
        $primero = $this->pedirCodigo();

        Mail::fake();
        $segundo = $this->pedirCodigo();

        $this->assertNotSame($primero, $segundo);
        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $primero])->assertUnprocessable();
        $this->postJson('/api/auth/otp/verificar', ['username' => 'admin', 'codigo' => $segundo])->assertOk();
    }

    public function test_si_falla_el_correo_no_queda_un_codigo_valido(): void
    {
        $this->usuario();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->postJson('/api/auth/otp/solicitar', ['username' => 'admin', 'password' => 'secret123'])
            ->assertStatus(503);

        $this->assertDatabaseMissing('otp_codigos', ['usado_en' => null]);
    }

    public function test_con_otp_requerido_el_login_solo_con_contrasena_se_bloquea(): void
    {
        $this->usuario();

        config(['otp.requerido' => true]);
        $this->postJson('/api/auth/token', ['username' => 'admin', 'password' => 'secret123'])->assertForbidden();

        config(['otp.requerido' => false]);
        $this->postJson('/api/auth/token', ['username' => 'admin', 'password' => 'secret123'])->assertOk();
    }

    public function test_con_el_codigo_desactivado_el_spa_entra_con_usuario_y_contrasena(): void
    {
        config(['otp.requerido' => false]);
        $cabeceras = ['Origin' => self::FRONTEND, 'Referer' => self::FRONTEND.'/'];
        User::create(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@example.com', 'password' => 'secret123']);

        $this->postJson('/api/auth/otp/solicitar', ['username' => 'ana', 'password' => 'mala'], $cabeceras)->assertUnprocessable();
        $this->postJson('/api/auth/otp/solicitar', ['username' => 'ana', 'password' => 'secret123'], $cabeceras)
            ->assertOk()->assertJsonPath('sin_codigo', true)->assertJsonPath('user.username', 'ana');
        $this->getJson('/api/user', $cabeceras)->assertOk()->assertJsonPath('username', 'ana');
    }
}
