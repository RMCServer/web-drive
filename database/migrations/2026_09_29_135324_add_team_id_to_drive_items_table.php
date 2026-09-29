<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('drive_items', function (Blueprint $table) {
            $table->foreignId('team_id')
                ->nullable()
                ->after('user_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->index(['team_id', 'parent_id', 'deleted_at']);
            $table->index(['team_id', 'explicitly_trashed', 'deleted_at']);
        });

        DB::table('drive_items')
            ->orderBy('id')
            ->chunkById(500, function (Collection $items): void {
                $teamIds = DB::table('users')
                    ->whereIn('id', $items->pluck('user_id'))
                    ->pluck('current_team_id', 'id');

                foreach ($items as $item) {
                    $teamId = $teamIds->get($item->user_id);

                    if ($teamId !== null) {
                        DB::table('drive_items')
                            ->where('id', $item->id)
                            ->update(['team_id' => $teamId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drive_items', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'parent_id', 'deleted_at']);
            $table->dropIndex(['team_id', 'explicitly_trashed', 'deleted_at']);
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
