import { ref } from 'vue';
import { apiGet, apiPost, apiPut } from '@/composables/useApi';

const activeShift = ref(null);

export function useShift() {

    async function checkActiveShift() {
        try {
            const data = await apiGet('/api/shifts/active');
            activeShift.value = data.shift;
        } catch {
            activeShift.value = null;
        }
    }

    async function openShift(cashStart) {
        const data = await apiPost('/api/shifts/open', { cash_start: cashStart });
        activeShift.value = data.shift;
        return data;
    }

    async function closeShift(shiftId, cashEnd, notes = null) {
        const data = await apiPut(`/api/shifts/${shiftId}/close`, { cash_end: cashEnd, notes });
        activeShift.value = null;
        return data;
    }

    return {
        activeShift,
        checkActiveShift,
        openShift,
        closeShift,
    };
}
