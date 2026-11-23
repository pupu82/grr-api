<?php

use App\Models\Plant;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());
});

test('an admin can manage teams and their plant', function () {
    $plant = Plant::factory()->create();
    $otherPlant = Plant::factory()->create();
    $team = Team::factory()->create(['plant_id' => $plant->id]);

    $this->getJson('/api/v1/teams')
        ->assertOk()
        ->assertJsonPath('data.0.plant.id', $plant->id);

    $this->postJson('/api/v1/teams', [
        'plant_id' => $plant->id,
        'name' => 'Line 1',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Line 1')
        ->assertJsonPath('data.plant.id', $plant->id);

    $created = Team::query()->where('name', 'Line 1')->firstOrFail();

    $this->patchJson('/api/v1/teams/'.$created->id, ['plant_id' => $otherPlant->id])
        ->assertOk()
        ->assertJsonPath('data.plant_id', $otherPlant->id);

    $this->deleteJson('/api/v1/teams/'.$team->id)->assertNoContent();
    $this->assertSoftDeleted('teams', ['id' => $team->id]);
});

test('team validation requires an existing plant', function () {
    $this->postJson('/api/v1/teams', ['name' => 'Line 1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plant_id');

    $this->postJson('/api/v1/teams', ['plant_id' => 9999, 'name' => 'Line 1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plant_id');
});

test('the team list can be filtered by plant', function () {
    $plant = Plant::factory()->create();
    Team::factory()->count(2)->create(['plant_id' => $plant->id]);
    Team::factory()->create();

    $this->getJson('/api/v1/teams?plant_id='.$plant->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('the team list accepts multiple plants, names and search terms', function () {
    $bcp = Plant::factory()->create(['code' => 'BCP']);
    $ucp = Plant::factory()->create(['code' => 'UCP']);
    $other = Plant::factory()->create(['code' => 'PLANT-A']);

    Team::factory()->create(['plant_id' => $bcp->id, 'name' => 'Line 1']);
    Team::factory()->create(['plant_id' => $ucp->id, 'name' => 'Line 2']);
    Team::factory()->create(['plant_id' => $other->id, 'name' => 'Warehouse Crew']);

    $this->getJson('/api/v1/teams?plant_id[]='.$bcp->id.'&plant_id[]='.$ucp->id)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Line 1')
        ->assertJsonPath('data.1.name', 'Line 2');

    $this->getJson('/api/v1/teams?search[]=line&search[]=warehouse')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $this->getJson('/api/v1/teams?name[]=Line 1&name[]=Warehouse Crew')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Line 1')
        ->assertJsonPath('data.1.name', 'Warehouse Crew');

    $this->getJson('/api/v1/teams?plant_id[]='.$bcp->id.'&name[]=Warehouse Crew')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a team cannot be created under a soft-deleted plant', function () {
    $plant = Plant::factory()->create();
    $plant->delete();

    $this->postJson('/api/v1/teams', ['plant_id' => $plant->id, 'name' => 'Line 1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plant_id');
});
