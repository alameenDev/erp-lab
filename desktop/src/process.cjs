const {spawn} = require('node:child_process');
// Always argv, never a shell; command output is returned only to trusted code.
function run(file, args, {env = process.env, cwd, input, timeout = 180000, output} = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(file, args, {env, cwd, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe']});
    const chunks = []; let length = 0, stderr = '';
    const timer = setTimeout(() => {child.kill();reject(new Error(`Process timed out: ${require('node:path').basename(file)}`));}, timeout);
    child.stdout.on('data', b => {if (output) output.write(b);else {length += b.length;if (length < 16 * 1024 * 1024) chunks.push(b);}});
    child.stderr.on('data', b => {if (stderr.length < 16000) stderr += b.toString();});
    child.stdin.on('error', () => {});
    child.on('error', e => {clearTimeout(timer);reject(e);});
    child.on('close', code => {clearTimeout(timer);code === 0 ? resolve(Buffer.concat(chunks).toString()) : reject(Object.assign(new Error(`Process failed: ${require('node:path').basename(file)} (${code})`), {detail: stderr + Buffer.concat(chunks).toString()}));});
    child.stdin.end(input);
  });
}
module.exports = {run};
