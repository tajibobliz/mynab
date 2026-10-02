<?php

namespace App\Http\Controllers;

use App\Services\PresupuestoMensualService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $activo = auth()->user()->presupuestoActivo;

        if (! $activo) {
            return Inertia::render('Dashboard', [
                'tienePresupuestoActivo' => false,
            ]);
        }

        $año = now()->year;
        $mes = now()->month;

        $service = app(PresupuestoMensualService::class);
        $rta = $service->calcularReadyToAssign($activo, $año, $mes);
        $vista = $service->obtenerVistaMensual($activo, $año, $mes);

        // flatMap() sobre 'categorias' por sí solo descarta el color del
        // grupo padre (solo quedaría el array de categorías, sin contexto
        // del grupo que las contiene) — la UI del Dashboard (Grupo 9B)
        // necesita ese color para cada card de "sobre atento", así que se
        // inyecta aquí antes de aplanar.
        $sobresAtentos = collect($vista['grupos'])
            ->flatMap(fn (array $grupo) => array_map(
                fn (array $categoria) => $categoria + ['grupoColor' => $grupo['color']],
                $grupo['categorias'],
            ))
            ->sortBy('available')
            ->take(5)
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'tienePresupuestoActivo' => true,
            'readyToAssign' => $rta,
            'sobresAtentos' => $sobresAtentos,
            // $vista['mesAño'] en vez de reconstruirlo a mano: ya trae
            // 'nombreMes' calculado (obtenerVistaMensual() lo resuelve con
            // Carbon), evita duplicar un array de 12 nombres de mes en el
            // frontend solo para este sub-texto.
            'mesAño' => $vista['mesAño'],
        ]);
    }
}
