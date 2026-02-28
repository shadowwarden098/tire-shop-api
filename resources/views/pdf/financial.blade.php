<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Reporte Financiero</title>
<style>body{font-family: Arial, sans-serif; font-size:12px;} h1{font-size:18px;} table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:4px;text-align:left;} .summary{margin-bottom:20px;}</style>
</head><body>
<h1>Reporte Financiero</h1>
<p>Periodo: {{ $data['date_range']['start'] ?? '' }} – {{ $data['date_range']['end'] ?? '' }}</p>
<div class="summary">
  <strong>Ingresos totales:</strong> S/ {{ $data['summary']['total_income'] ?? '0.00' }}<br>
  <strong>Gastos totales:</strong> S/ {{ $data['summary']['total_expenses'] ?? '0.00' }}<br>
  <strong>Ganancia neta:</strong> S/ {{ $data['summary']['net_profit'] ?? '0.00' }} ({{ $data['summary']['profit_margin'] ?? '0' }}%)
</div>
<h2>Ventas diarias</h2>
<table><thead><tr><th>Fecha</th><th>Ingresos</th></tr></thead><tbody>
@foreach($data['sales']['by_day'] ?? [] as $date => $row)
<tr><td>{{ $date }}</td><td>S/ {{ $row['revenue'] }}</td></tr>
@endforeach
</tbody></table>
</body></html>