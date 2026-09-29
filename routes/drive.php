<?php

use App\Http\Controllers\Drive\DriveItemController;
use App\Http\Controllers\Drive\FileController;
use App\Http\Controllers\Drive\FolderController;
use App\Http\Controllers\Drive\TrashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('drive')->name('drive.')->group(function () {
    Route::get('/', [FolderController::class, 'index'])->name('index');
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::get('folders/{folder:uuid}', [FolderController::class, 'show'])
        ->can('view', 'folder')
        ->name('folders.show');

    Route::post('files', [FileController::class, 'store'])->name('files.store');

    Route::get('items/{driveItem:uuid}/download', [FileController::class, 'download'])
        ->can('download', 'driveItem')
        ->name('items.download');
    Route::get('items/{driveItem:uuid}/preview', [FileController::class, 'preview'])
        ->can('view', 'driveItem')
        ->name('items.preview');
    Route::get('items/{driveItem:uuid}/thumbnail', [FileController::class, 'thumbnail'])
        ->can('view', 'driveItem')
        ->name('items.thumbnail');
    Route::patch('items/{driveItem:uuid}/name', [DriveItemController::class, 'rename'])
        ->can('update', 'driveItem')
        ->name('items.rename');
    Route::patch('items/{driveItem:uuid}/parent', [DriveItemController::class, 'move'])
        ->can('update', 'driveItem')
        ->name('items.move');
    Route::delete('items/{driveItem:uuid}', [DriveItemController::class, 'destroy'])
        ->can('delete', 'driveItem')
        ->name('items.destroy');

    Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('trash/{driveItem:uuid}/restore', [TrashController::class, 'restore'])
        ->withTrashed()
        ->can('restore', 'driveItem')
        ->name('trash.restore');
    Route::delete('trash/{driveItem:uuid}', [TrashController::class, 'destroy'])
        ->withTrashed()
        ->can('forceDelete', 'driveItem')
        ->name('trash.destroy');
});
