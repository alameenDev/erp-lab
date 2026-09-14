/**
 * plugins/index.js
 *
 * Automatically included in `./src/main.js`
 */

// Plugins
import pinia from "../store";

export function registerPlugins(app) {
     // Only register pinia - router is registered in main.js
     app.use(pinia);
     app.config.globalProperties.$store = pinia;
}
