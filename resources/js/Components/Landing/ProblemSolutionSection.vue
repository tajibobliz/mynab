<script setup>
import { Check, X } from 'lucide-vue-next';

const problemas = [
    'Reportes del pasado',
    'Te dicen qué gastaste',
    'Sin plan hacia adelante',
];

const soluciones = [
    'Plan hacia adelante',
    'Decides antes de gastar',
    'Cero sorpresas a fin de mes',
];
</script>

<template>
    <section class="bg-surface py-24 md:py-32">
        <div class="max-w-5xl mx-auto px-6 md:px-8 text-center">
            <h2 class="text-4xl font-bold text-text mb-6">
                ¿Te preguntas a dónde se fue tu sueldo?
            </h2>

            <p class="text-lg text-text-secondary max-w-2xl mx-auto mb-16">
                La mayoría de personas registran gastos pero no planifican. MyNAB invierte el flujo: cada peso tiene un trabajo asignado antes de que lo gastes.
            </p>

            <div class="grid md:grid-cols-2 gap-6 text-left">
                <div class="rounded-xl border border-border bg-base/40 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="flex items-center justify-center size-9 rounded-lg bg-status-danger/15">
                            <X class="size-5 text-status-danger" />
                        </span>
                        <h3 class="text-lg font-semibold text-text">Apps tradicionales</h3>
                    </div>
                    <ul class="space-y-3">
                        <li v-for="item in problemas" :key="item" class="flex items-center gap-2 text-text-secondary">
                            <X class="size-4 text-status-danger shrink-0" />
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-status-success/30 bg-status-success/5 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="flex items-center justify-center size-9 rounded-lg bg-status-success/15">
                            <Check class="size-5 text-status-success" />
                        </span>
                        <h3 class="text-lg font-semibold text-text">MyNAB</h3>
                    </div>
                    <ul class="space-y-3">
                        <li v-for="item in soluciones" :key="item" class="flex items-center gap-2 text-text">
                            <Check class="size-4 text-status-success shrink-0" />
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</template>
