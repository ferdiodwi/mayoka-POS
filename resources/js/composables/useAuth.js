import { ref, computed } from 'vue';

const user = ref(null);
const loading = ref(false);

export function useAuth() {
    const isAuthenticated = computed(() => !!user.value);
    const isOwner = computed(() => user.value?.role === 'owner');
    const isKasir = computed(() => user.value?.role === 'kasir');

    async function login(username, password) {
        loading.value = true;
        try {
            // Get CSRF cookie first
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });

            const res = await fetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ username, password }),
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Login gagal.');
            }

            user.value = data.user;
            return data;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            });
        } finally {
            user.value = null;
        }
    }

    async function fetchUser() {
        try {
            const res = await fetch('/api/me', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            if (res.ok) {
                const data = await res.json();
                user.value = data.user;
            } else {
                user.value = null;
            }
        } catch {
            user.value = null;
        }
    }

    function getCsrfToken() {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    return {
        user,
        loading,
        isAuthenticated,
        isOwner,
        isKasir,
        login,
        logout,
        fetchUser,
    };
}
