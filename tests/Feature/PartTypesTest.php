<?php

use App\Models\PartType;
use App\Models\Sample;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());
});

test('an admin can manage part types', function () {
    $partType = PartType::factory()->create(['name' => 'Housing']);

    $this->getJson('/api/v1/part-types')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Housing');

    $this->postJson('/api/v1/part-types', ['name' => 'Bracket'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bracket');

    $created = PartType::query()->where('name', 'Bracket')->firstOrFail();

    $this->patchJson('/api/v1/part-types/'.$created->id, ['name' => 'Bracket v2'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bracket v2');

    $this->deleteJson('/api/v1/part-types/'.$partType->id)->assertNoContent();
    $this->assertSoftDeleted('part_types', ['id' => $partType->id]);
});

test('part types expose their sample count', function () {
    $partType = PartType::factory()->create();
    Sample::factory()->count(2)->create(['part_type_id' => $partType->id]);

    $this->getJson('/api/v1/part-types/'.$partType->id)
        ->assertOk()
        ->assertJsonPath('data.samples_count', 2);
});

test('part type validation rejects a missing name', function () {
    $this->postJson('/api/v1/part-types', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});
