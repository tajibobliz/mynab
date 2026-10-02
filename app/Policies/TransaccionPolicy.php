<?php

namespace App\Policies;

use App\Models\Transaccion;
use App\Models\User;

class TransaccionPolicy
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
     * La transacción no tiene user_id propio: la ownership se resuelve vía
     * cuenta->presupuesto (2 saltos). El controller debe eager-cargar
     * `cuenta.presupuesto` antes de llamar authorize() para evitar 2 queries
     * lazy por cada check (mismo patrón que CategoriaPolicy en Change 5).
     */
    public function view(User $user, Transaccion $transaccion): bool
    {
        return $user->id === $transaccion->cuenta->presupuesto->user_id;
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
    public function update(User $user, Transaccion $transaccion): bool
    {
        return $user->id === $transaccion->cuenta->presupuesto->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Transaccion $transaccion): bool
    {
        return $user->id === $transaccion->cuenta->presupuesto->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Transaccion $transaccion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Transaccion $transaccion): bool
    {
        return false;
    }
}
