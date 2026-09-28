<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Plus, Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();

// Misma clave que PresupuestoSelector.vue: Eloquent serializa la relación
// presupuestoActivo() en snake_case dentro de User::toArray().
const activo = computed(() => page.props.auth.user.presupuesto_activo);
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    v-if="activo"
                    class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 dark:bg-surface dark:border dark:border-border dark:shadow-none"
                >
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-text">
                        Bienvenido a MyNAB, {{ $page.props.auth.user.name }}
                    </h1>

                    <p class="mt-4 text-sm text-gray-600 dark:text-text-secondary">
                        Aquí vivirá el dashboard del presupuesto activo.
                    </p>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center py-20 text-center bg-white shadow-xl sm:rounded-lg dark:bg-surface dark:border dark:border-border dark:shadow-none"
                >
                    <Wallet class="size-16 text-gray-300 dark:text-text-secondary mb-4" />

                    <h1 class="text-xl font-semibold text-gray-900 dark:text-text">
                        Crea tu primer presupuesto
                    </h1>

                    <p class="mt-2 max-w-md text-sm text-gray-600 dark:text-text-secondary">
                        Un presupuesto es donde viven todas tus cuentas, categorías y transacciones. Crea el tuyo para empezar.
                    </p>

                    <Link
                        :href="route('presupuestos.create')"
                        class="mt-6 inline-flex items-center gap-1.5 px-4 py-2 bg-accent-primary border border-transparent rounded-md font-semibold text-xs text-gray-950 uppercase tracking-widest hover:bg-green-400 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface transition ease-in-out duration-150"
                    >
                        <Plus class="size-4" />
                        Crear presupuesto
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
