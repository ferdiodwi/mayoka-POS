import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '@/composables/useAuth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: AppLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: '/',
                    name: 'dashboard',
                    component: () => import('@/views/Dashboard.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/pos',
                    name: 'pos',
                    component: () => import('@/views/pages/Empty.vue'), // Placeholder — Tahap 3
                },
                {
                    path: '/users',
                    name: 'users',
                    component: () => import('@/views/pages/Users.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/pages/empty',
                    name: 'empty',
                    component: () => import('@/views/pages/Empty.vue')
                },
            ]
        },
        {
            path: '/auth/login',
            name: 'login',
            component: () => import('@/views/pages/auth/Login.vue')
        },
        {
            path: '/auth/access',
            name: 'accessDenied',
            component: () => import('@/views/pages/auth/Access.vue')
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'notfound',
            component: () => import('@/views/pages/NotFound.vue')
        },
    ]
});

// Navigation guard
router.beforeEach(async (to, from, next) => {
    const { user, fetchUser, isAuthenticated } = useAuth();

    // Skip auth check for login page
    if (to.name === 'login') {
        if (isAuthenticated.value) {
            return next(user.value.role === 'owner' ? '/' : '/pos');
        }
        return next();
    }

    // Fetch user if not loaded yet
    if (!isAuthenticated.value) {
        await fetchUser();
    }

    // Redirect to login if not authenticated
    if (to.meta.requiresAuth && !isAuthenticated.value) {
        return next({ name: 'login' });
    }

    // Role-based access control
    if (to.meta.role && user.value?.role !== to.meta.role) {
        return next({ name: 'accessDenied' });
    }

    next();
});

export default router;
