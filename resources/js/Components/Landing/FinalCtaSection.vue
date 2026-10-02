<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

defineProps({
    canRegister: Boolean,
});
</script>

<template>
    <section class="relative bg-surface min-h-[400px] flex items-center justify-center overflow-hidden">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-20 -left-20 size-72 rounded-full bg-status-success/10 blur-3xl" />
            <div class="absolute -bottom-20 -right-20 size-72 rounded-full bg-status-success/10 blur-3xl" />
        </div>

        <div class="relative text-center px-6 py-24 max-w-2xl mx-auto">
            <h2 class="text-4xl md:text-5xl font-bold text-text mb-4">¿Listo para controlar tu dinero?</h2>
            <p class="text-lg text-text-secondary mb-10">Tu primer presupuesto está a 2 minutos de distancia.</p>

            <Link
                :href="canRegister ? route('register') : route('login')"
                class="inline-flex items-center gap-2 px-8 py-4 rounded-lg bg-status-success text-gray-950 text-lg font-semibold hover:bg-status-success/90 active:scale-[0.98] transition-all duration-200"
            >
                Comenzar ahora
                <ArrowRight class="size-5" />
            </Link>

            <p class="text-sm text-text-secondary mt-4">Sin tarjeta de crédito. Sin cuenta en la nube.</p>
        </div>
    </section>
</template>
