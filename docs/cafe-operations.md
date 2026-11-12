# OUT OF BOREDOM Business Owner Dashboard

The application is a single-owner dashboard with simple business input. The owner can record a sale and add a product; it does not expose a full POS, purchasing, stock adjustment history, staff management, payments, or accounting workflows.

## Data model

The dashboard schema contains six business tables:

| Table | Columns | Purpose |
|---|---|---|
| `roles` | `id`, `name` | Owner role reference for authenticated accounts. |
| `users` | `id`, `role_id`, `email`, `password` | Owner account used for PIN sign-in and role attribution. |
| `categories` | `id`, `name` | Product categories. |
| `products` | `id`, `category_id`, `name`, `price`, `cost_price`, `stock_quantity`, `is_available` | Product catalogue, on-hand quantity, and cost for profit estimates. |
| `orders` | `id`, `order_number`, `total_amount`, `cost_total`, `status`, `created_at` | Completed or cancelled order snapshots. |
| `order_items` | `id`, `order_id`, `product_id`, `quantity`, `unit_price`, `line_total` | Product lines connecting orders to products. |

`cost_price` and `cost_total` are retained to support the requested profit estimate. The estimate is completed order revenue less the recorded order cost; it is only as accurate as the cost snapshot supplied with each order.

The current attribute-level ERD is available as the editable [Draw.io diagram](./owner-dashboard-erd.drawio), [Mermaid source](./owner-dashboard-erd.mmd), and [SVG export](./milestone-assets/owner-dashboard-erd-v2.svg). It shows the `roles` 1-to-many `users`, `categories` 1-to-many `products`, `orders` 1-to-many `order_items`, and `products` 1-to-many `order_items` relationships.

## Dashboard metrics

- Today's revenue and completed-order count use the configured application timezone.
- Monthly sales and estimated profit use completed orders in the current calendar month.
- Low-stock products have `stock_quantity <= 10`.
- Active products have `is_available = true`.
- The seven-day trend and recent order stream follow the selected date range.

The business input page creates simple order and product records. It does not edit existing records. The schema cutover migration is destructive and intentionally non-reversible; restore a database backup to recover the prior cafe ERP tables.
