<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    monedas: Array,
});

const toggle = (moneda) => {
    router.post(route('monedas.toggle', moneda.codigo), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Monedas del sistema" />

    <AppLayout title="Monedas del sistema">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Monedas del sistema
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-xl sm:rounded-lg dark:bg-surface dark:border dark:border-border dark:shadow-none overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-border text-left text-gray-500 dark:text-text-secondary">
                                <th class="py-3 px-4 font-medium">Código</th>
                                <th class="py-3 px-4 font-medium">Nombre</th>
                                <th class="py-3 px-4 font-medium">Símbolo</th>
                                <th class="py-3 px-4 font-medium">Decimales</th>
                                <th class="py-3 px-4 font-medium">Estado</th>
                                <th class="py-3 px-4 font-medium text-right">Activar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="moneda in monedas"
                                :key="moneda.codigo"
                                class="border-b border-gray-100 dark:border-border last:border-0"
                            >
                                <td class="py-3 px-4 font-mono font-semibold text-gray-800 dark:text-text">
                                    {{ moneda.codigo }}
                                </td>
                                <td class="py-3 px-4 text-gray-700 dark:text-text">
                                    {{ moneda.nombre }}
                                </td>
                                <td class="py-3 px-4 font-mono text-gray-500 dark:text-text-secondary">
                                    {{ moneda.simbolo }}
                                </td>
                                <td class="py-3 px-4 text-gray-500 dark:text-text-secondary">
                                    {{ moneda.decimales }}
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="moneda.activa
                                            ? 'bg-status-success/10 text-status-success'
                                            : 'bg-gray-100 text-gray-500 dark:bg-border dark:text-text-secondary'"
                                    >
                                        {{ moneda.activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="moneda.activa"
                                        :aria-label="`${moneda.activa ? 'Desactivar' : 'Activar'} ${moneda.codigo}`"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition duration-150 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:focus:ring-offset-surface"
                                        :class="moneda.activa ? 'bg-accent-primary' : 'bg-gray-300 dark:bg-border'"
                                        @click="toggle(moneda)"
                                    >
                                        <span
                                            class="inline-block size-4 transform rounded-full bg-white transition duration-150"
                                            :class="moneda.activa ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
