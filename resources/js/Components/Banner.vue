<script setup>
import { ref, watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, Info, TriangleAlert, X } from 'lucide-vue-next';

const page = usePage();
const show = ref(true);
const style = ref('success');
const message = ref('');

// Sigue el patrón de CLAUDE.md: los controllers hacen
// ->with('flash.success'|'flash.info'|'flash.danger', 'mensaje'). Esa clave
// aterriza en session('flash') = { success: '...' } (Arr::set con dot
// notation), y Jetstream ya comparte esa sesión completa en
// page.props.jetstream.flash — no hace falta tocar HandleInertiaRequests.
watchEffect(() => {
    const flash = page.props.jetstream.flash ?? {};
    const encontrado = ['success', 'info', 'danger'].find((clave) => flash[clave]);

    style.value = encontrado ?? 'success';
    message.value = encontrado ? flash[encontrado] : '';
    show.value = true;
});
</script>

<template>
    <div>
        <div
            v-if="show && message"
            class="border-b"
            :class="{
                'bg-status-success/10 border-status-success/30': style == 'success',
                'bg-status-info/10 border-status-info/30': style == 'info',
                'bg-status-danger/10 border-status-danger/30': style == 'danger',
            }"
        >
            <div class="max-w-screen-xl mx-auto py-2 px-3 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between flex-wrap">
                    <div class="w-0 flex-1 flex items-center min-w-0">
                        <span
                            class="flex p-2 rounded-lg"
                            :class="{
                                'bg-status-success/20 text-status-success': style == 'success',
                                'bg-status-info/20 text-status-info': style == 'info',
                                'bg-status-danger/20 text-status-danger': style == 'danger',
                            }"
                        >
                            <CircleCheck v-if="style == 'success'" class="size-5" />
                            <Info v-else-if="style == 'info'" class="size-5" />
                            <TriangleAlert v-else-if="style == 'danger'" class="size-5" />
                        </span>

                        <p class="ms-3 font-medium text-sm text-gray-900 dark:text-text truncate">
                            {{ message }}
                        </p>
                    </div>

                    <div class="shrink-0 sm:ms-3">
                        <button
                            type="button"
                            class="-me-1 flex p-2 rounded-md text-gray-500 dark:text-text-secondary focus:outline-none sm:-me-2 transition duration-150"
                            :class="{
                                'hover:bg-status-success/20 focus:bg-status-success/20': style == 'success',
                                'hover:bg-status-info/20 focus:bg-status-info/20': style == 'info',
                                'hover:bg-status-danger/20 focus:bg-status-danger/20': style == 'danger',
                            }"
                            aria-label="Dismiss"
                            @click.prevent="show = false"
                        >
                            <X class="size-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
