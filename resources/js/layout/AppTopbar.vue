<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useLayout } from '@/layout/composables/layout';
import { useAuth } from '@/composables/useAuth';
import { useShift } from '@/composables/useShift';
import { useToast } from 'primevue/usetoast';
import ShiftDialog from '@/components/shift/ShiftDialog.vue';
import AppConfigurator from './AppConfigurator.vue';

const router = useRouter();
const toast = useToast();
const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const { user, isKasir, logout } = useAuth();
const { activeShift, checkActiveShift } = useShift();

const userMenuRef = ref(null);
const shiftDialogVisible = ref(false);
const shiftDialogMode = ref('open');

const userMenuItems = ref([
    {
        label: 'Logout',
        icon: 'pi pi-sign-out',
        command: handleLogout,
    }
]);

function toggleUserMenu(event) {
    userMenuRef.value.toggle(event);
}

async function handleLogout() {
    try {
        await logout();
        router.push('/auth/login');
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal logout.', life: 3000 });
    }
}

function openShiftDialog(mode) {
    shiftDialogMode.value = mode;
    shiftDialogVisible.value = true;
}

function onShifted(type) {
    if (type === 'close') {
        // After closing shift, prompt to open a new one or redirect
        checkActiveShift();
    }
}

onMounted(async () => {
    if (user.value) {
        await checkActiveShift();
        // If kasir has no active shift, show open shift dialog
        if (isKasir.value && !activeShift.value) {
            openShiftDialog('open');
        }
    }
});
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/" class="layout-topbar-logo">
                <span class="text-xl font-bold">MAYOKA</span>
            </router-link>
        </div>

        <div class="layout-topbar-actions">
            <!-- Shift Status -->
            <div v-if="user" class="flex items-center gap-2 mr-4">
                <Tag v-if="activeShift" severity="success" class="text-sm">
                    <i class="pi pi-clock mr-1"></i> Shift Aktif
                </Tag>
                <Tag v-else severity="danger" class="text-sm">
                    <i class="pi pi-times-circle mr-1"></i> Belum Shift
                </Tag>
                <Button v-if="activeShift" label="Tutup Shift" icon="pi pi-stop" size="small"
                    severity="warn" outlined @click="openShiftDialog('close')" />
                <Button v-else label="Buka Shift" icon="pi pi-play" size="small"
                    severity="success" outlined @click="openShiftDialog('open')" />
            </div>

            <!-- Config menu (Dark mode & Theme Palette) -->
            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
                <div class="relative">
                    <button
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
                        type="button"
                        class="layout-topbar-action layout-topbar-action-highlight"
                    >
                        <i class="pi pi-palette"></i>
                    </button>
                    <AppConfigurator />
                </div>
            </div>

            <!-- User menu -->
            <button
                class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
            >
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button type="button" class="flex items-center gap-2 hover:bg-surface-100 dark:hover:bg-surface-800 px-3 py-2 rounded-lg transition-colors cursor-pointer border-none bg-transparent" @click="toggleUserMenu">
                        <div class="w-8 h-8 flex items-center justify-center bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-100 rounded-full">
                            <i class="pi pi-user"></i>
                        </div>
                        <div class="flex flex-col text-left hidden sm:flex">
                            <span class="font-semibold text-sm leading-none">{{ user?.name || 'User' }}</span>
                            <span class="text-xs text-muted-color mt-1 capitalize">{{ user?.role || '-' }}</span>
                        </div>
                        <i class="pi pi-angle-down text-xs text-muted-color ml-2"></i>
                    </button>
                </div>
            </div>

            <Menu ref="userMenuRef" :model="userMenuItems" :popup="true" />
        </div>
    </div>

    <!-- Shift Dialog -->
    <ShiftDialog
        v-model:visible="shiftDialogVisible"
        :mode="shiftDialogMode"
        @shifted="onShifted"
    />
</template>
