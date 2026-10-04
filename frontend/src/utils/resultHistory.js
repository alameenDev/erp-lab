export function resultHistoryRows(record, item, type = 'test') {
  const id = item?.[`${type}_id_fk`] || item?.id;
  if (!id) return [];
  const rows = record?.result_history?.[`${type}_${id}`];
  return Array.isArray(rows) ? rows : [];
}
