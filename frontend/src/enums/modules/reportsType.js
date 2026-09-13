import { EnumTrait } from "@/enums/modules/EnumTrait";

export const reportsType = Object.freeze({
     ...EnumTrait,

     invoices: {
          value: "invoices",
          enlabel: "invoices",
     },

     tests: {
          value: "tests",
          enlabel: "tests",
     },
     referrals: {
          value: "referrals",
          enlabel: "referrals",
     },
     cultures: {
          value: "cultures",
          enlabel: "cultures",
     },
     contracts: {
          value: "contracts",
          enlabel: "contracts",
     },
     packages: {
          value: "packages",
          enlabel: "packages",
     },
});
