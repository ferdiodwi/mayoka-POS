import { ref, computed } from 'vue';
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

    const isOldShift = computed(() => {
        if (!activeShift.value || !activeShift.value.started_at) return false;
        // started_at is usually "YYYY-MM-DD HH:MM:SS"
        const shiftDate = activeShift.value.started_at.substring(0, 10);
        
        // Dapatkan string "YYYY-MM-DD" local timezone untuk hari ini
        const d = new Date();
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const today = `${year}-${month}-${day}`;

        return shiftDate !== today;
    });

    return {
        activeShift,
        isOldShift,
        checkActiveShift,
        openShift,
        closeShift,
    };
}
