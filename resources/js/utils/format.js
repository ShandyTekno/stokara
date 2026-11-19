const idr = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const num = new Intl.NumberFormat('id-ID');

export const formatRupiah = (v) => idr.format(Number(v) || 0);
export const formatNumber = (v) => num.format(Number(v) || 0);
