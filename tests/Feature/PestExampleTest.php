<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pest example test to demonstrate Pest testing framework integration
 * 
 * @group pest-demo
 */

// Enable database refresh for tests that need database
uses(RefreshDatabase::class);

test('can create user with pest', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
    expect($user->id)->toBeInt();
});

test('can create space with pest', function () {
    $space = Space::factory()->create([
        'name' => 'Test Space',
        'slug' => 'test-space'
    ]);

    expect($space->name)->toBe('Test Space');
    expect($space->slug)->toBe('test-space');
    expect($space->uuid)->toBeString();
});

test('database connection configuration', function () {
    // In production/development: PostgreSQL is default
    // In testing: SQLite is used for speed (configured in phpunit.xml)
    
    if (app()->environment('testing')) {
        expect(config('database.connections.sqlite'))->toBeArray();
        expect(config('database.connections.pgsql'))->toBeArray();
    } else {
        expect(config('database.default'))->toBe('pgsql');
    }
});

test('pest framework is properly integrated', function () {
    expect(true)->toBeTrue();
    expect('Hello World')->toContain('World');
    expect([1, 2, 3])->toHaveCount(3);
    expect('pest')->toBeString();
});

test('pest expectations are working', function () {
    expect(collect([1, 2, 3]))
        ->toHaveCount(3)
        ->and(collect(['a', 'b', 'c']))
        ->toContain('b');
});

test('pest can test laravel features', function () {
    $response = $this->get('/');
    
    expect($response->status())->toBe(200);
});