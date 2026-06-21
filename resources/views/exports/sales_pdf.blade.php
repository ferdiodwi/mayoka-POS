<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan MAYOKA</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #555; margin: 5px 0 0 0; }
        .period { margin-bottom: 15px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">MAYOKA</h1>
        <p class="subtitle">Laporan Penjualan</p>
    </div>

    <div class="period">
        Periode: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} - {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>Tanggal</th>
                <th>No. Transaksi</th>
                <th>Kasir</th>
                <th>Metode</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Diskon</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $sumSubtotal = 0;
                $sumDiscount = 0;
                $sumTotal = 0;
            @endphp
            @foreach($transactions as $idx => $t)
                @php
                    $sumSubtotal += $t->subtotal;
                    $sumDiscount += $t->discount;
                    $sumTotal += $t->total;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $t->receipt_number }}</td>
                    <td>{{ $t->user ? $t->user->name : 'Sistem' }}</td>
                    <td class="text-center">{{ strtoupper($t->payment_method) }}</td>
                    <td class="text-right">Rp {{ number_format($t->subtotal, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($t->discount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($t->total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL KESELURUHAN</td>
                <td class="text-right">Rp {{ number_format($sumSubtotal, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($sumDiscount, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($sumTotal, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #777;">
        Dicetak pada: {{ now()->format('d M Y H:i:s') }}
    </div>
</body>
</html>
