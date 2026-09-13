export const EnumTrait = {
     getLabelByValue(value) {
          return Object.values(this).find((v) => v.value === value)?.label ?? null;
     },
     getByLabel(label) {
          return Object.values(this).find((v) => v.label === label);
     },
     asList() {
          return Object.values(this)
               .filter((v) => typeof v === "object" && v !== null && v.hasOwnProperty("value"))
               .map((item) => ({
                    label: item.label,
                    value: item.value,
               }));
     },
     getColorByValue(value) {
          return Object.values(this).find((v) => v.value === value)?.color ?? null;
     },
};
