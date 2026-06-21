<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;

class SalesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $from;
    protected $to;

    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function collection()
    {
        return Transaction::with('user')
            ->whereBetween(DB::raw('DATE(created_at)'), [$this->from, $this->to])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            ['LAPORAN PENJUALAN MAYOKA'],
            ['Periode:', $this->from . ' s/d ' . $this->to],
            [''],
            [
                'Tanggal',
                'No. Transaksi',
                'Kasir',
                'Pelanggan',
                'Metode Bayar',
                'Subtotal',
                'Diskon',
                'Pajak',
                'Total',
            ]
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->created_at->format('Y-m-d H:i'),
            $transaction->receipt_number,
            $transaction->user ? $transaction->user->name : 'Sistem',
            $transaction->customer_name ?: '-',
            strtoupper($transaction->payment_method),
            $transaction->subtotal,
            $transaction->discount,
            $transaction->tax,
            $transaction->total,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
