import{M as y,ae as z}from"./vue-vendor-BEtjDboL.js";function _(){const s=new Map;let x=!1;const m=new Map,b=o=>{o.remove();const t=s.get(o);s.delete(o),t&&t()},f=o=>{const t=document.createElement("iframe");return t.style.cssText="position: fixed; left: -10000px; top: 0; width: 0; height: 0; border: 0; pointer-events: none;",t.setAttribute("aria-hidden","true"),t.setAttribute("tabindex","-1"),s.set(t,o),document.body.appendChild(t),t},h=(o,t,n)=>{if(x){n();return}const i=setTimeout(()=>{m.delete(i),o()},t);m.set(i,n)};return z(()=>{x=!0,m.forEach((o,t)=>{clearTimeout(t),o()}),m.clear(),[...s.keys()].forEach(b)}),{printWithIframe:async(o,t,n="Print",i=100,p=null)=>(p&&(await p(),await y()),new Promise((e,r)=>{h(()=>{const l=document.getElementById(o)?.outerHTML;if(!l){console.error(`Element with id "${o}" not found`),r(new Error(`Element not found: ${o}`));return}const a=f(e),d=a.contentWindow.document;d.open(),d.write(`
          <html>
            <head>
              <title>${n}</title>
              <style>${t}</style>
            </head>
            <body>${l}</body>
          </html>
        `),d.close(),(async()=>{await Promise.all(Array.from(d.images).map(c=>c.complete?Promise.resolve():new Promise(g=>{c.onload=g,c.onerror=g}))),d.fonts?.ready&&await d.fonts.ready,s.has(a)&&(a.contentWindow.onafterprint=()=>{b(a),e()},a.contentWindow.focus(),a.contentWindow.print())})().catch(()=>{b(a),e()})},i,e)})),printWithCustomContent:async(o,t,n="Print",i=50)=>new Promise(p=>{h(()=>{const e=f(p),r=e.contentWindow.document;r.open(),r.write(`
          <html>
            <head>
              <title>${n}</title>
              <style>${t} .page-break { page-break-before: always; }</style>
            </head>
            <body>${o}</body>
          </html>
        `),r.close(),(async()=>{await Promise.all(Array.from(r.images).map(a=>a.complete?Promise.resolve():new Promise(d=>{a.onload=d,a.onerror=d}))),r.fonts?.ready&&await r.fonts.ready,s.has(e)&&(e.contentWindow.onafterprint=()=>{b(e),p()},e.contentWindow.focus(),e.contentWindow.print())})().catch(()=>{b(e),p()})},i,p)}),printStyles:{jobOrder:`
      @page { size: A4; margin: 0 !important; }
      @media print { body { -webkit-print-color-adjust: exact; color: #000; } }
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: "Tajawal", sans-serif; font-size: 12px; padding: 10mm; color: #000; }
      .job-order-container { width: 100%; }
      .job-header { margin-bottom: 15px; }
      .header-table { width: 100%; border-collapse: collapse; }
      .header-table td { padding: 5px; vertical-align: top; }
      .barcode-section { display: flex; align-items: center; gap: 5px; }
      .barcode-wrapper { display: flex; flex-direction: column; align-items: center; }
      .barcode-wrapper svg { height: 25px !important; width: 80px !important; }
      .barcode-text { font-size: 10px; }
      .info-row { display: flex; gap: 5px; align-items: center; }
      .label { font-weight: normal; color: #374151; }
      .patient-name { font-size: 14px; }
      .tests-section { margin: 10px 0; }
      .tests-table { width: 100%; border-collapse: collapse; }
      .tests-table th { background-color: #14b8a6; color: white; padding: 8px; text-align: center; border: 1px solid #0d9488; font-size: 11px; }
      .tests-table td { border: 1px solid #e5e7eb; padding: 6px; text-align: center; font-size: 11px; }
      .tests-table tbody tr:nth-child(even) { background-color: #f9fafb; }
      .group-header { background-color: #f0fdfa !important; }
      .group-header td { font-weight: bold; color: #0f766e; text-align: left !important; }
      .result-cell, .signature-cell { min-width: 60px; height: 25px; }
      .signatures-section { display: flex; justify-content: space-around; margin-top: 30px; padding-top: 20px; }
      .signature-box { text-align: center; width: 150px; }
      .signature-line { border-top: 1px solid #000; margin-bottom: 5px; }
      .due-amount { color: #dc2626; }
    `,thermalReceipt:`
      @page { size: auto; margin: 0 !important; }
      @media print { body { -webkit-print-color-adjust: exact; color: #000; } }
      * { margin: 0; padding: 0; box-sizing: border-box; }
      html, body { width: 100%; height: 100%; }
      body { font-family: "Courier New", monospace; margin: 0; padding: 0; color: #000; direction: ltr; }
      .thermal-receipt { width: 100%; padding: 3mm; text-align: center; }
      .header { padding: 4px 0 8px; }
      .title { font-size: 22px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }
      .bc-row { display: flex; justify-content: space-around; gap: 6px; margin: 8px 0; }
      .bc-col { display: flex; flex-direction: column; align-items: center; flex: 1; }
      .bc-img { width: 100%; height: 30px; }
      .bc-txt { font-size: 12px; font-weight: bold; margin-top: 2px; }
      .sep { border-top: 1px dashed #000; margin: 6px 0; }
      .info { text-align: left; margin: 6px 0; }
      .info .row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 13px; border-bottom: 1px dotted #ccc; }
      .info .row:last-child { border-bottom: none; }
      .info .row span { color: #333; }
      .info .row strong { text-align: right; }
      .tbl { width: 100%; border-collapse: collapse; margin: 6px 0; }
      .tbl th { background: #000; color: #fff; font-size: 12px; padding: 4px 3px; text-align: center; }
      .tbl td { border-bottom: 1px dotted #999; font-size: 12px; padding: 4px 3px; text-align: center; }
      .tbl .num-col { width: 10%; }
      .tbl .name-col { text-align: left; width: 44%; }
      .tbl .sample-col { width: 26%; font-size: 11px; }
      .tbl .price-col { width: 20%; text-align: right; }
      .tbl th.name-col { text-align: left; }
      .tbl th.price-col { text-align: right; }
      .summary { margin: 6px 0; }
      .sum-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 13px; border-bottom: 1px dotted #ccc; }
      .sum-row.total { font-weight: bold; font-size: 15px; border-bottom: 2px solid #000; border-top: 2px solid #000; padding: 5px 0; }
      .sum-row.due { font-weight: bold; font-size: 14px; border-bottom: none; }
      .qr { text-align: center; margin: 10px 0 6px; }
      .qr-img { width: 90px; height: 90px; margin: 0 auto; display: block; }
      .qr-hint { font-size: 10px; margin-top: 3px; color: #555; }
      .footer { margin-top: 8px; font-size: 11px; text-align: center; border-top: 1px dashed #000; padding-top: 5px; }
      .footer p { margin: 2px 0; }
      .grp-title { background: #000; color: #fff; font-size: 13px; font-weight: bold; padding: 4px 6px; margin-top: 8px; margin-bottom: 0; text-align: center; letter-spacing: 1px; }
      .notes-section { text-align: left; font-size: 11px; margin: 6px 0; padding: 4px 0; border-bottom: 1px dotted #ccc; }
      .notes-section strong { font-size: 11px; }
    `,invoice:`
      @page { size: A4; margin: 15mm; }
      @media print { body { -webkit-print-color-adjust: exact; color: #000; margin: 0 !important; } }
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: "Segoe UI", "Tajawal", Arial, sans-serif; font-size: 12px; padding: 15mm; color: #1f2937; }
      .inv { width: 100%; max-width: 800px; margin: 0 auto; }
      .inv-title { text-align: center; font-size: 20px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #0f766e; margin-bottom: 12px; border-bottom: 3px solid #14b8a6; padding-bottom: 8px; }
      .hdr { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 12px; }
      .hdr-bc { display: flex; flex-direction: column; align-items: center; gap: 2px; }
      .hdr-bc img { max-width: 130px; height: 28px; }
      .hdr-bc span { font-size: 10px; font-weight: 600; }
      .hdr-qr { display: flex; flex-direction: column; align-items: center; gap: 2px; }
      .hdr-qr svg { width: 70px !important; height: 70px !important; }
      .hdr-qr span { font-size: 8px; color: #6b7280; }
      .info-grid { display: table; width: 100%; border: 1px solid #d1d5db; border-collapse: collapse; margin-bottom: 16px; }
      .info-row { display: table-row; }
      .info-cell { display: table-cell; padding: 5px 10px; border: 1px solid #e5e7eb; font-size: 11px; vertical-align: middle; }
      .info-lbl { font-weight: 700; color: #374151; background-color: #f9fafb; white-space: nowrap; width: 110px; }
      .info-val { color: #111827; }
      .section-title { background-color: #0f766e; color: white; font-size: 13px; font-weight: 700; padding: 7px 12px; margin-top: 14px; margin-bottom: 0; letter-spacing: 0.5px; }
      .grp-hdr { background-color: #ccfbf1 !important; }
      .grp-hdr td { font-weight: 700; color: #0f766e; border-bottom: 2px solid #14b8a6 !important; }
      .tbl { width: 100%; border-collapse: collapse; margin-bottom: 0; }
      .tbl th { background-color: #14b8a6; color: white; padding: 7px 10px; text-align: center; border: 1px solid #0d9488; font-size: 11px; font-weight: 600; }
      .tbl td { border: 1px solid #e5e7eb; padding: 5px 10px; font-size: 11px; text-align: center; }
      .tbl tbody tr:nth-child(even) { background-color: #f9fafb; }
      .tbl .txt-start { text-align: left; }
      .tbl .num { width: 35px; }
      .tbl .sample { width: 100px; }
      .tbl .price { width: 90px; font-weight: 600; }
      .summ { margin-top: 18px; display: flex; justify-content: flex-end; }
      .summ-tbl { width: 280px; border-collapse: collapse; }
      .summ-tbl td { padding: 6px 14px; border: 1px solid #e5e7eb; font-size: 11px; }
      .summ-tbl .lbl { font-weight: 700; background-color: #f9fafb; color: #374151; }
      .summ-tbl .total { background-color: #14b8a6; color: white; font-weight: 700; font-size: 13px; }
      .summ-tbl .total td { border-color: #0d9488; }
      .summ-tbl .due { background-color: #fef2f2; color: #dc2626; font-weight: 700; }
      .summ-tbl .paid-row { background-color: #f0fdf4; color: #16a34a; font-weight: 600; }
      .notes { margin-top: 14px; padding: 8px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; font-size: 11px; color: #92400e; }
      .notes strong { color: #78350f; }
      .pay-details { margin-top: 10px; }
      .pay-details table { width: 280px; border-collapse: collapse; margin-left: auto; }
      .pay-details th { background-color: #f3f4f6; padding: 4px 10px; font-size: 10px; font-weight: 600; color: #374151; border: 1px solid #e5e7eb; }
      .pay-details td { padding: 4px 10px; font-size: 10px; border: 1px solid #e5e7eb; text-align: center; }
      .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
      @media print {
        .inv { max-width: 100%; }
        .tbl { page-break-inside: auto; }
        .tbl tr { page-break-inside: avoid; }
        .section-title { page-break-after: avoid; }
      }
    `,getBarcodeCss:o=>{const t=o||{},n=t.label_width||3,i=t.label_height||1.5,p=t.name_size||9,e=t.info_size||7,r=t.number_size||6,l=t.barcode_height||40,a=t.sample_size||8,d=t.tests_size||7;return`
        @page { size: ${n}in ${i}in; margin: 0 !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: ${n}in; height: ${i}in; }
        body { font-family: Arial, sans-serif; color: #000; }
        .lbl { width: ${n}in; height: ${i}in; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.08in 0.15in; overflow: hidden; }
        .lbl + .lbl { page-break-before: always; }
        .top-row { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; }
        .num { font-size: ${r}pt; font-weight: bold; line-height: 1.1; }
        .sample { font-size: ${a}pt; font-weight: bold; line-height: 1.2; text-transform: uppercase; }
        .bc { width: 95%; margin: 2px 0; }
        .bc svg { width: 100% !important; height: ${l}px !important; }
        .pname { font-size: ${p}pt; font-weight: bold; line-height: 1.3; margin: 2px 0; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .info-row { width: 100%; display: flex; justify-content: space-between; align-items: center; font-size: ${e}pt; line-height: 1.2; margin-bottom: 2px; }
        .info-row span { white-space: nowrap; }
        .tests-row { font-size: ${d}pt; font-weight: bold; line-height: 1.2; text-align: center; width: 100%; padding: 0 0.05in; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      `},getPrintTableCss:o=>{const t=o||{},n=t.header_font_size||13,i=t.header_font_family||"inherit",p=t.header_color||"#0f172a",e=t.header_bg_color||"#f1f5f9",r=t.header_font_weight||"bold",l=t.body_font_size||13,a=t.body_font_family||"inherit",d=t.body_color||"#1e293b",u=t.body_bg_color||"#ffffff",c=t.border_color||"#e2e8f0",g=t.cell_padding!=null?t.cell_padding:6,w=t.section_spacing!=null?t.section_spacing:12;return`
        #Result .test-group-section table thead th,
        #Result table thead.result-header th,
        .test-group-section table thead th,
        table.result-table th,
        #Result table:not(.print-wrapper):not(.info-table) > thead > tr > th {
          font-size: ${n}px !important;
          font-family: ${i} !important;
          color: ${p} !important;
          background-color: ${e} !important;
          font-weight: ${r} !important;
          border: 1px solid ${c} !important;
          padding: ${g}px !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        #Result .test-group-section table tbody td,
        .test-group-section table tbody td,
        table.result-table td,
        #Result table:not(.print-wrapper):not(.info-table) > tbody > tr > td {
          font-size: ${l}px !important;
          font-family: ${a} !important;
          color: ${d} !important;
          background-color: ${u} !important;
          border: 1px solid ${c} !important;
          padding: ${g}px !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        #Result .test-group-section, .test-group-section,
        #Result .result-section, .result-section {
          margin-bottom: ${w}px !important;
        }
      `},getBlackWhiteCss:()=>`
        #Result, #Result * {
          color: #000 !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        #Result table:not(.print-wrapper):not(.info-table) > thead > tr > th,
        #Result .test-group-section table thead th,
        .test-group-section table thead th {
          color: #000 !important;
          background-color: #fff !important;
          border-color: #000 !important;
        }
        #Result table:not(.print-wrapper):not(.info-table) > tbody > tr > td,
        #Result .test-group-section table tbody td,
        .test-group-section table tbody td {
          color: #000 !important;
          background-color: #fff !important;
          border-color: #000 !important;
        }
        /* Category / group captions keep their shape, lose the tint */
        #Result .section-header,
        #Result .caption,
        #Result .bg-gray-300 {
          background-color: #fff !important;
          color: #000 !important;
          border-color: #000 !important;
        }
        #Result img { filter: grayscale(100%) !important; }
      `,getPatientHeaderCss:o=>{const t=o||{},n=t.name_size||20,i=t.info_size||13,p=t.barcode_height||35,e=t.qr_size||90,r=t.line_height||1.7;return`
        .rs-header .rs-info, .rs-header .rs-dates { font-size: ${i}px !important; line-height: ${r} !important; }
        .rs-header .rs-info > div:first-child { font-size: ${n}px !important; }
        .rs-header .rs-qr svg { width: ${e}px !important; height: ${e}px !important; }
        #Result .patient-header { font-size: ${i}px !important; line-height: ${r} !important; }
        #Result .patient-header .patient-name { font-size: ${n}px !important; }
        #Result .patient-header .info-table td { font-size: ${i}px !important; }
        #Result .patient-header .barcode-box svg { height: ${p}px !important; }
        #Result .patient-header .qr-col svg { width: ${e}px !important; height: ${e}px !important; }
        .patient-header { font-size: ${i}px !important; line-height: ${r} !important; }
        .patient-header .patient-name { font-size: ${n}px !important; }
        .patient-header .info-table td { font-size: ${i}px !important; }
        .patient-header .barcode-box svg { height: ${p}px !important; }
        .patient-header .qr-col svg { width: ${e}px !important; height: ${e}px !important; }
      `},medicalJobOrder:`
      @page { size: A4; margin: 0 !important; }
      @media print {
        body, .page { margin: 0px !important; box-shadow: 0; -webkit-print-color-adjust: exact; color: #000; }
        body { font-family: "Tajawal", text-transform: capitalize; sans-serif; font-size: 14px; margin: 0; padding: 20mm; color: #000; }
        h1 { font-size: 24px; }
        p { font-size: 14px; }
        .report-container { font-family: Arial, sans-serif; width: 100%; margin: 0 auto; }
        .head { margin-bottom: 20px; }
        .header-block { border: 1px solid #ccc; padding: 15px; border-radius: 6px; background-color: #fdfdfd; }
        .barcode-row { display: flex; justify-content: space-around; margin-bottom: 10px; }
        .barcode-box { text-align: center; font-size: 13px; }
        .barcode { display: flex; flex-direction: column; align-items: center; margin-top: 5px; font-size: 12px; }
        .barcode-divider { margin-top: 10px; margin-bottom: 15px; border: none; border-top: 1px dashed #bbb; }
        .header-row { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 15px; margin-bottom: 10px; }
        .header-row > div { flex: 1 1 22%; font-size: 13px; }
        .caption { background: #eaeaea; padding: 6px; font-weight: bold; text-align: center; border-radius: 4px; margin-bottom: 10px; color: #333; }
        .tests-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .tests-table th, .tests-table td { border: 1px solid #ccc; padding: 6px 8px; font-size: 13px; text-align: center; }
        .tests-table th { background-color: #f0f0f0; }
        .footer { margin-top: 30px; display: flex; justify-content: space-around; font-weight: bold; font-size: 14px; padding-top: 10px; border-top: 1px solid #ccc; }
      }
    `,getResultCss:()=>`
        @page { size: A4 portrait; margin: 0 !important; }

        @media print {
          body, .page { margin: 0 !important; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; color: #000; }
          .page-break { page-break-before: always; break-before: page; }

          /* Prevent orphaned headers at bottom of page */
          .caption, .bg-gray-300, .section-header { page-break-after: avoid; break-after: avoid; }

          /* Tables flow across pages; individual rows don't split */
          table { page-break-inside: auto; break-inside: auto; }
          tr { page-break-inside: avoid; break-inside: avoid; page-break-after: auto; }
          thead { display: table-header-group; }
          tfoot { display: table-footer-group; }

          /* Allow print-wrapper content row to break across pages (it holds ALL test data in one cell) */
          .print-wrapper > tbody > tr { page-break-inside: auto; break-inside: auto; }

          /* Sections CAN break across pages (they may be longer than one page) */
          .test-group-section { page-break-inside: auto; break-inside: auto; }

          /* Footer signature stays with surrounding content */
          .sign { page-break-inside: avoid; break-inside: avoid; }
        }

        /* Print wrapper - repeats patient header on every page */
        .print-wrapper { width: 100%; border-collapse: collapse; }
        .pw-cell {
          border: none !important;
          padding: 0 15mm !important;
          text-align: left !important;
          vertical-align: top !important;
          font-size: 14px !important;
        }
        .pw-cell.pw-top { padding-top: 10mm !important; }
        .pw-cell.pw-bottom { padding-bottom: 10mm !important; }

        /* Base styles */
        body { font-family: Arial, sans-serif; font-size: 14px; margin: 0; padding: 0; color: #000; text-transform: capitalize; font-weight: bold; direction: ltr; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; font-weight: bold !important; }

        /* Tailwind utility equivalents for print */
        .hidden { display: none; }
        .w-full { width: 100%; }
        .max-w-3xl { max-width: 48rem; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .p-5 { padding: 1.25rem; }
        .my-5 { margin-top: 1.25rem; margin-bottom: 1.25rem; }
        .my-2 { margin-top: 0.5rem; margin-bottom: 0.5rem; }
        .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }
        .p-1 { padding: 0.25rem; }
        .p-0\\.5 { padding: 0.125rem; }
        .p-2\\.5 { padding: 0.625rem; }
        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        .text-center { text-align: center; }
        .text-base { font-size: 1rem; }
        .font-bold { font-weight: 700; }
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .justify-around { justify-content: space-around; }
        .min-h-\\[25px\\] { min-height: 25px; }
        .w-\\[150px\\] { width: 150px; }

        /* Border utilities */
        .border { border: 1px solid #000; }
        .border-t { border-top: 1px solid #000; }
        .border-b { border-bottom: 1px solid #000; }
        .border-black { border-color: #000; }
        .border-black\\/15 { border-color: rgba(0, 0, 0, 0.15); }
        .border-gray-300 { border-color: #d1d5db; }

        /* Background colors */
        .bg-white { background-color: #fff; }
        .bg-gray-300 { background-color: #d1d5db; }

        /* Table styles */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.125rem; text-align: center; font-size: 12px !important; }
        .border.border-black\\/15 { border: 1px solid rgba(0, 0, 0, 0.15); }

        /* Custom component styles */
        .sign { width: 100%; display: flex; justify-content: center; margin: 9px 0; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; direction: ltr; font-weight: bold; }
        .caption { width: 100%; font-weight: bold; border: 1px solid black; padding: 5px; text-align: center; color: black; border-radius: 5px; background: #dddddce0; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .sections { border-top: 1px solid #000; border-bottom: 1px solid #000; }
        .test-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .test-table th, .test-table td { border: 1px solid rgba(0, 0, 0, 0.158); padding: 10px; text-align: center; }
        .summary { padding: 16px; }
        .logo img { max-width: 150px; }
        .section { display: flex; flex-direction: column; justify-content: space-between; }
        .sectionItem { display: flex; }
        /* Patient header grid */
        .patient-header { display: grid; grid-template-columns: 1fr auto auto; gap: 16px; padding: 12px 20px; border-bottom: 1.5px solid #333; font-size: 13px; line-height: 1.7; align-items: start; font-weight: 700 !important; }
        .patient-header .patient-info { display: block; padding: 0; border: none; }
        .patient-header .patient-name { font-size: 20px; font-weight: 700; margin-bottom: 6px; color: #111; letter-spacing: 0.2px; }
        .patient-header .info-table { border-collapse: collapse; width: auto; }
        .patient-header .info-table td { padding: 1.5px 0 !important; vertical-align: top; white-space: nowrap; text-align: left !important; font-size: 13px !important; color: #333; font-weight: 700 !important; }
        .patient-header .info-table .label { font-weight: 700 !important; padding-right: 4px; color: #555; }
        .patient-header .info-table .colon { padding: 1.5px 6px; }
        .patient-header .center-col { display: flex; flex-direction: column; align-items: center; gap: 8px; padding-left: 16px; border-left: 1px solid #ddd; }
        .patient-header .barcode-box { display: flex; flex-direction: column; align-items: center; }
        .patient-header .barcode-box svg { height: 35px !important; width: auto !important; }
        .patient-header .barcode-num { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .patient-header .qr-col { display: flex; flex-direction: column; align-items: center; gap: 4px; padding-left: 16px; border-left: 1px solid #ddd; }
        .patient-header .qr-label { font-size: 8px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .patient-header .qr-col svg { width: 90px !important; height: 90px !important; }

        .barcode-section { text-align: center; display: flex; }
        .barcode-section img { max-width: 100px; margin: 10px 0; }
        .patient-details { display: flex; flex-direction: column; justify-content: space-between; font-size: 14px; }
        .test-details { padding: 15px 0; border-bottom: 1px solid #ccc; font-size: 16px; text-align: center; }
        .page-break { page-break-before: always; break-before: page; }

        /* Print result container - ensure visibility */
        #Result, .print-result-container { display: block !important; }
      `}}}export{_ as u};
