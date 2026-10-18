/** Both explicit and automatic saves use the same upload contract. */
export function appendResultAttachments(formData, attachments) {
  if (!Array.isArray(attachments)) return;
  const existing = [];
  let index = 0;
  for (const item of attachments) {
    if (item.file instanceof File) {
      item.upload_id ||= crypto.randomUUID();
      formData.append(`attachments[${index}][name]`, item.name?.trim() || item.file.name);
      formData.append(`attachments[${index}][upload_id]`, item.upload_id);
      formData.append(`attachments[${index}][file]`, item.file);
      index++;
    } else if (typeof item.file === 'string' && item.file) {
      existing.push({name: item.name, file: item.file});
    }
  }
  // An explicit empty list removes the last attachment. Omission preserves it.
  formData.append('existing_attachments', JSON.stringify(existing));
}
