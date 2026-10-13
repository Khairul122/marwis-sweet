<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountingTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'name' => ['type' => 'VARCHAR', 'constraint' => 140],
            'account_type' => ['type' => 'ENUM', 'constraint' => ['asset', 'liability', 'equity', 'revenue', 'contra_revenue', 'expense']],
            'normal_balance' => ['type' => 'ENUM', 'constraint' => ['debit', 'credit']],
            'system_key' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'allow_manual' => ['type' => 'BOOLEAN', 'default' => true],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addUniqueKey('system_key');
        $this->forge->addKey(['account_type', 'is_active']);
        $this->forge->createTable('accounts', true, $options);

        $now = date('Y-m-d H:i:s');
        $accounts = [
            ['1000', 'Kas utama', 'asset', 'debit', 'main_cash', false], ['1010', 'Laci kasir', 'asset', 'debit', 'cash_drawer', false],
            ['1020', 'Bank dan QRIS', 'asset', 'debit', 'bank', true], ['1100', 'Persediaan', 'asset', 'debit', 'inventory', false],
            ['1200', 'Aset lain', 'asset', 'debit', 'other_assets', true], ['2000', 'Utang usaha', 'liability', 'credit', 'accounts_payable', true],
            ['2100', 'Kewajiban awal', 'liability', 'credit', 'opening_liability', true], ['2200', 'Pajak terutang', 'liability', 'credit', 'tax_payable', false],
            ['3000', 'Modal pemilik', 'equity', 'credit', 'owner_capital', true], ['3100', 'Prive', 'equity', 'debit', 'owner_draw', true],
            ['4000', 'Penjualan', 'revenue', 'credit', 'sales_revenue', false], ['4010', 'Retur dan diskon penjualan', 'contra_revenue', 'debit', 'sales_returns', false],
            ['4020', 'Pendapatan layanan', 'revenue', 'credit', 'service_revenue', false], ['4100', 'Pendapatan selisih stok', 'revenue', 'credit', 'inventory_adjustment_income', false],
            ['5000', 'Harga pokok penjualan', 'expense', 'debit', 'cogs', false], ['6000', 'Beban operasional lain', 'expense', 'debit', 'operating_expense', true],
            ['6100', 'Beban selisih stok', 'expense', 'debit', 'inventory_variance', false], ['6200', 'Beban selisih kas', 'expense', 'debit', 'cash_variance', false],
        ];
        foreach ($accounts as $account) {
            $this->db->table('accounts')->insert(['code' => $account[0], 'name' => $account[1], 'account_type' => $account[2], 'normal_balance' => $account[3], 'system_key' => $account[4], 'allow_manual' => $account[5], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->forge->addColumn('expense_categories', ['account_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'slug']]);
        $this->db->query('ALTER TABLE expense_categories ADD INDEX expense_category_account_index (account_id), ADD CONSTRAINT expense_categories_account_fk FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT ON UPDATE CASCADE');
        $expenseCategories = $this->db->table('expense_categories')->orderBy('id')->get()->getResultArray();
        foreach ($expenseCategories as $index => $category) {
            $code = (string) (6010 + ($index * 10));
            $this->db->table('accounts')->insert(['code' => $code, 'name' => 'Beban ' . $category['name'], 'account_type' => 'expense', 'normal_balance' => 'debit', 'system_key' => 'expense_' . $category['slug'], 'allow_manual' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $this->db->table('expense_categories')->where('id', $category['id'])->update(['account_id' => $this->db->insertID()]);
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'period_start' => ['type' => 'DATE'], 'period_end' => ['type' => 'DATE'],
            'status' => ['type' => 'ENUM', 'constraint' => ['open', 'closed'], 'default' => 'open'],
            'closed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true], 'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'close_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'reopened_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true], 'reopened_at' => ['type' => 'DATETIME', 'null' => true],
            'reopen_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey(['period_start', 'period_end']);
        $this->forge->addForeignKey('closed_by', 'users', 'id', 'RESTRICT', 'CASCADE'); $this->forge->addForeignKey('reopened_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('accounting_periods', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true], 'journal_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'entry_date' => ['type' => 'DATE'], 'description' => ['type' => 'VARCHAR', 'constraint' => 500],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 50], 'source_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'source_key' => ['type' => 'VARCHAR', 'constraint' => 120], 'status' => ['type' => 'ENUM', 'constraint' => ['posted', 'reversed'], 'default' => 'posted'],
            'reversal_of_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true], 'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey('journal_number'); $this->forge->addUniqueKey('source_key'); $this->forge->addKey(['entry_date', 'status']);
        $this->forge->addForeignKey('reversal_of_id', 'journal_entries', 'id', 'RESTRICT', 'CASCADE'); $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('journal_entries', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true], 'journal_entry_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'account_id' => ['type' => 'BIGINT', 'unsigned' => true], 'debit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'credit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0], 'memo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true); $this->forge->addKey(['journal_entry_id', 'account_id']);
        $this->forge->addForeignKey('journal_entry_id', 'journal_entries', 'id', 'CASCADE', 'CASCADE'); $this->forge->addForeignKey('account_id', 'accounts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('journal_lines', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('journal_lines', true); $this->forge->dropTable('journal_entries', true); $this->forge->dropTable('accounting_periods', true);
        $this->db->query('ALTER TABLE expense_categories DROP FOREIGN KEY expense_categories_account_fk, DROP INDEX expense_category_account_index');
        $this->forge->dropColumn('expense_categories', 'account_id'); $this->forge->dropTable('accounts', true);
    }
}
