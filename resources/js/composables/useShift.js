import { ref } from 'vue';

const activeShift = ref(null);

export function useShift() {

    function getCsrfToken() {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    async function checkActiveShift() {
        try {
            const res = await fetch('/api/shifts/active', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                const data = await res.json();
                activeShift.value = data.shift;
            }
        } catch {
            activeShift.value = null;
        }
    }

    async function openShift(cashStart) {
        const res = await fetch('/api/shifts/open', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ cash_start: cashStart }),
        });

        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.message || 'Gagal membuka shift.');
        }

        activeShift.value = data.shift;
        return data;
    }

    async function closeShift(shiftId, cashEnd, notes = null) {
        const res = await fetch(`/api/shifts/${shiftId}/close`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ cash_end: cashEnd, notes }),
        });

        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.message || 'Gagal menutup shift.');
        }

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
