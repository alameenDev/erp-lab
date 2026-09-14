// TipTap slash-menu extension. User types "/" at start of a line and gets a
// quick-insert command palette.
//
// The actual command list is passed in via options.getItems(query) so the
// caller (the Vue component) can inject dynamic items (e.g. sub_test-aware
// "Insert sub-test row", "Insert report table").

import { Extension } from "@tiptap/core";
import Suggestion from "@tiptap/suggestion";
import { PluginKey } from "@tiptap/pm/state";

export const SlashCommands = Extension.create({
     name: "slashCommands",

     addOptions() {
          return {
               getItems: () => [],
               render: null,
          };
     },

     addProseMirrorPlugins() {
          return [
               Suggestion({
                    editor: this.editor,
                    pluginKey: new PluginKey("slashCommands"),
                    char: "/",
                    startOfLine: false,
                    allowSpaces: false,
                    items: ({ query }) => this.options.getItems(query || ""),
                    command: ({ editor, range, props }) => {
                         // Delete the typed "/query" then run the item's action.
                         editor.chain().focus().deleteRange(range).run();
                         if (typeof props.action === "function") {
                              props.action({ editor });
                         }
                    },
                    render: this.options.render,
               }),
          ];
     },
});
