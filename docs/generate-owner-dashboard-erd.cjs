const fs = require('node:fs');
const path = require('node:path');

const sourcePath = path.join(__dirname, 'owner-dashboard-erd.mmd');
const outputPath = path.join(__dirname, 'owner-dashboard-erd.drawio');
const source = fs.readFileSync(sourcePath, 'utf8');
const escapeXml = (value) => value
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;');

const entities = [...source.matchAll(/^    ([A-Z_]+) \{\r?\n([\s\S]*?)^    \}\r?$/gm)]
  .map(([, name, body]) => ({
    name: name.toLowerCase(),
    fields: body.trim().split(/\r?\n/).map((field) => field.trim().replace(/\s+/g, ' ')),
  }));
const relationships = [...source.matchAll(/^    ([A-Z_]+) (?:\|\||o\|)--(?:\|\{|o\{|\|\|) ([A-Z_]+) : (.+)\r?$/gm)]
  .map(([, from, to, label]) => ({ from: from.toLowerCase(), to: to.toLowerCase(), label }));

if (entities.length !== 6) {
  throw new Error(`Expected six business entities in ${sourcePath}; found ${entities.length}.`);
}

const positions = new Map([
  ['roles', { x: 50, y: 170, width: 350 }],
  ['users', { x: 500, y: 170, width: 420 }],
  ['categories', { x: 980, y: 170, width: 370 }],
  ['products', { x: 980, y: 570, width: 420 }],
  ['orders', { x: 50, y: 570, width: 450 }],
  ['order_items', { x: 520, y: 850, width: 420 }],
]);
const locations = new Map();
const cells = [];
let nextId = 2;
const addVertex = (id, value, style, geometry) =>
  `<mxCell id="${id}" value="${escapeXml(value)}" style="${style}" vertex="1" parent="1"><mxGeometry ${geometry} as="geometry"/></mxCell>`;

cells.push(addVertex(
  nextId++,
  'OUT OF BOREDOM - Owner Dashboard Database\nSix business tables | attribute-level relational ERD',
  'text;html=1;align=left;verticalAlign=middle;whiteSpace=wrap;rounded=0;fontSize=24;fontStyle=1;fontColor=#25211D;',
  'x="40" y="35" width="1000" height="70"',
));

for (const entity of entities) {
  const position = positions.get(entity.name);
  if (!position) throw new Error(`Missing layout position for entity: ${entity.name}`);
  const id = String(nextId++);
  locations.set(entity.name, id);
  const rows = entity.fields.map((field) =>
    `<tr><td style="padding:6px 8px;border-top:1px solid #ead8c4">${field.includes(' PK ') ? '<b>PK</b>' : field.includes(' FK ') ? '<b>FK</b>' : ''}</td><td style="padding:6px 8px;border-top:1px solid #ead8c4">${field}</td></tr>`,
  ).join('');
  const label = `<table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif"><tr><th colspan="2" style="background:#b85f1b;color:#fff;padding:10px;font-size:16px;text-align:left">${entity.name}</th></tr>${rows}</table>`;
  const height = 62 + entity.fields.length * 32;
  cells.push(addVertex(
    id,
    label,
    'shape=rectangle;html=1;whiteSpace=wrap;rounded=0;align=left;verticalAlign=top;fillColor=#FFFFFF;strokeColor=#AA6A2A;strokeWidth=2;spacing=0;',
    `x="${position.x}" y="${position.y}" width="${position.width}" height="${height}"`,
  ));
}

for (const relationship of relationships) {
  const sourceId = locations.get(relationship.from);
  const targetId = locations.get(relationship.to);
  if (!sourceId || !targetId) {
    throw new Error(`Missing relationship endpoint: ${relationship.from} -> ${relationship.to}`);
  }
  cells.push(
    `<mxCell id="${nextId++}" value="${escapeXml(`1 : 0..* — ${relationship.label}`)}" style="edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;startArrow=ERone;startFill=0;endArrow=ERzeroToMany;endFill=0;strokeColor=#5A493B;strokeWidth=2;fontSize=13;" edge="1" parent="1" source="${sourceId}" target="${targetId}"><mxGeometry relative="1" as="geometry"/></mxCell>`,
  );
}

cells.push(addVertex(
  nextId++,
  '<b>Relationship and scope notes</b><br>- One role can have zero or many users.<br>- One category can have zero or many products.<br>- One order contains one or many order items.<br>- One product can appear in zero or many order items.',
  'rounded=1;whiteSpace=wrap;html=1;fillColor=#FFF1DF;strokeColor=#DDAA72;align=left;spacing=12;fontSize=14;',
  'x="520" y="830" width="420" height="150"',
));

cells.push(addVertex(
  nextId++,
  'Laravel framework metadata (including migrations) is separate from the six business tables.',
  'text;html=1;align=left;verticalAlign=middle;whiteSpace=wrap;rounded=0;fontSize=14;fontColor=#62584F;',
  'x="50" y="890" width="900" height="35"',
));

const xml = `<mxfile host="app.diagrams.net" modified="${new Date().toISOString()}" version="25.0.3" type="device"><diagram id="owner-dashboard-erd" name="Owner Dashboard ERD"><mxGraphModel dx="1440" dy="1200" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1440" pageHeight="1200" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>${cells.join('')}</root></mxGraphModel></diagram></mxfile>`;
fs.writeFileSync(outputPath, xml, 'utf8');
console.log(`Generated ${entities.length} tables and ${relationships.length} relationships in ${path.basename(outputPath)}.`);
