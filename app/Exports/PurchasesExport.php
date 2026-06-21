<?php

namespace App\Exports;

use App\Models\Purchase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $from;
    protected $to;
    protected $supplier;

    public function __construct($from, $to, $supplier)
    {
        $this->from = $from;
        $this->to = $to;
        $this->supplier = $supplier;
    }

    public function collection()
    {
        $query = Purchase::with(['user', 'items.product'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        if ($this->from) {
            $query->whereDate('purchase_date', '>=', $this->from);
        }
        if ($this->to) {
            $query->whereDate('purchase_date', '<=', $this->to);
        }
        if ($this->supplier) {
            $query->where('supplier_name', 'like', '%' . $this->supplier . '%');
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            ['LAPORAN PEMBELIAN BARANG MAYOKA'],
            ['Periode:', ($this->from ?: 'Awal') . ' s/d ' . ($this->to ?: 'Akhir')],
            ['Supplier:', $this->supplier ?: 'Semua'],
            [''],
            [
                'Tanggal',
                'No. Pembelian',
                'Supplier',
                'Status',
                'Dicatat Oleh',
                'Total',
            ]
        ];
    }

    public function map($purchase): array
    {
        return [
            $purchase->purchase_date->format('d/m/Y'),
            $purchase->purchase_number,
            $purchase->supplier_name ?: '-',
            $purchase->payment_status === 'paid' ? 'Lunas' : 'Belum Lunas',
            $purchase->user ? $purchase->user->name : 'Sistem',
            $purchase->total_amount,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true]],
            3 => ['font' => ['italic' => true]],
            5 => ['font' => ['bold' => true]],
        ];
    }
}
