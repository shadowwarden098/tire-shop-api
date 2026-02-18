POST http://localhost:8000/api/expenses
Content-Type: application/json

{
  "description": "Compra de llantas al proveedor",
  "category": "compra_inventario",
  "amount_usd": 1000.00,
  "payment_method": "transferencia",
  "supplier": "Distribuidora XYZ",
  "expense_date": "2024-02-16"
}