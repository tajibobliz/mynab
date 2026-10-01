<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoriaPolicy
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
     * Dos saltos de relación (categoria -> grupoCategoria -> presupuesto).
     * Sin eager loading previo esto dispara 2 queries lazy adicionales por
     * request — aceptable para un check puntual de autorización, pero el
     * controller (Grupo 6) debe cargar `grupoCategoria.presupuesto` antes de
     * llamar authorize() si ya va a necesitar esas relaciones de todos modos.
     */
    public function view(User $user, Categoria $categoria): bool
    {
        return $user->id === $categoria->grupoCategoria->presupuesto->user_id;
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
    public function update(User $user, Categoria $categoria): bool
    {
        return $user->id === $categoria->grupoCategoria->presupuesto->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Categoria $categoria): bool
    {
        return $user->id === $categoria->grupoCategoria->presupuesto->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Categoria $categoria): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Categoria $categoria): bool
    {
        return false;
    }
}
