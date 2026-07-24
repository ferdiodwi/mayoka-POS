<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useLayout } from '@/layout/composables/layout';
import { useAuth } from '@/composables/useAuth';
import { useBranch } from '@/composables/useBranch';
import { useShift } from '@/composables/useShift';
import { useToast } from 'primevue/usetoast';
import ShiftDialog from '@/components/shift/ShiftDialog.vue';
import PrinterSettingsDialog from '@/components/pos/PrinterSettingsDialog.vue';
import AppConfigurator from './AppConfigurator.vue';

const router = useRouter();
const toast = useToast();
const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const { user, isKasir, isOwner, logout } = useAuth();
const { activeBranchId, branches, fetchBranches, setActiveBranch, activeBranch, loading: branchLoading } = useBranch();
const { activeShift, checkActiveShift } = useShift();

const userMenuRef = ref(null);
const branchMenuRef = ref(null);
const shiftDialogVisible = ref(false);
const shiftDialogMode = ref('open');
const printerDialogVisible = ref(false);

const branchMenuItems = computed(() => {
    return branches.value.map(b => ({
        label: b.name,
        icon: b.id == activeBranchId.value ? 'pi pi-check text-success' : 'pi pi-building',
        command: () => setActiveBranch(b.id)
    }));
});

function toggleBranchMenu(event) {
    branchMenuRef.value.toggle(event);
}

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

async function openShiftDialog(mode) {
    if (mode === 'close') {
        await checkActiveShift(); // Refresh data to get realtime live_expected_cash
    }
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
        if (isOwner.value) {
            await fetchBranches();
        }
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
            <!-- Branch Selector (Owner only) -->
            <div v-if="isOwner && (branches.length > 0 || branchLoading)" class="flex items-center gap-2 mr-2 lg:mr-4 border-r border-surface-200 dark:border-surface-700 pr-4">
                <span class="text-sm hidden md:inline"><span class="text-muted-color">Cabang:</span> <span class="font-bold">
                    {{ activeBranch ? activeBranch.name : 'Pusat' }}
                </span></span>
                <Button label="Ganti Cabang" icon="pi pi-building" size="small" outlined @click="toggleBranchMenu" class="!py-1 !px-2 text-xs" :disabled="branchLoading" />
                <Menu ref="branchMenuRef" :model="branchMenuItems" :popup="true" />
            </div>

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

            <!-- Config menu (Printer, Dark mode & Theme Palette) -->
            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="printerDialogVisible = true" title="Pengaturan Printer Kasir">
                    <i class="pi pi-print"></i>
                </button>
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode" title="Ganti Tema Terang/Gelap">
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

    <!-- Printer Settings Dialog -->
    <PrinterSettingsDialog v-model:visible="printerDialogVisible" />

    <!-- Full-page Loading Overlay -->
    <div v-if="branchLoading" class="fixed inset-0 z-[9999] bg-surface-0/90 dark:bg-surface-900/90 backdrop-blur-sm flex flex-col items-center justify-center">
        <i class="pi pi-spin pi-spinner text-primary-500 text-5xl mb-4"></i>
        <span class="font-bold text-xl">Memuat Data Sistem...</span>
    </div>
</template>
