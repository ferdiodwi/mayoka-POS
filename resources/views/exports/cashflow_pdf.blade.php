<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Arus Kas MAYOKA</title>
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
        <p class="subtitle">Laporan Arus Kas (Cash Flow)</p>
    </div>

    <div class="period">
        Periode: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} - {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th>Tanggal</th>
                <th class="text-right">Kas Masuk (In)</th>
                <th class="text-right">Kas Keluar (Out)</th>
                <th class="text-right">Arus Kas Bersih (Net)</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $sumIn = 0;
                $sumOut = 0;
                $sumNet = 0;
            @endphp
            @foreach($dailyCashFlow as $idx => $row)
                @php
                    $net = $row->cash_in - $row->cash_out;
                    $sumIn += $row->cash_in;
                    $sumOut += $row->cash_out;
                    $sumNet += $net;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                    <td class="text-right">Rp {{ number_format($row->cash_in, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($row->cash_out, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($net, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL KESELURUHAN</td>
                <td class="text-right">Rp {{ number_format($sumIn, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($sumOut, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($sumNet, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #777;">
        Dicetak pada: {{ now()->format('d M Y H:i:s') }}
    </div>
</body>
</html>
