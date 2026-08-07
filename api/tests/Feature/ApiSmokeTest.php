<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_contract_is_available(): void
    {
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_customer_can_login_and_receive_sanctum_token(): void
    {
        User::create(['name'=>'Cliente Teste','email'=>'cliente@test.local','password'=>Hash::make('secret'),'portal'=>'cliente','active'=>true]);
        $this->postJson('/api/auth/login', ['email'=>'cliente@test.local','password'=>'secret','portal'=>'cliente'])->assertOk()->assertJsonStructure(['data'=>['access_token','profile']]);
    }

    public function test_registration_requires_adult_confirmation_and_terms_acceptance(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Cliente sem aceite',
            'email' => 'sem-aceite@test.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors(['age_confirmed', 'terms_accepted']);
    }
}
