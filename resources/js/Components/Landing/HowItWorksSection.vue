<script setup>
import { ArrowRight, LayoutGrid, PiggyBank, Receipt } from 'lucide-vue-next';

const pasos = [
    {
        numero: '01',
        icon: LayoutGrid,
        titulo: 'Define tu estructura',
        descripcion: 'Crea tus cuentas (banco, efectivo, wallet), categorías agrupadas (los sobres), y beneficiarios.',
    },
    {
        numero: '02',
        icon: PiggyBank,
        titulo: 'Asigna cada peso',
        descripcion: 'Tu ingreso entra a Ready to Assign. Distribuye el total entre tus sobres hasta llegar a cero.',
    },
    {
        numero: '03',
        icon: Receipt,
        titulo: 'Registra y ajusta',
        descripcion: 'Registra tus gastos durante el mes. Si un sobre queda vacío, mueve dinero de otro. Sin estrés.',
    },
];
</script>

<template>
    <section id="como-funciona" class="bg-surface py-24 md:py-32 scroll-mt-16">
        <div class="max-w-6xl mx-auto px-6 md:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-text mb-4">Empieza en 3 pasos</h2>
                <p class="text-lg text-text-secondary">20 minutos y tienes tu primer mes planificado</p>
            </div>

            <div class="flex flex-col md:flex-row md:items-start gap-8 md:gap-6">
                <template v-for="(paso, index) in pasos" :key="paso.numero">
                    <div class="flex-1 flex flex-col items-start">
                        <div class="flex items-center gap-4 mb-5">
                            <span class="text-4xl font-mono font-bold text-status-success">{{ paso.numero }}</span>
                            <span class="flex items-center justify-center size-11 rounded-full bg-status-success/15">
                                <component :is="paso.icon" class="size-5 text-status-success" />
                            </span>
                        </div>

                        <h3 class="text-xl font-semibold text-text mb-2">{{ paso.titulo }}</h3>
                        <p class="text-text-secondary leading-relaxed">{{ paso.descripcion }}</p>
                    </div>

                    <div v-if="index < pasos.length - 1" class="hidden md:flex items-center justify-center pt-6 shrink-0">
                        <ArrowRight class="size-6 text-border" />
                    </div>
                </template>
            </div>
        </div>
    </section>
</template>
