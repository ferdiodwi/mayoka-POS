import { computed, reactive, watch } from 'vue';

const STORAGE_KEY = 'mayoka_layout_config';

function loadLayoutConfig() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) return JSON.parse(raw);
    } catch {}
    return null;
}

const savedConfig = loadLayoutConfig();

const layoutConfig = reactive({
    preset: savedConfig?.preset || 'Aura',
    primary: savedConfig?.primary || 'emerald',
    surface: savedConfig?.surface || null,
    darkTheme: savedConfig?.darkTheme !== undefined ? savedConfig.darkTheme : false,
    menuMode: savedConfig?.menuMode || 'static'
});

// Watch for changes and save to localStorage
watch(layoutConfig, (val) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
}, { deep: true });

const STATE_KEY = 'mayoka_layout_state';

function loadLayoutState() {
    try {
        const raw = localStorage.getItem(STATE_KEY);
        if (raw) return JSON.parse(raw);
    } catch {}
    return null;
}

const savedState = loadLayoutState();

// Apply dark mode class immediately on script evaluation
if (typeof document !== 'undefined') {
    if (layoutConfig.darkTheme) {
        document.documentElement.classList.add('app-dark');
    } else {
        document.documentElement.classList.remove('app-dark');
    }
}

const layoutState = reactive({
    staticMenuInactive: savedState?.staticMenuInactive ?? false,
    overlayMenuActive: false,
    profileSidebarVisible: false,
    configSidebarVisible: false,
    sidebarExpanded: false,
    menuHoverActive: false,
    activeMenuItem: null,
    activePath: null
});

watch(() => layoutState.staticMenuInactive, (val) => {
    const currentState = loadLayoutState() || {};
    currentState.staticMenuInactive = val;
    localStorage.setItem(STATE_KEY, JSON.stringify(currentState));
});

export function useLayout() {
    const toggleDarkMode = () => {
        if (!document.startViewTransition) {
            executeDarkModeToggle();

            return;
        }

        document.startViewTransition(() => executeDarkModeToggle(event));
    };

    const executeDarkModeToggle = () => {
        layoutConfig.darkTheme = !layoutConfig.darkTheme;
        document.documentElement.classList.toggle('app-dark');
    };

    const toggleMenu = () => {
        if (isDesktop()) {
            if (layoutConfig.menuMode === 'static') {
                layoutState.staticMenuInactive = !layoutState.staticMenuInactive;
            }

            if (layoutConfig.menuMode === 'overlay') {
                layoutState.overlayMenuActive = !layoutState.overlayMenuActive;
            }
        } else {
            layoutState.mobileMenuActive = !layoutState.mobileMenuActive;
        }
    };

    const toggleConfigSidebar = () => {
        layoutState.configSidebarVisible = !layoutState.configSidebarVisible;
    };

    const hideMobileMenu = () => {
        layoutState.mobileMenuActive = false;
    };

    const changeMenuMode = (event) => {
        layoutConfig.menuMode = event.value;
        layoutState.staticMenuInactive = false;
        layoutState.mobileMenuActive = false;
        layoutState.sidebarExpanded = false;
        layoutState.menuHoverActive = false;
        layoutState.anchored = false;
    };

    const isDarkTheme = computed(() => layoutConfig.darkTheme);
    const isDesktop = () => window.innerWidth > 991;

    const hasOpenOverlay = computed(() => layoutState.overlayMenuActive);

    return {
        layoutConfig,
        layoutState,
        isDarkTheme,
        toggleDarkMode,
        toggleConfigSidebar,
        toggleMenu,
        hideMobileMenu,
        changeMenuMode,
        isDesktop,
        hasOpenOverlay
    };
}
