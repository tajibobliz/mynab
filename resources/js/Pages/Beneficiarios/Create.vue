<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Textarea from '@/Components/Textarea.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    presupuestoActivo: Object,
});

const form = useForm({
    nombre: '',
    notas: '',
});

const longitudNotas = computed(() => form.notas?.length ?? 0);

const colorContador = computed(() => {
    if (longitudNotas.value >= 1000) return 'text-status-danger';
    if (longitudNotas.value > 900) return 'text-status-warning';
    return 'text-text-secondary';
});

const submit = () => {
    form.post(route('beneficiarios.store'));
};
</script>

<template>
    <Head title="Nuevo beneficiario" />

    <AppLayout title="Nuevo beneficiario">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Nuevo beneficiario
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-text-secondary">
                    Este beneficiario se creará en el presupuesto <span class="font-medium text-text">{{ presupuestoActivo.nombre }}</span>.
                </p>

                <form
                    class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 space-y-6 dark:bg-surface dark:border dark:border-border dark:shadow-none"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel for="nombre" value="Nombre" />
                        <TextInput
                            id="nombre"
                            v-model="form.nombre"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.nombre" />
                    </div>

                    <div>
                        <InputLabel for="notas" value="Notas (opcional)" />
                        <Textarea
                            id="notas"
                            v-model="form.notas"
                            :rows="4"
                            maxlength="1000"
                            class="mt-1 block w-full"
                        />
                        <div class="mt-1 flex justify-end">
                            <span class="text-xs" :class="colorContador">
                                {{ longitudNotas }} / 1000 caracteres
                            </span>
                        </div>
                        <InputError class="mt-2" :message="form.errors.notas" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-border">
                        <Link :href="route('beneficiarios.index')">
                            <SecondaryButton type="button" :disabled="form.processing">
                                Cancelar
                            </SecondaryButton>
                        </Link>

                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Crear beneficiario
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
