/**
 * Executa o Parcel com a pasta temporária dentro do projeto.
 *
 * O Parcel grava arquivos num temporário e depois os move para o destino. No Windows, se o
 * %TEMP% fica num drive diferente do projeto (ex.: C: e B:), a movimentação falha em silêncio:
 * o build diz "Built" mas o dist/ não é atualizado. Usar um temporário no mesmo drive resolve.
 *
 * Uso: node scripts/parcel.js build|watch [argumentos do parcel]
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const tmp = path.join(root, '.parcel-tmp');
fs.mkdirSync(tmp, { recursive: true });

const env = { ...process.env, TMP: tmp, TEMP: tmp, TMPDIR: tmp };
const bin = path.join(root, 'node_modules', 'parcel', 'lib', 'bin.js');

const child = spawn(process.execPath, [bin, ...process.argv.slice(2)], { cwd: root, env, stdio: 'inherit' });
child.on('exit', (code) => process.exit(code ?? 1));
