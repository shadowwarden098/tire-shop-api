<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

<h1 align="center">Tire Shop System API</h1>

<p align="center">
  Sistema de gestión para una tienda de llantas desarrollado con Laravel
</p>

---

## 📌 Descripción

**Tire Shop System** es una API REST desarrollada en **Laravel**, diseñada para gestionar:

- 📦 Productos (llantas)
- 📉 Control de stock
- 💰 Ventas
- 👥 Clientes
- 🛠️ Servicios
- 💱 Tipo de cambio
- 📊 Reportes

- 🧾 Endpoints de dashboard separados por rol:
  - `/api/reports/staff-dashboard` (vista protegida para personal)
  - `/api/reports/admin-dashboard` (requiere token de admin)
  - `/api/reports/export` (admin) — genera PDF de informes financieros o inventario; instala `barryvdh/laravel-dompdf` para habilitar la exportación.

Este proyecto fue creado como **práctica profesional** y simulación de un **sistema comercial real**.

---

## ⚙️ Tecnologías usadas

- PHP 8.x
- Laravel 10+
- MySQL
- cURL (testing)
- Postman (opcional)

---

## 🚀 Instalación

```bash
git clone https://github.com/TU_USUARIO/tire-shop-system.git
cd tire-shop-system
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# uso
- empleados: GET /api/reports/staff-dashboard
- administradores: GET /api/reports/admin-dashboard (requiere token)
- desde el chat IA el admin puede pedir: "Generar PDF de ganancias" o "Descargar PDF de inventario" y recibirá un enlace.
