<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { lucideIconMap } from '@/lucideIconMap';

const props = defineProps({
    presupuesto: Object,
});

const IconComponent = computed(() => lucideIconMap[props.presupuesto.icono] ?? Wallet);

const fechaCreacion = computed(() => new Date(props.presupuesto.created_at).toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
}));
</script>

<template>
    <Head :title="presupuesto.nombre" />

    <AppLayout :title="presupuesto.nombre">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                    {{ presupuesto.nombre }}
                </h2>

                <Link
                    :href="route('presupuestos.edit', presupuesto.id)"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-primary focus:ring-offset-2 dark:bg-surface-elevated dark:border-border dark:text-text dark:hover:bg-border dark:focus:ring-offset-surface transition ease-in-out duration-150"
                >
                    Editar
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 dark:bg-surface dark:border dark:border-border dark:shadow-none">
                    <div class="flex items-center gap-4">
                        <span
                            class="flex items-center justify-center size-16 rounded-xl shrink-0"
                            :style="{ backgroundColor: `${presupuesto.color}26` }"
                        >
                            <component :is="IconComponent" class="size-8" :style="{ color: presupuesto.color }" />
                        </span>

                        <div class="min-w-0">
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-text truncate">
                                {{ presupuesto.nombre }}
                            </h1>
                            <p v-if="presupuesto.descripcion" class="text-sm text-gray-600 dark:text-text-secondary mt-1">
                                {{ presupuesto.descripcion }}
                            </p>
                        </div>
                    </div>

                    <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-gray-200 dark:border-border">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-text-secondary">
                                Moneda base
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-text">
                                Sin definir todavía
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-text-secondary">
                                Creado el
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-text">
                                {{ fechaCreacion }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-status-info/10 border border-status-info/30 rounded-lg p-4 text-sm text-gray-700 dark:text-text-secondary">
                    El detalle del presupuesto (dashboard con Ready to Assign, categorías y transacciones) se implementa en el change <strong>zero-based-budgeting-basico</strong> (Change 8).
                </div>
            </div>
        </div>
    </AppLayout>
</template>
