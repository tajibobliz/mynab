<?php

namespace App\Policies;

use App\Models\TipoCambio;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TipoCambioPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TipoCambio $tipoCambio): bool
    {
        return $user->id === $tipoCambio->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TipoCambio $tipoCambio): bool
    {
        return $user->id === $tipoCambio->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TipoCambio $tipoCambio): bool
    {
        return $user->id === $tipoCambio->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TipoCambio $tipoCambio): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TipoCambio $tipoCambio): bool
    {
        return false;
    }
}
