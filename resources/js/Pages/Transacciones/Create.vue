<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MontoCalculadora from '@/Components/MontoCalculadora.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectorBeneficiario from '@/Components/SelectorBeneficiario.vue';
import SelectorCategoria from '@/Components/SelectorCategoria.vue';
import SelectorCuenta from '@/Components/SelectorCuenta.vue';
import SplitForm from '@/Components/SplitForm.vue';
import Textarea from '@/Components/Textarea.vue';
import { combinarFechaHora, fechaYHoraLocal } from '@/utils/fechaHora';
import { tipoTransaccionIconMap } from '@/tipoTransaccionIconMap';

const props = defineProps({
    presupuestoActivo: Object,
    tiposTransaccion: Array,
});

// Tokens literales (no interpolados) para que Tailwind los detecte en build
// — una clase construida con `${color}` en runtime no aparecería en el CSS
// generado, Tailwind escanea el código fuente, no el valor en memoria.
const CLASES_TIPO_ACTIVO = {
    'status-danger': 'border-status-danger bg-status-danger/10 text-status-danger',
    'status-success': 'border-status-success bg-status-success/10 text-status-success',
    'status-info': 'border-status-info bg-status-info/10 text-status-info',
};

const { fecha, hora } = fechaYHoraLocal();

const form = useForm({
    cuenta_id: '',
    cuenta_destino_id: '',
    categoria_id: '',
    beneficiario_id: null,
    tipo: 'outflow',
    monto_centavos: null,
    fecha,
    hora,
    notas: '',
    es_split: false,
    splits: [],
});

const cuentas = computed(() => usePage().props.cuentasDelPresupuestoActivo);

const monedaCodigoActual = computed(() => {
    const cuenta = cuentas.value.find((c) => c.id === Number(form.cuenta_id));
    return cuenta?.moneda_codigo ?? props.presupuestoActivo.moneda_base_codigo;
});

// Al cambiar de tipo, los campos que no aplican al nuevo tipo se resetean
// explícitamente (design.md Decisión 6): evita que un valor de "outflow"
// (ej. categoria_id) sobreviva silenciosamente a un cambio a "transfer" y
// viaje en el payload aunque la UI ya no lo muestre.
const seleccionarTipo = (tipo) => {
    form.tipo = tipo;
    form.cuenta_destino_id = '';
    form.categoria_id = '';
    form.beneficiario_id = null;
    form.es_split = false;
    form.splits = [];
};

const alCambiarSplit = (activo) => {
    form.es_split = activo;

    if (activo) {
        form.categoria_id = '';
        form.splits = [
            { categoria_id: '', monto_centavos: null, notas: '' },
            { categoria_id: '', monto_centavos: null, notas: '' },
        ];
    } else {
        form.splits = [];
    }
};

const submit = () => {
    form.transform((datos) => {
        const { fecha, hora, ...resto } = datos;
        const payload = { ...resto, fecha_hora: combinarFechaHora(fecha, hora) };

        // Defensa en profundidad: aunque la UI ya oculta/vacía estos campos
        // al cambiar de tipo, el backend no debería recibir basura si algo
        // se le escapó al estado local.
        if (datos.tipo !== 'outflow') {
            payload.es_split = false;
            payload.splits = [];
        }

        if (datos.es_split) {
            payload.categoria_id = null;
        }

        return payload;
    }).post(route('transacciones.store'));
};
</script>

<template>
    <Head title="Nueva transacción" />

    <AppLayout title="Nueva transacción">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-text leading-tight">
                Nueva transacción
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <form
                    class="bg-white shadow-xl sm:rounded-lg p-6 lg:p-8 space-y-6 dark:bg-surface dark:border dark:border-border dark:shadow-none"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel value="Tipo de transacción" />
                        <div class="mt-2 grid grid-cols-3 gap-3">
                            <button
                                v-for="opcion in tiposTransaccion"
                                :key="opcion.value"
                                type="button"
                                class="flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition duration-150 ease-in-out"
                                :class="form.tipo === opcion.value
                                    ? CLASES_TIPO_ACTIVO[opcion.color]
                                    : 'border-gray-200 dark:border-border text-text-secondary hover:border-gray-300 dark:hover:border-text-secondary'"
                                @click="seleccionarTipo(opcion.value)"
                            >
                                <component :is="tipoTransaccionIconMap[opcion.icono]" class="size-6" />
                                <span class="text-sm font-medium">{{ opcion.label }}</span>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.tipo" />
                    </div>

                    <div>
                        <InputLabel :value="form.tipo === 'transfer' ? 'Cuenta origen' : 'Cuenta'" />
                        <SelectorCuenta v-model="form.cuenta_id" :cuentas="cuentas" class="mt-1" />
                        <InputError class="mt-2" :message="form.errors.cuenta_id" />
                    </div>

                    <div v-if="form.tipo === 'transfer'">
                        <InputLabel value="Cuenta destino" />
                        <SelectorCuenta
                            v-model="form.cuenta_destino_id"
                            :cuentas="cuentas"
                            :excluir-id="Number(form.cuenta_id) || null"
                            class="mt-1"
                        />
                        <InputError class="mt-2" :message="form.errors.cuenta_destino_id" />
                    </div>

                    <div v-if="form.tipo !== 'transfer'">
                        <InputLabel :value="form.tipo === 'inflow' ? 'Beneficiario' : 'Beneficiario (opcional)'" />
                        <SelectorBeneficiario v-model="form.beneficiario_id" class="mt-1" />
                        <InputError class="mt-2" :message="form.errors.beneficiario_id" />
                    </div>

                    <div v-if="form.tipo === 'outflow' && ! form.es_split">
                        <InputLabel value="Categoría" />
                        <SelectorCategoria v-model="form.categoria_id" class="mt-1" />
                        <InputError class="mt-2" :message="form.errors.categoria_id" />
                    </div>

                    <div>
                        <InputLabel for="monto" value="Monto" />
                        <p class="text-xs text-text-secondary mb-1">Puedes usar expresiones: + - * /</p>
                        <MontoCalculadora id="monto" v-model="form.monto_centavos" :moneda-codigo="monedaCodigoActual" />
                        <InputError class="mt-2" :message="form.errors.monto_centavos" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="fecha" value="Fecha" />
                            <input
                                id="fecha"
                                v-model="form.fecha"
                                type="date"
                                class="mt-1 block w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text transition duration-150 ease-in-out"
                            >
                        </div>
                        <div>
                            <InputLabel for="hora" value="Hora" />
                            <input
                                id="hora"
                                v-model="form.hora"
                                type="time"
                                class="mt-1 block w-full border-gray-300 focus:border-accent-primary focus:ring-accent-primary rounded-md shadow-sm dark:bg-base dark:border-border dark:text-text transition duration-150 ease-in-out"
                            >
                        </div>
                    </div>
                    <InputError :message="form.errors.fecha_hora" />

                    <label v-if="form.tipo === 'outflow'" class="flex items-center gap-2 cursor-pointer w-fit">
                        <Checkbox :checked="form.es_split" @update:checked="alCambiarSplit" />
                        <span class="text-sm text-text">Dividir en categorías</span>
                    </label>

                    <SplitForm
                        v-if="form.es_split"
                        v-model="form.splits"
                        :monto-total-centavos="form.monto_centavos ?? 0"
                        :moneda-codigo="monedaCodigoActual"
                    />
                    <InputError :message="form.errors.splits" />

                    <div>
                        <InputLabel for="notas" value="Notas (opcional)" />
                        <Textarea id="notas" v-model="form.notas" :rows="3" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.notas" />
                    </div>

                    <p class="text-sm text-text-secondary">
                        Esta transacción se registrará en el presupuesto <span class="font-medium text-text">{{ presupuestoActivo.nombre }}</span>.
                    </p>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-border">
                        <Link :href="route('transacciones.index')">
                            <SecondaryButton type="button" :disabled="form.processing">
                                Cancelar
                            </SecondaryButton>
                        </Link>

                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
