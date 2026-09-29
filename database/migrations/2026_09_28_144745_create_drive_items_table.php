<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('drive_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('drive_items')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('disk', 64)->nullable();
            $table->string('storage_path')->nullable();
            $table->boolean('explicitly_trashed')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'parent_id', 'deleted_at']);
            $table->index(['parent_id', 'name']);
            $table->index(['user_id', 'explicitly_trashed', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drive_items');
    }
};
