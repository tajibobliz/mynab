<?php

namespace App\Policies;

use App\Models\Beneficiario;
use App\Models\User;

class BeneficiarioPolicy
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
     *
     * El beneficiario no tiene user_id propio: la ownership se resuelve vía
     * el presupuesto al que pertenece. `$beneficiario->presupuesto` dispara
     * una query lazy si no viene precargada — aceptable para un check de
     * autorización puntual (no en un listado N+1).
     */
    public function view(User $user, Beneficiario $beneficiario): bool
    {
        return $user->id === $beneficiario->presupuesto->user_id;
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
    public function update(User $user, Beneficiario $beneficiario): bool
    {
        return $user->id === $beneficiario->presupuesto->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Beneficiario $beneficiario): bool
    {
        return $user->id === $beneficiario->presupuesto->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Beneficiario $beneficiario): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Beneficiario $beneficiario): bool
    {
        return false;
    }
}
