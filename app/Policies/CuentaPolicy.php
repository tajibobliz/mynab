<?php

namespace App\Policies;

use App\Models\Cuenta;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CuentaPolicy
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
     * La cuenta no tiene user_id propio: la ownership se resuelve vía el
     * presupuesto al que pertenece. `$cuenta->presupuesto` dispara una query
     * lazy si no viene precargada — aceptable para un check de autorización
     * puntual (no en un listado N+1).
     */
    public function view(User $user, Cuenta $cuenta): bool
    {
        return $user->id === $cuenta->presupuesto->user_id;
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
    public function update(User $user, Cuenta $cuenta): bool
    {
        return $user->id === $cuenta->presupuesto->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cuenta $cuenta): bool
    {
        return $user->id === $cuenta->presupuesto->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Cuenta $cuenta): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Cuenta $cuenta): bool
    {
        return false;
    }
}
