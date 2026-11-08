<?php

use App\Models\Plant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());
});

test('an admin can list, create, update and soft delete plants', function () {
    Plant::factory()->count(2)->create();

    $this->getJson('/api/v1/plants')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $created = $this->postJson('/api/v1/plants', [
        'code' => 'NEW-PLANT',
        'name' => 'New Plant',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'NEW-PLANT')
        ->assertJsonPath('data.is_active', true);

    $plant = Plant::query()->where('code', 'NEW-PLANT')->firstOrFail();

    $this->getJson('/api/v1/plants/'.$plant->id)
        ->assertOk()
        ->assertJsonPath('data.name', 'New Plant');

    $this->patchJson('/api/v1/plants/'.$plant->id, ['name' => 'Renamed Plant'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Plant');

    $this->deleteJson('/api/v1/plants/'.$plant->id)->assertNoContent();

    $this->assertSoftDeleted('plants', ['id' => $plant->id]);
    expect($created->json('data.code'))->toBe('NEW-PLANT');
});

test('plant codes are unique among non-deleted rows', function () {
    Plant::factory()->create(['code' => 'BCP']);

    $this->postJson('/api/v1/plants', ['code' => 'BCP', 'name' => 'Duplicate'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

test('a deleted plant code can be reused', function () {
    $plant = Plant::factory()->create(['code' => 'BCP']);

    $this->deleteJson('/api/v1/plants/'.$plant->id)->assertNoContent();

    $this->postJson('/api/v1/plants', ['code' => 'BCP', 'name' => 'BCP again'])
        ->assertCreated();

    expect(Plant::query()->where('code', 'BCP')->count())->toBe(1)
        ->and(Plant::withTrashed()->where('code', 'BCP')->count())->toBe(2);
});

test('plant validation rejects missing fields', function () {
    $this->postJson('/api/v1/plants', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'name']);
});

test('the plant list can be searched', function () {
    Plant::factory()->create(['code' => 'PLANT-A', 'name' => 'Plant A']);
    Plant::factory()->create(['code' => 'WAREHOUSE-A', 'name' => 'Warehouse A']);

    $this->getJson('/api/v1/plants?search=warehouse')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'WAREHOUSE-A');
});
