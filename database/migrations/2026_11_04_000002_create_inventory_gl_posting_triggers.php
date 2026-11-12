<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->createSqliteTriggers(),
            'mysql', 'mariadb' => $this->createMysqlTriggers(),
            'pgsql' => $this->createPostgresTrigger(),
            default => throw new RuntimeException('Inventory GL posting triggers are not supported by this database driver.'),
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => $this->dropSqliteTriggers(),
            'mysql', 'mariadb' => $this->dropMysqlTriggers(),
            'pgsql' => $this->dropPostgresTrigger(),
            default => throw new RuntimeException('Inventory GL posting triggers are not supported by this database driver.'),
        };
    }

    private function createSqliteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_validate_values
            BEFORE INSERT ON inventory_movements
            WHEN NEW.direction NOT IN ('in', 'out') OR NEW.quantity <= 0 OR NEW.unit_cost < 0
            BEGIN
                SELECT RAISE(ABORT, 'Invalid inventory movement values');
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_validate_stock
            BEFORE INSERT ON inventory_movements
            WHEN NEW.direction = 'out'
             AND COALESCE((
                 SELECT quantity_on_hand FROM inventory_stocks
                 WHERE product_id = NEW.product_id AND warehouse_bin_id = NEW.warehouse_bin_id
             ), 0) < NEW.quantity
            BEGIN
                SELECT RAISE(ABORT, 'Insufficient inventory for stock issue');
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_prevent_update
            BEFORE UPDATE ON inventory_movements
            BEGIN
                SELECT RAISE(ABORT, 'Inventory movements are immutable; create a correcting movement');
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_prevent_delete
            BEFORE DELETE ON inventory_movements
            BEGIN
                SELECT RAISE(ABORT, 'Inventory movements are immutable; create a correcting movement');
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_post_gl
            AFTER INSERT ON inventory_movements
            BEGIN
                INSERT INTO inventory_stocks (product_id, warehouse_bin_id, quantity_on_hand, created_at, updated_at)
                VALUES (
                    NEW.product_id,
                    NEW.warehouse_bin_id,
                    CASE WHEN NEW.direction = 'in' THEN NEW.quantity ELSE -NEW.quantity END,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON CONFLICT(product_id, warehouse_bin_id) DO UPDATE SET
                    quantity_on_hand = inventory_stocks.quantity_on_hand
                        + CASE WHEN NEW.direction = 'in' THEN NEW.quantity ELSE -NEW.quantity END,
                    updated_at = CURRENT_TIMESTAMP;

                INSERT INTO journal_entries (entry_date, description, source_type, source_id, status, created_at, updated_at)
                VALUES (
                    date(NEW.occurred_at),
                    'Inventory movement #' || NEW.id,
                    'inventory_movement',
                    NEW.id,
                    'posted',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                );

                INSERT INTO journal_lines (journal_entry_id, account_id, line_number, debit, credit, description, created_at, updated_at)
                SELECT
                    (SELECT id FROM journal_entries WHERE source_type = 'inventory_movement' AND source_id = NEW.id),
                    p.inventory_account_id,
                    1,
                    CASE WHEN NEW.direction = 'in' THEN NEW.quantity * NEW.unit_cost ELSE 0 END,
                    CASE WHEN NEW.direction = 'out' THEN NEW.quantity * NEW.unit_cost ELSE 0 END,
                    'Inventory movement #' || NEW.id,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                FROM products p WHERE p.id = NEW.product_id;

                INSERT INTO journal_lines (journal_entry_id, account_id, line_number, debit, credit, description, created_at, updated_at)
                VALUES (
                    (SELECT id FROM journal_entries WHERE source_type = 'inventory_movement' AND source_id = NEW.id),
                    NEW.offset_account_id,
                    2,
                    CASE WHEN NEW.direction = 'out' THEN NEW.quantity * NEW.unit_cost ELSE 0 END,
                    CASE WHEN NEW.direction = 'in' THEN NEW.quantity * NEW.unit_cost ELSE 0 END,
                    'Inventory movement offset #' || NEW.id,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                );
            END
            SQL);
    }

    private function dropSqliteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_post_gl');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_validate_stock');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_validate_values');
    }

    private function createMysqlTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_validate_values
            BEFORE INSERT ON inventory_movements
            FOR EACH ROW
            BEGIN
                IF NEW.direction NOT IN ('in', 'out') OR NEW.quantity <= 0 OR NEW.unit_cost < 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid inventory movement values';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_validate_stock
            BEFORE INSERT ON inventory_movements
            FOR EACH ROW
            BEGIN
                DECLARE available_quantity DECIMAL(19, 4) DEFAULT NULL;
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET available_quantity = NULL;

                SELECT quantity_on_hand INTO available_quantity
                FROM inventory_stocks
                WHERE product_id = NEW.product_id AND warehouse_bin_id = NEW.warehouse_bin_id
                FOR UPDATE;

                IF NEW.direction = 'out' AND COALESCE(available_quantity, 0) < NEW.quantity THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient inventory for stock issue';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_prevent_update
            BEFORE UPDATE ON inventory_movements
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory movements are immutable; create a correcting movement'
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_prevent_delete
            BEFORE DELETE ON inventory_movements
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory movements are immutable; create a correcting movement'
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_post_gl
            AFTER INSERT ON inventory_movements
            FOR EACH ROW
            BEGIN
                DECLARE entry_id BIGINT;

                INSERT INTO inventory_stocks (product_id, warehouse_bin_id, quantity_on_hand, created_at, updated_at)
                VALUES (
                    NEW.product_id,
                    NEW.warehouse_bin_id,
                    IF(NEW.direction = 'in', NEW.quantity, -NEW.quantity),
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    quantity_on_hand = quantity_on_hand + IF(NEW.direction = 'in', NEW.quantity, -NEW.quantity),
                    updated_at = CURRENT_TIMESTAMP;

                INSERT INTO journal_entries (entry_date, description, source_type, source_id, status, created_at, updated_at)
                VALUES (
                    DATE(NEW.occurred_at),
                    CONCAT('Inventory movement #', NEW.id),
                    'inventory_movement',
                    NEW.id,
                    'posted',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                );

                SET entry_id = LAST_INSERT_ID();

                INSERT INTO journal_lines (journal_entry_id, account_id, line_number, debit, credit, description, created_at, updated_at)
                SELECT
                    entry_id,
                    p.inventory_account_id,
                    1,
                    IF(NEW.direction = 'in', NEW.quantity * NEW.unit_cost, 0),
                    IF(NEW.direction = 'out', NEW.quantity * NEW.unit_cost, 0),
                    CONCAT('Inventory movement #', NEW.id),
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                FROM products p WHERE p.id = NEW.product_id;

                INSERT INTO journal_lines (journal_entry_id, account_id, line_number, debit, credit, description, created_at, updated_at)
                VALUES (
                    entry_id,
                    NEW.offset_account_id,
                    2,
                    IF(NEW.direction = 'out', NEW.quantity * NEW.unit_cost, 0),
                    IF(NEW.direction = 'in', NEW.quantity * NEW.unit_cost, 0),
                    CONCAT('Inventory movement offset #', NEW.id),
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                );
            END
            SQL);
    }

    private function dropMysqlTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_post_gl');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_validate_stock');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_validate_values');
    }

    private function createPostgresTrigger(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION prevent_inventory_movement_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Inventory movements are immutable; create a correcting movement';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_prevent_mutation
            BEFORE UPDATE OR DELETE ON inventory_movements
            FOR EACH ROW EXECUTE FUNCTION prevent_inventory_movement_mutation()
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION post_inventory_movement_to_gl() RETURNS trigger AS $$
            DECLARE
                current_stock numeric(19, 4);
                entry_id bigint;
                movement_value numeric(19, 4);
                inventory_account bigint;
            BEGIN
                SELECT quantity_on_hand INTO current_stock
                FROM inventory_stocks
                WHERE product_id = NEW.product_id AND warehouse_bin_id = NEW.warehouse_bin_id
                FOR UPDATE;

                IF NEW.direction = 'out' AND COALESCE(current_stock, 0) < NEW.quantity THEN
                    RAISE EXCEPTION 'Insufficient inventory for stock issue';
                END IF;

                INSERT INTO inventory_stocks (product_id, warehouse_bin_id, quantity_on_hand, created_at, updated_at)
                VALUES (
                    NEW.product_id,
                    NEW.warehouse_bin_id,
                    CASE WHEN NEW.direction = 'in' THEN NEW.quantity ELSE -NEW.quantity END,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON CONFLICT (product_id, warehouse_bin_id) DO UPDATE SET
                    quantity_on_hand = inventory_stocks.quantity_on_hand
                        + CASE WHEN NEW.direction = 'in' THEN NEW.quantity ELSE -NEW.quantity END,
                    updated_at = CURRENT_TIMESTAMP;

                SELECT inventory_account_id INTO inventory_account
                FROM products WHERE id = NEW.product_id;

                movement_value := round(NEW.quantity * NEW.unit_cost, 4);

                INSERT INTO journal_entries (entry_date, description, source_type, source_id, status, created_at, updated_at)
                VALUES (
                    NEW.occurred_at::date,
                    'Inventory movement #' || NEW.id,
                    'inventory_movement',
                    NEW.id,
                    'posted',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                RETURNING id INTO entry_id;

                INSERT INTO journal_lines (journal_entry_id, account_id, line_number, debit, credit, description, created_at, updated_at)
                VALUES (
                    entry_id,
                    inventory_account,
                    1,
                    CASE WHEN NEW.direction = 'in' THEN movement_value ELSE 0 END,
                    CASE WHEN NEW.direction = 'out' THEN movement_value ELSE 0 END,
                    'Inventory movement #' || NEW.id,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                );

                INSERT INTO journal_lines (journal_entry_id, account_id, line_number, debit, credit, description, created_at, updated_at)
                VALUES (
                    entry_id,
                    NEW.offset_account_id,
                    2,
                    CASE WHEN NEW.direction = 'out' THEN movement_value ELSE 0 END,
                    CASE WHEN NEW.direction = 'in' THEN movement_value ELSE 0 END,
                    'Inventory movement offset #' || NEW.id,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                );

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_movements_post_gl
            AFTER INSERT ON inventory_movements
            FOR EACH ROW EXECUTE FUNCTION post_inventory_movement_to_gl()
            SQL);
    }

    private function dropPostgresTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_post_gl ON inventory_movements');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_prevent_mutation ON inventory_movements');
        DB::unprepared('DROP FUNCTION IF EXISTS post_inventory_movement_to_gl()');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_movement_mutation()');
    }
};
