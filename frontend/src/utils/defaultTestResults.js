export function configuredDefaultResult(test) {
  const options = test.selection_type_options ?? test.selction_type_options ?? [];
  const value = test.default_result;
  return Number(test.result_type_id_fk) === 4 && !test.is_special_test &&
    typeof value === 'string' && value.trim() !== '' && Array.isArray(options) && options.includes(value)
    ? value : null;
}

// Call only when selecting a NEW catalogue item, never when loading saved invoice results.
export function withDefaultResults(item) {
  const selected = structuredClone(item);
  const seed = (test) => {
    if ((test.result == null || test.result === '') && !test.is_done) {
      const value = configuredDefaultResult(test);
      if (value !== null) test.result = value;
    }
  };
  seed(selected);
  for (const key of ['tests', 'package_tests', 'test_group_tests']) {
    if (Array.isArray(selected[key])) selected[key].forEach(seed);
  }
  return selected;
}

export function renameResultOption(record, index, value) {
  if (record.default_result === record.selection_type_options[index]) {
    record.default_result = value.trim() ? value : null;
  }
  record.selection_type_options[index] = value;
}

export function removeResultOption(record, index) {
  if (record.default_result === record.selection_type_options[index]) record.default_result = null;
  record.selection_type_options.splice(index, 1);
}
