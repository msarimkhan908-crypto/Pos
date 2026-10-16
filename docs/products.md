# Products & Catalog

Catalog management: categories, brands, products, product images, variants and modifiers (add-ons).

**There is no inventory module.** Stock tracking (stock levels, adjustments, movements and low-stock alerts) was built on Day 2 and then removed at the owner's request on 2026-09-24. Products are catalog-only: prices, variants and add-ons. See [Removed: inventory](#removed-inventory).

Schema details are in [database-design.md](database-design.md).

## Pages

| Page | URL | Permission |
|---|---|---|
| Categories | `/categories` | `categories.view` / `.create` / `.edit` / `.delete` |
| Brands | `/brands` | `brands.*` |
| Products | `/products` | `products.*` |
| Product variants | `/products/{id}/variants/…` | `products.edit` |
| Modifier groups | `/modifier-groups` | view `products.view`, create `products.create`, edit `products.edit`, delete `products.delete` |

Every controller enforces its permission with Spatie middleware, and every Form Request checks it again. Typing a URL directly returns **403**.

## Product types

| Type | Behaviour |
|---|---|
| **Simple product** | One item with one price, SKU and barcode. |
| **Variant product** | Sold in options (Small / Medium / Large). Variants are added, edited and removed **in the product form**, or one at a time from the product page. Each has its own name, SKU, barcode and optional price and cost. A blank price or cost uses the product default. |

- A product *is* a variant product when it has variants; there is no separate type column.
- Variants can be added to any existing product at any time.
- Switching variant → simple archives all its variants.
- SKUs and barcodes typed in the form are checked against each other too, and two variants can swap codes in one save.

## Business rules

| Question | Rule |
|---|---|
| Can a category or brand with products be deleted? | **No.** Move the products or deactivate the category or brand instead. |
| Deleting a product or variant | **Soft delete** (archive), so future sales history can keep referencing it. |
| Inactive products, variants, categories, brands, modifier groups and modifiers | Hidden from the POS (to be enforced by the POS). Still visible in admin lists. |
| Can a barcode be empty? | **Yes.** Any number of items can have no barcode. |
| Can two items share a SKU or barcode? | **No**, not across products and variants combined, including archived ones. |
| SKU left blank? | Generated: `SKU-XXXXXX` for products, `{PRODUCT-SKU}-{VARIANT}` for variants. |
| Do variants inherit pricing? | **Yes**, when their price or cost is blank. |
| Discounts | Optional, percent (≤ 100%) or fixed (≤ selling price). `Product::discountedPrice()` never goes below 0. |

### Modifiers
- Product → modifier groups (many-to-many) → modifiers. One group, e.g. "Burger Add-ons", can be shared by many products.
- `min_select` (0 = optional) and `max_select` (blank = no limit) tell the POS how many options may be chosen.
- Each modifier adds its price to the line: item price + selected modifier prices. 0 is allowed.
- Modifiers work with or without variants. Future order lines must **snapshot** the modifier name and price.

### Images
- JPG, JPEG, PNG and WEBP. The limit is **the lower of 5 MB and PHP's `upload_max_filesize`** (currently 2 MB).
- **Large photos are resized in the browser** (max 1600 px, re-compressed) before upload, so phone photos work.
- The type is checked by **sniffing the file content**, not the filename. Renamed text or PHP files, SVG and GIF are rejected.
- Files are stored on the `public` disk under random names; only the path is saved in MySQL. Requires `php artisan storage:link`.
- To allow bigger originals, raise `upload_max_filesize` and `post_max_size` in `php.ini`. The app adapts automatically, up to 5 MB.

## Integration points (for the POS)

- Lookup: filter `is_active`, match `sku` or `barcode` on both products and variants (`Product::search()` already does both).
- Price: `ProductVariant::effectiveSellingPrice()` for variants, `Product::discountedPrice()` for the product discount.
- Add-ons: load `modifierGroups.modifiers` and filter to active ones.

## Removed: inventory

Removed on 2026-09-24:
- Pages: Stock, Low Stock, Stock Adjustment, Stock Movements
- `stock_movements` table
- Columns `products.track_inventory`, `products.stock_quantity`, `products.min_stock` and `product_variants.stock_quantity`
- Permissions `inventory.view`, `.manage`, `.adjust` and `.history`
- Sidebar section Inventory, including the Suppliers and Purchase Orders placeholders, plus the Inventory Reports placeholder
- Dashboard Low Stock card and Low Stock Alerts list

The removal is the migration `2026_09_24_100006_remove_inventory.php`. Its `down()` restores the table and columns (empty); the data was not kept. To bring inventory back later, build it as its own module on top of this catalog.

## Code map

| Area | Files |
|---|---|
| Models | `app/Models/{Category,Brand,Product,ProductVariant,ModifierGroup,Modifier}.php` |
| Controllers | `app/Http/Controllers/{Category,Brand,Product,ProductVariant,ModifierGroup}Controller.php` |
| Validation | `app/Http/Requests/*Request.php`, `app/Rules/{UniqueItemCode,ImageUpload}.php` |
| Helpers | `app/Support/{ItemCode,ImageStorage,Money}.php`, `app/Enums/DiscountType.php` |
| Views | `resources/views/{categories,brands,products,modifier-groups}/` |
| Tests | `tests/Feature/Catalog/*` |
