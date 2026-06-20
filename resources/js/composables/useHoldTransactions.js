import { ref, watch } from 'vue';

const STORAGE_KEY = 'mayoka_hold_transactions';
const MAX_HOLD = 10;

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

const holdList = ref(loadFromStorage());

// Sync to localStorage on change
watch(holdList, (val) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
}, { deep: true });

export function useHoldTransactions() {

    /**
     * Hold the current cart items.
     */
    function holdCurrentCart(items, transactionDiscount, label = '') {
        if (holdList.value.length >= MAX_HOLD) {
            throw new Error(`Maksimal ${MAX_HOLD} transaksi ditahan.`);
        }

        holdList.value.push({
            id: Date.now(),
            label: label || `Transaksi #${holdList.value.length + 1}`,
            items: JSON.parse(JSON.stringify(items)),
            transactionDiscount,
            timestamp: new Date().toISOString(),
        });
    }

    /**
     * Resume (restore) a held transaction.
     * Returns the held data and removes it from the list.
     */
    function resumeTransaction(index) {
        const held = holdList.value[index];
        if (!held) return null;

        holdList.value.splice(index, 1);
        return held;
    }

    /**
     * Remove a held transaction without restoring.
     */
    function removeHold(index) {
        holdList.value.splice(index, 1);
    }

    const holdCount = ref(holdList.value.length);
    watch(holdList, (val) => { holdCount.value = val.length; }, { deep: true });

    return {
        holdList,
        holdCount,
        holdCurrentCart,
        resumeTransaction,
        removeHold,
    };
}
