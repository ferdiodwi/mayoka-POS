<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import AppMenuItem from './AppMenuItem.vue';

const { user, isOwner } = useAuth();

const ownerMenu = ref([
    {
        label: 'Menu Utama',
        items: [
            {
                label: 'Dashboard',
                icon: 'pi pi-fw pi-home',
                to: '/'
            }
        ]
    },
    {
        label: 'Manajemen',
        items: [
            {
                label: 'Manajemen User',
                icon: 'pi pi-fw pi-users',
                to: '/users'
            }
        ]
    }
]);

const kasirMenu = ref([
    {
        label: 'Kasir',
        items: [
            {
                label: 'Point of Sale',
                icon: 'pi pi-fw pi-shopping-cart',
                to: '/pos'
            }
        ]
    }
]);

const model = computed(() => {
    if (isOwner.value) {
        return ownerMenu.value;
    }
    return kasirMenu.value;
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
