<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavegacionTest extends TestCase
{
    public function test_el_panel_y_serologia_siguen_requiriendo_autenticacion(): void
    {
        $this->get(route('home'))->assertRedirectToRoute('login');
        $this->get(route('serologia'))->assertRedirectToRoute('login');
        $this->get(route('operaciones'))->assertRedirectToRoute('login');
    }

    public function test_el_dashboard_utiliza_inertia_para_el_usuario_autenticado(): void
    {
        $this->withoutVite();
        $usuario = UserFactory::new()->make(['id' => 1, 'empresa' => '001']);

        $this->actingAs($usuario)->get(route('home'))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('dashboard')->where('empresa', '001'));
    }

    public function test_el_login_conserva_su_vista_blade_y_formulario_actual(): void
    {
        $this->withoutVite();
        Schema::create('cor_companias', function (Blueprint $table): void {
            $table->string('codigo_compania');
            $table->string('nombre_corto');
        });
        DB::table('cor_companias')->insert(['codigo_compania' => '001', 'nombre_corto' => 'Criocord']);

        $this->get(route('login'))->assertOk()->assertViewIs('auth.login')
            ->assertSee('Criocord')->assertSee('name="password"', false);
    }

    public function test_operaciones_utiliza_inertia_para_el_usuario_autenticado(): void
    {
        $this->withoutVite();
        $usuario = UserFactory::new()->make(['id' => 1, 'empresa' => '001']);

        $this->actingAs($usuario)->get(route('operaciones'))->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('operaciones')->where('empresa', '001'));
    }

    public function test_el_logout_conserva_la_salida_a_la_pagina_blade_de_login(): void
    {
        $usuario = UserFactory::new()->make(['id' => 1, 'empresa' => '001', 'remember_token' => null]);

        $this->actingAs($usuario)->withHeader('X-Inertia', 'true')->post(route('logout'))->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));
        $this->assertGuest();
    }
}
