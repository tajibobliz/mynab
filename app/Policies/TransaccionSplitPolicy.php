<?php

namespace App\Policies;

use App\Models\TransaccionSplit;
use App\Models\User;

/**
 * No hay rutas propias para splits en este change (gestion-transacciones):
 * se crean/editan/eliminan únicamente como parte del store/update de
 * Transaccion, nunca vía un endpoint independiente. Esta policy se crea por
 * completitud y como anticipación para Change 8 (zero-based-budgeting-basico),
 * que podría necesitar autorizar operaciones directas sobre splits.
 */
class TransaccionSplitPolicy
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
     * 3 saltos: split->transaccion->cuenta->presupuesto->user_id. Si algún
     * día se usa desde un controller, cargar esas 3 relaciones antes de
     * authorize() para evitar N+1 (mismo patrón que TransaccionPolicy).
     */
    public function view(User $user, TransaccionSplit $split): bool
    {
        return $user->id === $split->transaccion->cuenta->presupuesto->user_id;
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
    public function update(User $user, TransaccionSplit $split): bool
    {
        return $user->id === $split->transaccion->cuenta->presupuesto->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TransaccionSplit $split): bool
    {
        return $user->id === $split->transaccion->cuenta->presupuesto->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TransaccionSplit $split): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TransaccionSplit $split): bool
    {
        return false;
    }
}
