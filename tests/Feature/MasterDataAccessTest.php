<?php

use App\Models\PartType;
use App\Models\Plant;
use App\Models\Sample;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function masterDataRoutes(Plant $plant, PartType $partType, Team $team, Sample $sample): array
{
    return [
        ['GET', '/api/v1/plants'],
        ['POST', '/api/v1/plants'],
        ['GET', '/api/v1/plants/'.$plant->id],
        ['PATCH', '/api/v1/plants/'.$plant->id],
        ['DELETE', '/api/v1/plants/'.$plant->id],
        ['GET', '/api/v1/part-types'],
        ['POST', '/api/v1/part-types'],
        ['GET', '/api/v1/part-types/'.$partType->id],
        ['PATCH', '/api/v1/part-types/'.$partType->id],
        ['DELETE', '/api/v1/part-types/'.$partType->id],
        ['GET', '/api/v1/teams'],
        ['POST', '/api/v1/teams'],
        ['GET', '/api/v1/teams/'.$team->id],
        ['PATCH', '/api/v1/teams/'.$team->id],
        ['DELETE', '/api/v1/teams/'.$team->id],
        ['GET', '/api/v1/samples'],
        ['POST', '/api/v1/samples'],
        ['GET', '/api/v1/samples/'.$sample->id],
        ['PATCH', '/api/v1/samples/'.$sample->id],
        ['DELETE', '/api/v1/samples/'.$sample->id],
        ['GET', '/api/v1/samples/'.$sample->id.'/key-histories'],
    ];
}

function masterDataRecords(): array
{
    $plant = Plant::factory()->create();
    $partType = PartType::factory()->create();
    $team = Team::factory()->create(['plant_id' => $plant->id]);
    $sample = Sample::factory()->create(['part_type_id' => $partType->id]);

    return [$plant, $partType, $team, $sample];
}

test('every master data route requires authentication', function () {
    $routes = masterDataRoutes(...masterDataRecords());

    foreach ($routes as [$method, $uri]) {
        $this->json($method, $uri)->assertUnauthorized();
    }
});

test('inspectors cannot access master data at all', function () {
    $routes = masterDataRoutes(...masterDataRecords());

    Sanctum::actingAs(User::factory()->create());

    foreach ($routes as [$method, $uri]) {
        $this->json($method, $uri)->assertForbidden();
    }
});

test('managers can read master data but cannot change it', function () {
    [$plant, $partType, $team, $sample] = masterDataRecords();

    Sanctum::actingAs(User::factory()->manager()->create());

    $reads = [
        '/api/v1/plants',
        '/api/v1/plants/'.$plant->id,
        '/api/v1/part-types',
        '/api/v1/part-types/'.$partType->id,
        '/api/v1/teams',
        '/api/v1/teams/'.$team->id,
        '/api/v1/samples',
        '/api/v1/samples/'.$sample->id,
        '/api/v1/samples/'.$sample->id.'/key-histories',
    ];

    foreach ($reads as $uri) {
        $this->getJson($uri)->assertOk();
    }

    $writes = [
        ['POST', '/api/v1/plants'],
        ['PATCH', '/api/v1/plants/'.$plant->id],
        ['DELETE', '/api/v1/plants/'.$plant->id],
        ['POST', '/api/v1/part-types'],
        ['PATCH', '/api/v1/part-types/'.$partType->id],
        ['DELETE', '/api/v1/part-types/'.$partType->id],
        ['POST', '/api/v1/teams'],
        ['PATCH', '/api/v1/teams/'.$team->id],
        ['DELETE', '/api/v1/teams/'.$team->id],
        ['POST', '/api/v1/samples'],
        ['PATCH', '/api/v1/samples/'.$sample->id],
        ['DELETE', '/api/v1/samples/'.$sample->id],
    ];

    foreach ($writes as [$method, $uri]) {
        $this->json($method, $uri)->assertForbidden();
    }
});

test('a manager can see answer keys in the sample response', function () {
    [, , , $sample] = masterDataRecords();

    Sanctum::actingAs(User::factory()->manager()->create());

    $this->getJson('/api/v1/samples/'.$sample->id)
        ->assertOk()
        ->assertJsonPath('data.answer_key', $sample->answer_key->value);
});
