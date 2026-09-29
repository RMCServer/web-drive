<?php

use App\Enums\DriveItemType;
use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('drive.disk'));
});

test('users can upload a file to the root of their drive', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('drive.files.store'), [
            'file' => UploadedFile::fake()->create('Holiday Photo.png', 120, 'image/png'),
        ])
        ->assertSessionHasNoErrors();

    $file = DriveItem::sole();

    expect($file)
        ->user_id->toBe($user->id)
        ->parent_id->toBeNull()
        ->type->toBe(DriveItemType::File)
        ->name->toBe('Holiday Photo.png')
        ->mime_type->toBe('image/png')
        ->extension->toBe('png')
        ->size->toBe(120 * 1024)
        ->disk->toBe(config('drive.disk'))
        ->storage_path->toBe("drive/{$file->uuid}/original");

    Storage::disk(config('drive.disk'))->assertExists($file->storage_path);
});

test('users can upload a file into one of their folders', function () {
    $folder = DriveItem::factory()->create();

    $this->actingAs($folder->owner)
        ->post(route('drive.files.store'), [
            'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            'parent' => $folder->uuid,
        ])
        ->assertSessionHasNoErrors();

    expect(DriveItem::files()->sole()->parent_id)->toBe($folder->id);
});

test('uploading a duplicate filename keeps both files under unique names', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)->post(route('drive.files.store'), [
            'file' => UploadedFile::fake()->create('Holiday Photo.png', 10, 'image/png'),
        ]);
    }

    $files = DriveItem::files()->orderBy('id')->get();

    expect($files->pluck('name')->all())->toBe(['Holiday Photo.png', 'Holiday Photo (1).png', 'Holiday Photo (2).png'])
        ->and($files->pluck('storage_path')->unique())->toHaveCount(3);

    $files->each(fn (DriveItem $file) => Storage::disk(config('drive.disk'))->assertExists($file->storage_path));
});

test('uploads larger than the configured limit are rejected', function () {
    config(['drive.max_upload_size' => 10]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('drive.files.store'), ['file' => UploadedFile::fake()->create('big.zip', 11)])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('drive_items', 0);
});

test('users cannot upload into another users folder', function () {
    $foreignFolder = DriveItem::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('drive.files.store'), [
            'file' => UploadedFile::fake()->create('notes.txt', 1),
            'parent' => $foreignFolder->uuid,
        ])
        ->assertSessionHasErrors('parent');

    $this->assertDatabaseCount('drive_items', 1);
    expect(Storage::disk(config('drive.disk'))->allFiles())->toBeEmpty();
});

test('users can download their file under its visible name', function () {
    $file = DriveItem::factory()->file()->create(['name' => 'Holiday Photo.png', 'mime_type' => 'image/png']);
    Storage::disk(config('drive.disk'))->put($file->storage_path, 'image-bytes');

    $response = $this->actingAs($file->owner)
        ->get(route('drive.items.download', $file))
        ->assertOk()
        ->assertDownload('Holiday Photo.png');

    expect($response->streamedContent())->toBe('image-bytes');
});

test('users cannot download another users file', function () {
    $file = DriveItem::factory()->file()->create();
    Storage::disk(config('drive.disk'))->put($file->storage_path, 'secret');

    $this->actingAs(User::factory()->create())
        ->get(route('drive.items.download', $file))
        ->assertNotFound();
});

test('trashed files cannot be downloaded', function () {
    $file = DriveItem::factory()->file()->trashed()->create();
    Storage::disk(config('drive.disk'))->put($file->storage_path, 'content');

    $this->actingAs($file->owner)
        ->get(route('drive.items.download', $file))
        ->assertNotFound();
});
