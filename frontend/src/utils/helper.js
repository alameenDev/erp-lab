import $alert from "sweetalert2";
import DOMPurify from "dompurify";
import en from "./en.json";
import ar from "./ar.json";

/**
 * SECURITY: Sanitize HTML to prevent XSS attacks
 * Use this function before rendering any user-provided HTML content
 * @param {string} dirty - The potentially unsafe HTML string
 * @param {object} config - Optional DOMPurify configuration
 * @returns {string} - Sanitized HTML string
 */
export const sanitizeHtml = (dirty, config = {}) => {
     if (!dirty) return "";

     // Default configuration - allows safe HTML for templates
     const defaultConfig = {
          ALLOWED_TAGS: [
               "p", "br", "span", "div", "strong", "b", "i", "em", "u",
               "h1", "h2", "h3", "h4", "h5", "h6",
               "ul", "ol", "li", "table", "tr", "td", "th", "thead", "tbody", "tfoot", "colgroup", "col", "caption",
               "img", "a", "blockquote", "pre", "code", "mark", "sub", "sup", "hr"
          ],
          ALLOWED_ATTR: [
               "class", "style", "src", "alt", "href", "target",
               "width", "height", "colspan", "rowspan", "border", "cellpadding", "cellspacing",
               "data-colwidth", "data-cell-type", "data-cell-padding", "data-type", "align", "valign", "dir"
          ],
          ALLOW_DATA_ATTR: false,
          // Prevent dangerous protocols
          ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto):|[^a-z]|[a-z+.-]+(?:[^a-z+.\-:]|$))/i
     };

     return DOMPurify.sanitize(dirty, { ...defaultConfig, ...config });
};

/**
 * SECURITY: Sanitize SVG icons (more restrictive)
 * @param {string} svgString - The SVG string
 * @returns {string} - Sanitized SVG string
 */
export const sanitizeSvg = (svgString) => {
     if (!svgString) return "";

     return DOMPurify.sanitize(svgString, {
          USE_PROFILES: { svg: true },
          ALLOWED_TAGS: ["svg", "path", "g", "circle", "rect", "line", "polyline", "polygon", "ellipse", "defs", "clipPath", "use"],
          ALLOWED_ATTR: ["viewBox", "d", "fill", "stroke", "stroke-width", "stroke-linecap", "stroke-linejoin", "class", "cx", "cy", "r", "x", "y", "width", "height", "points", "transform", "clip-path", "id", "href", "xlink:href"]
     });
};

export const showAlertWithConfirm = (
     title,
     text = t("This step cannot be undone"),
     confirmButtonText = t("ok"),
     cancelButtonText = t("cancel")
) => {
     return $alert.fire({
          title,
          text,
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#4CAF50",
          cancelButtonColor: "#9da6af",
          confirmButtonText,
          cancelButtonText,
     });
};
export const t = (key) => {
     let local = localStorage.getItem("locale") == "en" ? en : ar;
     return local[key];
};
export const showAlertWithSuccess = (title, text) => {
     return $alert.fire({
          title,
          text,
          icon: "success",
          confirmButtonColor: "#96D701",
     });
};

export const decodeToken = (token) => {
     try {
          const base64Url = token.split(".")[1];
          const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");
          const jsonPayload = decodeURIComponent(
               atob(base64)
                    .split("")
                    .map(function (c) {
                         return "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2);
                    })
                    .join("")
          );

          return JSON.parse(jsonPayload);
     } catch (e) {
          throw new Error("Token is not valid!");
     }
};

export const downloadFile = (data, fileName) => {
     const link = document.createElement("a");
     link.href = data;
     link.setAttribute("download", fileName);
     document.body.appendChild(link);
     link.click();
};

export const checkObjectParams = (obj) => {
     return Object.fromEntries(Object.entries(obj).filter(([_, value]) => value != null && value !== ""));
};

export const alertSuccess = (message) => {
     const toast = document.createElement("div");
     toast.className = "success-toast";
     // SECURITY: Use textContent instead of innerHTML to prevent XSS
     toast.textContent = message;
     document.body.appendChild(toast);
     setTimeout(() => {
          document.body.removeChild(toast);
     }, 3000);
};

export const alertError = (message) => {
     const toast = document.createElement("div");
     toast.className = "error-toast";
     // SECURITY: Use textContent instead of innerHTML to prevent XSS
     toast.textContent = message;
     document.body.appendChild(toast);
     setTimeout(() => {
          document.body.removeChild(toast);
     }, 3000);
};

export const clearObjectValues = (record) => {
     Object.keys(record).forEach((key) => {
          record[key] = "";
     });
     return record;
};

export const dateTimeFormat = (dateTimeStr) => {
     if (!dateTimeStr) {
          return "";
     }
     const dateTime = new Date(dateTimeStr);
     const year = dateTime.getFullYear();
     const month = ("0" + (dateTime.getMonth() + 1)).slice(-2);
     const date = ("0" + dateTime.getDate()).slice(-2);
     const hours = ("0" + dateTime.getHours()).slice(-2);
     const minutes = ("0" + dateTime.getMinutes()).slice(-2);
     return `${hours}:${minutes} ${year}-${month}-${date}`;
};

export const dateFormat = (date) => {
     return date ? date.slice(0, 10) : "-";
};

export const formatNum = (value) => {
     if (value == null || value === "") return "0";
     return new Intl.NumberFormat("en-US").format(value);
};
