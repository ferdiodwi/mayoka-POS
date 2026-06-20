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
                    component: () => import('@/views/pages/Pos.vue'),
                },
                {
                    path: '/transactions',
                    name: 'transactions',
                    component: () => import('@/views/pages/TransactionHistory.vue'),
                },
                // Manajemen (Owner)
                {
                    path: '/users',
                    name: 'users',
                    component: () => import('@/views/pages/Users.vue'),
                    meta: { role: 'owner' }
                },
                // Master Data (Owner)
                {
                    path: '/categories',
                    name: 'categories',
                    component: () => import('@/views/pages/Categories.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/products',
                    name: 'products',
                    component: () => import('@/views/pages/Products.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/print-prices',
                    name: 'printPrices',
                    component: () => import('@/views/pages/PrintPrices.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/addon-services',
                    name: 'addonServices',
                    component: () => import('@/views/pages/AddonServices.vue'),
                    meta: { role: 'owner' }
                },
                // Laporan (Owner)
                {
                    path: '/reports/sales',
                    name: 'reportSales',
                    component: () => import('@/views/pages/reports/Sales.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/reports/cashier',
                    name: 'reportCashier',
                    component: () => import('@/views/pages/reports/CashierReport.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/reports/shifts',
                    name: 'reportShifts',
                    component: () => import('@/views/pages/reports/ShiftReport.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/reports/stock',
                    name: 'reportStock',
                    component: () => import('@/views/pages/reports/StockReport.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/reports/profit-loss',
                    name: 'reportProfitLoss',
                    component: () => import('@/views/pages/reports/ProfitLoss.vue'),
                    meta: { role: 'owner' }
                },
                // Keuangan (Owner)
                {
                    path: '/purchases',
                    name: 'purchases',
                    component: () => import('@/views/pages/Purchases.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/expenses',
                    name: 'expenses',
                    component: () => import('@/views/pages/Expenses.vue'),
                    meta: { role: 'owner' }
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

    if (to.name === 'login') {
        if (isAuthenticated.value) {
            return next(user.value.role === 'owner' ? '/' : '/pos');
        }
        return next();
    }

    if (!isAuthenticated.value) {
        await fetchUser();
    }

    if (to.meta.requiresAuth && !isAuthenticated.value) {
        return next({ name: 'login' });
    }

    if (to.meta.role && user.value?.role !== to.meta.role) {
        return next({ name: 'accessDenied' });
    }

    next();
});

export default router;
