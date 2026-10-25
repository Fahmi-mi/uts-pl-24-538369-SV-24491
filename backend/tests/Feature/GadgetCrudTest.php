<?php

namespace Tests\Feature;

use App\Models\Gadget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GadgetCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_access_gadgets_without_token(): void
    {
        $response = $this->get('/api/gadgets');

        $response->assertStatus(401);
    }

    public function test_user_can_list_own_gadgets_with_filters(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Gadget::factory()->create([
            'user_id' => $user->id,
            'name' => 'MacBook Pro M3',
            'category' => 'laptop',
            'status' => 'wishlist',
        ]);
        Gadget::factory()->create([
            'user_id' => $user->id,
            'name' => 'iPad Air',
            'category' => 'other',
            'status' => 'purchased',
        ]);
        Gadget::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Dell XPS',
            'category' => 'laptop',
            'status' => 'wishlist',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/gadgets?category=laptop');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'MacBook Pro M3');
    }

    public function test_user_can_create_gadget_with_initial_price_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Sony WH-1000XM5',
            'brand' => 'Sony',
            'category' => 'audio',
            'store' => 'Tokopedia',
            'product_url' => 'https://tokopedia.com/sony',
            'target_price' => 4500000,
            'current_price' => 4200000,
            'priority' => 'high',
            'status' => 'wishlist',
            'notes' => 'Incar saat diskon gajian',
        ];

        $response = $this->postJson('/api/gadgets', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Gadget berhasil ditambahkan',
            ])
            ->assertJsonPath('data.name', 'Sony WH-1000XM5')
            ->assertJsonPath('data.target_reached', true)
            ->assertJsonPath('data.price_gap', -300000);

        $this->assertDatabaseHas('gadgets', [
            'user_id' => $user->id,
            'name' => 'Sony WH-1000XM5',
        ]);

        $gadget = Gadget::where('name', 'Sony WH-1000XM5')->first();
        $this->assertDatabaseHas('price_histories', [
            'gadget_id' => $gadget->id,
            'price' => 4200000,
        ]);
    }

    public function test_create_gadget_validation_errors_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/gadgets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'brand', 'category', 'target_price', 'current_price']);
    }

    public function test_user_can_view_single_gadget(): void
    {
        $user = User::factory()->create();
        $gadget = Gadget::factory()->create([
            'user_id' => $user->id,
            'target_price' => 5000000,
            'current_price' => 6000000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/gadgets/' . $gadget->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $gadget->id)
            ->assertJsonPath('data.target_reached', false)
            ->assertJsonPath('data.price_gap', 1000000);
    }

    public function test_user_can_update_gadget_and_price_change_creates_history(): void
    {
        $user = User::factory()->create();
        $gadget = Gadget::factory()->create([
            'user_id' => $user->id,
            'target_price' => 5000000,
            'current_price' => 6000000,
        ]);
        $gadget->priceHistories()->create(['price' => 6000000]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/gadgets/' . $gadget->id, [
            'current_price' => 4900000,
            'notes' => 'Harga turun di bawah target',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Gadget berhasil diperbarui',
            ])
            ->assertJsonPath('data.current_price', 4900000)
            ->assertJsonPath('data.target_reached', true);

        $this->assertDatabaseCount('price_histories', 2);
    }

    public function test_updating_gadget_without_price_change_does_not_create_history(): void
    {
        $user = User::factory()->create();
        $gadget = Gadget::factory()->create([
            'user_id' => $user->id,
            'target_price' => 5000000,
            'current_price' => 6000000,
        ]);
        $gadget->priceHistories()->create(['price' => 6000000]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/gadgets/' . $gadget->id, [
            'notes' => 'Hanya mengubah catatan',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('price_histories', 1);
    }

    public function test_user_can_delete_own_gadget(): void
    {
        $user = User::factory()->create();
        $gadget = Gadget::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/gadgets/' . $gadget->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Gadget berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('gadgets', [
            'id' => $gadget->id,
        ]);
    }

    public function test_user_cannot_access_other_user_gadget(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $gadget = Gadget::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/gadgets/' . $gadget->id)->assertStatus(404);
        $this->putJson('/api/gadgets/' . $gadget->id, ['name' => 'Hacked'])->assertStatus(404);
        $this->deleteJson('/api/gadgets/' . $gadget->id)->assertStatus(404);
    }

    public function test_user_can_view_gadget_price_histories(): void
    {
        $user = User::factory()->create();
        $gadget = Gadget::factory()->create([
            'user_id' => $user->id,
        ]);
        $gadget->priceHistories()->create(['price' => 5000000]);
        $gadget->priceHistories()->create(['price' => 4800000]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/gadgets/' . $gadget->id . '/price-histories');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
