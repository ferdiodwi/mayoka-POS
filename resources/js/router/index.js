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
                    meta: { permission: 'reports.read' }
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
                    path: '/branches',
                    name: 'branches',
                    component: () => import('@/views/pages/Branches.vue'),
                    meta: { role: 'owner' }
                },
                {
                    path: '/users',
                    name: 'users',
                    component: () => import('@/views/pages/Users.vue'),
                    meta: { permission: 'users.read' }
                },
                // Master Data (Owner)
                {
                    path: '/categories',
                    name: 'categories',
                    component: () => import('@/views/pages/Categories.vue'),
                    meta: { permission: 'categories.read' }
                },
                {
                    path: '/products',
                    name: 'products',
                    component: () => import('@/views/pages/Products.vue'),
                    meta: { permission: 'products.read' }
                },
                {
                    path: '/customers',
                    name: 'customers',
                    component: () => import('@/views/pages/Customers.vue'),
                    meta: { permission: 'customers.read' }
                },
                {
                    path: '/print-prices',
                    name: 'printPrices',
                    component: () => import('@/views/pages/PrintPrices.vue'),
                    meta: { permission: 'print_prices.read' }
                },
                {
                    path: '/addon-services',
                    name: 'addonServices',
                    component: () => import('@/views/pages/AddonServices.vue'),
                    meta: { permission: 'addons.read' }
                },
                {
                    path: '/price-labels',
                    name: 'priceLabels',
                    component: () => import('@/views/pages/PriceLabel.vue'),
                    meta: { permission: 'products.read' }
                },
                // Laporan (Owner)
                {
                    path: '/reports/sales',
                    name: 'reportSales',
                    component: () => import('@/views/pages/reports/Sales.vue'),
                    meta: { permission: 'reports.read' }
                },
                {
                    path: '/reports/cashier',
                    name: 'reportCashier',
                    component: () => import('@/views/pages/reports/CashierReport.vue'),
                    meta: { permission: 'reports.read' }
                },
                {
                    path: '/reports/shifts',
                    name: 'reportShifts',
                    component: () => import('@/views/pages/reports/ShiftReport.vue'),
                    meta: { permission: 'reports.read' }
                },
                {
                    path: '/reports/stock',
                    name: 'reportStock',
                    component: () => import('@/views/pages/reports/StockReport.vue'),
                    meta: { permission: 'reports.read' }
                },
                {
                    path: '/reports/profit-loss',
                    name: 'reportProfitLoss',
                    component: () => import('@/views/pages/reports/ProfitLoss.vue'),
                    meta: { permission: 'reports.read' }
                },
                {
                    path: '/reports/cash-flow',
                    name: 'reportCashFlow',
                    component: () => import('@/views/pages/reports/CashFlow.vue'),
                    meta: { permission: 'reports.read' }
                },
                // Keuangan (Owner)
                {
                    path: '/purchases',
                    name: 'purchases',
                    component: () => import('@/views/pages/Purchases.vue'),
                    meta: { permission: 'purchases.read' }
                },
                {
                    path: '/expenses',
                    name: 'expenses',
                    component: () => import('@/views/pages/Expenses.vue'),
                    meta: { permission: 'expenses.read' }
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
router.beforeEach(async (to, from) => {
    const { user, fetchUser, isAuthenticated, hasPermission } = useAuth();

    if (to.name === 'login') {
        if (isAuthenticated.value) {
            return user.value.role === 'owner' ? '/' : '/pos';
        }
        return true;
    }

    if (!isAuthenticated.value) {
        await fetchUser();
    }

    if (to.meta.requiresAuth && !isAuthenticated.value) {
        return { name: 'login' };
    }

    if (to.meta.permission && !hasPermission(to.meta.permission)) {
        return { name: 'accessDenied' };
    }

    if (to.meta.role && to.meta.role === 'owner' && user.value.role !== 'owner') {
        return { name: 'accessDenied' };
    }

    return true;
});

export default router;
