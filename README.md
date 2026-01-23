# 💳 Payment Gateway API – E-Commerce Backend System

> **RESTful API backend untuk sistem e-commerce dengan integrasi Midtrans Payment Gateway**

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?logo=postgresql)
![Midtrans](https://img.shields.io/badge/Midtrans-Payment_Gateway-00A6E5)

---

## 📌 Overview

**Payment Gateway API** adalah backend system untuk aplikasi e-commerce yang menangani:

* Manajemen user & autentikasi
* Manajemen produk & stok
* Pemesanan (order)
* Pembayaran online melalui **Midtrans**

Project ini dibangun dengan fokus pada **clean architecture**, **security**, dan **real-time payment handling** menggunakan webhook.

---

## 🚀 Key Features

* 🔐 Authentication & Authorization (Laravel Sanctum)
* 🛍 Product & Stock Management (auto decrease & rollback)
* 📦 Order Management (pending, paid, cancelled, expired)
* 💳 Midtrans Integration (Snap, VA, QRIS, E-Wallet, Credit Card)
* 🔔 Real-time payment update via Webhook
* 🧾 Payment & audit logging
* 🐳 Dockerized PostgreSQL

---

## 🛠 Tech Stack

| Layer    | Technology           |
| -------- | -------------------- |
| Backend  | Laravel 11, PHP 8.2  |
| Database | PostgreSQL 16        |
| Auth     | Laravel Sanctum      |
| Payment  | Midtrans Snap API    |
| Tools    | Docker, Postman, Git |

---

## 🏗 System Architecture

**Layered Architecture Approach**

* **Client Layer**: Web / Mobile / Postman
* **API Gateway Layer**: Laravel Routes & Middleware
* **Business Logic Layer**: Auth, Product, Order, Payment Controllers
* **Data Access Layer**: Eloquent ORM
* **External Service**: Midtrans API
* **Database Layer**: PostgreSQL (ACID compliant)

> Architecture dirancang agar scalable, maintainable, dan mudah dikembangkan.

---

## 🗄 Database Design (ERD)

### Main Entities

* **users** – user & role management
* **products** – product catalog & stock
* **orders** – order records
* **order_items** – detail item per order
* **payments** – payment records
* **payment_logs** – audit trail & webhook logs

**Relasi utama:**

* User → Orders (1:N)
* Order → Order Items (1:N)
* Order → Payment (1:1)
* Payment → Payment Logs (1:N)

---

## 🔌 API Endpoints (Summary)

### Auth

* `POST /api/register`
* `POST /api/login`
* `GET /api/user`
* `POST /api/logout`

### Products

* `GET /api/products`
* `GET /api/products/{id}`
* `POST /api/products` *(Admin)*
* `PUT /api/products/{id}` *(Admin)*
* `DELETE /api/products/{id}` *(Admin)*

### Orders

* `POST /api/orders`
* `GET /api/orders`
* `GET /api/orders/{id}`
* `POST /api/orders/{id}/cancel`

### Payment

* `POST /api/payment/{orderId}`
* `GET /api/payment/{orderId}/status`
* `POST /api/payment/notification` *(Webhook)*

---

## 💰 Payment Flow (Simplified)

1. User membuat order
2. Sistem validasi stok & create order
3. Client request pembayaran
4. Backend generate **Snap Token**
5. User melakukan pembayaran
6. Midtrans kirim webhook
7. Backend update status order & payment

---

## 🔒 Security Implementation

* Token-based authentication (Sanctum)
* Role-based access control
* Request validation (FormRequest)
* Database transaction (ACID)
* Webhook signature verification
* Environment variable protection

---

## 🧪 Testing

* API testing menggunakan **Postman**
* Midtrans **Sandbox Environment**
* Webhook testing untuk payment status update

---

## 📚 What I Learned

* Integrasi payment gateway real-world
* Webhook handling & async payment flow
* Transaction-safe database design
* Secure REST API development
* Clean & scalable backend architecture

---

## 📌 Notes

Project ini dibuat untuk keperluan **portfolio backend developer** dan simulasi sistem pembayaran e-commerce skala production.

---

## 👤 Author

**Said Hamzah**
Backend Developer (Laravel)

📫 Feel free to connect on LinkedIn or review this repository.
