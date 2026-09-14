// TipTap suggestion extension that fires on "{{" and lists every sub-test
// variable candidate (name × field). Lets the user insert {{sub_test.X.value}}
// without ever typing it by hand.
//
// Expects: editor.storage.subTestsRef -> a Vue ref-like { value: [...] } of sub_tests.

import { Extension } from "@tiptap/core";
import Suggestion from "@tiptap/suggestion";
import { PluginKey } from "@tiptap/pm/state";
import { listVariables } from "@/composables/useSubTestVar";

export const VariableSuggestion = Extension.create({
     name: "variableSuggestion",

     addOptions() {
          return {
               getSubTests: () => [],
               render: null, // function(): { onStart, onUpdate, onKeyDown, onExit }
          };
     },

     addProseMirrorPlugins() {
          return [
               Suggestion({
                    editor: this.editor,
                    pluginKey: new PluginKey("variableSuggestion"),
                    char: "{{",
                    startOfLine: false,
                    allowSpaces: false,
                    allowedPrefixes: null,
                    items: ({ query }) => {
                         const subTests = this.options.getSubTests() || [];
                         const all = listVariables(subTests);
                         const q = (query || "").toLowerCase();
                         if (!q) return all.slice(0, 20);
                         return all
                              .filter(
                                   (v) =>
                                        v.name.toLowerCase().includes(q) ||
                                        v.key.toLowerCase().includes(q) ||
                                        v.field.toLowerCase().includes(q)
                              )
                              .slice(0, 20);
                    },
                    command: ({ editor, range, props }) => {
                         // Insert the placeholder replacing the "{{" the user already typed.
                         // range.from points to the "{" — we need to remove {{ and the query.
                         editor
                              .chain()
                              .focus()
                              .deleteRange({ from: range.from, to: range.to })
                              .insertContent(props.insertText + " ")
                              .run();
                    },
                    render: this.options.render,
               }),
          ];
     },
});
