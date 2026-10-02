<script setup>
import { Github } from 'lucide-vue-next';

const emit = defineEmits(['navigate']);

const enlacesProducto = [
    { id: 'caracteristicas', texto: 'Características' },
    { id: 'como-funciona', texto: 'Cómo funciona' },
    { id: 'tecnologia', texto: 'Tecnología' },
];
</script>

<template>
    <footer class="bg-surface-elevated border-t border-border/50">
        <div class="max-w-7xl mx-auto px-6 md:px-8 py-16">
            <div class="grid sm:grid-cols-3 gap-10">
                <div>
                    <div class="flex items-center gap-1.5 mb-3">
                        <span class="text-xl font-bold text-text">MyNAB</span>
                        <span class="size-1.5 rounded-full bg-status-success" />
                    </div>
                    <p class="text-sm text-text-secondary mb-4">Zero-based budgeting local y privado.</p>
                    <a
                        href="https://github.com/tajibobliz/mynab"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center size-9 rounded-lg border border-border text-text-secondary hover:text-text hover:border-text-secondary transition duration-200"
                        aria-label="Repositorio en GitHub"
                    >
                        <Github class="size-4" />
                    </a>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-4">Producto</h3>
                    <ul class="space-y-2">
                        <li v-for="enlace in enlacesProducto" :key="enlace.id">
                            <button type="button" class="text-sm text-text-secondary hover:text-text transition duration-200" @click="emit('navigate', enlace.id)">
                                {{ enlace.texto }}
                            </button>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-4">Contexto académico</h3>
                    <ul class="space-y-2 text-sm text-text-secondary">
                        <li>Proyecto de la materia Tópicos</li>
                        <li>UAGRM — Fac. Ing. Cs. Computación y Telecomunicaciones</li>
                        <li>2026</li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-border/50 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-text-secondary">
                <p>© 2026 MyNAB — Proyecto académico</p>
                <a href="https://github.com/tajibobliz/mynab" target="_blank" rel="noopener noreferrer" class="hover:text-text transition duration-200">
                    github.com/tajibobliz/mynab
                </a>
            </div>
        </div>
    </footer>
</template>
