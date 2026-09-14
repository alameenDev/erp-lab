/**
 * Lab Branding Utility
 * Applies lab colors, font, and logo across the app by setting CSS variables.
 * Single source of truth — called once on mount, once on settings save.
 */

// Convert hex to HSL
function hexToHsl(hex) {
     let r = parseInt(hex.slice(1, 3), 16) / 255;
     let g = parseInt(hex.slice(3, 5), 16) / 255;
     let b = parseInt(hex.slice(5, 7), 16) / 255;
     const max = Math.max(r, g, b), min = Math.min(r, g, b);
     let h = 0, s = 0, l = (max + min) / 2;
     if (max !== min) {
          const d = max - min;
          s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
          if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
          else if (max === g) h = ((b - r) / d + 2) / 6;
          else h = ((r - g) / d + 4) / 6;
     }
     return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
}

// Convert HSL to hex
function hslToHex(h, s, l) {
     s /= 100; l /= 100;
     const a = s * Math.min(l, 1 - l);
     const f = (n) => { const k = (n + h / 30) % 12; return l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1); };
     const toHex = (x) => Math.round(x * 255).toString(16).padStart(2, "0");
     return `#${toHex(f(0))}${toHex(f(8))}${toHex(f(4))}`;
}

// Generate 11 shades from a single hex color
function generateShades(hex) {
     const { h, s, l } = hexToHsl(hex);
     return {
          50: hslToHex(h, Math.min(s + 20, 100), 97),
          100: hslToHex(h, Math.min(s + 15, 100), 93),
          200: hslToHex(h, Math.min(s + 10, 100), 86),
          300: hslToHex(h, Math.min(s + 5, 100), 76),
          400: hslToHex(h, s, 62),
          500: hslToHex(h, s, l > 55 ? l : 48),
          600: hslToHex(h, s, Math.max(l - 8, 15)),
          700: hslToHex(h, s, Math.max(l - 16, 12)),
          800: hslToHex(h, s, Math.max(l - 24, 10)),
          900: hslToHex(h, s, Math.max(l - 30, 8)),
          950: hslToHex(h, s, Math.max(l - 38, 5)),
     };
}

/**
 * Apply branding to the entire app.
 * Sets Tailwind CSS variables + PrimeVue overrides + font.
 * @param {Object} settings - { primary_color, secondary_color, font_family }
 */
export function applyBranding(settings) {
     if (!settings) return;
     const root = document.documentElement;

     // 1. Primary color → override all Tailwind --color-primary-* variables
     if (settings.primary_color && /^#[0-9a-fA-F]{6}$/.test(settings.primary_color)) {
          const shades = generateShades(settings.primary_color);
          for (const [key, value] of Object.entries(shades)) {
               root.style.setProperty(`--color-primary-${key}`, value);
          }
          // Also set PrimeVue primary surface
          root.style.setProperty("--p-primary-color", shades[500]);
          root.style.setProperty("--p-primary-contrast-color", "#ffffff");
          root.style.setProperty("--p-highlight-background", shades[50]);
          root.style.setProperty("--p-highlight-color", shades[700]);
          root.style.setProperty("--p-focus-ring-color", shades[500]);
     }

     // 2. Secondary color
     if (settings.secondary_color && /^#[0-9a-fA-F]{6}$/.test(settings.secondary_color)) {
          root.style.setProperty("--lab-secondary", settings.secondary_color);
     }

     // 3. Font
     if (settings.font_family) {
          const font = settings.font_family + ", sans-serif";
          root.style.fontFamily = font;
          document.body.style.fontFamily = font;
     }

     // 4. Page header backgrounds — darken primary color for gradient
     if (settings.primary_color && /^#[0-9a-fA-F]{6}$/.test(settings.primary_color)) {
          const { h, s } = hexToHsl(settings.primary_color);
          const dark1 = hslToHex(h, Math.min(s, 30), 12);
          const dark2 = hslToHex(h, Math.min(s, 25), 15);
          root.style.setProperty("--header-from", dark1);
          root.style.setProperty("--header-via", dark2);
          root.style.setProperty("--header-to", dark1);
     }

     // 5. Dark mode — inject dynamic style for dark mode overrides
     if (settings.primary_color && /^#[0-9a-fA-F]{6}$/.test(settings.primary_color)) {
          const shades = generateShades(settings.primary_color);
          let darkStyle = document.getElementById("lab-branding-dark");
          if (!darkStyle) {
               darkStyle = document.createElement("style");
               darkStyle.id = "lab-branding-dark";
               document.head.appendChild(darkStyle);
          }
          darkStyle.textContent = `
               .dark-mode .bg-primary-50 { background-color: ${shades[950]} !important; }
               .dark-mode .bg-primary-100 { background-color: ${shades[900]} !important; }
               .dark-mode .bg-primary-500\\/20 { background-color: ${shades[500]}33 !important; }
               .dark-mode .bg-primary-500\\/30 { background-color: ${shades[500]}4D !important; }
               .dark-mode .bg-primary-600 { background-color: ${shades[600]} !important; }
               .dark-mode .text-primary-300 { color: ${shades[300]} !important; }
               .dark-mode .text-primary-400 { color: ${shades[400]} !important; }
               .dark-mode .text-primary-500 { color: ${shades[500]} !important; }
               .dark-mode .text-primary-600 { color: ${shades[600]} !important; }
               .dark-mode .text-primary-700 { color: ${shades[500]} !important; }
               .dark-mode .border-primary-200 { border-color: ${shades[800]} !important; }
               .dark-mode .border-primary-500 { border-color: ${shades[500]} !important; }
               .dark-mode .hover\\:bg-primary-700:hover { background-color: ${shades[700]} !important; }
               .dark-mode .bg-primary-200\\/20 { background-color: ${shades[800]}33 !important; }
               .dark-mode .bg-primary-300\\/10 { background-color: ${shades[700]}1A !important; }
          `;
     }

     // 6. Cache for print pages and offline access
     localStorage.setItem("labBranding", JSON.stringify(settings));
}

/**
 * Load branding from localStorage (for instant apply before API responds).
 */
export function loadCachedBranding() {
     try {
          const cached = localStorage.getItem("labBranding");
          if (cached) {
               applyBranding(JSON.parse(cached));
          }
     } catch {
          // Ignore parse errors
     }
}
