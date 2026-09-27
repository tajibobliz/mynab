<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
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
                <div class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 dark:bg-surface dark:border dark:border-border dark:shadow-none">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-text">
                        Bienvenido a MyNAB, {{ $page.props.auth.user.name }}
                    </h1>

                    <p class="mt-4 text-sm text-gray-600 dark:text-text-secondary">
                        Aquí vivirá el dashboard del presupuesto activo.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
