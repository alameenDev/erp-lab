const list = value => Array.isArray(value) ? value : [];
const sameItem = (left, right) => {
  const leftId = left.id ?? left.test_id_fk ?? left.culture_id_fk;
  const rightId = right.id ?? right.test_id_fk ?? right.culture_id_fk;
  return leftId != null && rightId != null
    ? String(leftId) === String(rightId)
    : Boolean(left.name && left.name === right.name);
};

// Display projection only: keep invoice selections and saved result snapshots intact.
// Existing invoice responses can include group children in their flat tests list.
export function invoicePackageContents(item) {
  const groups = list(item?.test_groups).map(group => ({
    ...group,
    tests: list(group.tests),
    cultures: list(group.cultures ?? group.culture),
  }));
  const groupTests = groups.flatMap(group => group.tests);
  const groupCultures = groups.flatMap(group => group.cultures);
  const tests = list(item?.tests).filter(test => !groupTests.some(child => sameItem(test, child)));
  const cultures = list(item?.cultures).filter(culture => !groupCultures.some(child => sameItem(culture, child)));
  return {
    groups, tests, cultures,
    testCount: tests.length + groupTests.length,
    cultureCount: cultures.length + groupCultures.length,
  };
}
