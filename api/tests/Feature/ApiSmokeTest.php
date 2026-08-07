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
}

