import { ref, computed } from 'vue';
import { apiGet } from '@/composables/useApi';

const branches = ref([]);
const activeBranchId = ref(localStorage.getItem('activeBranchId') || null);
const loading = ref(false);

export function useBranch() {
    const activeBranch = computed(() => {
        if (!branches.value.length) return null;
        if (!activeBranchId.value) return branches.value[0]; // fallback
        return branches.value.find(b => b.id == activeBranchId.value) || branches.value[0];
    });

    async function fetchBranches() {
        if (loading.value) return;
        loading.value = true;
        try {
            // Need a new API endpoint to fetch branches
            const res = await apiGet('/api/branches');
            branches.value = res.branches;
            
            if (branches.value.length > 0 && !activeBranchId.value) {
                setActiveBranch(branches.value[0].id);
            }
        } catch (e) {
            console.error('Failed to fetch branches', e);
        } finally {
            loading.value = false;
        }
    }

    function setActiveBranch(id) {
        activeBranchId.value = id;
        localStorage.setItem('activeBranchId', id);
        // Force reload to refresh all data with new header
        window.location.reload();
    }

    return {
        branches,
        activeBranchId,
        activeBranch,
        loading,
        fetchBranches,
        setActiveBranch
    };
}
