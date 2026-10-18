export function money(text: string): number {
  const match = text.replace(/\s/g, '').match(/(-?)(?:Rp)?([\d.]+)(?:,(\d{2}))?/);
  if (!match) throw new Error(`Nominal tidak valid: ${text}`);
  if (match[3] && Number(match[3]) !== 0) throw new Error(`Nominal bukan integer Rupiah: ${text}`);
  return Number(match[1] + match[2].replace(/\./g, ''));
}
