# OUT OF BOREDOM Business Owner Dashboard

A Laravel 12, single-owner business overview for SMS Cafe. It records simple sales and products, then summarizes completed sales, estimated monthly profit, low stock, active products, a seven-day sales trend, and recent orders.

## Important: destructive schema cutover

The final migration intentionally removes the previous cafe ERP tables and their data, including POS details, inventory movements, purchasing, recipes, and accounting history. It keeps only the configured owner account and rebuilds the business data tables. **Back up the database before running migrations. This migration cannot be rolled back; restore a backup to recover removed data.**

The resulting application tables are:

- `roles`: the owner role used by the authenticated account.
- `users`: the configured owner's role, email, and password.
- `categories`: product categories.
- `products`: category, name, selling price, cost price, stock quantity, and availability.
- `orders`: order number, amount, cost snapshot, status, and creation time.
- `order_items`: product, quantity, unit price, and line total for each order.

Laravel's migration metadata table is retained. Sessions, cache, and queue use file/synchronous drivers instead of database support tables.

## Local setup

1. Configure `.env` with the database connection, a unique six-digit `MANAGER_SHARED_PIN`, the owner's `MANAGER_SHARED_PIN_USER_EMAIL`, and optionally `OWNER_CURRENCY`.
2. Back up any existing database, then run:

   ```powershell
   php artisan migrate
   php artisan db:seed
   ```

   The migration requires the configured email to match an existing account when users are present. The seeder creates that owner account if the database is new. Shared-PIN sessions are all attributed to this owner account, not to individual staff.
3. Build the frontend assets:

   ```powershell
   npm install
   npm run build
   ```

4. Start the application:

   ```powershell
   php artisan serve
   ```

   Open `http://127.0.0.1:8000`.

After signing in, use **Add business data** to record a sale linked to a product or add a product. Completed sales reduce stock and create an order item. This is intentionally a small input workflow, not a full POS, purchasing, inventory, staff, or accounting system. The current release uses an owner role for access. Monthly profit is an estimate calculated as completed order totals minus each order's stored cost snapshot.

## Testing

The tests use an isolated in-memory SQLite database:

```powershell
php artisan test
```
