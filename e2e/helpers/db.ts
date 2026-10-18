import mysql from 'mysql2/promise';
import { expect } from '@playwright/test';
import { connection, schema } from '../scripts/env.mjs';

export class Database {
  private pool = mysql.createPool({ ...connection, connectionLimit: 2 });

  async rows<T = Record<string, any>>(sql: string, values: any[] = []): Promise<T[]> {
    if (!/^\s*(SELECT|SHOW|DESCRIBE)\b/i.test(sql) || /;\s*\S/.test(sql)) {
      throw new Error('Helper E2E hanya menerima query baca.');
    }
    const [identity] = await this.pool.query<mysql.RowDataPacket[]>('SELECT DATABASE() AS name');
    if (identity[0].name !== schema) throw new Error('Koneksi bukan schema E2E.');
    const [rows] = await this.pool.execute(sql, values);
    return rows as T[];
  }

  async one(sql: string, values: any[] = []) {
    const rows = await this.rows(sql, values);
    expect(rows.length, sql).toBe(1);
    return rows[0];
  }

  async count(table: string, where = '1=1', values: any[] = []) {
    if (!/^[a-z_]+$/.test(table)) throw new Error('Nama tabel tidak valid.');
    return Number((await this.one(`SELECT COUNT(*) AS n FROM ${table} WHERE ${where}`, values)).n);
  }

  async fixtureDate(table: 'sales' | 'review_tokens', id: number, column: 'paid_at' | 'expires_at', value: string) {
    const allowed = { sales: 'paid_at', review_tokens: 'expires_at' };
    if (allowed[table] !== column) throw new Error('Fixture tanggal tidak diizinkan.');
    await this.rows('SELECT DATABASE() AS name');
    // fixture: tidak ada UI untuk ini; hanya tanggal pada schema E2E.
    await this.pool.execute(`UPDATE ${schema}.${table} SET ${column} = ? WHERE id = ?`, [value, id]);
  }

  async assertJournalsBalanced() {
    expect(await this.rows(`SELECT e.id, SUM(l.debit) debit, SUM(l.credit) credit
      FROM journal_entries e LEFT JOIN journal_lines l ON l.journal_entry_id=e.id
      WHERE e.status='posted' GROUP BY e.id HAVING COALESCE(SUM(l.debit),0) <> COALESCE(SUM(l.credit),0)`)).toEqual([]);
  }

  async close() { await this.pool.end(); }
}
