<?php

use App\Models\DriveItem;
use App\Models\User;

describe('rename', function () {
    test('users can rename an item', function () {
        $file = DriveItem::factory()->file()->create(['name' => 'draft.txt']);
        $originalPath = $file->storage_path;

        $this->actingAs($file->owner)
            ->patch(route('drive.items.rename', $file), ['name' => 'final.txt'])
            ->assertSessionHasNoErrors();

        expect($file->fresh())
            ->name->toBe('final.txt')
            ->storage_path->toBe($originalPath);
    });

    test('renaming to the name of a sibling is rejected', function () {
        $folder = DriveItem::factory()->create();
        DriveItem::factory()->file()->in($folder)->create(['name' => 'taken.txt']);
        $file = DriveItem::factory()->file()->in($folder)->create(['name' => 'free.txt']);

        $this->actingAs($folder->owner)
            ->patch(route('drive.items.rename', $file), ['name' => 'taken.txt'])
            ->assertSessionHasErrors(['name' => 'An item named "taken.txt" already exists in this folder.']);

        expect($file->fresh()->name)->toBe('free.txt');
    });

    test('users cannot rename another users item', function () {
        $item = DriveItem::factory()->create(['name' => 'Original']);

        $this->actingAs(User::factory()->create())
            ->patch(route('drive.items.rename', $item), ['name' => 'Hijacked'])
            ->assertNotFound();

        expect($item->fresh()->name)->toBe('Original');
    });
});

describe('move', function () {
    test('users can move an item into a folder and back to the root', function () {
        $file = DriveItem::factory()->file()->create();
        $folder = DriveItem::factory()->for($file->owner, 'owner')->create();

        $this->actingAs($file->owner)
            ->patch(route('drive.items.move', $file), ['destination' => $folder->uuid])
            ->assertSessionHasNoErrors();

        expect($file->fresh()->parent_id)->toBe($folder->id);

        $this->actingAs($file->owner)
            ->patch(route('drive.items.move', $file), ['destination' => null])
            ->assertSessionHasNoErrors();

        expect($file->fresh()->parent_id)->toBeNull();
    });

    test('moving into a folder with a same-named item gives the moved item a unique name', function () {
        $folder = DriveItem::factory()->create();
        DriveItem::factory()->file()->in($folder)->create(['name' => 'report.pdf']);
        $file = DriveItem::factory()->file()->for($folder->owner, 'owner')->create(['name' => 'report.pdf']);

        $this->actingAs($folder->owner)
            ->patch(route('drive.items.move', $file), ['destination' => $folder->uuid])
            ->assertSessionHasNoErrors();

        expect($file->fresh()->name)->toBe('report (1).pdf');
    });

    test('a folder cannot be moved into itself', function () {
        $folder = DriveItem::factory()->create();

        $this->actingAs($folder->owner)
            ->patch(route('drive.items.move', $folder), ['destination' => $folder->uuid])
            ->assertSessionHasErrors(['destination' => 'A folder cannot be moved into itself or one of its subfolders.']);

        expect($folder->fresh()->parent_id)->toBeNull();
    });

    test('a folder cannot be moved into one of its descendants', function () {
        $projects = DriveItem::factory()->create();
        $minecraft = DriveItem::factory()->in($projects)->create();
        $realityMc = DriveItem::factory()->in($minecraft)->create();

        $this->actingAs($projects->owner)
            ->patch(route('drive.items.move', $projects), ['destination' => $realityMc->uuid])
            ->assertSessionHasErrors('destination');

        expect($projects->fresh()->parent_id)->toBeNull();
    });

    test('items cannot be moved into a file', function () {
        $file = DriveItem::factory()->file()->create();
        $folder = DriveItem::factory()->for($file->owner, 'owner')->create();

        $this->actingAs($file->owner)
            ->patch(route('drive.items.move', $folder), ['destination' => $file->uuid])
            ->assertSessionHasErrors('destination');
    });

    test('users cannot move items into another users folder', function () {
        $file = DriveItem::factory()->file()->create();
        $foreignFolder = DriveItem::factory()->create();

        $this->actingAs($file->owner)
            ->patch(route('drive.items.move', $file), ['destination' => $foreignFolder->uuid])
            ->assertSessionHasErrors('destination');

        expect($file->fresh()->parent_id)->toBeNull();
    });

    test('users cannot move another users item', function () {
        $item = DriveItem::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('drive.items.move', $item), ['destination' => null])
            ->assertNotFound();
    });
});
