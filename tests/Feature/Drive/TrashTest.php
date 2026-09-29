<?php

use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('drive.disk'));
});

test('deleting an item moves it to the trash without removing its stored file', function () {
    $file = DriveItem::factory()->file()->create();
    Storage::disk(config('drive.disk'))->put($file->storage_path, 'content');

    $this->actingAs($file->owner)
        ->delete(route('drive.items.destroy', $file))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($file);
    Storage::disk(config('drive.disk'))->assertExists($file->storage_path);

    $this->actingAs($file->owner)
        ->get(route('drive.trash.index'))
        ->assertOk()
        ->assertSee($file->name);
});

test('trashing a folder trashes its descendants and hides them from the drive', function () {
    $projects = DriveItem::factory()->create(['name' => 'Projects']);
    $minecraft = DriveItem::factory()->in($projects)->create(['name' => 'Minecraft']);
    $file = DriveItem::factory()->file()->in($minecraft)->create(['name' => 'world.zip']);

    $this->actingAs($projects->owner)->delete(route('drive.items.destroy', $projects));

    $this->assertSoftDeleted($projects);
    $this->assertSoftDeleted($minecraft);
    $this->assertSoftDeleted($file);

    $this->actingAs($projects->owner)->get(route('drive.folders.show', $minecraft))->assertNotFound();

    $trash = $this->actingAs($projects->owner)->get(route('drive.trash.index'))->viewData('items');

    expect($trash->pluck('name')->all())->toBe(['Projects']);
});

test('users cannot trash another users item', function () {
    $item = DriveItem::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('drive.items.destroy', $item))
        ->assertNotFound();

    $this->assertNotSoftDeleted($item);
});

test('the trash lists only the users own items', function () {
    $user = User::factory()->create();
    DriveItem::factory()->trashed()->create(['name' => 'Someone Elses Trash']);

    $this->actingAs($user)
        ->get(route('drive.trash.index'))
        ->assertOk()
        ->assertDontSee('Someone Elses Trash');
});

test('restoring a folder restores the descendants that were trashed with it', function () {
    $projects = DriveItem::factory()->create();
    $minecraft = DriveItem::factory()->in($projects)->create();
    $separatelyTrashed = DriveItem::factory()->file()->in($minecraft)->create();

    $this->actingAs($projects->owner)->delete(route('drive.items.destroy', $separatelyTrashed));
    $this->actingAs($projects->owner)->delete(route('drive.items.destroy', $projects));

    $this->actingAs($projects->owner)
        ->post(route('drive.trash.restore', $projects))
        ->assertSessionHasNoErrors();

    $this->assertNotSoftDeleted($projects);
    $this->assertNotSoftDeleted($minecraft);
    $this->assertSoftDeleted($separatelyTrashed);
    expect($projects->fresh()->parent_id)->toBeNull();
});

test('restoring an item whose folder is still in the trash restores it to the root', function () {
    $folder = DriveItem::factory()->create();
    $file = DriveItem::factory()->file()->in($folder)->create();

    $this->actingAs($folder->owner)->delete(route('drive.items.destroy', $file));
    $this->actingAs($folder->owner)->delete(route('drive.items.destroy', $folder));

    $this->actingAs($folder->owner)
        ->post(route('drive.trash.restore', $file))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'restored to My Drive'));

    expect($file->fresh())
        ->trashed()->toBeFalse()
        ->parent_id->toBeNull();
});

test('restoring next to a same-named item gives the restored item a unique name', function () {
    $trashed = DriveItem::factory()->trashed()->create(['name' => 'Projects']);
    DriveItem::factory()->for($trashed->owner, 'owner')->create(['name' => 'Projects']);

    $this->actingAs($trashed->owner)->post(route('drive.trash.restore', $trashed));

    expect($trashed->fresh()->name)->toBe('Projects (1)');
});

test('users cannot restore another users item', function () {
    $item = DriveItem::factory()->trashed()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('drive.trash.restore', $item))
        ->assertNotFound();

    $this->assertSoftDeleted($item);
});

test('permanently deleting a folder removes all records and stored files', function () {
    $folder = DriveItem::factory()->create();
    $subfolder = DriveItem::factory()->in($folder)->create();
    $file = DriveItem::factory()->file()->in($subfolder)->create();
    $otherFile = DriveItem::factory()->file()->for($folder->owner, 'owner')->create();
    Storage::disk(config('drive.disk'))->put($file->storage_path, 'content');
    Storage::disk(config('drive.disk'))->put($otherFile->storage_path, 'keep me');

    $this->actingAs($folder->owner)->delete(route('drive.items.destroy', $folder));

    $this->actingAs($folder->owner)
        ->delete(route('drive.trash.destroy', $folder))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($folder);
    $this->assertModelMissing($subfolder);
    $this->assertModelMissing($file);
    Storage::disk(config('drive.disk'))->assertMissing($file->storage_path);
    Storage::disk(config('drive.disk'))->assertExists($otherFile->storage_path);
});

test('items that are not in the trash cannot be deleted permanently', function () {
    $file = DriveItem::factory()->file()->create();

    $this->actingAs($file->owner)
        ->delete(route('drive.trash.destroy', $file))
        ->assertSessionHasErrors('item');

    $this->assertModelExists($file);
});

test('users cannot permanently delete another users item', function () {
    $file = DriveItem::factory()->file()->trashed()->create();
    Storage::disk(config('drive.disk'))->put($file->storage_path, 'content');

    $this->actingAs(User::factory()->create())
        ->delete(route('drive.trash.destroy', $file))
        ->assertNotFound();

    $this->assertModelExists($file);
    Storage::disk(config('drive.disk'))->assertExists($file->storage_path);
});
