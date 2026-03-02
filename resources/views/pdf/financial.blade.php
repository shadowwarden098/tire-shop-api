<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }

  .header { background: #c0392b; color: #fff; padding: 20px 30px; }
  .header h1 { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
  .header p  { font-size: 10px; opacity: .85; margin-top: 4px; }

  .body { padding: 24px 30px; }

  .section-title {
    font-size: 13px; font-weight: 700; color: #c0392b;
    border-bottom: 2px solid #c0392b; padding-bottom: 4px; margin: 20px 0 10px;
    text-transform: uppercase; letter-spacing: .5px;
  }

  /* KPI cards */
  .kpi-grid { display: table; width: 100%; border-collapse: separate; border-spacing: 8px; }
  .kpi-row  { display: table-row; }
  .kpi-cell { display: table-cell; width: 25%; }
  .kpi-card {
    background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px;
    padding: 12px 14px; text-align: center;
  }
  .kpi-card .label { font-size: 9px; color: #777; text-transform: uppercase; letter-spacing: .4px; }
  .kpi-card .value { font-size: 16px; font-weight: 700; color: #1a1a1a; margin-top: 4px; }
  .kpi-card .value.green { color: #27ae60; }
  .kpi-card .value.red   { color: #c0392b; }

  /* Table */
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
  thead th {
    background: #2c3e50; color: #fff; padding: 7px 10px;
    text-align: left; font-weight: 600; text-transform: uppercase; font-size: 9px;
  }
  tbody tr:nth-child(even) { background: #f4f4f4; }
  tbody td { padding: 6px 10px; border-bottom: 1px solid #e8e8e8; }
  tbody td.right { text-align: right; }
  tbody td.center { text-align: center; }

  .footer {
    margin-top: 30px; padding: 12px 30px; background: #f4f4f4;
    border-top: 1px solid #ddd; font-size: 9px; color: #888; text-align: center;
  }

  .tag-green { background:#d4edda; color:#155724; padding:2px 6px; border-radius:3px; font-size:9px; }
  .tag-red   { background:#f8d7da; color:#721c24; padding:2px 6px; border-radius:3px; font-size:9px; }
</style>
</head>
<body>

{{-- ═══ HEADER ══════════════════════════════════════════ --}}
<div class="header">
  <h1>&#128200; Reporte Financiero — Importaciones Adan</h1>
  <p>Período: {{ $data['date_range']['start'] }} al {{ $data['date_range']['end'] }}
     &nbsp;|&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}</p>
</div>

<div class="body">

  {{-- ═══ RESUMEN KPIs ═══════════════════════════════════ --}}
  <div class="section-title">Resumen Financiero</div>
  <div class="kpi-grid">
    <div class="kpi-row">
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Ingresos Totales</div>
          <div class="value">S/ {{ number_format($data['summary']['total_income'], 2) }}</div>
        </div>
      </div>
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Costos</div>
          <div class="value red">S/ {{ number_format($data['summary']['total_costs'], 2) }}</div>
        </div>
      </div>
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Gastos Operativos</div>
          <div class="value red">S/ {{ number_format($data['summary']['total_expenses'], 2) }}</div>
        </div>
      </div>
      <div class="kpi-cell">
        <div class="kpi-card">
          <div class="label">Utilidad Neta</div>
          <div class="value {{ $data['summary']['net_profit'] >= 0 ? 'green' : 'red' }}">
            S/ {{ number_format($data['summary']['net_profit'], 2) }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Margen --}}
  <p style="margin-top:8px; font-size:10px; color:#555;">
    Utilidad Bruta: <strong>S/ {{ number_format($data['summary']['gross_profit'], 2) }}</strong>
    &nbsp;|&nbsp; Margen Neto: <strong>{{ $data['summary']['profit_margin'] }}%</strong>
  </p>

  {{-- ═══ VENTAS ════════════════════════════════════════ --}}
  <div class="section-title">Ventas</div>
  <p style="margin-bottom:8px; font-size:10px; color:#555;">
    Total ventas: <strong>S/ {{ number_format($data['sales']['total'], 2) }}</strong>
    &nbsp;|&nbsp; Cantidad: <strong>{{ $data['sales']['count'] }}</strong>
  </p>

  {{-- Métodos de pago --}}
  @if (!empty($data['sales']['by_payment_method']))
  <table>
    <thead><tr><th>Método de Pago</th><th>Ventas</th><th>Total S/</th></tr></thead>
    <tbody>
      @foreach ($data['sales']['by_payment_method'] as $method => $info)
      <tr>
        <td>{{ ucfirst($method) }}</td>
        <td class="center">{{ $info['count'] }}</td>
        <td class="right">S/ {{ number_format($info['total'], 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- Top productos --}}
  @if (!empty($data['sales']['top_products']))
  <div class="section-title" style="margin-top:16px;">Productos más Vendidos</div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Producto</th>
        <th>Marca / Modelo</th>
        <th class="right">Cant.</th>
        <th class="right">Ingresos S/</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($data['sales']['top_products'] as $i => $p)
      <tr>
        <td class="center">{{ $i + 1 }}</td>
        <td>{{ $p->name ?? $p['name'] }}</td>
        <td>{{ ($p->brand ?? $p['brand']) . ' ' . ($p->model ?? $p['model']) }}</td>
        <td class="right">{{ $p->total_quantity ?? $p['total_quantity'] }}</td>
        <td class="right">S/ {{ number_format($p->total_revenue ?? $p['total_revenue'], 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- ═══ SERVICIOS ════════════════════════════════════ --}}
  <div class="section-title">Servicios</div>
  <p style="margin-bottom:8px; font-size:10px; color:#555;">
    Total servicios: <strong>S/ {{ number_format($data['services']['total'], 2) }}</strong>
    &nbsp;|&nbsp; Cantidad: <strong>{{ $data['services']['count'] }}</strong>
  </p>

  @if (!empty($data['services']['by_type']))
  <table>
    <thead><tr><th>Servicio</th><th>Cant.</th><th class="right">Total S/</th></tr></thead>
    <tbody>
      @foreach ($data['services']['by_type'] as $type => $info)
      <tr>
        <td>{{ $type ?: 'Sin clasificar' }}</td>
        <td class="center">{{ $info['count'] }}</td>
        <td class="right">S/ {{ number_format($info['revenue'], 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- ═══ GASTOS ════════════════════════════════════════ --}}
  <div class="section-title">Gastos Operativos</div>
  <p style="margin-bottom:8px; font-size:10px; color:#555;">
    Total gastos: <strong>S/ {{ number_format($data['expenses']['total'], 2) }}</strong>
  </p>

  @if (!empty($data['expenses']['by_category']))
  <table>
    <thead><tr><th>Categoría</th><th>Items</th><th class="right">Total S/</th></tr></thead>
    <tbody>
      @foreach ($data['expenses']['by_category'] as $cat => $info)
      <tr>
        <td>{{ ucfirst($cat) }}</td>
        <td class="center">{{ $info['count'] }}</td>
        <td class="right">S/ {{ number_format($info['total'], 2) }}</td>
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