<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    modo: {
        type: String,
        required: true, // 'create' | 'edit'
    },
    grupoCategoriaId: {
        type: [Number, String],
        default: null,
    },
    categoria: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    nombre: '',
    grupo_categoria_id: null,
});

// Repuebla el form cada vez que el modal se abre: el mismo componente se
// reutiliza para crear (grupo vacío) y editar (categoría existente), así que
// no basta con los valores iniciales de useForm.
watch(() => props.show, (visible) => {
    if (!visible) return;

    form.clearErrors();
    form.nombre = props.modo === 'edit' && props.categoria ? props.categoria.nombre : '';
    form.grupo_categoria_id = props.grupoCategoriaId;
});

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => close() };

    if (props.modo === 'edit') {
        form.put(route('categorias.update', props.categoria.id), opciones);
    } else {
        form.post(route('categorias.store'), opciones);
    }
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-text">
                {{ modo === 'edit' ? 'Editar categoría' : 'Nueva categoría' }}
            </h2>

            <div class="mt-4">
                <InputLabel for="categoria_nombre" value="Nombre" />
                <TextInput
                    id="categoria_nombre"
                    v-model="form.nombre"
                    type="text"
                    class="mt-1 block w-full"
                    maxlength="100"
                    autofocus
                    @keyup.enter="submit"
                />
                <InputError class="mt-2" :message="form.errors.nombre" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" :disabled="form.processing" @click="close">
                    Cancelar
                </SecondaryButton>

                <PrimaryButton
                    type="button"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ modo === 'edit' ? 'Actualizar' : 'Crear' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
