// Comments are plain text. Legacy JSON arrays/null sent by old invoices remain readable.
export function commentText(value) {
  if (Array.isArray(value)) return value.map(commentText).filter(Boolean).join('\n');
  if (typeof value !== 'string') return '';
  const text = value.trim();
  if (!text || text === 'null') return '';
  if (text.startsWith('[')) {
    try { const parsed = JSON.parse(text); if (Array.isArray(parsed)) return commentText(parsed); } catch { /* plain text */ }
  }
  return text;
}

export function commentOptions(value) {
  if (typeof value === 'string') {
    try { value = JSON.parse(value); } catch { value = [value]; }
  }
  return [...new Set((Array.isArray(value) ? value : []).map(commentText).filter(Boolean))];
}

export function appendComment(current, choice) {
  const text = commentText(current), addition = commentText(choice);
  if (!addition || text === addition || (`\n${text}\n`).includes(`\n${addition}\n`)) return text;
  return text ? `${text}\n${addition}` : addition;
}

export function reportCommentsVisible(settings) {
  return ![false, 0, '0', 'false'].includes(settings?.print_table_config?.show_comments);
}

export function commentHtml(value) {
  return commentText(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;').replaceAll('{', '&#123;').replaceAll('}', '&#125;').replace(/\r?\n/g, '<br>');
}

const subCommentKeys = sub => [sub.name, String(sub.name || '').trim().replace(/\s+/g, '_').replace(/[^\w؀-ۿ]/g, '').slice(0, 64)]
  .map(name => `{{sub_test.${name}.comment}}`);

// Custom templates may already place comments. Append only the comments without a slot.
export function templateExtraComments(test) {
  const html = test?.content?.html || '';
  return [
    ...(!html.includes('{{comment}}') ? [{ label: test?.report_name || test?.name, text: commentText(test?.comment) }] : []),
    ...(test?.sub_tests || []).filter(sub => !subCommentKeys(sub).some(key => html.includes(key)))
      .map(sub => ({ label: sub.name, text: commentText(sub.comment) })),
  ].filter(row => row.text);
}
