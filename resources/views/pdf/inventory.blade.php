<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; background: #fff; }

  .header { background: #1a3a5c; color: #fff; padding: 20px 30px; }
  .header h1 { font-size: 21px; font-weight: 700; letter-spacing: 1px; }
  .header p  { font-size: 10px; opacity: .85; margin-top: 4px; }

  .body { padding: 24px 30px; }

  .section-title {
    font-size: 12px; font-weight: 700; color: #1a3a5c;
    border-bottom: 2px solid #1a3a5c; padding-bottom: 4px;
    margin: 18px 0 10px; text-transform: uppercase; letter-spacing: .5px;
  }

  /* KPI row */
  .kpi-grid { display: table; width: 100%; border-collapse: separate; border-spacing: 8px; }
  .kpi-row  { display: table-row; }
  .kpi-cell { display: table-cell; width: 20%; }
  .kpi-card {
    background: #f0f4f8; border: 1px solid #d0dce8; border-radius: 6px;
    padding: 11px 12px; text-align: center;
  }
  .kpi-card .label { font-size: 8px; color: #666; text-transform: uppercase; letter-spacing: .4px; }
  .kpi-card .value { font-size: 14px; font-weight: 700; color: #1a3a5c; margin-top: 4px; }
  .kpi-card .value.warn { color: #e67e22; }

  /* Table */
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9.5px; }
  thead th {
    background: #1a3a5c; color: #fff; padding: 7px 8px;
    text-align: left; font-weight: 600; text-transform: uppercase; font-size: 8.5px;
  }
  tbody tr:nth-child(even) { background: #f4f7fa; }
  tbody td { padding: 5px 8px; border-bottom: 1px solid #e0e8f0; vertical-align: middle; }
  tbody td.right  { text-align: right; }
  tbody td.center { text-align: center; }

  .badge-ok   { background:#d4edda; color:#155724; padding:2px 6px; border-radius:3px; font-size:8px; }
  .badge-warn { background:#fff3cd; color:#856404; padding:2px 6px; border-radius:3px; font-size:8px; }
  .badge-low  { background:#f8d7da; color:#721c24; padding:2px 6px; border-radius:3px; font-size:8px; }

  .footer {
    margin-top: 28px; padding: 12px 30px; background: #f4f4f4;
    border-top: 1px solid #ddd; font-size: 9px; color: #888; text-align: center;
  }
</style>
</head>
<body>

{{-- ═══ HEADER ═══════════════════════════════════════════ --}}
<div class="header">
  <h1>&#128230; Reporte de Inventario — Importaciones Adan</h1>
  <p>Tipo de cambio: S/ {{ $data['exchange_rate']['buy'] }} (compra) / S/ {{ $data['exchange_rate']['sell'] }} (venta)
     &nbsp;|&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}</p>
</div>

<div class="body">

  {{-- ═══ KPIs ═══════════════════════════════════════════ --}}
  <div class="section-title">Resumen de Inventario</div>
  <div class="kpi-grid">
    <div class="kpi-row">
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Productos Activos</div>
          <div class="value">{{ $data['summary']['total_products'] }}</div>
        </div>
      </div>
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Stock Total</div>
          <div class="value">{{ $data['summary']['total_stock'] }}</div>
        </div>
      </div>
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Valor (USD)</div>
          <div class="value">$ {{ number_format($data['summary']['total_value_usd'], 2) }}</div>
        </div>
      </div>
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Valor (PEN)</div>
          <div class="value">S/ {{ number_format($data['summary']['total_value_pen'], 2) }}</div>
        </div>
      </div>
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Stock Crítico</div>
          <div class="value {{ $data['summary']['low_stock_count'] > 0 ? 'warn' : '' }}">
            {{ $data['summary']['low_stock_count'] }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══ TABLA DE PRODUCTOS ════════════════════════════ --}}
  <div class="section-title">Detalle de Productos</div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Producto</th>
        <th>Marca / Modelo</th>
        <th>Medida</th>
        <th class="center">Stock</th>
        <th class="right">Costo USD</th>
        <th class="right">Precio PEN</th>
        <th class="right">Valor Stock S/</th>
        <th class="center">Estado</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($data['products'] as $i => $p)
      <tr>
        <td class="center">{{ $i + 1 }}</td>
        <td>{{ $p['name'] }}</td>
        <td>{{ $p['brand'] }} {{ $p['model'] }}</td>
        <td class="center">{{ $p['size'] }}</td>
        <td class="center">{{ $p['stock'] }}</td>
        <td class="right">$ {{ number_format($p['cost_usd'], 2) }}</td>
        <td class="right">S/ {{ number_format($p['price_pen'], 2) }}</td>
        <td class="right">S/ {{ number_format($p['stock_value_pen'], 2) }}</td>
        <td class="center">
          @if ($p['stock'] == 0)
            <span class="badge-low">Sin stock</span>
          @elseif ($p['is_low_stock'])
            <span class="badge-warn">Stock bajo</span>
          @else
            <span class="badge-ok">OK</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- ═══ PRODUCTOS CON STOCK CRÍTICO ══════════════════ --}}
  @php
    $lowStockProducts = collect($data['products'])->where('is_low_stock', true);
  @endphp

  @if ($lowStockProducts->count() > 0)
  <div class="section-title" style="margin-top:20px; color:#c0392b; border-color:#c0392b;">
    &#9888; Alerta: Productos con Stock Bajo
  </div>
  <table>
    <thead style="background:#c0392b;">
      <tr>
        <th>Producto</th>
        <th>Marca / Modelo</th>
        <th class="center">Stock Actual</th>
        <th class="right">Valor Restante S/</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($lowStockProducts as $p)
      <tr>
        <td>{{ $p['name'] }}</td>
        <td>{{ $p['brand'] }} {{ $p['model'] }}</td>
        <td class="center" style="color:#c0392b; font-weight:700;">{{ $p['stock'] }}</td>
        <td class="right">S/ {{ number_format($p['stock_value_pen'], 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

</div>

<div class="footer">
  Importaciones Adan — Sistema de Gestión &nbsp;|&nbsp; Documento generado automáticamente — {{ now()->format('d/m/Y H:i:s') }}
</div>
</body>
</html>