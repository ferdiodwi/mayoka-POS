<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\ReturnTransaction;

class CashFlowExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
        // Use the same raw SQL query from ReportController to get daily cash flow
        $dailyCashFlow = DB::select("
            SELECT dates.date,
                COALESCE(cash_in.total, 0) as cash_in,
                COALESCE(cash_out.total, 0) as cash_out
            FROM (
                SELECT DATE(created_at) as date FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
                UNION
                SELECT purchase_date as date FROM purchases
                WHERE purchase_date BETWEEN ? AND ?
                UNION
                SELECT expense_date as date FROM expenses
                WHERE expense_date BETWEEN ? AND ?
                GROUP BY date
            ) dates
            LEFT JOIN (
                SELECT DATE(created_at) as date, SUM(total) as total
                FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY date
            ) cash_in ON dates.date = cash_in.date
            LEFT JOIN (
                SELECT date, SUM(total) as total FROM (
                    SELECT purchase_date as date, SUM(total_amount) as total
                    FROM purchases WHERE purchase_date BETWEEN ? AND ? AND payment_status = 'paid'
                    GROUP BY purchase_date
                    UNION ALL
                    SELECT expense_date as date, SUM(amount) as total
                    FROM expenses WHERE expense_date BETWEEN ? AND ?
                    GROUP BY expense_date
                    UNION ALL
                    SELECT DATE(created_at) as date, SUM(refund_amount) as total
                    FROM returns WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY date
                ) combined GROUP BY date
            ) cash_out ON dates.date = cash_out.date
            ORDER BY dates.date
        ", [$this->from, $this->to, $this->from, $this->to, $this->from, $this->to, $this->from, $this->to, $this->from, $this->to, $this->from, $this->to, $this->from, $this->to]);

        return collect($dailyCashFlow);
    }

    public function headings(): array
    {
        return [
            ['LAPORAN ARUS KAS (CASH FLOW) MAYOKA'],
            ['Periode:', $this->from . ' s/d ' . $this->to],
            [''],
            [
                'Tanggal',
                'Kas Masuk',
                'Kas Keluar',
                'Arus Kas Bersih',
            ]
        ];
    }

    public function map($row): array
    {
        $net = $row->cash_in - $row->cash_out;
        return [
            $row->date,
            $row->cash_in,
            $row->cash_out,
            $net,
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
