<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_ok_when_healthy(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'services' => [
                    'database' => 'healthy',
                    'cache' => 'healthy',
                ],
            ]);
    }

    public function test_api_health_check_returns_ok_when_healthy(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'services' => [
                    'database' => 'healthy',
                    'cache' => 'healthy',
                ],
            ]);
    }

    public function test_health_check_returns_503_when_database_fails(): void
    {
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \PDOException('Connection refused by database'));

        Log::spy();

        $response = $this->getJson('/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'services' => [
                    'database' => 'unhealthy',
                ],
            ])
            ->assertJsonStructure([
                'errors' => ['database'],
            ]);

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
            return $message === 'Health check database failed'
                && isset($context['user'])
                && $context['action'] === 'health_check_database'
                && str_contains($context['reason'], 'Connection refused by database');
        });
    }

    public function test_health_check_returns_503_when_cache_fails(): void
    {
        Cache::shouldReceive('put')
            ->andThrow(new \RuntimeException('Cache unavailable'));

        Log::spy();

        $response = $this->getJson('/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'services' => [
                    'database' => 'healthy',
                    'cache' => 'unhealthy',
                ],
            ])
            ->assertJsonStructure([
                'errors' => ['cache'],
            ]);

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
            return $message === 'Health check cache failed'
                && isset($context['user'])
                && $context['action'] === 'health_check_cache'
                && str_contains($context['reason'], 'Cache unavailable');
        });
    }

    public function test_structured_log_emitted_on_store_gadget(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Log::spy();

        $response = $this->postJson('/api/gadgets', [
            'name' => 'Test Phone',
            'brand' => 'BrandX',
            'category' => 'smartphone',
            'target_price' => 1000000,
            'current_price' => 950000,
        ]);

        $response->assertStatus(201);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($user) {
            return $message === 'Gadget stored successfully'
                && $context['user'] === (string) $user->id
                && $context['action'] === 'store_gadget'
                && isset($context['gadget_id']);
        });
    }

    public function test_structured_log_emitted_on_validation_failure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Log::spy();

        $response = $this->postJson('/api/gadgets', []);

        $response->assertStatus(422);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) use ($user) {
            return $message === 'Validation failed for storing gadget'
                && $context['user'] === (string) $user->id
                && $context['action'] === 'store_gadget_validation'
                && isset($context['reason']['name']);
        });
    }

    public function test_structured_log_emitted_on_login_validation_failure_without_pii(): void
    {
        Log::spy();

        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return $message === 'Validation failed for login'
                && $context['user'] === 'guest'
                && $context['action'] === 'login_validation'
                && ! isset($context['email'])
                && ! isset($context['password']);
        });
    }

    public function test_structured_log_emitted_on_auth_failure_without_pii(): void
    {
        User::factory()->create([
            'email' => 'user@example.test',
            'password' => 'secret123',
        ]);

        Log::spy();

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.test',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return $message === 'Authentication failed for login'
                && $context['user'] === 'guest'
                && $context['action'] === 'user_authentication'
                && ! isset($context['email'])
                && ! isset($context['password']);
        });
    }
}
