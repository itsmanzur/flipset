const fs = require('fs');
const path = require('path');

const pluginRoot = path.resolve(__dirname, '..');
const poFile = path.join(pluginRoot, 'translations-src/foliora-bn_BD.po');
const moFile = path.join(pluginRoot, 'translations-src/foliora-bn_BD.mo');

const content = fs.readFileSync(poFile, 'utf8');
const lines = content.split(/\r?\n/);

let entries = [];
let currentMsgid = null;
let currentMsgstr = null;
let reading = null;

function unescapeStr(str) {
    return str.replace(/\\"/g, '"').replace(/\\\\/g, '\\').replace(/\\n/g, '\n');
}

for (let line of lines) {
    if (line.startsWith('msgid "')) {
        if (currentMsgid !== null && currentMsgstr !== null) {
            entries.push({ id: currentMsgid, str: currentMsgstr });
        }
        currentMsgid = unescapeStr(line.slice(7, -1));
        currentMsgstr = null;
        reading = 'msgid';
    } else if (line.startsWith('msgstr "')) {
        currentMsgstr = unescapeStr(line.slice(8, -1));
        reading = 'msgstr';
    } else if (line.startsWith('"') && line.endsWith('"')) {
        const text = unescapeStr(line.slice(1, -1));
        if (reading === 'msgid') {
            currentMsgid += text;
        } else if (reading === 'msgstr') {
            currentMsgstr += text;
        }
    }
}
if (currentMsgid !== null && currentMsgstr !== null) {
    entries.push({ id: currentMsgid, str: currentMsgstr });
}

entries = entries.filter(e => e.id === '' || e.str.length > 0);

entries.sort((a, b) => {
    if (a.id < b.id) return -1;
    if (a.id > b.id) return 1;
    return 0;
});

const numStrings = entries.length;
const originalsTableOffset = 28;
const translationsTableOffset = originalsTableOffset + numStrings * 8;
let currentStringOffset = translationsTableOffset + numStrings * 8;

const originalBuffers = entries.map(e => Buffer.from(e.id, 'utf8'));
const translationBuffers = entries.map(e => Buffer.from(e.str, 'utf8'));

const header = Buffer.alloc(28);
header.writeUInt32LE(0x950412de, 0); // Magic
header.writeUInt32LE(0, 4); // Revision
header.writeUInt32LE(numStrings, 8); // Number of strings
header.writeUInt32LE(originalsTableOffset, 12); // Offset of originals table
header.writeUInt32LE(translationsTableOffset, 16); // Offset of translations table
header.writeUInt32LE(0, 20); // Hash table size
header.writeUInt32LE(0, 24); // Hash table offset

const originalsTable = Buffer.alloc(numStrings * 8);
const translationsTable = Buffer.alloc(numStrings * 8);

const dataBuffers = [];

for (let i = 0; i < numStrings; i++) {
    const origBuf = originalBuffers[i];
    originalsTable.writeUInt32LE(origBuf.length, i * 8);
    originalsTable.writeUInt32LE(currentStringOffset, i * 8 + 4);
    dataBuffers.push(origBuf, Buffer.from([0]));
    currentStringOffset += origBuf.length + 1;
}

for (let i = 0; i < numStrings; i++) {
    const transBuf = translationBuffers[i];
    translationsTable.writeUInt32LE(transBuf.length, i * 8);
    translationsTable.writeUInt32LE(currentStringOffset, i * 8 + 4);
    dataBuffers.push(transBuf, Buffer.from([0]));
    currentStringOffset += transBuf.length + 1;
}

const finalBuffer = Buffer.concat([header, originalsTable, translationsTable, ...dataBuffers]);
fs.writeFileSync(moFile, finalBuffer);
console.log(`Successfully compiled ${numStrings} strings into ${moFile} (${finalBuffer.length} bytes)`);
