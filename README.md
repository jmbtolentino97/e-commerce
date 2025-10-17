# 📦 E-commerce Order Management API

A **Laravel 12 RESTful API** for managing customers, products, orders, discounts, and inventory — built with clean architecture, JWT authentication, and full test coverage via Pest.  
Designed to showcase backend development best practices.

---

## ⚙️ Tech Stack

- **Laravel 12**, PHP 8.2+
- **JWT Authentication** — [`tymon/jwt-auth`](https://github.com/tymondesigns/jwt-auth)
- **Filtering / Sorting / Includes** — [`spatie/laravel-query-builder`](https://spatie.be/docs/laravel-query-builder/v6/introduction)
- **Testing** — [Pest](https://pestphp.com)
- **Database** — MySQL (SQLite used in tests)
- **Password hashing** — `Hash::make()`

---

## 🚀 Features

### 🔐 Authentication
- JWT login, refresh, logout, and `/me` profile endpoints
- Role-based access (admin, staff)
- Token expiry & refresh logic handled by JWTAuth

### 👥 Customers
- CRUD operations with validation
- Filters: email, first/last name, date ranges
- Sorts: created_at, last_name
- Spatie Query Builder support for filtering, sorting, and includes (`orders`)

### 🛒 Products
- CRUD operations with soft deletes
- Price, cost, inventory tracking (`track_inventory`)
- Filters: SKU, name, is_active, price range
- Sorts: name, price, created_at
- Includes: `inventoryMovements`
- Uses the `product_current_stock` DB view to compute stock on hand

### 📦 Orders
- Lifecycle: draft → pending_payment → paid → fulfilled → cancelled
- Add/update/remove items while in draft
- Place → reserves stock  
  Pay → marks paid  
  Fulfill → converts reservations to sales  
  Cancel → releases reservations
- Totals recalculated on every change
- Filters: status, customer, totals, date ranges
- Sorts: created_at, grand_total
- Includes: customer, items.product, discountApplications
- Protected endpoints (jwt.auth)

### 🎟️ Discounts
- CRUD with validation
- Types: percentage, fixed, free_shipping
- Target: order or item
- Optional usage limits (global/per-customer)
- Optional min/max amounts
- Stackable flag to control combinations
- Active date window (`starts_at`, `ends_at`)
- Scope filters and active logic
- Integrated into order flow via `DiscountApplicationService`

### 📊 Inventory Reports
- Real-time stock-on-hand (from view)
- Movement history (purchases, sales, returns, adjustments, reservations, releases)
- Filters: product_id, type, date ranges
- Sorts: created_at asc/desc
- Includes: product
- Compatible with MySQL and SQLite
- Reports support pagination, sorting, and filters via Query Builder or raw joins

---

## 🧱 API Endpoints Overview

### Auth
| Method | Endpoint | Description |
|--------|-----------|--------------|
| POST | `/api/auth/login` | Login and get JWT |
| GET | `/api/me` | Get current user |
| POST | `/api/auth/refresh` | Refresh token |
| POST | `/api/auth/logout` | Logout |

### Customers
| Method | Endpoint | Description |
|--------|-----------|--------------|
| GET | `/api/customers` | List customers (filters/sorts/includes) |
| GET | `/api/customers/{id}` | View single customer |
| POST | `/api/customers` | Create customer |
| PUT | `/api/customers/{id}` | Update customer |
| DELETE | `/api/customers/{id}` | Delete customer |

### Products
| Method | Endpoint | Description |
|--------|-----------|--------------|
| GET | `/api/products` | List products (filters/sorts/includes) |
| GET | `/api/products/{id}` | View single product |
| POST | `/api/products` | Create product |
| PUT | `/api/products/{id}` | Update product |
| DELETE | `/api/products/{id}` | Soft delete product |

### Orders
| Method | Endpoint | Description |
|--------|-----------|--------------|
| GET | `/api/orders` | List orders |
| GET | `/api/orders/{id}` | View order |
| POST | `/api/orders` | Create draft |
| POST | `/api/orders/{id}/items` | Add item |
| PUT | `/api/orders/{id}/items/{item}` | Update item |
| DELETE | `/api/orders/{id}/items/{item}` | Remove item |
| POST | `/api/orders/{id}/apply-discount` | Apply discount |
| DELETE | `/api/orders/{id}/remove-discounts` | Remove all discounts |
| POST | `/api/orders/{id}/place` | Place order (reserve stock) |
| POST | `/api/orders/{id}/pay` | Mark as paid |
| POST | `/api/orders/{id}/fulfill` | Fulfill (release + sale) |
| POST | `/api/orders/{id}/cancel` | Cancel (release reservations) |

### Discounts
| Method | Endpoint | Description |
|--------|-----------|--------------|
| GET | `/api/discounts` | List discounts (filters/sorts) |
| GET | `/api/discounts/{id}` | View discount |
| POST | `/api/discounts` | Create discount |
| PUT | `/api/discounts/{id}` | Update discount |
| DELETE | `/api/discounts/{id}` | Delete discount |

### Inventory Reports
| Method | Endpoint | Description |
|--------|-----------|--------------|
| GET | `/api/inventory/stock` | Paginated stock-on-hand list |
| GET | `/api/inventory/stock/{product}` | Stock-on-hand for one product |
| GET | `/api/inventory/movements` | Movement history (filters/sorts/includes) |

---

## 🔎 Filtering & Sorting Examples

| Use Case | Example |
|-----------|----------|
| Filter products by name | `/api/products?filter[name]=alpha` |
| Price range | `/api/products?filter[min_price]=10&filter[max_price]=100` |
| Filter orders by status | `/api/orders?filter[status]=paid` |
| Orders within date range | `/api/orders?filter[min_created_at]=2025-10-01&filter[max_created_at]=2025-10-31` |
| Include related data | `/api/orders?include=customer,items.product` |
| Sort by newest orders | `/api/orders?sort=-created_at` |
| Filter inventory movements | `/api/inventory/movements?filter[type]=sale&filter[min_created_at]=2025-10-01&filter[max_created_at]=2025-10-31` |

---

## 🧪 Testing

- All tests use **Pest** with `test()` blocks.
- Run full suite:

```bash
php artisan migrate:fresh --seed
php artisan test
```

**Example passing tests include:**

- Authentication (login/refresh/logout)
- Product CRUD & filters
- Customer CRUD
- Order lifecycles (draft → paid → fulfilled → cancel)
- Discount application rules (stackable, usage limits)
- Inventory reports (stock and movements)

---

## 🧰 Postman Collection

📘 **Postman Workspace:** [E-commerce Order Management API](https://www.postman.com/solar-crescent-501963/e-commerce/overview)  
_(updated collection includes Auth, Customers, Products, Orders, Discounts, and Inventory Reports)_

Each request includes:
- JWT pre-request script (auto injects `Bearer` token)
- Sample payloads for each module
- Environment variables (`{{host}}`, `{{token}}`, etc.)

---

## 📑 Project Scope Summary

| Area | Description |
|------|--------------|
| Authentication | JWT-based, stateless |
| Customers | CRUD + filters |
| Products | CRUD + soft delete + inventory tracking |
| Orders | Draft workflow, totals, item management |
| Discounts | Configurable with rules & limits |
| Inventory | Movement ledger + stock-on-hand view |
| Reports | Stock & movement history |
| Testing | Full Pest coverage |

---

## 🧭 Roadmap

| Milestone | Status |
|------------|----------|
| Database schema | ✅ |
| JWT authentication | ✅ |
| Customers module | ✅ |
| Products module | ✅ |
| Orders & lifecycle | ✅ |
| Discounts with limits | ✅ |
| Inventory reports | ✅ |
| Postman collection | ✅ |

---

## 🧠 Developer Notes

- All queries tested against both MySQL and SQLite (test environment).
- Database view `product_current_stock` rebuilt automatically in migrations and tests.
- Services follow SRP:
  - `OrderTotalsService` for recomputation
  - `StockReservationService` for stock events
  - `DiscountApplicationService` for validation and limits
- Code adheres to SOLID principles and clean Laravel conventions.
