<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Play, Sparkles } from 'lucide-vue-next';
import DashboardMockup from '@/Components/Landing/DashboardMockup.vue';

defineProps({
    canRegister: Boolean,
});

const emit = defineEmits(['navigate']);

const stats = [
    { valor: '100%', etiqueta: 'Local y privado' },
    { valor: '3', etiqueta: 'Monedas soportadas' },
    { valor: '∞', etiqueta: 'Presupuestos personales' },
];
</script>

<template>
    <section class="relative min-h-screen flex items-center overflow-hidden bg-base pt-16">
        <!-- Decoración de fondo -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute top-1/4 right-0 size-[32rem] rounded-full bg-status-success/10 blur-3xl" />
            <div
                class="absolute inset-0 opacity-5"
                style="background-image: linear-gradient(to right, #fafafa 1px, transparent 1px), linear-gradient(to bottom, #fafafa 1px, transparent 1px); background-size: 48px 48px;"
            />
        </div>

        <div class="relative max-w-7xl mx-auto px-6 md:px-8 py-24 md:py-32 grid lg:grid-cols-2 gap-16 items-center">
            <!-- Izquierda: contenido -->
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-status-success/15 text-status-success text-xs font-semibold mb-6">
                    <Sparkles class="size-3.5" />
                    Zero-Based Budgeting
                </span>

                <h1 class="text-5xl md:text-7xl font-bold tracking-tight text-text leading-[1.05] mb-6">
                    Toma el <span class="text-status-success">control</span> de tu dinero.
                </h1>

                <p class="text-xl text-text-secondary max-w-xl mb-10">
                    El método del sobre, digitalizado. Asigna cada peso a un propósito antes de gastarlo, y nunca te preguntes a dónde se fue tu dinero.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 mb-12">
                    <Link
                        :href="canRegister ? route('register') : route('login')"
                        class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-lg bg-status-success text-gray-950 font-semibold hover:bg-status-success/90 active:scale-[0.98] transition-all duration-200"
                    >
                        Comenzar gratis
                        <ArrowRight class="size-4" />
                    </Link>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-lg border border-border text-text hover:border-text-secondary transition-all duration-200"
                        @click="emit('navigate', 'como-funciona')"
                    >
                        <Play class="size-4" />
                        Ver cómo funciona
                    </button>
                </div>

                <div class="flex flex-wrap gap-8">
                    <div v-for="stat in stats" :key="stat.etiqueta">
                        <p class="text-3xl font-mono font-bold text-text">{{ stat.valor }}</p>
                        <p class="text-sm text-text-secondary">{{ stat.etiqueta }}</p>
                    </div>
                </div>
            </div>

            <!-- Derecha: mockup -->
            <div class="relative hidden lg:block">
                <DashboardMockup />
            </div>
        </div>
    </section>
</template>
