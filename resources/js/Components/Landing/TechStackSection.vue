<script setup>
import { Database, FileCode, Palette, TestTube2, Zap } from 'lucide-vue-next';

const stack = [
    { icon: FileCode, texto: 'Laravel 12 + PHP 8.4' },
    { icon: Zap, texto: 'Vue 3 Composition API + Inertia.js' },
    { icon: Database, texto: 'PostgreSQL 17' },
    { icon: Palette, texto: 'Tailwind CSS + Lucide icons' },
    { icon: TestTube2, texto: 'Pest (testing)' },
];

const metricas = [
    { valor: '213', etiqueta: 'Tests automatizados' },
    { valor: '10', etiqueta: 'Specs OpenSpec' },
    { valor: '8', etiqueta: 'Changes documentados' },
    { valor: '100%', etiqueta: 'Cobertura de flujos críticos' },
];
</script>

<template>
    <section id="tecnologia" class="bg-base py-24 md:py-32 scroll-mt-16">
        <div class="max-w-6xl mx-auto px-6 md:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-text">Construido con tecnologías modernas</h2>
            </div>

            <div class="grid md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-6">Stack técnico</h3>
                    <ul class="space-y-4">
                        <li v-for="item in stack" :key="item.texto" class="flex items-center gap-3">
                            <span class="flex items-center justify-center size-9 rounded-lg bg-surface border border-border/70 shrink-0">
                                <component :is="item.icon" class="size-4 text-accent-secondary" />
                            </span>
                            <span class="text-text">{{ item.texto }}</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-6">Métricas del proyecto</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div v-for="metrica in metricas" :key="metrica.etiqueta" class="rounded-xl border border-border/70 bg-surface p-5">
                            <p class="text-3xl font-mono font-bold text-status-success">{{ metrica.valor }}</p>
                            <p class="text-sm text-text-secondary mt-1">{{ metrica.etiqueta }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-sm text-text-secondary text-center mt-16 max-w-2xl mx-auto">
                Desarrollado con Spec-Driven Development usando OpenSpec. Cada feature comenzó con una especificación antes del código.
            </p>
        </div>
    </section>
</template>
