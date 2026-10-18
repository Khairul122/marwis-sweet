<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class FixForeignKeyDeleteRules extends Migration
{
    // Excludes the eight raw-SQL constraints whose delete rules were already correct.
    private const DELETE_RULES = [
        'accounting_periods_closed_by_foreign' => 'RESTRICT',
        'accounting_periods_reopened_by_foreign' => 'RESTRICT',
        'audit_events_actor_user_id_foreign' => 'RESTRICT',
        'cash_movements_created_by_foreign' => 'RESTRICT',
        'cash_movements_shift_id_foreign' => 'RESTRICT',
        'expenses_approved_by_foreign' => 'RESTRICT',
        'expenses_cancelled_by_foreign' => 'RESTRICT',
        'expenses_created_by_foreign' => 'RESTRICT',
        'expenses_expense_category_id_foreign' => 'RESTRICT',
        'expenses_paid_by_foreign' => 'RESTRICT',
        'expenses_shift_id_foreign' => 'RESTRICT',
        'expenses_submitted_by_foreign' => 'RESTRICT',
        'goods_receipt_items_inventory_item_id_foreign' => 'RESTRICT',
        'goods_receipts_created_by_foreign' => 'RESTRICT',
        'goods_receipts_posted_by_foreign' => 'RESTRICT',
        'goods_receipts_supplier_id_foreign' => 'SET NULL',
        'journal_entries_created_by_foreign' => 'RESTRICT',
        'journal_entries_reversal_of_id_foreign' => 'RESTRICT',
        'journal_lines_account_id_foreign' => 'RESTRICT',
        'opname_suggestions_stock_opname_id_foreign' => 'SET NULL',
        'pos_orders_cashier_id_foreign' => 'RESTRICT',
        'pos_orders_sale_id_foreign' => 'RESTRICT',
        'product_reviews_moderated_by_foreign' => 'RESTRICT',
        'product_reviews_product_id_foreign' => 'RESTRICT',
        'product_reviews_review_token_id_foreign' => 'RESTRICT',
        'product_reviews_sale_item_id_foreign' => 'RESTRICT',
        'products_category_id_foreign' => 'RESTRICT',
        'recipe_items_inventory_item_id_foreign' => 'RESTRICT',
        'refund_items_sale_item_id_foreign' => 'RESTRICT',
        'refunds_approved_by_foreign' => 'RESTRICT',
        'refunds_rejected_by_foreign' => 'RESTRICT',
        'refunds_requested_by_foreign' => 'RESTRICT',
        'refunds_sale_id_foreign' => 'RESTRICT',
        'refunds_shift_id_foreign' => 'RESTRICT',
        'review_tokens_sale_id_foreign' => 'RESTRICT',
        'sale_items_product_id_foreign' => 'RESTRICT',
        'sale_items_product_variant_id_foreign' => 'RESTRICT',
        'sales_cashier_id_foreign' => 'RESTRICT',
        'sales_shift_id_foreign' => 'RESTRICT',
        'shifts_cashier_id_foreign' => 'RESTRICT',
        'stock_adjustment_items_inventory_item_id_foreign' => 'RESTRICT',
        'stock_adjustments_created_by_foreign' => 'RESTRICT',
        'stock_adjustments_offset_account_id_foreign' => 'RESTRICT',
        'stock_adjustments_posted_by_foreign' => 'RESTRICT',
        'stock_adjustments_reversal_of_id_foreign' => 'RESTRICT',
        'stock_adjustments_reviewed_by_foreign' => 'RESTRICT',
        'stock_adjustments_submitted_by_foreign' => 'RESTRICT',
        'stock_movements_created_by_foreign' => 'RESTRICT',
        'stock_movements_inventory_item_id_foreign' => 'RESTRICT',
        'stock_opname_items_inventory_item_id_foreign' => 'RESTRICT',
        'stock_opnames_approved_by_foreign' => 'RESTRICT',
        'stock_opnames_cancelled_by_foreign' => 'RESTRICT',
        'stock_opnames_created_by_foreign' => 'RESTRICT',
        'stock_opnames_submitted_by_foreign' => 'RESTRICT',
        'topping_recipe_items_inventory_item_id_foreign' => 'RESTRICT',
    ];

    public function up(): void
    {
        $this->replaceRules(false);
    }

    public function down(): void
    {
        $this->replaceRules(true);
    }

    private function replaceRules(bool $rollback): void
    {
        $schema = $this->db->getDatabase();
        $rows = $this->db->query(
            'SELECT r.TABLE_NAME, r.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE,
                    k.COLUMN_NAME, k.REFERENCED_TABLE_SCHEMA, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS r
             JOIN information_schema.KEY_COLUMN_USAGE k
               ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.TABLE_NAME = r.TABLE_NAME
              AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
             WHERE r.CONSTRAINT_SCHEMA = ? ORDER BY r.TABLE_NAME, r.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            [$schema],
        )->getResultArray();
        $constraints = [];
        foreach ($rows as $row) {
            $name = $row['CONSTRAINT_NAME'];
            if ($rollback && !str_ends_with($name, '_fk24')) {
                continue;
            }
            $originalName = $rollback && str_ends_with($name, '_fk24') ? substr($name, 0, -5) : $name;
            $deleteRule = self::DELETE_RULES[$originalName] ?? null;
            $isReversed = $row['DELETE_RULE'] === 'CASCADE' && in_array($row['UPDATE_RULE'], ['RESTRICT', 'SET NULL'], true);
            if (!$rollback && $isReversed && $deleteRule === null) {
                throw new RuntimeException('Foreign key terbalik tidak dikenal: ' . $name);
            }
            if ($deleteRule === null || ($rollback
                ? ($row['DELETE_RULE'] !== $deleteRule || $row['UPDATE_RULE'] !== 'CASCADE')
                : (!$isReversed || $row['UPDATE_RULE'] !== $deleteRule))) {
                continue;
            }
            $constraints[$row['TABLE_NAME']][$name]['row'] = $row;
            $constraints[$row['TABLE_NAME']][$name]['columns'][] = $this->db->escapeIdentifiers($row['COLUMN_NAME']);
            $constraints[$row['TABLE_NAME']][$name]['references'][] = $this->db->escapeIdentifiers($row['REFERENCED_COLUMN_NAME']);
        }
        foreach ($constraints as $table => $items) {
            $changes = [];
            foreach ($items as $name => $constraint) {
                $row = $constraint['row'];
                $originalName = $rollback ? substr($name, 0, -5) : $name;
                $replacementName = $rollback ? $originalName : $name . '_fk24';
                $quotedName = $this->db->escapeIdentifiers($name);
                $delete = $rollback ? 'CASCADE' : self::DELETE_RULES[$originalName];
                $update = $rollback ? self::DELETE_RULES[$originalName] : 'CASCADE';
                $reference = $this->db->escapeIdentifiers($row['REFERENCED_TABLE_SCHEMA']) . '.' . $this->db->escapeIdentifiers($row['REFERENCED_TABLE_NAME']);
                $changes[] = 'DROP FOREIGN KEY ' . $quotedName;
                $changes[] = 'ADD CONSTRAINT ' . $this->db->escapeIdentifiers($replacementName) . ' FOREIGN KEY (' . implode(', ', $constraint['columns']) . ') REFERENCES '
                    . $reference . ' (' . implode(', ', $constraint['references']) . ') ON DELETE ' . $delete . ' ON UPDATE ' . $update;
            }
            // Distinct names avoid MySQL bug #113226 while keeping checks enabled and each ALTER atomic.
            $this->db->query('ALTER TABLE ' . $this->db->escapeIdentifiers($schema) . '.' . $this->db->escapeIdentifiers($table) . ' ' . implode(', ', $changes));
        }
    }
}
