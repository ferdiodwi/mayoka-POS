/**
 * Shared formatting utilities for MAYOKA POS.
 */

/**
 * Format number as Indonesian Rupiah currency.
 */
export function formatRp(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);
}
