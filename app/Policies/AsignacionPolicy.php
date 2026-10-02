<?php

namespace App\Policies;

use App\Models\Asignacion;
use App\Models\User;

class AsignacionPolicy
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
     * La asignación no tiene user_id propio: la ownership se resuelve vía
     * el presupuesto al que pertenece, mismo patrón que Beneficiario/Cuenta.
     */
    public function view(User $user, Asignacion $asignacion): bool
    {
        return $user->id === $asignacion->presupuesto->user_id;
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
    public function update(User $user, Asignacion $asignacion): bool
    {
        return $user->id === $asignacion->presupuesto->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Asignacion $asignacion): bool
    {
        return $user->id === $asignacion->presupuesto->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * Sin soft delete en Asignacion (design.md Decisión 1): nunca aplica.
     */
    public function restore(User $user, Asignacion $asignacion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Asignacion $asignacion): bool
    {
        return false;
    }
}
