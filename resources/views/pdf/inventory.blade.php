<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Reporte de Inventario</title>
<style>body{font-family: Arial, sans-serif; font-size:12px;}h1{font-size:18px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:4px;text-align:left;}</style>
</head><body>
<h1>Reporte de Inventario</h1>
<table><thead><tr><th>Producto</th><th>Marca</th><th>Stock</th><th>Valor PEN</th></tr></thead><tbody>
@foreach($data['products'] ?? [] as $p)
<tr><td>{{ $p['name'] }}</td><td>{{ $p['brand'] }}</td><td>{{ $p['stock'] }}</td><td>S/ {{ $p['stock_value_pen'] }}</td></tr>
@endforeach
</tbody></table>
</body></html>