<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_la_raiz_muestra_la_bienvenida_con_el_video_a_quien_no_inicio_sesion(): void
    {
        $this->get('/')->assertOk()->assertSee('LILIM')->assertSee('media/otis-fondo.mp4', false)->assertSee(route('login'), false);
    }

    public function test_la_raiz_lleva_al_dashboard_a_quien_ya_inicio_sesion(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
    }
}
