const categoryName = value => typeof value === 'string' ? value.trim() : '';

// A panel remains intact. Infer its category only when every printed member
// belongs to the same category; never label a mixed panel as its first test.
export function reportBlockCategory(parent, items = []) {
  const explicit = categoryName(parent?.category);
  if (explicit) return explicit;
  const categories = new Set(items.map(item => categoryName(item?.category)));
  return categories.size === 1 ? [...categories][0] : '';
}

// Fill ordinary report pages before starting dedicated template/panel sheets.
// Category grouping is independent in each part so a shared category cannot
// pull an isolated CBC back between ordinary results. Saved selection order
// still determines categories and members within each part.
export function orderReportCategories(sections) {
  const sorted = [...sections].sort((a, b) => (a.rank ?? Number.MAX_SAFE_INTEGER) - (b.rank ?? Number.MAX_SAFE_INTEGER));
  return [sorted.filter(section => !section.isolated), sorted.filter(section => section.isolated)]
    .flatMap(groupReportCategories);
}

// Output-only: never mutate invoice arrays, selection ranks or result values.
function groupReportCategories(sections) {
  const buckets = [], categories = new Map();
  for (const section of sections) {
    const category = categoryName(section.category);
    let bucket = category ? categories.get(category) : null;
    if (!bucket) {
      bucket = [];
      buckets.push(bucket);
      if (category) categories.set(category, bucket);
    }
    bucket.push({ ...section, category });
  }
  return buckets.flatMap(bucket => bucket.map((section, index) => ({
    ...section,
    // Isolated reports start new sheets on both sides, so restore their title.
    categoryHeading: index === 0 || section.isolated || bucket[index - 1]?.isolated ? section.category : '',
  })));
}
