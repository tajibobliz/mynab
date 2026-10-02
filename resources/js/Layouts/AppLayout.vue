<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronsUpDown, CircleCheck, Menu, X } from 'lucide-vue-next';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import PresupuestoSelector from '@/Components/PresupuestoSelector.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-100 dark:bg-base">
            <nav class="bg-white border-b border-gray-100 dark:bg-surface dark:border-border">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <span class="font-sans font-bold text-2xl text-green-600 dark:text-accent-primary tracking-tight">MyNAB</span>
                                </Link>
                            </div>

                            <!-- Presupuesto Selector -->
                            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:border-s sm:border-gray-200 dark:sm:border-border sm:ps-6">
                                <PresupuestoSelector />
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-6 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>

                                <NavLink :href="route('presupuesto-mensual.index')" :active="route().current('presupuesto-mensual.*')">
                                    Presupuesto
                                </NavLink>

                                <NavLink :href="route('transacciones.index')" :active="route().current('transacciones.*')">
                                    Transacciones
                                </NavLink>

                                <NavLink :href="route('cuentas.index')" :active="route().current('cuentas.*')">
                                    Cuentas
                                </NavLink>

                                <NavLink :href="route('grupos-categorias.index')" :active="route().current('grupos-categorias.*')">
                                    Categorías
                                </NavLink>

                                <NavLink :href="route('beneficiarios.index')" :active="route().current('beneficiarios.*')">
                                    Beneficiarios
                                </NavLink>

                                <NavLink :href="route('presupuestos.index')" :active="route().current('presupuestos.*')">
                                    Presupuestos
                                </NavLink>

                                <NavLink :href="route('monedas.index')" :active="route().current('monedas.*')">
                                    Monedas
                                </NavLink>

                                <NavLink :href="route('tipos-cambio.index')" :active="route().current('tipos-cambio.*')">
                                    Tipos de cambio
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <!-- Theme Toggle -->
                            <ThemeToggle />

                            <div class="ms-3 relative">
                                <!-- Teams Dropdown -->
                                <Dropdown v-if="$page.props.jetstream.hasTeamFeatures" align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 dark:text-text-secondary dark:bg-surface dark:hover:text-text dark:focus:bg-surface-elevated dark:active:bg-surface-elevated transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.current_team.name }}

                                                <ChevronsUpDown class="ms-2 -me-0.5 size-4" />
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60">
                                            <!-- Team Management -->
                                            <div class="block px-4 py-2 text-xs text-gray-400 dark:text-text-secondary">
                                                Manage Team
                                            </div>

                                            <!-- Team Settings -->
                                            <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                                Team Settings
                                            </DropdownLink>

                                            <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">
                                                Create New Team
                                            </DropdownLink>

                                            <!-- Team Switcher -->
                                            <template v-if="$page.props.auth.user.all_teams.length > 1">
                                                <div class="border-t border-gray-200 dark:border-border" />

                                                <div class="block px-4 py-2 text-xs text-gray-400 dark:text-text-secondary">
                                                    Switch Teams
                                                </div>

                                                <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                    <form @submit.prevent="switchToTeam(team)">
                                                        <DropdownLink as="button">
                                                            <div class="flex items-center">
                                                                <CircleCheck v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-accent-primary" />

                                                                <div>{{ team.name }}</div>
                                                            </div>
                                                        </DropdownLink>
                                                    </form>
                                                </template>
                                            </template>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 dark:focus:border-text-secondary transition">
                                            <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 dark:text-text-secondary dark:bg-surface dark:hover:text-text dark:focus:bg-surface-elevated dark:active:bg-surface-elevated transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <ChevronDown class="ms-2 -me-0.5 size-4" />
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400 dark:text-text-secondary">
                                            Manage Account
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            Profile
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-gray-200 dark:border-border" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Cerrar sesión
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 dark:text-text-secondary dark:hover:text-text dark:hover:bg-surface-elevated dark:focus:bg-surface-elevated dark:focus:text-text transition duration-150 ease-in-out"
                                aria-label="Menú"
                                @click="showingNavigationDropdown = ! showingNavigationDropdown"
                            >
                                <X v-if="showingNavigationDropdown" class="size-6" />
                                <Menu v-else class="size-6" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <!-- Presupuesto Selector -->
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-border">
                        <PresupuestoSelector />
                    </div>

                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('presupuesto-mensual.index')" :active="route().current('presupuesto-mensual.*')">
                            Presupuesto
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('transacciones.index')" :active="route().current('transacciones.*')">
                            Transacciones
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('cuentas.index')" :active="route().current('cuentas.*')">
                            Cuentas
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('grupos-categorias.index')" :active="route().current('grupos-categorias.*')">
                            Categorías
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('beneficiarios.index')" :active="route().current('beneficiarios.*')">
                            Beneficiarios
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('presupuestos.index')" :active="route().current('presupuestos.*')">
                            Presupuestos
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('monedas.index')" :active="route().current('monedas.*')">
                            Monedas
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('tipos-cambio.index')" :active="route().current('tipos-cambio.*')">
                            Tipos de cambio
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200 dark:border-border">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800 dark:text-text">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500 dark:text-text-secondary">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <!-- Theme Toggle -->
                            <div class="flex items-center justify-between px-4 py-1">
                                <span class="text-base font-medium text-gray-600 dark:text-text-secondary">Tema</span>
                                <ThemeToggle />
                            </div>

                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Profile
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                API Tokens
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Cerrar sesión
                                </ResponsiveNavLink>
                            </form>

                            <!-- Team Management -->
                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                <div class="border-t border-gray-200 dark:border-border" />

                                <div class="block px-4 py-2 text-xs text-gray-400 dark:text-text-secondary">
                                    Manage Team
                                </div>

                                <!-- Team Settings -->
                                <ResponsiveNavLink :href="route('teams.show', $page.props.auth.user.current_team)" :active="route().current('teams.show')">
                                    Team Settings
                                </ResponsiveNavLink>

                                <ResponsiveNavLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" :active="route().current('teams.create')">
                                    Create New Team
                                </ResponsiveNavLink>

                                <!-- Team Switcher -->
                                <template v-if="$page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-gray-200 dark:border-border" />

                                    <div class="block px-4 py-2 text-xs text-gray-400 dark:text-text-secondary">
                                        Switch Teams
                                    </div>

                                    <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                        <form @submit.prevent="switchToTeam(team)">
                                            <ResponsiveNavLink as="button">
                                                <div class="flex items-center">
                                                    <CircleCheck v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-accent-primary" />
                                                    <div>{{ team.name }}</div>
                                                </div>
                                            </ResponsiveNavLink>
                                        </form>
                                    </template>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white shadow dark:bg-surface dark:shadow-none dark:border-b dark:border-border">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
