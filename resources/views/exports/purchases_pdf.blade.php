<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Pembelian Barang MAYOKA</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #555; margin: 5px 0 0 0; }
        .info-box { margin-bottom: 15px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
        .status-paid { color: #16a34a; font-weight: bold; }
        .status-unpaid { color: #d97706; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">MAYOKA</h1>
        <p class="subtitle">Laporan Pembelian Barang</p>
    </div>

    <div class="info-box">
        Periode: {{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : 'Awal' }} - {{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : 'Akhir' }}<br>
        Supplier: {{ $supplier ?: 'Semua' }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th>Tanggal</th>
                <th>No. Pembelian</th>
                <th>Supplier</th>
                <th>Dicatat Oleh</th>
                <th class="text-center">Status</th>
                <th class="text-right">Total Tagihan</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $sumTotal = 0;
            @endphp
            @foreach($purchases as $idx => $p)
                @php
                    $sumTotal += $p->total_amount;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $p->purchase_date->format('d/m/Y') }}</td>
                    <td>{{ $p->purchase_number }}</td>
                    <td>{{ $p->supplier_name ?: '-' }}</td>
                    <td>{{ $p->user ? $p->user->name : 'Sistem' }}</td>
                    <td class="text-center">
                        @if($p->payment_status === 'paid')
                            <span class="status-paid">Lunas</span>
                        @else
                            <span class="status-unpaid">Belum</span>
                        @endif
                    </td>
                    <td class="text-right">Rp {{ number_format($p->total_amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="text-right">TOTAL KESELURUHAN</td>
                <td class="text-right">Rp {{ number_format($sumTotal, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #777;">
        Dicetak pada: {{ now()->format('d M Y H:i:s') }}
    </div>
</body>
</html>
