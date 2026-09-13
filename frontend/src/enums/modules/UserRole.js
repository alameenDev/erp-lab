import { EnumTrait } from "@/enums/modules/EnumTrait";

export const UserRole = Object.freeze({
     ...EnumTrait,

     Admin: {
          value: 1,
          label: "Admin",
     },

     Lab: {
          value: 2,
          label: "lab",
     },
     Patient: {
          value: 3,
          label: "patient",
     },
     Branch_lab: {
          value: 4,
          label: "branch_lab",
     },
     Doctor: {
          value: 5,
          label: "doctor",
     },
     Sample_collector: {
          value: 6,
          label: "sample_collector",
     },
     user: {
          value: 7,
          label: "user",
     },
});
export const ReverralUserRole = Object.freeze({
     ...EnumTrait,

     Lab: {
          value: 2,
          label: "lab",
     },

     Doctor: {
          value: 5,
          label: "doctor",
     },
});
