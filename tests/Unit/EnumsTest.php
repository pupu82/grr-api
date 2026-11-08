<?php

use App\Enums\Judgement;
use App\Enums\RegistrationSource;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Enums\UserStatus;
use App\Enums\Verdict;

test('enum values match what the database and check constraints expect', function () {
    expect(array_column(Verdict::cases(), 'value'))->toBe(['good', 'defect']);
    expect(array_column(Role::cases(), 'value'))->toBe(['admin', 'manager', 'inspector']);
    expect(array_column(UserStatus::cases(), 'value'))->toBe(['pending', 'active', 'inactive']);
    expect(array_column(RegistrationSource::cases(), 'value'))->toBe(['self', 'admin']);
    expect(array_column(SessionStatus::cases(), 'value'))->toBe(['draft', 'in_progress', 'completed']);
    expect(array_column(Judgement::cases(), 'value'))->toBe(['acceptable', 'marginal', 'unacceptable']);
});

test('only active accounts can log in', function () {
    expect(UserStatus::Active->canLogin())->toBeTrue();
    expect(UserStatus::Pending->canLogin())->toBeFalse();
    expect(UserStatus::Inactive->canLogin())->toBeFalse();
});

test('only an admin can manage data and a manager is read-only', function () {
    expect(Role::Admin->canManageData())->toBeTrue();
    expect(Role::Manager->canManageData())->toBeFalse();
    expect(Role::Inspector->canManageData())->toBeFalse();
    expect(Role::Manager->canViewAllResults())->toBeTrue();
    expect(Role::Inspector->canViewAllResults())->toBeFalse();
});

test('only an in-progress session accepts answers', function () {
    expect(SessionStatus::InProgress->acceptsAnswers())->toBeTrue();
    expect(SessionStatus::Draft->acceptsAnswers())->toBeFalse();
    expect(SessionStatus::Completed->acceptsAnswers())->toBeFalse();
});
