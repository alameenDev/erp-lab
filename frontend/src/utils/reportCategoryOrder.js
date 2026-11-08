const categoryName = value => typeof value === 'string' ? value.trim() : '';

// A panel remains intact. Infer its category only when every printed member
// belongs to the same category; never label a mixed panel as its first test.
export function reportBlockCategory(parent, items = []) {
  const explicit = categoryName(parent?.category);
  if (explicit) return explicit;
  const categories = new Set(items.map(item => categoryName(item?.category)));
  return categories.size === 1 ? [...categories][0] : '';
}

// Saved invoice order decides the first appearance of each category and the
// order of its members. Unclassified panels retain independent slots. This is
// output-only: no invoice arrays, selection ranks or result values are changed.
export function orderReportCategories(sections) {
  const buckets = [], categories = new Map();
  const sorted = [...sections].sort((a, b) => (a.rank ?? Number.MAX_SAFE_INTEGER) - (b.rank ?? Number.MAX_SAFE_INTEGER));
  for (const section of sorted) {
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
