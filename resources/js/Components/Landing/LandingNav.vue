<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, X } from 'lucide-vue-next';

const emit = defineEmits(['navigate']);

const page = usePage();
const user = page.props.auth?.user ?? null;

const menuAbierto = ref(false);

const enlaces = [
    { id: 'caracteristicas', texto: 'Características' },
    { id: 'como-funciona', texto: 'Cómo funciona' },
    { id: 'tecnologia', texto: 'Tecnología' },
];

const navegar = (id) => {
    menuAbierto.value = false;
    emit('navigate', id);
};
</script>

<template>
    <header class="fixed top-0 inset-x-0 z-50 bg-base/80 backdrop-blur-md border-b border-border/50">
        <div class="max-w-7xl mx-auto px-6 md:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="#" class="flex items-center gap-1.5 shrink-0" @click.prevent="navegar(null)">
                    <span class="text-2xl font-bold tracking-tight text-text">MyNAB</span>
                    <span class="size-2 rounded-full bg-status-success" aria-hidden="true" />
                </a>

                <nav class="hidden md:flex items-center gap-8">
                    <button
                        v-for="enlace in enlaces"
                        :key="enlace.id"
                        type="button"
                        class="text-sm text-text-secondary hover:text-text transition duration-200"
                        @click="navegar(enlace.id)"
                    >
                        {{ enlace.texto }}
                    </button>
                </nav>

                <div class="hidden md:flex items-center gap-3">
                    <template v-if="user">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex items-center px-4 py-2 rounded-md bg-status-success text-gray-950 text-sm font-semibold hover:bg-status-success/90 active:scale-[0.98] transition duration-200"
                        >
                            Ir al Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm text-text-secondary hover:text-text transition duration-200"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center px-4 py-2 rounded-md bg-status-success text-gray-950 text-sm font-semibold hover:bg-status-success/90 active:scale-[0.98] transition duration-200"
                        >
                            Comenzar
                        </Link>
                    </template>
                </div>

                <button
                    type="button"
                    class="md:hidden flex items-center justify-center size-10 text-text-secondary hover:text-text transition duration-200"
                    :aria-label="menuAbierto ? 'Cerrar menú' : 'Abrir menú'"
                    @click="menuAbierto = ! menuAbierto"
                >
                    <X v-if="menuAbierto" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>
        </div>

        <div v-if="menuAbierto" class="md:hidden border-t border-border/50 bg-base px-6 py-4 space-y-4">
            <button
                v-for="enlace in enlaces"
                :key="enlace.id"
                type="button"
                class="block w-full text-left text-sm text-text-secondary hover:text-text transition duration-200"
                @click="navegar(enlace.id)"
            >
                {{ enlace.texto }}
            </button>

            <div class="pt-4 border-t border-border/50 flex flex-col gap-3">
                <template v-if="user">
                    <Link :href="route('dashboard')" class="inline-flex justify-center px-4 py-2 rounded-md bg-status-success text-gray-950 text-sm font-semibold">
                        Ir al Dashboard
                    </Link>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="inline-flex justify-center px-4 py-2 rounded-md border border-border text-sm text-text">
                        Iniciar sesión
                    </Link>
                    <Link :href="route('register')" class="inline-flex justify-center px-4 py-2 rounded-md bg-status-success text-gray-950 text-sm font-semibold">
                        Comenzar
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>
