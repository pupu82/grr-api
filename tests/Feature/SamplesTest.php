<?php

use App\Models\PartType;
use App\Models\Sample;
use App\Models\SampleKeyHistory;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin);
});

test('an admin can manage samples', function () {
    $partType = PartType::factory()->create();

    $this->postJson('/api/v1/samples', [
        'code' => 'SMP-0001',
        'part_type_id' => $partType->id,
        'answer_key' => 'defect',
    ])->assertCreated()
        ->assertJsonPath('data.code', 'SMP-0001')
        ->assertJsonPath('data.answer_key', 'defect');

    $sample = Sample::query()->where('code', 'SMP-0001')->firstOrFail();

    $this->getJson('/api/v1/samples?part_type_id='.$partType->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.part_type.id', $partType->id);

    $this->deleteJson('/api/v1/samples/'.$sample->id)->assertNoContent();
    $this->assertSoftDeleted('samples', ['id' => $sample->id]);
});

test('sample validation rejects an invalid answer key', function () {
    $partType = PartType::factory()->create();

    $this->postJson('/api/v1/samples', [
        'code' => 'SMP-0001',
        'part_type_id' => $partType->id,
        'answer_key' => 'ok',
    ])->assertStatus(422)->assertJsonValidationErrors('answer_key');
});

test('changing a sample answer key records history', function () {
    $sample = Sample::factory()->good()->create();

    $this->patchJson('/api/v1/samples/'.$sample->id, ['answer_key' => 'defect'])
        ->assertOk()
        ->assertJsonPath('data.answer_key', 'defect');

    expect($sample->refresh()->answer_key->value)->toBe('defect');

    $history = SampleKeyHistory::query()->where('sample_id', $sample->id)->sole();

    expect($history->old_key->value)->toBe('good')
        ->and($history->new_key->value)->toBe('defect')
        ->and($history->changed_by)->toBe($this->admin->id)
        ->and($history->changed_at)->not->toBeNull();
});

test('updating a sample without changing the key does not record history', function () {
    $sample = Sample::factory()->good()->create();

    $this->patchJson('/api/v1/samples/'.$sample->id, ['code' => 'SMP-9999'])
        ->assertOk();

    expect(SampleKeyHistory::query()->where('sample_id', $sample->id)->count())->toBe(0);
});

test('the key history exposes the actor by uuid, never by the internal id', function () {
    $sample = Sample::factory()->good()->create();

    $this->patchJson('/api/v1/samples/'.$sample->id, ['answer_key' => 'defect'])
        ->assertOk();

    $this->getJson('/api/v1/samples/'.$sample->id.'/key-histories')
        ->assertOk()
        ->assertJsonPath('data.0.changed_by', $this->admin->uuid)
        ->assertJsonPath('data.0.changed_by_name', $this->admin->name);
});

test('the answer key history of a sample is listed newest first', function () {
    $sample = Sample::factory()->good()->create();

    SampleKeyHistory::factory()->create([
        'sample_id' => $sample->id,
        'changed_at' => now()->subDay(),
    ]);
    SampleKeyHistory::factory()->create([
        'sample_id' => $sample->id,
        'changed_at' => now(),
    ]);

    $this->getJson('/api/v1/samples/'.$sample->id.'/key-histories')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('sample codes are unique among non-deleted rows', function () {
    $existing = Sample::factory()->create(['code' => 'SMP-0001']);

    $this->postJson('/api/v1/samples', [
        'code' => 'SMP-0001',
        'part_type_id' => $existing->part_type_id,
        'answer_key' => 'good',
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});
