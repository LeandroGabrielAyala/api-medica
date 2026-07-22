<?php

namespace Tests\Feature;

use App\Models\Consulta;
use App\Models\Notification;
use App\Models\Pago;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModoGratuitoPagosTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::connection()->getPdo()->sqliteCreateFunction(
            'CONCAT',
            fn (...$values) => implode('', $values),
            -1,
            \PDO::SQLITE_DETERMINISTIC
        );
    }

    public function test_un_turno_gratuito_se_crea_y_confirma_una_sola_vez(): void
    {
        config(['services.pagos.habilitados' => false]);

        $paciente = User::factory()->create();
        User::factory()->create(['role' => 'medico']);

        $response = $this->postJson('/api/crear-pago', [
            'tipo' => 'turno',
            'monto' => 1500,
            'user_id' => $paciente->id,
            'metadata' => [
                'fecha' => '2026-08-10',
                'hora' => '10:30',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'modo_gratuito' => true,
                'estado' => 'aprobado',
                'modulo' => 'turno',
                'redirect' => 'confirmacion',
            ])
            ->assertJsonMissingPath('init_point');

        $externalReference = $response->json('external_reference');

        $this->assertDatabaseHas('pagos', [
            'user_id' => $paciente->id,
            'modulo' => 'turno',
            'monto' => 0,
            'estado' => 'pagado',
            'external_reference' => $externalReference,
            'mp_payment_id' => null,
        ]);

        $this->assertDatabaseHas('turnos', [
            'user_id' => $paciente->id,
            'monto' => 0,
            'estado' => 'pendiente',
            'metodo_pago' => 'gratuito',
            'external_reference' => $externalReference,
            'fecha' => '2026-08-10',
            'hora' => '10:30',
        ]);

        $this->postJson('/api/simular-pago', [
            'external_reference' => $externalReference,
        ])->assertOk()->assertJson([
            'success' => true,
            'status' => 'ya procesado',
        ]);

        $this->assertSame(1, Turno::where('external_reference', $externalReference)->count());
        $this->assertSame(1, Notification::where('title', 'Nuevo turno confirmado')->count());
    }

    public function test_no_se_puede_reservar_dos_veces_el_mismo_horario_activo(): void
    {
        config(['services.pagos.habilitados' => false]);

        $paciente = User::factory()->create();

        $payload = [
            'tipo' => 'turno',
            'monto' => 1500,
            'user_id' => $paciente->id,
            'metadata' => [
                'fecha' => '2026-08-11',
                'hora' => '09:00',
            ],
        ];

        $this->postJson('/api/crear-pago', $payload)->assertOk();
        $this->postJson('/api/crear-pago', $payload)->assertStatus(409);

        $this->assertSame(1, Turno::where('fecha', '2026-08-11')
            ->where('hora', '09:00')
            ->count());
    }

    public function test_una_consulta_gratuita_aparece_en_listados_y_notifica_al_medico(): void
    {
        config(['services.pagos.habilitados' => false]);

        $paciente = User::factory()->create();
        User::factory()->create(['role' => 'medico']);

        $response = $this->postJson('/api/crear-pago', [
            'tipo' => 'consulta-rapida',
            'monto' => 1200,
            'user_id' => $paciente->id,
            'metadata' => [
                'descripcion' => 'Dolor de garganta',
            ],
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'modo_gratuito' => true,
            'modulo' => 'consulta-rapida',
        ]);

        $externalReference = $response->json('external_reference');

        $this->assertDatabaseHas('consultas', [
            'user_id' => $paciente->id,
            'tipo' => 'consulta-rapida',
            'monto' => 0,
            'estado' => 'pendiente',
            'metodo_pago' => 'gratuito',
            'external_reference' => $externalReference,
        ]);

        $this->getJson('/api/consultas?user_id=' . $paciente->id)
            ->assertOk()
            ->assertJsonFragment([
                'external_reference' => $externalReference,
            ]);

        $this->assertDatabaseHas('notifications', [
            'title' => 'Nueva consulta rapida',
            'read' => false,
        ]);
    }

    public function test_con_pagos_habilitados_se_crea_pago_pendiente(): void
    {
        config(['services.pagos.habilitados' => true]);
        config(['services.mercadopago.access_token' => 'TEST-TOKEN']);

        $paciente = User::factory()->create();

        $client = \Mockery::mock('overload:MercadoPago\Client\Preference\PreferenceClient');
        $client->shouldReceive('create')
            ->once()
            ->andReturn((object) [
                'id' => 'pref-test',
                'init_point' => 'https://mercadopago.test/init',
            ]);

        $response = $this->postJson('/api/crear-pago', [
            'tipo' => 'consulta-rapida',
            'monto' => 1200,
            'user_id' => $paciente->id,
            'metadata' => [],
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'init_point' => 'https://mercadopago.test/init',
            ])
            ->assertJsonMissing([
                'modo_gratuito' => true,
            ]);

        $this->assertDatabaseHas('pagos', [
            'user_id' => $paciente->id,
            'modulo' => 'consulta-rapida',
            'monto' => 1200,
            'estado' => 'pendiente',
        ]);

        $this->assertSame(0, Consulta::count());
    }
}
