<?php

use App\Enums\DriveItemType;
use App\Models\DriveItem;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('drive.index'))->assertRedirect(route('login'));
});

test('users can create a folder at the root of their drive', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('drive.folders.store'), ['name' => 'Projects'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('drive_items', [
        'user_id' => $user->id,
        'parent_id' => null,
        'type' => DriveItemType::Folder->value,
        'name' => 'Projects',
        'storage_path' => null,
    ]);
});

test('users can create nested folders', function () {
    $projects = DriveItem::factory()->create(['name' => 'Projects']);

    $this->actingAs($projects->owner)
        ->post(route('drive.folders.store'), ['name' => 'Minecraft', 'parent' => $projects->uuid])
        ->assertSessionHasNoErrors();

    $minecraft = DriveItem::where('name', 'Minecraft')->sole();

    $this->actingAs($projects->owner)
        ->post(route('drive.folders.store'), ['name' => 'RealityMC', 'parent' => $minecraft->uuid])
        ->assertSessionHasNoErrors();

    expect($minecraft->parent_id)->toBe($projects->id)
        ->and(DriveItem::where('name', 'RealityMC')->sole()->parent_id)->toBe($minecraft->id);
});

test('duplicate folder names in the same folder receive a numeric suffix', function () {
    $existing = DriveItem::factory()->create(['name' => 'Projects']);

    $this->actingAs($existing->owner)
        ->post(route('drive.folders.store'), ['name' => 'Projects'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('drive_items', ['user_id' => $existing->user_id, 'name' => 'Projects (1)']);
});

test('folder names containing slashes are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('drive.folders.store'), ['name' => 'a/b'])
        ->assertSessionHasErrors(['name' => 'The name may not contain slashes or control characters.']);

    $this->assertDatabaseCount('drive_items', 0);
});

test('users cannot create folders inside another users folder', function () {
    $foreignFolder = DriveItem::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('drive.folders.store'), ['name' => 'Intruder', 'parent' => $foreignFolder->uuid])
        ->assertSessionHasErrors('parent');

    $this->assertDatabaseMissing('drive_items', ['name' => 'Intruder']);
});

test('users cannot use a file as a parent folder', function () {
    $file = DriveItem::factory()->file()->create();

    $this->actingAs($file->owner)
        ->post(route('drive.folders.store'), ['name' => 'Inside a file', 'parent' => $file->uuid])
        ->assertSessionHasErrors('parent');

    $this->assertDatabaseMissing('drive_items', ['name' => 'Inside a file']);
});

test('my drive lists only the users own root items', function () {
    $user = User::factory()->create();
    $rootFolder = DriveItem::factory()->for($user, 'owner')->create(['name' => 'Root Folder']);
    DriveItem::factory()->in($rootFolder)->create(['name' => 'Nested Folder']);
    DriveItem::factory()->create(['name' => 'Someone Elses Folder']);

    $response = $this->actingAs($user)
        ->get(route('drive.index'))
        ->assertOk()
        ->assertSee('Root Folder');

    expect($response->viewData('items')->pluck('name')->all())->toBe(['Root Folder']);
});

test('opening a folder shows its direct children and breadcrumbs', function () {
    $projects = DriveItem::factory()->create(['name' => 'Projects']);
    $minecraft = DriveItem::factory()->in($projects)->create(['name' => 'Minecraft']);
    $realityMc = DriveItem::factory()->in($minecraft)->create(['name' => 'RealityMC']);
    DriveItem::factory()->in($realityMc)->create(['name' => 'Deeply Nested']);
    DriveItem::factory()->file()->in($realityMc)->create(['name' => 'server.properties']);

    $response = $this->actingAs($projects->owner)
        ->get(route('drive.folders.show', $realityMc))
        ->assertOk()
        ->assertViewHas('folder', fn (DriveItem $folder) => $folder->is($realityMc))
        ->assertViewHas('breadcrumbs', fn ($breadcrumbs) => $breadcrumbs->pluck('name')->all() === ['Projects', 'Minecraft']);

    expect($response->viewData('items')->pluck('name')->all())->toBe(['Deeply Nested', 'server.properties']);
});

test('users cannot open another users folder', function () {
    $foreignFolder = DriveItem::factory()->create(['name' => 'Private Folder']);

    $this->actingAs(User::factory()->create())
        ->get(route('drive.folders.show', $foreignFolder))
        ->assertNotFound();
});

test('opening a file as a folder returns 404', function () {
    $file = DriveItem::factory()->file()->create();

    $this->actingAs($file->owner)
        ->get(route('drive.folders.show', $file))
        ->assertNotFound();
});
