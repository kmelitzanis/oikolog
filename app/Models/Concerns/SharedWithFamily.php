<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one sharing rule for household records: a row belongs to whoever
 * created it, and a shared row is also visible to the creator's family.
 *
 * Both halves of the family check insist on an actual family. Comparing
 * `family_id` values alone matched NULL against NULL — Eloquent turns
 * `where('family_id', null)` into `IS NULL` — so a row marked shared by
 * someone without a family was visible to every other user without one.
 */
trait SharedWithFamily
{
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where($this->qualifyColumn('created_by'), $user->getKey());

            if ($user->family_id) {
                $q->orWhere(function (Builder $shared) use ($user) {
                    $shared->where($this->qualifyColumn('is_shared'), true)
                        ->where($this->qualifyColumn('family_id'), $user->family_id);
                });
            }
        });
    }

    /** The same rule as `forUser()`, for a model already in hand. */
    public function isVisibleTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ((string) $this->created_by === (string) $user->getKey()) {
            return true;
        }

        return $this->is_shared
            && $user->family_id !== null
            && (string) $this->family_id === (string) $user->family_id;
    }
}
