<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Preliminary Contract Module — Administrator & Buyer Guidebook</title>
    <style>
        @page {
            margin: 40px 35px 50px 35px;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.5;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        .header-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .header-subtitle {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .cover {
            text-align: center;
            padding-top: 130px;
            padding-bottom: 140px;
        }
        .cover-badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 4px;
            margin-bottom: 16px;
        }
        .cover h1 {
            font-size: 26px;
            color: #0f172a;
            margin: 0 0 10px 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        .cover h2 {
            font-size: 13px;
            font-weight: normal;
            color: #475569;
            margin: 0 0 24px 0;
            line-height: 1.4;
        }
        .cover-meta {
            margin-top: 60px;
            font-size: 10px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
            display: inline-block;
            width: 80%;
        }
        .page-break {
            page-break-before: always;
        }
        h2.section {
            font-size: 14px;
            color: #0f172a;
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 4px;
            margin-top: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        h3.sub {
            font-size: 12px;
            color: #1e293b;
            margin-top: 14px;
            margin-bottom: 4px;
            font-weight: bold;
        }
        p {
            margin: 4px 0 8px 0;
            text-align: justify;
        }
        ol, ul {
            margin: 4px 0 8px 0;
            padding-left: 18px;
        }
        li {
            margin-bottom: 3px;
        }
        .callout-info {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #0071e3;
            padding: 8px 12px;
            margin: 8px 0 12px 0;
            font-size: 10.5px;
            border-radius: 2px;
        }
        .callout-warn {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            padding: 8px 12px;
            margin: 8px 0 12px 0;
            font-size: 10.5px;
            border-radius: 2px;
        }
        .callout-protocol {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
            padding: 8px 12px;
            margin: 8px 0 12px 0;
            font-size: 10.5px;
            border-radius: 2px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 14px 0;
            font-size: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.03em;
        }
        .step-badge {
            display: inline-block;
            background: #0071e3;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 1px 6px;
            border-radius: 3px;
            margin-right: 4px;
        }
        .footer-note {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8.5px;
            color: #94a3b8;
            border-top: 0.5px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="footer-note">
        PT MEGA PERINTIS TBK &bull; Fabric Sourcing Operations &bull; Preliminary Contract Module Guidebook (Internal Admin Edition)
    </div>

    {{-- COVER PAGE --}}
    <div class="cover">
        <div class="cover-badge">STANDARD OPERATING PROCEDURE</div>
        <h1>Preliminary Contract (PC) Module<br/>Administrator &amp; Buyer Guidebook</h1>
        <h2>End-to-End Workflow, Capacity Reservation, Approval Protocols, and Supplier Collaboration</h2>
        
        <div class="cover-meta">
            <strong>Issued by:</strong> PT Mega Perintis Tbk &bull; Procurement &amp; Sourcing Operations<br/>
            <strong>Platform:</strong> Vendor Portal &bull; Preliminary Contract (PC) Phase 2 Subsystem<br/>
            <strong>Document Reference:</strong> SOP-PC-ADM-2026-V2 &bull; Effective Date: November 2026<br/>
            <strong>Classification:</strong> Confidential &bull; Internal Sourcing &amp; Merchandising Teams
        </div>
    </div>

    <div class="page-break"></div>

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="header-title">PT MEGA PERINTIS TBK</div>
                <div class="header-subtitle">Preliminary Contract Operating Manual &bull; Section 1: Overview &amp; Roles</div>
            </td>
            <td style="text-align: right; color: #64748b; font-size: 9px;">
                CONFIDENTIAL &bull; FOR INTERNAL USE
            </td>
        </tr>
    </table>

    <h2 class="section">1. Purpose &amp; Strategic Foundation</h2>
    <p>
        A <strong>Preliminary Contract (PC)</strong> is the binding commercial instrument through which <strong>PT Mega Perintis Tbk</strong> locks in fabric mill manufacturing capacity, raw material yarn booking, and production lead-times with overseas and domestic textile mills prior to issuing finished garment Purchase Orders (POs).
    </p>
    <div class="callout-protocol">
        <strong>The Golden Manufacturing Rule:</strong><br/>
        A Preliminary Contract authorizes yarn procurement and greige weaving/knitting capacity only. Bulk dyeing, color breakdowns, and finished fabric finishing must proceed strictly against confirmed Purchase Orders (POs) and approved lab dips/shadebands.
    </div>

    <h3 class="sub">Key Business Objectives</h3>
    <ul>
        <li><strong>Lead-Time Protection:</strong> Locks weaving loom space 60–90 days ahead of seasonal garment manufacturing cut dates.</li>
        <li><strong>Specification Freezing:</strong> Eliminates downstream customs and technical mismatches by locking HS Codes, cuttable width, fabric weight (GSM), and tolerances upfront.</li>
        <li><strong>Milestone Planning:</strong> Establishes committed FOB/CIF shipment windows aligned with factory cutting schedules.</li>
        <li><strong>Standard Quality Assurance:</strong> Enforces mandatory internal mill fabric test reports, shadeband submissions, and third-party SGS factory inspections.</li>
    </ul>

    <h2 class="section">2. Governance &amp; Role-Based Access</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Role</th>
                <th style="width: 35%;">Permissions</th>
                <th style="width: 40%;">Key Responsibilities</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Preparer / Merchandiser</strong></td>
                <td>Create Draft, Edit Lines, Add Delivery Splits, Configure Clauses, Submit for Approval, Revise.</td>
                <td>Inputs vendor terms, searches D365/PLM items, confirms pricing, schedules deliveries, and submits for management approval.</td>
            </tr>
            <tr>
                <td><strong>Approver</strong></td>
                <td>Review Commercial Terms, Approve with 2FA/TOTP, Reject with Comments.</td>
                <td>Validates pricing thresholds, payment terms, and delivery alignment; executes digital signing and freezing.</td>
            </tr>
            <tr>
                <td><strong>Fabric Admin / Super Admin</strong></td>
                <td>Full Admin Controls, Resend Supplier Email, Regenerate PDF, Manage Master Data &amp; ERP Sync.</td>
                <td>Manages HS codes, lots, transport modes, seasons, approver pool, and D365 synchronization feeds.</td>
            </tr>
            <tr>
                <td><strong>Fabric Vendor (Supplier)</strong></td>
                <td>View Assigned Contracts, Accept Booking, Query Terms, Download Signed PDF.</td>
                <td>Reviews technical matrix, confirms manufacturing booking, or submits technical/lead-time queries.</td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    {{-- SECTION 3: STEP-BY-STEP WORKFLOW --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="header-title">PT MEGA PERINTIS TBK</div>
                <div class="header-subtitle">Preliminary Contract Operating Manual &bull; Section 2: Step-by-Step Execution</div>
            </td>
            <td style="text-align: right; color: #64748b; font-size: 9px;">
                CONFIDENTIAL &bull; FOR INTERNAL USE
            </td>
        </tr>
    </table>

    <h2 class="section">3. Step-by-Step Contract Lifecycle</h2>

    <h3 class="sub"><span class="step-badge">STEP 1</span> Initializing a Contract</h3>
    <ol>
        <li>Navigate to <strong>Preliminary Contract &rarr; Contracts Register</strong> and click <strong>"+ New Contract"</strong>.</li>
        <li>Select the <strong>Supplier / Vendor</strong> from the type-to-search dropdown. The system automatically populates the vendor's default currency and payment terms.</li>
        <li>Fill the Contract Header parameters:
            <ul>
                <li><strong>Season:</strong> Select active seasonal catalog (e.g. <code>FALL'26</code>, <code>FESTIVE'26</code>).</li>
                <li><strong>Fabric Type:</strong> Enter fabric category (e.g. Woven Cotton Twill, CVC Knits, Denim).</li>
                <li><strong>Currency &amp; Payment Term:</strong> USD, CNY, EUR, or IDR; select verified terms (e.g. <em>LC 60 Days</em>, <em>TT 30 Days</em>).</li>
                <li><strong>Delivery Term (Incoterm):</strong> FOB, CIF, CNF, EXW.</li>
                <li><strong>Mode of Transport:</strong> Sea Freight, Air Freight, Land Logistics.</li>
            </ul>
        </li>
    </ol>

    <h3 class="sub"><span class="step-badge">STEP 2</span> Specifying Fabric Line Items &amp; Technical Attributes</h3>
    <ol>
        <li>Click <strong>"+ Add Line"</strong> to create a fabric specification row.</li>
        <li><strong>Item Lookup / PLM Ref:</strong> Enter or search the item number. The system auto-completes available descriptions and construction matrices.</li>
        <li><strong>HS Code:</strong> Assign an 8-digit tariff code (e.g. <code>5407.69.90</code>).</li>
        <li><strong>Lot &amp; Batch:</strong> Select the production lot code (e.g. <code>1-2</code>, <code>3-4</code>).</li>
        <li><strong>Cuttable Width &amp; Weight:</strong> Specify minimum usable width (e.g. <code>57/58"</code>) and density (e.g. <code>180 GSM</code>).</li>
        <li><strong>Booking Volume &amp; Pricing:</strong> Enter quantity (YDS/MTR) and unit purchase price. Default tolerance is set to &plusmn;5.00%.</li>
    </ol>

    <h3 class="sub"><span class="step-badge">STEP 3</span> Milestone Delivery Scheduling</h3>
    <p>
        Under the <strong>Fabric Delivery Schedule</strong> section, define when the mill must have goods FOB/CIF ready. Click <strong>"+ Add Delivery Split"</strong> to schedule staggered partial shipments. The system validates that total delivery splits reconcile with contracted line volumes.
    </p>

    <h3 class="sub"><span class="step-badge">STEP 4</span> Standard Clauses &amp; Factory Requirements</h3>
    <p>
        The module automatically preloads PT Mega Perintis standard booking terms (Clauses 1–9). Critical operational constraints (e.g. Clause 1 yarn booking limitation, Clause 3 test reports, Clause 4 SGS inspection) are highlighted. Preparers can click <strong>"+ Add Clause"</strong> to append factory-specific requirements (e.g. OEKO-TEX Standard 100, shrinkage thresholds).
    </p>

    <h3 class="sub"><span class="step-badge">STEP 5</span> Submission &amp; Internal Approval</h3>
    <ol>
        <li>Click <strong>"Save Draft"</strong> at any point to save work.</li>
        <li>Once validated, click <strong>"Submit for Approval"</strong>. The contract enters <code>Awaiting Approval</code>.</li>
        <li>An authorized Approver reviews commercial pricing, delivery feasibility, and tolerance rules.</li>
        <li>Upon approval, the system:
            <ul>
                <li>Freezes contract notes, delivery schedules, and header snapshots into audit tables.</li>
                <li>Generates the tamper-evident <strong>Official Contract PDF</strong> with digital approval stamps.</li>
                <li>Transitions status to <code>Approved (Ready to Send)</code>.</li>
            </ul>
        </li>
    </ol>

    <h3 class="sub"><span class="step-badge">STEP 6</span> Dispatching to Vendor &amp; Resend Controls</h3>
    <ol>
        <li>Open the approved contract and click <strong>"Send to Supplier"</strong>.</li>
        <li>The system verifies that the vendor has an active portal account and dispatches an <strong>APAC Enterprise Standard Email Notification</strong> with the official PDF contract attached.</li>
        <li><strong>Resend Email:</strong> If the supplier requests another copy or updates merchandiser contacts, click the <strong>"Resend Email"</strong> button on the toolbar to re-dispatch the email and PDF attachment with full audit logging.</li>
    </ol>

    <div class="page-break"></div>

    {{-- SECTION 4: POST-DISPATCH & REVISIONS --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="header-title">PT MEGA PERINTIS TBK</div>
                <div class="header-subtitle">Preliminary Contract Operating Manual &bull; Section 3: Vendor Responses &amp; Revisions</div>
            </td>
            <td style="text-align: right; color: #64748b; font-size: 9px;">
                CONFIDENTIAL &bull; FOR INTERNAL USE
            </td>
        </tr>
    </table>

    <h2 class="section">4. Managing Vendor Responses</h2>

    <h3 class="sub">Scenario A: Vendor Accepts Contract</h3>
    <ul>
        <li>The vendor clicks <strong>"Accept Contract"</strong> in the Vendor Portal.</li>
        <li>The contract status updates immediately to <code>Confirmed</code>.</li>
        <li>The official PDF is updated with the bilateral vendor signature stamp and timestamp.</li>
        <li>The procurement team may now allocate style cutting and issue final Purchase Orders (POs).</li>
    </ul>

    <h3 class="sub">Scenario B: Vendor Queries Contract</h3>
    <ul>
        <li>If the mill has an MOQ issue, raw yarn delivery delay, or technical discrepancy, they click <strong>"Query Contract"</strong> and submit detailed remarks.</li>
        <li>The contract status transitions to <code>Vendor Queried</code>.</li>
        <li>The buyer evaluates the query. If adjustments are agreed upon, the buyer initiates a <strong>Revision</strong>.</li>
    </ul>

    <h2 class="section">5. Contract Revision Protocol (REV Workflow)</h2>
    <p>
        In accordance with international procurement audit standards, active or approved contracts cannot be silently modified. To adjust terms:
    </p>
    <ol>
        <li>Open the contract and click <strong>"Revise"</strong>.</li>
        <li>Enter the official justification (e.g. <em>Mill requested 2-week FOB extension due to yarn spinning holiday</em>).</li>
        <li>The system creates a new revision linked to the parent contract, updates the reference to <code>... (REV)</code>, and safely marks the prior version as <code>Superseded</code>.</li>
        <li>The revised contract is resubmitted for approval, ensuring full historical transparency.</li>
    </ol>

    <h2 class="section">6. Master Data Maintenance &amp; Settings</h2>
    <p>
        Under <strong>Preliminary Contract &rarr; Master Data &amp; Settings</strong>, administrators maintain essential lookup catalogs:
    </p>
    <ul>
        <li><strong>HS Codes:</strong> International customs codes used for export declaration and Indonesian customs clearance.</li>
        <li><strong>Lots:</strong> Standardized production batch categories.</li>
        <li><strong>Terms of Delivery (Incoterms):</strong> FOB, CIF, CFR, EXW lookup records.</li>
        <li><strong>Mode of Transport:</strong> Sea Freight, Air Freight, Land Transport definitions.</li>
        <li><strong>Seasons:</strong> Active merchandising seasons; closed seasons restrict new draft bookings.</li>
        <li><strong>Approver Pool:</strong> Comma-separated email list authorized to approve Preliminary Contracts.</li>
    </ul>

    <div class="callout-info">
        <strong>Audit Trail Integrity:</strong><br/>
        Every action (creation, edit, approval, rejection, supplier dispatch, email resend, vendor acceptance, query, revision, and cancellation) is logged permanently in <code>pc_audit_logs</code> with user email, timestamp, IP address, and change remarks.
    </div>
</body>
</html>
