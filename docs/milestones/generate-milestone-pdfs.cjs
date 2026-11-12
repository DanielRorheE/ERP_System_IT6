const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawnSync } = require('node:child_process');

const root = path.resolve(__dirname, '..', '..');
const outputDirectory = path.join(__dirname, 'pdf');
const assetDirectory = path.join(root, 'docs', 'milestone-assets');
const chromeCandidates = [
  process.env.CHROME_PATH,
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
];
const chrome = chromeCandidates.find((candidate) => candidate && fs.existsSync(candidate));
const projectTitle = 'OUT OF BOREDOM Business Owner Dashboard';
const businessContext = 'SMS Cafe';
const asOf = '12 November 2026';
let tableNumber = 0;

if (!chrome) {
  throw new Error('Google Chrome or Microsoft Edge is required to export the milestone PDFs.');
}

fs.mkdirSync(outputDirectory, { recursive: true });

const esc = (value) => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#39;');
const paragraph = (text) => `<p>${text}</p>`;
const bullets = (items) => `<ul>${items.map((item) => `<li>${item}</li>`).join('')}</ul>`;
const numbered = (items) => `<ol>${items.map((item) => `<li>${item}</li>`).join('')}</ol>`;
const code = (text, language = 'sql') =>
  `<pre class="code ${language}"><code>${esc(text.trim())}</code></pre>`;
const callout = (label, text) =>
  `<aside class="callout"><strong>${label}</strong><div>${text}</div></aside>`;
const section = (title, body) => `<section class="report-section"><h2>${title}</h2>${body}</section>`;
const subsection = (title, body, level = 3, className = '') =>
  `<section class="subsection ${className}"><h${level}>${title}</h${level}>${body}</section>`;
const grid = (headers, rows, caption, className = '') => `
  <div class="table-wrap">
    <table class="${className}">
      <caption><strong>Table ${++tableNumber}. ${caption}</strong></caption>
      <thead><tr>${headers.map((value) => `<th>${value}</th>`).join('')}</tr></thead>
      <tbody>${rows.map((row) => `<tr>${row.map((value) => `<td>${value}</td>`).join('')}</tr>`).join('')}</tbody>
    </table>
  </div>`;
const imageFigure = (file, caption, className = '') => {
  const imagePath = path.join(assetDirectory, file);
  if (!fs.existsSync(imagePath)) {
    throw new Error(`Required milestone image not found: ${imagePath}`);
  }
  const mimeType = path.extname(file).toLowerCase() === '.svg' ? 'image/svg+xml' : 'image/png';
  const data = fs.readFileSync(imagePath).toString('base64');
  return `<figure class="image-figure ${className}"><img src="data:${mimeType};base64,${data}" alt="${esc(caption)}"><figcaption>${caption}</figcaption></figure>`;
};
const schemaCards = (items) => `
  <div class="schema-cards">
    ${items.map(([name, description]) => `<article class="schema-card"><h4><code>${esc(name)}</code></h4><p>${esc(description)}</p></article>`).join('')}
  </div>`;

const schema = [
  ['roles', 'id BIGINT UNSIGNED PK; name VARCHAR(50) NOT NULL UNIQUE. Current role: owner.'],
  ['users', 'id BIGINT UNSIGNED PK; role_id BIGINT UNSIGNED NOT NULL FK -> roles.id; email VARCHAR(255) NOT NULL UNIQUE; password VARCHAR(255) NOT NULL.'],
  ['categories', 'id BIGINT UNSIGNED PK; name VARCHAR(120) NOT NULL UNIQUE.'],
  ['products', 'id BIGINT UNSIGNED PK; category_id BIGINT UNSIGNED NULL FK → categories.id (ON DELETE SET NULL); name VARCHAR(150) NOT NULL; price DECIMAL(12,2) NOT NULL; cost_price DECIMAL(12,2) NOT NULL DEFAULT 0; stock_quantity DECIMAL(12,4) NOT NULL DEFAULT 0; is_available BOOLEAN NOT NULL DEFAULT TRUE; index (is_available, name).'],
  ['orders', 'id BIGINT UNSIGNED PK; order_number VARCHAR(40) NOT NULL UNIQUE; total_amount DECIMAL(14,2) NOT NULL; cost_total DECIMAL(14,2) NOT NULL DEFAULT 0; status VARCHAR(12) NOT NULL; created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP; index (status, created_at).'],
  ['order_items', 'id BIGINT UNSIGNED PK; order_id BIGINT UNSIGNED NOT NULL FK -> orders.id (ON DELETE CASCADE); product_id BIGINT UNSIGNED NOT NULL FK -> products.id (ON DELETE RESTRICT); quantity DECIMAL(12,4) NOT NULL; unit_price DECIMAL(12,2) NOT NULL; line_total DECIMAL(14,2) NOT NULL.'],
];

const chapter1 = [
  section('Chapter 1. Project Introduction', `
    ${subsection('1.1 Project Title', `<p><strong>${projectTitle}</strong> — a single-owner business overview for the ${businessContext} business. OUT OF BOREDOM is the project and brand name; ${businessContext} identifies the business context.</p>`)}
    ${subsection('1.2 Background of the Study / Existing Process', `
      <p>A cafe owner needs a quick way to review sales, product availability, stock warnings, and recent orders without navigating a full point-of-sale or staff-management system. This project simplifies the earlier cafe ERP into a focused business-owner dashboard.</p>
      <p>The current Laravel application presents a protected overview for one owner. It displays business metrics and recent activity from a compact relational schema; it is not an operational POS, purchasing, payment, or accounting application.</p>
    `)}
    ${subsection('1.3 Statement of the Problem', `
      <p>The project addresses the following oversight needs:</p>
      ${numbered([
        'How can the owner review daily revenue and completed-order volume from one page?',
        'How can products requiring replenishment be identified from current stock quantities?',
        'How can monthly sales, estimated profit, active products, and recent order status be summarized clearly?',
        'How can the owner enter basic sales and product data so the dashboard reflects the business?',
      ])}
    `)}
    ${subsection('1.4 General and Specific Objectives', `
      <p><strong>General objective:</strong> provide a minimal, secure overview of ${businessContext} business health with simple owner data input.</p>
      ${bullets([
        'Keep the business data model to roles, users, categories, products, orders, and order items.',
        'Show daily sales, completed-order count, monthly revenue, estimated profit, low-stock count, and active-product count.',
        'Provide a seven-day daily-sales trend and a recent-order stream.',
        'Use owner-only authentication and provide only simple sale and product input forms.',
        'Document the implemented schema and its constraints in a diagram with field attributes.',
      ])}
    `)}
    ${subsection('1.5 Scope and Limitations', `
      <p><strong>Included:</strong> owner sign-in, simple sale input, simple product input, dashboard metrics, product stock and availability status, a product-category relationship, order totals/status/timestamps, and an estimated monthly profit based on recorded order cost snapshots.</p>
      <p><strong>Excluded:</strong> full POS features, editing/deleting records, customers, payment records, purchasing, inventory movement history, accounting, refunds, staff and permission management, and multi-branch operations. Sales now include one product line per simple sale; multiple-line checkout is outside this release.</p>
      <p>The estimated profit is completed-order revenue less the recorded <code>cost_total</code>; its accuracy depends on the cost snapshots supplied when orders are recorded. The dashboard does not calculate tax or audited net income.</p>
    `)}
    ${subsection('1.6 Intended Users and Benefits', `
      <p>The intended user is the SMS Cafe business owner. The application is designed as a single-owner system; the database credentials and shared PIN are not included in this report.</p>
    `)}
  `),
];

const chapter2 = [
  section('Chapter 2. Requirements Analysis', `
    ${subsection('2.1 Description of the Existing Process', `
      <p>This milestone describes the implemented application and database, not a measured cafe workflow. The analysis is based on the Laravel routes, controller, models, migrations, tests, and current project documentation. No production transaction dataset or interview results are claimed.</p>
    `)}
    ${subsection('2.2 Data Gathering Method and Findings', `
      <p>Requirements were identified by reviewing the existing application structure and intended owner workflow: record basic business data, then review a concise dashboard. The findings were that a read-only dashboard could not receive the inputs needed for meaningful reporting, so the release adds two controlled creation forms while keeping the scope small.</p>
    `)}
    ${subsection('2.3 Functional Requirements', `
      ${grid(['ID','Requirement','Implementation'], [
        ['FR-01','Authenticate the owner before showing dashboard data.','Guest login routes and authenticated dashboard route.'],
        ['FR-02','Summarize today’s completed sales and order count.','Aggregated query over completed orders and created_at.'],
        ['FR-03','Show current low-stock products.','Products with stock_quantity less than or equal to 10.'],
        ['FR-04','Summarize current-month revenue and estimated profit.','Completed orders; total_amount less cost_total for the estimate.'],
        ['FR-05','Show available products and recent orders.','Product availability count and latest ten orders.'],
        ['FR-06','Display a seven-day daily-sales trend.','Completed-order totals grouped into dates and zero-filled for missing dates.'],
        ['FR-07','Allow the owner to record a simple sale.','Authenticated sale form writes an order snapshot.'],
        ['FR-08','Allow the owner to add a product.','Authenticated product form writes a product with price, cost, stock, and availability.'],
      ], 'Functional requirements and their current implementation.') }
    `)}
    ${subsection('2.4 Business Rules', `
      ${numbered([
        'A completed order contributes to revenue and completed-order metrics; cancelled orders do not.',
        'Low-stock products are those with stock_quantity less than or equal to 10.',
        'An active product is a product whose is_available value is true.',
        'Estimated profit is a reporting estimate: total_amount minus cost_total for completed orders.',
        'Order status is stored as a VARCHAR; completed and cancelled are application values rather than a database enum/check constraint.',
        'The category reference on a product is optional. Deleting a category sets the product category_id to NULL.',
      ])}
    `, 3, 'keep-together')}
    ${subsection('2.5 Non-Functional Requirements', `
      ${bullets([
        'Protect dashboard routes with Laravel authentication.',
        'Aggregate metrics in database queries and return only the fields needed by the view.',
        'Use configured application time zone for date boundaries and present a responsive, uncluttered interface.',
        'Protect input forms with authentication and do not display authentication secrets in source or documentation.',
      ])}
    `)}
  `),
];

const chapter3 = [
  section('Chapter 3. Database Design', `
    ${subsection('3.1 Identified Entities and Attributes', `
      <p>The business schema has six tables: <code>roles</code>, <code>users</code>, <code>categories</code>, <code>products</code>, <code>orders</code>, and <code>order_items</code>. The declared business foreign keys connect roles to users, categories to products, orders to order items, and products to order items.</p>
      <p>Orders connect to products through <code>order_items</code>. The current simple input form creates one order item per sale; a future full POS may add multiple items to one order.</p>
    `)}
    ${subsection('3.2 Entity-Relationship Diagram (ERD)', `
      <p>Figure 3.1 shows the complete six-table schema in diagram mode, including attributes, data types, key markers, nullability, defaults, cardinalities, and all declared foreign-key relationships.</p>
      ${imageFigure('owner-dashboard-erd-v2.svg', 'Figure 3.1. Attribute-level ERD for the OUT OF BOREDOM owner dashboard.', 'erd-figure')}
      <p class="small">Editable sources: <code>docs/owner-dashboard-erd.drawio</code> and <code>docs/owner-dashboard-erd.mmd</code>. SVG export: <code>docs/milestone-assets/owner-dashboard-erd-v2.svg</code>.</p>
    `)}
    ${subsection('3.3 Relational Schema', `
      <p>PK = primary key; FK = foreign key; UK = unique; NULL = nullable. The six business tables are listed below. Laravel’s migration-history table is framework metadata, not a business entity.</p>
    `)}
    ${subsection('3.4 Database Normalization (1NF, 2NF, 3NF)', `
      <p>Each relation has a surrogate primary key and scalar attributes. Category names and owner email values are unique. Product attributes are stored on products; order totals and cost totals are stored as transaction snapshots. No derived product/order association is fabricated in the ERD.</p>
      <p>The nullable foreign key on products preserves a product if its category is deleted. Orders are independent records in this release. The <code>status</code> field has application conventions but no enum or check constraint in the migration.</p>
    `)}
    ${subsection('3.5 Data Dictionary', `
      <p>PK = primary key; FK = foreign key; UK = unique; NULL = nullable. Each card below shows one business table and its important fields, keys, and constraints.</p>
      ${schemaCards(schema)}
    `)}
    ${subsection('3.6 Primary Keys, Foreign Keys, and Constraints', `
      <p>Each relation uses an auto-incrementing primary key. Owner email, category name, and order number are unique. <code>products.category_id</code> is nullable with <code>ON DELETE SET NULL</code>. Product and order indexes support the dashboard lookup patterns, and order status values are validated by the application.</p>
    `)}
    ${subsection('3.7 Initial SQL Database Implementation', `
      <p>The Laravel migrations are the source of truth for the schema. They create the owner role relationship and the six business tables: roles, users, categories, products, orders, and order_items. The original cutover migration is intentionally non-reversible; restore a database backup to recover removed data.</p>
    `)}
  `),
];

const chapter4 = [
  section('Chapter 4. System Development and Database Integration', `
    ${subsection('4.1 Development Tools and Technologies', `
      ${grid(['Layer','Implementation'], [
        ['Application','Laravel, PHP, Eloquent models, controllers, routes, and Blade templates.'],
        ['Database','MySQL/MariaDB for local XAMPP use; SQLite is used for isolated automated tests.'],
        ['Interface','Blade with Tailwind CSS and Vite assets.'],
        ['Authentication','Owner-only sign-in protected with Laravel guest/auth middleware; secrets are not documented here.'],
        ['Schema','Versioned Laravel migrations; the migration-history table is framework metadata.'],
      ], 'Development technologies and their roles.')}
    `)}
    ${subsection('4.2 System Architecture', `
      <p>The browser requests authenticated dashboard or business-input routes. Laravel validates input, stores simple order/product records, calculates summary aggregates and date-series data, and passes results to Blade views.</p>
      <div class="architecture-diagram" role="img" aria-label="System architecture flow from browser to Laravel route, controller, database, and Blade interface">
        <div class="architecture-node">Browser<br><span>Owner</span></div>
        <div class="architecture-arrow">→</div>
        <div class="architecture-node">Authenticated<br>Laravel Route</div>
        <div class="architecture-arrow">→</div>
        <div class="architecture-node">Controller<br><span>Input / Dashboard</span></div>
        <div class="architecture-arrow">→</div>
        <div class="architecture-node">Database<br><span>Records / Aggregates</span></div>
        <div class="architecture-arrow">→</div>
        <div class="architecture-node">Blade Interface<br><span>Results</span></div>
      </div>
    `)}
    ${subsection('4.3 Database Connection Implementation and Dashboard Data Processing', `
      ${bullets([
        'Today’s revenue and order count use completed orders within the configured application-time-zone day.',
        'Monthly revenue and estimated profit use completed orders in the current calendar month.',
        'Low-stock alerts query products at or below the threshold and order them by quantity.',
        'The seven-day series groups completed-order totals by date and supplies zero totals for dates with no orders.',
        'Recent activity selects at most ten orders and returns only the fields needed for display.',
        'The business input page validates and stores a sale, its product line, stock change, or a new product using the related orders, order_items, and products tables.',
      ])}
    `)}
    ${subsection('4.4 CRUD Operations Implementation', `
      <p>The following representative aggregate illustrates the completed-order basis for sales and estimated profit:</p>
      ${code(`
SELECT
    COALESCE(SUM(total_amount), 0) AS revenue,
    COALESCE(SUM(total_amount - cost_total), 0) AS estimated_profit
FROM orders
WHERE status = 'completed'
  AND created_at >= :period_start
  AND created_at <= :period_end;`)}
      <p>Date boundaries are supplied as bound parameters by the application; user-provided values are not concatenated into SQL.</p>
    `)}
    ${subsection('4.5 Advanced SQL Implementation', `
      <p>The dashboard uses aggregate functions such as <code>SUM</code> and <code>COUNT</code>, conditional filtering, date grouping, and ordered result sets. These queries provide reporting without loading every record into application memory.</p>
    `)}
    ${subsection('4.6 Application Interface and Screenshots', `
      <p>The application provides an authenticated owner dashboard and a separate business-input page. The interface uses clear cards, date filters, low-stock alerts, recent activity, and two focused forms. Screenshots may be captured from the running application for presentation evidence.</p>
    `)}
    ${subsection('4.7 Input Validation and Error Handling', `
      <p>Inputs require valid numbers, non-negative amounts, allowed statuses, required names, unique order numbers, and valid dates. Failed validation returns the user to the form with readable error messages; successful submissions return a confirmation message.</p>
    `)}
  `),
];

const chapter5 = [
  section('Chapter 5. Transaction Management and Advanced Database Operations', `
    ${subsection('5.1 Transactional Process 1', `<p>The owner submits a sale. The application validates the order number, product, quantity, status, and date, then creates an order and its order item using the selected product price and cost snapshot. A completed sale reduces stock and becomes available to dashboard aggregates.</p>`)}
    ${subsection('5.2 Transactional Process 2', `<p>The owner submits a product. The application validates the name, prices, stock quantity, and availability, then creates one product record. The dashboard immediately reflects the product count and stock status.</p>`)}
    ${subsection('5.3 Transactional Process 3', `<p>The owner selects a date range on the dashboard. The application applies date boundaries and status filters, calculates aggregates, and returns the selected trend and recent activity without changing records.</p>`)}
    ${subsection('5.4 COMMIT and ROLLBACK Implementation', `<p>Sale creation uses a database transaction because it writes an order, an order item, and the completed-sale stock change together. If validation or a stock check fails, the operation is rejected; if an unexpected database error occurs, the transaction is rolled back.</p>`)}
    ${subsection('5.5 Stored Procedures and Functions', `<p>No stored procedures or database functions are required in this release. Reporting calculations are expressed through Laravel’s query builder.</p>`)}
    ${subsection('5.6 SQL Triggers', `<p>No business triggers are used. Stock is entered directly as part of the simple product record; inventory movement history is outside scope.</p>`)}
    ${subsection('5.7 Indexing and Query Optimization', `
      <p>Primary keys identify records. Unique constraints protect owner email, category name, and order number. The product-category foreign key is nullable and uses <code>ON DELETE SET NULL</code>. The schema adds indexes for product availability/name, order status/creation time, and the order-item foreign keys used to connect sales and products.</p>
    `)}
    ${subsection('5.8 Database Security and SQL Injection Prevention', `
      <p>The dashboard route is protected by authentication. The application is scoped to one owner rather than a staff role matrix. Authentication secrets are configured locally and are intentionally excluded from the milestone deliverables.</p>
      <p>The dashboard is reporting-focused, while the separate business-input page permits only simple creation of sales and products. Editing workflows, customer records, payment records, and audit/event logs are outside this system’s current scope.</p>
    `)}
    ${subsection('5.9 Query Optimization Details', `
      ${bullets([
        'Use database aggregates for summary totals instead of loading all orders into application memory.',
        'Select only required columns for low-stock and recent-order lists.',
        'Apply date bounds and status filters before grouping.',
        'Use the migration-defined composite indexes for product availability/name and order status/created_at access patterns.',
      ])}
      <p>For larger datasets, review query plans and index selectivity against representative data before adding indexes.</p>
    `)}
  `),
];

const chapter6 = [
  section('Chapter 6. Database Administration and Testing', `
    ${subsection('6.1 Database User Roles and Privileges', `<p>The system uses one configured owner account protected by the application authentication middleware. Staff roles and privilege administration are outside the current scope.</p>`)}
    ${subsection('6.2 Database Backup and Restoration', `<p>The schema cutover is destructive and must be preceded by a database backup. Restoration is performed from the selected database backup because the cutover migration is intentionally non-reversible.</p>`)}
    ${subsection('6.3 System Test Cases and Results', `
      <p>The Laravel feature suite verifies owner authentication, dashboard rendering, key metric calculations, product low-stock behavior, date filtering, business input, role-aware schema setup, and protected routes using an isolated test database. The current project verification is <strong>13 tests passed with 85 assertions</strong>; this is an automated test result, not production acceptance certification.</p>
      ${grid(['Area','Expected behavior'], [
        ['Authentication','Guests are directed to sign-in; authenticated owner can access the dashboard.'],
        ['Sales totals','Only completed orders in the requested period contribute to revenue metrics.'],
        ['Low stock','Products at or below the configured threshold are listed.'],
        ['Trend','Seven dates are returned, including zero-value dates.'],
        ['Business input','Authenticated forms create valid sale and product records.'],
      ], 'Representative test coverage.')}
      <p>Test results against SQLite do not replace acceptance testing against the configured XAMPP MySQL/MariaDB database.</p>
    `)}
    ${subsection('6.4 Data Integrity and Validation Testing', `<p>Validation tests confirm that required fields, numeric limits, allowed statuses, date formats, and unique order numbers are enforced before records are inserted.</p>`)}
    ${subsection('6.5 System Limitations and Identified Issues', `
      ${bullets([
        'The simple form creates one order item per sale; multi-line checkout and product-level charts are future enhancements.',
        'No customers, payment methods, payment references, staff attribution, or staff activity are retained.',
        'Sales and products can be created, but existing records cannot yet be edited or deleted.',
        'Estimated profit depends on stored cost_total snapshots and is not an audited accounting measure.',
        'Order statuses are application-level strings rather than a database enum/check constraint.',
        'The schema cutover migration is destructive and cannot be rolled back automatically.',
        'The user table is intended for one owner account; account-management and multi-user workflows are outside scope.',
      ])}
    `)}
  `),
];

const chapter7 = [
  section('Chapter 7. Conclusion and Recommendations', `
    ${subsection('7.1 Summary', `
      <p>OUT OF BOREDOM is now a focused, single-owner SMS Cafe overview rather than a multi-workflow cafe ERP. The owner can enter sales linked to products and add products, while the Laravel dashboard reports sales, product availability, stock alerts, estimated profit, daily trends, and recent orders from six business tables.</p>
    `)}
    ${subsection('7.2 Achievement of Project Objectives', `
      ${grid(['Objective','Current result'], [
        ['Simplify the business schema','Six business tables: roles, users, categories, products, orders, and order_items.'],
        ['Provide owner business metrics','Daily and monthly sales summaries, estimated profit, low stock, active products, and a seven-day trend.'],
        ['Keep the interface simple','Dashboard reporting is paired with small authenticated sale and product input forms.'],
        ['Document attributes and keys','Attribute-level Draw.io and Mermaid ERD sources plus a schema/data dictionary.'],
      ], 'Objective completion summary.')}
    `)}
    ${subsection('7.4 Recommendations for Future Improvements', `
      ${numbered([
        'Maintain verified database backups before any destructive migration or schema change.',
        'Add product-level sales charts and multi-line checkout after collecting enough order-item history.',
        'Add edit/delete workflows only after confirming the required audit and correction rules.',
        'Validate the cost snapshot policy with the business owner before using estimated profit for decisions.',
        'Perform acceptance testing against the intended MySQL/MariaDB configuration and confirm timezone and currency settings.',
        'Keep authentication secrets out of reports, source control, and shared screenshots.',
      ])}
    `)}
  `),
];

const appendix = [
  section('Appendices', `
    ${subsection('Appendix A — Complete SQL Scripts', `
      <p>The authoritative schema cutover is maintained in <code>database/migrations/2026_11_11_000001_replace_cafe_erp_with_owner_dashboard.php</code>. It is destructive and intentionally has no down migration. The database’s <code>migrations</code> table is retained as Laravel framework metadata.</p>
      <p>The role relationship is added by <code>database/migrations/2026_11_12_000001_add_owner_role.php</code>. It creates the <code>roles</code> table, adds <code>users.role_id</code>, and assigns the owner role.</p>
      <p>The schema includes the cost snapshots <code>products.cost_price</code> and <code>orders.cost_total</code> to support the requested estimated-profit metric.</p>
    `)}
    ${subsection('Appendix B — Attribute-Level ERD', `
      ${imageFigure('owner-dashboard-erd-v2.svg', 'Figure B.1. Current owner dashboard database ERD.', 'erd-figure')}
      <p>Editable diagram: <code>docs/owner-dashboard-erd.drawio</code>. Text diagram: <code>docs/owner-dashboard-erd.mmd</code>. The diagram shows all declared relationships: roles to users, categories to products, orders to order_items, and products to order_items.</p>
    `)}
    ${subsection('Appendix C — Application Screenshots', `
      ${imageFigure('dashboard-live.png', 'Figure C.1. Authenticated owner dashboard with business summary, trend, stock alerts, and recent activity.', 'screenshot-figure')}
      ${imageFigure('business-input-live.png', 'Figure C.2. Authenticated business input page for recording a sale and adding a product.', 'screenshot-figure')}
    `)}
    ${subsection('Appendix D — System Testing Results', `
      <p>The final automated verification completed with <strong>13 tests passed and 85 assertions passed</strong>. Coverage includes authentication, dashboard calculations, date validation, business input, owner-role schema setup, and protected routes.</p>
    `)}
    ${subsection('Appendix E — Source Code File Inventory', `
      <p>Core source files include <code>app/Http/Controllers/OwnerDashboardController.php</code>, <code>app/Http/Controllers/BusinessInputController.php</code>, <code>app/Http/Controllers/Auth/AuthenticatedSessionController.php</code>, <code>resources/views/owner/dashboard.blade.php</code>, <code>resources/views/owner/input.blade.php</code>, the database migrations, and the feature tests in <code>tests/Feature</code>.</p>
      <p>The current release includes simple creation forms for sales and products, but not a full POS. No owner PIN, database password, or other credential is printed in the reports.</p>
    `)}
  `),
];

const milestoneContents = [
  { number: 1, title: 'First Milestone Deliverable', chapters: [...chapter1, ...chapter2, ...chapter3] },
  { number: 2, title: 'Second Milestone Deliverable', chapters: [...chapter1, ...chapter2, ...chapter3, ...chapter4] },
  { number: 3, title: 'Final Milestone Deliverable', chapters: [...chapter1, ...chapter2, ...chapter3, ...chapter4, ...chapter5, ...chapter6, ...chapter7, ...appendix] },
];

const stylesheet = `
  @page { size: A4; margin: 25.4mm; }
  @page { @bottom-center { content: counter(page); color: #333; font: 10pt "Times New Roman", serif; } }
  * { box-sizing: border-box; }
  body { margin: 0; color: #171411; font: 12pt/1.5 "Times New Roman", Times, serif; text-align: justify; }
  h1, h2, h3 { line-height: 1.2; text-align: left; page-break-inside: avoid; break-inside: avoid; page-break-after: avoid; }
  h1 { font-size: 16pt; margin: 0 0 5mm; text-align: center; }
  h2 { font-size: 16pt; margin: 0 0 5mm; }
  h3 { font-size: 13pt; margin: 5mm 0 2mm; }
  p { margin: 0 0 4mm; orphans: 3; widows: 3; }
  li { margin: 0 0 1.5mm; }
  ul, ol { margin: 2mm 0 4mm; padding-left: 7mm; }
  code { font: 9.5pt "Courier New", monospace; overflow-wrap: anywhere; }
  .cover { min-height: 253mm; page-break-after: always; display: flex; flex-direction: column; justify-content: center; text-align: center; }
  .cover img { width: 35mm; height: 35mm; object-fit: contain; margin: 0 auto 5mm; }
  .university { font-size: 14pt; font-weight: bold; }
  .college { font-size: 12pt; margin: 1mm 0 7mm; }
  .course { font-size: 12pt; margin-bottom: 7mm; }
  .cover h1 { font-size: 18pt; margin: 0 auto 3mm; max-width: 165mm; text-align: center; }
  .subtitle { font-size: 14pt; font-weight: bold; margin-bottom: 8mm; }
  .doc-label { font-size: 12pt; font-weight: bold; margin-bottom: 12mm; }
  .submitted { border-top: 1px solid #444; padding-top: 5mm; width: 120mm; margin: 0 auto; text-align: center; }
  .submitted p { margin-bottom: 1.5mm; text-align: center; }
  .toc { page-break-after: always; }
  .toc li { margin-bottom: 2mm; }
  .report-section { page-break-before: always; break-before: page; }
  .keep-together { page-break-inside: avoid; break-inside: avoid; }
  .table-wrap { margin: 4mm 0 6mm; page-break-inside: auto; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.5pt; line-height: 1.25; text-align: left; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; break-inside: avoid; }
  caption { text-align: left; margin-bottom: 2mm; font-size: 10pt; }
  th, td { border: 1px solid #8a8178; padding: 1.8mm; vertical-align: top; overflow-wrap: anywhere; word-break: normal; }
  th { background: #f3e5d6; }
  .schema-table { font-size: 8.5pt; }
  .schema-table th:first-child, .schema-table td:first-child { width: 24%; }
  .schema-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; margin: 4mm 0 6mm; break-inside: avoid; page-break-inside: avoid; }
  .schema-card { border: 1px solid #8a8178; background: #fffaf5; padding: 3mm; break-inside: avoid; page-break-inside: avoid; }
  .schema-card h4 { margin: 0 0 2mm; padding-bottom: 1.5mm; border-bottom: 1px solid #d6c6b8; font-size: 11pt; }
  .schema-card p { margin: 0; font-size: 8.5pt; line-height: 1.3; text-align: left; overflow-wrap: anywhere; }
  .code { white-space: pre-wrap; overflow-wrap: anywhere; background: #f6f2ed; border: 1px solid #b7aaa0; padding: 3mm; margin: 3mm 0; font: 9pt/1.3 "Courier New", monospace; text-align: left; }
  .architecture { border: 1px solid #aa6a2a; background: #fff6eb; text-align: center; padding: 6mm; margin: 5mm 0; font-weight: bold; }
  .architecture-diagram { display: flex; align-items: center; justify-content: center; gap: 2mm; margin: 6mm 0; padding: 5mm 2mm; border: 1px solid #aa6a2a; background: #fffaf5; break-inside: avoid; page-break-inside: avoid; }
  .architecture-node { flex: 1; min-height: 18mm; display: flex; flex-direction: column; justify-content: center; border: 1px solid #8a5b36; background: #f3e5d6; padding: 2.5mm 1.5mm; text-align: center; font-weight: bold; font-size: 9pt; line-height: 1.2; }
  .architecture-node span { font-size: 7.5pt; font-weight: normal; }
  .architecture-arrow { color: #8a5b36; font-size: 15pt; font-weight: bold; }
  .callout { border-left: 3px solid #b85f1b; background: #fff6eb; padding: 3mm; margin: 3mm 0; }
  .image-figure { break-inside: avoid; page-break-inside: avoid; margin: 3mm auto 5mm; text-align: center; }
  .image-figure img { display: block; width: 100%; max-height: 150mm; object-fit: contain; }
  .erd-figure img { max-height: 150mm; border: 0; }
  .screenshot-figure img { max-height: 155mm; border: 1px solid #c9b9ab; object-fit: contain; }
  figcaption { font-size: 10pt; font-style: italic; padding: 2mm; }
  .small { font-size: 9pt; }
`;

const logoPath = path.join(assetDirectory, 'university-of-mindanao-logo.png');
if (!fs.existsSync(logoPath)) {
  throw new Error(`University of Mindanao logo not found: ${logoPath}`);
}
const logo = `data:image/png;base64,${fs.readFileSync(logoPath).toString('base64')}`;

function documentHtml(milestone) {
  tableNumber = 0;
  const contents = milestone.chapters
    .map((item) => item.match(/<h2>(.*?)<\/h2>/)?.[1])
    .filter(Boolean);
  const toc = `<nav class="toc"><h2>Contents</h2><ol>${contents.map((item) => `<li>${item}</li>`).join('')}</ol></nav>`;
  const cover = `
    <header class="cover">
      <img src="${logo}" alt="University of Mindanao seal">
      <div class="university">UNIVERSITY OF MINDANAO</div>
      <div class="college">COLLEGE OF COMPUTING EDUCATION</div>
      <div class="course">CCE/BSIT &nbsp;·&nbsp; IT6/L – FUNDAMENTALS OF DATABASE</div>
      <h1>${esc(projectTitle)}</h1>
      <div class="subtitle">Milestone ${milestone.number}</div>
      <div class="doc-label">${esc(milestone.title.toUpperCase())}<br>FINAL PROJECT TECHNICAL DOCUMENTATION</div>
      <div class="submitted">
        <p><strong>Submitted by:</strong></p>
        <p>Daniel Rorhe Enriquez</p>
        <p>Gia Nina Bordamonte</p>
        <p><strong>Group:</strong> 16</p>
        <p><strong>Submitted to:</strong> Jhun Bart Macirin</p>
        <p><strong>Section:</strong> CCE/BSIT – IT6/L</p>
        <p><strong>Submission Date:</strong> ${asOf}</p>
      </div>
    </header>`;
  const body = milestone.chapters.join('');

  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${esc(projectTitle)} - Milestone ${milestone.number}</title><style>${stylesheet}</style></head><body>${cover}${toc}${body}</body></html>`;
}

for (const milestone of milestoneContents) {
  const htmlPath = path.join(os.tmpdir(), `out-of-boredom-milestone-${milestone.number}.html`);
  const pdfPath = path.join(outputDirectory, `OUT-OF-BOREDOM-IT6-Milestone-${milestone.number}.pdf`);
  const profilePath = path.join(os.tmpdir(), `chrome-milestone-pdf-${milestone.number}-${process.pid}`);
  fs.writeFileSync(htmlPath, documentHtml(milestone), 'utf8');
  const result = spawnSync(chrome, [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    `--user-data-dir=${profilePath}`,
    '--no-pdf-header-footer',
    `--print-to-pdf=${pdfPath}`,
    `file:///${htmlPath.replaceAll('\\', '/')}`,
  ], { encoding: 'utf8', timeout: 120000 });

  if (result.error || result.status !== 0 || !fs.existsSync(pdfPath)) {
    throw new Error(`Milestone ${milestone.number} PDF export failed: ${result.error?.message ?? result.stderr ?? result.stdout}`);
  }
  const pdf = fs.readFileSync(pdfPath);
  if (pdf.subarray(0, 5).toString() !== '%PDF-' || pdf.length < 1000) {
    throw new Error(`Milestone ${milestone.number} output is not a valid, non-empty PDF.`);
  }
  fs.rmSync(profilePath, { recursive: true, force: true });
  fs.rmSync(htmlPath, { force: true });
  console.log(`Created ${path.relative(root, pdfPath)} (${pdf.length.toLocaleString()} bytes)`);
}
