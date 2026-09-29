<?php

namespace Database\Factories;

use App\Enums\DriveItemType;
use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DriveItem>
 */
class DriveItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'type' => DriveItemType::Folder,
            'name' => Str::title(fake()->unique()->word()),
        ];
    }

    /**
     * Indicate that the item is a stored file.
     */
    public function file(): static
    {
        return $this->state(function (array $attributes) {
            $uuid = (string) Str::uuid7();

            return [
                'uuid' => $uuid,
                'type' => DriveItemType::File,
                'name' => fake()->unique()->word().'.txt',
                'mime_type' => 'text/plain',
                'extension' => 'txt',
                'size' => 12,
                'disk' => config('drive.disk'),
                'storage_path' => "drive/{$uuid}/original",
            ];
        });
    }

    /**
     * Place the item inside the given folder, owned by the folder's owner.
     */
    public function in(DriveItem $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $folder->user_id,
            'parent_id' => $folder->id,
        ]);
    }

    /**
     * Indicate that the user sent the item to the trash.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
            'explicitly_trashed' => true,
        ]);
    }
}
