# Database Design

The POS is a **single-business** system on MySQL. Tests use in-memory SQLite, and every query is written to work on both.

This document covers the current schema. Update it whenever a migration is added.

## Overview (catalog)

```
categories 1───* products *───1 brands                (category / brand optional)
                    │
                    ├──* product_variants              (0..n per product)
                    └──*─* modifier_groups ──* modifiers  (via modifier_group_product)
```

There is **no inventory / stock tracking**. It was built on Day 2 and removed on 2026-09-24 by migration `2026_09_24_100006_remove_inventory`, which dropped `stock_movements` and the stock columns. See [products.md](products.md#removed-inventory).

## Design decisions

### SKU and barcode
- Each **SKU** and **barcode** must be unique **across products and variants combined**, so a scan identifies exactly one sellable item. Each table has its own `UNIQUE` index, and `App\Rules\UniqueItemCode` checks across both tables.
- Barcodes are nullable, and MySQL allows many `NULL`s in a unique index, so any number of items can have no barcode.
- Codes of soft-deleted rows stay reserved, so future sales history never points at an ambiguous code.

### Deletion
- **Products and variants are soft-deleted**, because future sales will reference them.
- **Categories and brands are hard-deleted**, but only when no product uses them. Foreign keys are `nullOnDelete`, so products that were already soft-deleted simply lose the link.

### Variants
- A product *is* a variant product when it has non-deleted variants. There is no type column, so the two can't disagree.
- Variant prices and costs are nullable: **NULL = use the product's price**.

## Tables

### categories
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(100) | unique |
| slug | varchar(120) | unique; generated from name if blank |
| description | text null | |
| image_path | varchar null | path on the `public` disk |
| is_active | bool | indexed; inactive = hidden from POS |
| sort_order | smallint unsigned | index (sort_order, name) |
| timestamps | | |

### brands
Same shape as categories, with `logo_path` instead of `image_path` and no `sort_order`.

### products
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| category_id | FK null | → categories, null on delete |
| brand_id | FK null | → brands, null on delete |
| name | varchar(150) | indexed |
| sku | varchar(64) | unique; auto `SKU-XXXXXX` if blank |
| barcode | varchar(64) null | unique |
| description | text null | |
| image_path | varchar null | |
| purchase_price | decimal(12,2) | default 0 |
| selling_price | decimal(12,2) | default price for variants |
| tax_rate | decimal(5,2) | percent, default 0 |
| discount_type | varchar(10) null | `percent` / `fixed` (`App\Enums\DiscountType`) |
| discount_value | decimal(12,2) | default 0 |
| is_active | bool | index (is_active, category_id) |
| timestamps, deleted_at | | soft deletes |

### product_variants
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| product_id | FK | → products, cascade |
| name | varchar(100) | unique per product among non-deleted variants (validated, not indexed) |
| sku | varchar(64) | unique; defaults to `{PRODUCT-SKU}-{NAME}` |
| barcode | varchar(64) null | unique |
| purchase_price, selling_price | decimal(12,2) null | **NULL = use the product's price** |
| is_active | bool | |
| sort_order | smallint unsigned | index (product_id, sort_order) |
| timestamps, deleted_at | | soft deletes |

### modifier_groups
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(100) | unique |
| min_select | tinyint unsigned | 0 = optional |
| max_select | tinyint unsigned null | NULL = no limit |
| is_active | bool | |
| sort_order | smallint unsigned | |
| timestamps | | |

### modifiers
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| modifier_group_id | FK | cascade |
| name | varchar(100) | unique per group |
| price | decimal(12,2) | added to the line price; 0 allowed |
| is_active | bool | |
| sort_order | smallint unsigned | |
| timestamps | | |

### modifier_group_product (pivot)
`product_id` + `modifier_group_id` (composite primary key, both cascade) and `sort_order`.

## Purchasing & customers (Day 3)

```
suppliers 1───* purchases *───1 users (created_by)
                    │
                    └──* purchase_items *──1 products
                                     └──? product_variants
customers  1───* sales (see below)
```

### suppliers
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(150) | indexed |
| company_name | varchar(150) null | indexed |
| phone | varchar(30) null | indexed |
| email | varchar(150) null | indexed |
| address, notes | text null | |
| tax_number, registration_number | varchar(50) null | |
| status | varchar(20) | `active` / `inactive` (`App\Enums\RecordStatus`), indexed |
| timestamps | | |

### customers
Same pattern: id, name, phone, email, address, notes, status and timestamps. Phone is indexed but not unique.

### purchases
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| purchase_number | varchar(20) | unique; `PUR-` + id padded to 6, set inside the create transaction |
| supplier_id | FK | → suppliers, **restrict** (suppliers with purchases can't be deleted) |
| purchase_date | date | indexed; index (supplier_id, purchase_date) |
| subtotal, discount, tax, total | decimal(14,2) | computed on the server |
| payment_status | varchar(20) | `unpaid` / `partially_paid` / `paid`, indexed |
| status | varchar(20) | `draft` / `completed` / `cancelled`, indexed |
| notes | text null | |
| created_by | FK null | → users, null on delete |
| timestamps | | |

### purchase_items
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| purchase_id | FK | → purchases, cascade |
| product_id | FK | → products, restrict (products are soft-deleted, so this never blocks) |
| variant_id | FK null | → product_variants, restrict |
| quantity | decimal(12,3) | > 0 |
| purchase_price | decimal(14,2) | unit cost |
| discount, tax | decimal(14,2) | amounts for this line |
| line_total | decimal(14,2) | quantity × price − discount + tax |
| timestamps | | |

Index: (product_id, variant_id).

## POS sales (Day 4)

```
customers ?──* sales *──? users (cashier)
                 │
                 ├──* sale_items *──1 products
                 │        │    └───? product_variants
                 │        └──* sale_item_modifiers ──? modifiers
                 └──* sale_payments
number_sequences   (invoice counter)
```

Sales **do not change stock**. Names and prices are copied onto each line, so later catalog changes never alter a past sale. Every amount is calculated by `App\Services\SaleService` (see [pos-and-sales.md](pos-and-sales.md)).

### sales
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| invoice_number | varchar(20) null | unique; `INV-000001`…, set at checkout; NULL for held sales |
| customer_id | FK null | → customers, **restrict**; NULL = Walk-in Customer |
| customer_name | varchar(150) null | as typed at the POS, else the linked customer's name; all empty = Walk-in Customer |
| customer_phone | varchar(30) null | indexed |
| customer_address | text null | delivery address |
| user_id | FK null | → users (cashier), null on delete |
| status | varchar(20) | `held` / `completed` / `cancelled` (`App\Enums\SaleStatus`), indexed |
| subtotal | decimal(14,2) | Σ quantity × unit price |
| item_discount | decimal(14,2) | Σ line discounts |
| order_discount_type | varchar(10) null | `fixed` / `percent` |
| order_discount_value | decimal(14,2) | as entered (amount or %) |
| order_discount | decimal(14,2) | amount |
| discount | decimal(14,2) | item_discount + order_discount |
| tax | decimal(14,2) | Σ line tax |
| delivery_fee | decimal(14,2) | added after tax; not taxed or discounted |
| total | decimal(14,2) | subtotal − discount + tax + delivery_fee |
| payment_method | varchar(10) null | `cash` / `card` / `mixed` (`App\Enums\PaymentMethod`) |
| cash_received | decimal(14,2) null | cash handed over (cash), or the cash part (mixed) |
| change_amount | decimal(14,2) | cash_received − total for cash sales, else 0 |
| completed_at | timestamp null | indexed; index (status, completed_at) |
| business_date | date null | the business day the sale belongs to (from Settings → General → Business day start); indexed |
| edit_version | tinyint unsigned | 0 = original, incremented on each edit |
| timestamps | | |

### sale_items
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| sale_id | FK | → sales, cascade |
| product_id | FK | → products, restrict (products are soft-deleted) |
| variant_id | FK null | → product_variants, restrict |
| product_name, variant_name, sku | varchar | snapshot |
| quantity | int unsigned | ≥ 1 |
| unit_price | decimal(14,2) | after product discount, **including** add-ons |
| modifiers_total | decimal(14,2) | add-on part of unit_price |
| discount_type, discount_value | varchar(10) null, decimal(14,2) | line discount as entered |
| discount | decimal(14,2) | line discount amount |
| order_discount | decimal(14,2) | this line's share of the order discount |
| tax_rate | decimal(5,2) | snapshot of the product's rate |
| tax | decimal(14,2) | (total − order_discount) × tax_rate % |
| total | decimal(14,2) | quantity × unit_price − discount |
| timestamps | | |

Index: (product_id, variant_id).

### sale_item_modifiers
id, `sale_item_id` (cascade), `modifier_id` (null on delete), `group_name`, `name`, `price` decimal(12,2). All values are snapshots.

### sale_payments
id, `sale_id` (cascade), `payment_method` (`cash` / `card`, indexed), `amount` decimal(14,2), timestamps. A cash or card sale has one row and a mixed sale has two. No card details are stored.

### sale_edits
Audit log of every post-checkout edit to a completed sale.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| sale_id | FK | → sales, cascade |
| edit_version | tinyint unsigned | the version number being replaced (0 = original was replaced to make version 1) |
| snapshot | json | full copy of the sale totals, items and payment before this edit |
| edited_by | FK null | → users, null on delete |
| note | text null | optional reason for the edit |
| timestamps | | |

### number_sequences
`name` (PK, e.g. `invoice`) and `value` (last number used). It is incremented under a row lock inside the checkout transaction, so numbers are unique and gap-free.

## Restaurant & kitchen (Day 5)

```
restaurant_tables 1──* sales ──* kitchen_orders ──* kot_items ──1 sale_items
        │                              │                │  ├──1 products
        └──────────────* kitchen_orders┘                │  ├──? product_variants
kitchen_stations 1──* categories / products             └──? kitchen_stations
```

KOTs never change stock (there is no inventory). See [restaurant-and-kitchen.md](restaurant-and-kitchen.md).

### restaurant_tables
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(50) | unique; table number/name, e.g. "Table 12" |
| section | varchar(50) null | location, e.g. Patio |
| capacity | tinyint unsigned null | seats |
| status | varchar(20) | `available` / `occupied` / `reserved` (`App\Enums\TableStatus`), indexed |
| is_active | bool | indexed; inactive = hidden from the POS |
| sort_order | smallint unsigned | index (sort_order, name) |
| timestamps | | |

### kitchen_stations
id, `name` (unique), `description` (null), `is_active`, `sort_order`, timestamps.

`categories.kitchen_station_id` and `products.kitchen_station_id` are nullable FKs (null on delete). A product's station overrides its category's.

### Columns added to sales and sale_items
| Column | Type | Notes |
|---|---|---|
| sales.order_type | varchar(20) | `dine_in` / `take_away` / `delivery` (`App\Enums\OrderType`), default `take_away`, indexed |
| sales.table_id | FK null | → restaurant_tables, **restrict**; Dine In only |
| sales.order_note | text null | general note for the kitchen |
| sale_items.note | varchar(200) null | item note, e.g. "No onions" |

### kitchen_orders (KOTs)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| kot_number | varchar(20) | unique; `KOT-1`, `KOT-2`, … sequential per business day (resets at midnight or the configured business-day start); backed by `number_sequences` (`kot`) |
| sale_id | FK | → sales, cascade; **not unique**, so a sale can have several KOTs |
| table_id | FK null | → restaurant_tables, null on delete |
| order_type | varchar(20) | copied from the sale |
| status | varchar(20) | `pending` / `preparing` / `ready` / `completed` / `cancelled`, worked out from the items |
| notes | text null | the order note |
| business_date | date null | the business day this KOT was created on (matches the parent sale's business_date) |
| created_by | FK null | → users (cashier), null on delete |
| started_at, ready_at, completed_at | timestamp null | when each stage was reached |
| timestamps | | index (status, created_at) |

### kot_items
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| kitchen_order_id | FK | → kitchen_orders, cascade |
| sale_item_id | FK | → sale_items, cascade; names, variant and add-ons are read from here |
| product_id | FK | → products, restrict |
| variant_id | FK null | → product_variants, restrict |
| kitchen_station_id | FK null | → kitchen_stations, null on delete |
| quantity | int unsigned | |
| notes | varchar(200) null | item note |
| status | varchar(20) | per-item kitchen status |
| timestamps | | index (kitchen_station_id, status) |

## Returns, expenses & settings (Day 6)

None of these tables change stock. See [returns-and-expenses.md](returns-and-expenses.md) and [settings-and-printing.md](settings-and-printing.md).

### returns (model `SaleReturn`)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| return_number | varchar(20) | unique; `RET-000001`… from `number_sequences` (`return`) |
| sale_id | FK | → sales, restrict |
| customer_id | FK null | → customers, null on delete |
| return_date | date | indexed; index (status, return_date) |
| subtotal | decimal(14,2) | returned items at their sale price |
| refund_amount | decimal(14,2) | calculated on the server (paid amount per unit, to the cent) |
| refund_method | varchar(10) | `cash` / `card` / `bank` / `other` (`MoneyMethod`) |
| reason | varchar(100) | `ReturnReason` |
| status | varchar(20) | `pending` / `completed` / `cancelled`, indexed |
| notes | text null | |
| processed_by | FK null | → users, null on delete |
| timestamps | | |

### return_items
id, `return_id` (cascade), `sale_item_id` (restrict), `product_id` (restrict), `variant_id` (null, restrict), `quantity` (int), `unit_price`, `refund_amount` (decimal 14,2), `reason` (null), timestamps.

### Columns added for returns
| Column | Type | Notes |
|---|---|---|
| sale_items.returned_quantity | int unsigned | units in pending and completed returns |
| sales.return_status | varchar(20) | `none` / `partial` / `full`, indexed |
| sales.returned_amount | decimal(14,2) | completed refunds |

### expense_categories
id, `name` (unique), `description` (null), `status` (`active` / `inactive`, indexed), timestamps.

### expenses
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| expense_number | varchar(20) | unique; `EXP-000001`… from `number_sequences` (`expense`) |
| category_id | FK | → expense_categories, **restrict** |
| amount | decimal(14,2) | > 0 |
| expense_date | date | indexed; index (category_id, expense_date) |
| payment_method | varchar(10) | `MoneyMethod`, indexed |
| description | text null | |
| reference | varchar(100) null | bill or receipt number |
| created_by | FK null | → users, null on delete |
| timestamps | | |

### settings
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| key | varchar(100) | unique, e.g. `business.name`, `receipt.customer` |
| value | longtext null | cast by `type` |
| type | varchar(20) | `string` / `integer` / `decimal` / `boolean` / `json` |
| group | varchar(50) | settings section, indexed |
| timestamps | | |

## Pre-existing tables
- Day 1: `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs` (Laravel defaults)
- Spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
