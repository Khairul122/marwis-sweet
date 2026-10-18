import { writeFileSync } from 'node:fs';
import mysql from 'mysql2/promise';
import { connection } from './scripts/env.mjs';

export default async function teardown() {
  const db = await mysql.createConnection(connection);
  try {
    const [identity] = await db.query<mysql.RowDataPacket[]>('SELECT DATABASE() name');
    if (identity[0].name !== 'marwis_sweet_e2e') throw new Error('Teardown menolak schema di luar E2E.');
    const [unbalanced] = await db.query<mysql.RowDataPacket[]>(`SELECT e.id FROM journal_entries e
      LEFT JOIN journal_lines l ON l.journal_entry_id=e.id WHERE e.status='posted'
      GROUP BY e.id HAVING COALESCE(SUM(l.debit),0) <> COALESCE(SUM(l.credit),0)`);
    const [stock] = await db.query<mysql.RowDataPacket[]>('SELECT COALESCE(SUM(inventory_value),0) value FROM inventory_items');
    const [ledger] = await db.query<mysql.RowDataPacket[]>(`SELECT COALESCE(SUM(l.debit-l.credit),0) value
      FROM journal_lines l JOIN accounts a ON a.id=l.account_id JOIN journal_entries e ON e.id=l.journal_entry_id
      WHERE a.system_key='inventory' AND e.status='posted'`);
    const [events] = await db.query<mysql.RowDataPacket[]>('SELECT COUNT(DISTINCT event_type) n FROM audit_events');
    const [foreignKeys] = await db.query<mysql.RowDataPacket[]>(`SELECT COUNT(*) n FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA=DATABASE() AND DELETE_RULE='CASCADE' AND UPDATE_RULE IN ('RESTRICT','SET NULL')`);
    const state = { capturedAt: new Date().toISOString(), unbalancedJournals: unbalanced.length,
      inventoryValue: Number(stock[0].value), inventoryLedger: Number(ledger[0].value),
      difference: Number(stock[0].value) - Number(ledger[0].value), auditEventTypes: Number(events[0].n),
      reversedForeignKeys: Number(foreignKeys[0].n) };
    writeFileSync(process.env.E2E_EVALUATION === '1' ? 'writable/e2e/evaluation-state.json' : 'writable/e2e/full-run-state.json', JSON.stringify(state, null, 2));
    if (state.unbalancedJournals || state.difference !== 0 || state.reversedForeignKeys) {
      throw new Error('Invariant akuntansi atau foreign key tidak terpenuhi: ' + JSON.stringify(state));
    }
  } finally { await db.end(); }
}
