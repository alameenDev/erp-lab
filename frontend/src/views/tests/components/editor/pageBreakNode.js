// TipTap node for "print page break".
// Emits:  <div class="page-break" data-type="page-break" style="page-break-before: always; break-before: page;">Page break</div>
//
// Why a custom node (not raw HTML via insertContent):
//   TipTap's schema doesn't know arbitrary <div>; when you insertContent() a
//   literal <div>, TipTap parses it through the schema and drops unknown
//   elements — the class/style get stripped and the CSS can't match on print.

import { Node, mergeAttributes } from "@tiptap/core";

export const PageBreak = Node.create({
     name: "pageBreak",
     group: "block",
     atom: true,            // one indivisible unit
     selectable: true,
     draggable: false,
     isolating: true,

     parseHTML() {
          return [
               { tag: 'div[data-type="page-break"]' },
               { tag: "div.page-break" },
          ];
     },

     renderHTML({ HTMLAttributes }) {
          return [
               "div",
               mergeAttributes(HTMLAttributes, {
                    class: "page-break",
                    "data-type": "page-break",
                    // Inline style so even if print CSS isn't loaded, browsers still honor it.
                    style: "page-break-before: always; break-before: page;",
               }),
               "Page break",
          ];
     },

     addCommands() {
          return {
               insertPageBreak: () => ({ commands }) =>
                    commands.insertContent({ type: this.name }),
          };
     },

     addKeyboardShortcuts() {
          return {
               "Mod-Enter": () => this.editor.commands.insertPageBreak(),
          };
     },
});
