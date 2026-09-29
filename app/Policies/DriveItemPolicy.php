<?php

namespace App\Policies;

use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Drive items are private to their team. Denials respond with 404 so
 * members of another team cannot learn whether an item UUID exists.
 */
class DriveItemPolicy
{
    /**
     * Determine whether the user can open the item.
     */
    public function view(User $user, DriveItem $driveItem): Response
    {
        return $this->owns($user, $driveItem);
    }

    /**
     * Determine whether the user can download the item.
     */
    public function download(User $user, DriveItem $driveItem): Response
    {
        return $this->owns($user, $driveItem);
    }

    /**
     * Determine whether the user can rename or move the item.
     */
    public function update(User $user, DriveItem $driveItem): Response
    {
        return $this->owns($user, $driveItem);
    }

    /**
     * Determine whether the user can move the item to the trash.
     */
    public function delete(User $user, DriveItem $driveItem): Response
    {
        return $this->owns($user, $driveItem);
    }

    /**
     * Determine whether the user can restore the item from the trash.
     */
    public function restore(User $user, DriveItem $driveItem): Response
    {
        return $this->owns($user, $driveItem);
    }

    /**
     * Determine whether the user can permanently delete the item.
     */
    public function forceDelete(User $user, DriveItem $driveItem): Response
    {
        return $this->owns($user, $driveItem);
    }

    protected function owns(User $user, DriveItem $driveItem): Response
    {
        $team = $driveItem->team;

        return $team !== null
            && $user->current_team_id === $team->id
            && $user->belongsToTeam($team)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
