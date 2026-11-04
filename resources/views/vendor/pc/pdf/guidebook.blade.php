<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Preliminary Contract (PC) — Supplier & Mill Operating Guidebook</title>
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
            font-size: 13.5px;
            color: #0f172a;
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 4px;
            margin-top: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        h3.sub {
            font-size: 11.5px;
            color: #1e293b;
            margin-top: 12px;
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
        PT MEGA PERINTIS TBK &bull; Fabric Sourcing Operations &bull; Preliminary Contract Module Guidebook (Supplier Edition)
    </div>

    {{-- COVER PAGE --}}
    <div class="cover">
        <div class="cover-badge">SUPPLIER &amp; MILL OPERATING STANDARD</div>
        <h1>Preliminary Contract (PC) Module<br/>Supplier &amp; Fabric Mill Guidebook</h1>
        <h2>Advance Manufacturing Reservation, Technical Review, Acceptance Protocols &amp; PO Transition</h2>
        
        <div class="cover-meta">
            <strong>Issued to:</strong> Fabric Mills, Textile Suppliers &amp; Export Sales Teams<br/>
            <strong>Platform:</strong> PT Mega Perintis Tbk &bull; Vendor Portal Preliminary Contract Subsystem<br/>
            <strong>Document Reference:</strong> SOP-PC-VND-2026-V1 &bull; Effective Date: November 2026<br/>
            <strong>Language:</strong> English (APAC Regional Sourcing Standard)
        </div>
    </div>

    <div class="page-break"></div>

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="header-title">PT MEGA PERINTIS TBK</div>
                <div class="header-subtitle">Vendor Collaboration Portal &bull; Fabric Sourcing Division</div>
            </td>
            <td align="right">
                <div style="font-size: 11px; font-weight: bold; color: #0071e3;">SUPPLIER OPERATING GUIDE</div>
                <div style="font-size: 9px; color: #64748b;">Ref: SOP-PC-VND-2026</div>
            </td>
        </tr>
    </table>

    <h2 class="section">1. Purpose &amp; Manufacturing Protocol</h2>
    <p>
        A <strong>Preliminary Contract (PC)</strong> is a binding commitment issued by PT Mega Perintis Tbk to secure fabric manufacturing capacity, raw yarn allotments, and weaving/knitting production slots ahead of seasonal garment manufacturing.
    </p>

    <div class="callout-protocol">
        <strong>CRITICAL MANUFACTURING NOTICE — APACS SOURCING RULE:</strong><br/>
        A Preliminary Contract authorizes <strong>raw yarn booking, spinning, and greige weaving/knitting</strong>.
        <strong>Bulk dyeing and finishing must strictly await the official Purchase Order (PO)</strong> containing finalized color breakdowns, quantities, and approved lab dips/shadebands.
    </div>

    <h2 class="section">2. Accessing the Vendor Portal</h2>
    <ol>
        <li><strong>Portal URL:</strong> Access the Vendor Portal via the secure URL provided by PT Mega Perintis Tbk.</li>
        <li><strong>Credentials:</strong> Log in using your registered supplier email address and authorized credentials.</li>
        <li><strong>Navigating to Contracts:</strong> In the primary sidebar menu, click <strong>"Contracts (PC)"</strong>.</li>
        <li><strong>Notification Counter:</strong> When a new Preliminary Contract is assigned to your mill, a counter badge will appear on your navigation bar.</li>
    </ol>

    <h2 class="section">3. Navigating Contract Status Tabs</h2>
    <p>The Preliminary Contract register is organized into four distinct operational tabs:</p>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Status Tab</th>
                <th style="width: 45%;">Description</th>
                <th style="width: 30%;">Supplier Action Required</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>New / Review Required</strong></td>
                <td>Contracts recently issued by PT Mega Perintis merchandisers and awaiting your review.</td>
                <td><span style="color: #b45309; font-weight: bold;">Immediate Review</span> (within 3 business days).</td>
            </tr>
            <tr>
                <td><strong>Confirmed</strong></td>
                <td>Contracts accepted by your mill. Bilateral digital timestamps and sealed PDF generated.</td>
                <td>Lock production capacity &amp; initiate yarn procurement.</td>
            </tr>
            <tr>
                <td><strong>Queried</strong></td>
                <td>Contracts where your mill requested technical, commercial, or delivery date adjustments.</td>
                <td>Awaiting review by PT Mega Perintis sourcing team.</td>
            </tr>
            <tr>
                <td><strong>Contract History</strong></td>
                <td>Archived repository of all historical, amended, and superseded agreements.</td>
                <td>Read-only reference and official PDF reprints.</td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="header-title">PT MEGA PERINTIS TBK</div>
                <div class="header-subtitle">Vendor Collaboration Portal &bull; Fabric Sourcing Division</div>
            </td>
            <td align="right">
                <div style="font-size: 11px; font-weight: bold; color: #0071e3;">SUPPLIER OPERATING GUIDE</div>
                <div style="font-size: 9px; color: #64748b;">Ref: SOP-PC-VND-2026</div>
            </td>
        </tr>
    </table>

    <h2 class="section">4. Technical Specifications Review</h2>
    <p>
        Click on any contract reference (e.g., <code>MPG/PC/PDV/26/X/451</code>) to open the <strong>Contract Workspace</strong>. Thoroughly inspect each operational section:
    </p>

    <h3 class="sub">4.1 Commercial Header &amp; Logistics</h3>
    <ul>
        <li><strong>Contract Reference:</strong> Unique agreement identifier for all shipping documents.</li>
        <li><strong>Season &amp; Brand:</strong> Assigned target brand collection (e.g., <em>CARDINAL JEANS FALL'26</em>).</li>
        <li><strong>Currency &amp; Credit Terms:</strong> Currency (USD, CNY, EUR, IDR) and agreed payment terms (LC, TT, CBD).</li>
        <li><strong>Incoterms &amp; Port:</strong> Delivery baseline (e.g., <em>FOB Shanghai — Sea Freight</em> or <em>CIF Jakarta</em>).</li>
    </ul>

    <h3 class="sub">4.2 Fabric Technical Specifications Table</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;">Specification</th>
                <th style="width: 80%;">Mill Verification Responsibility</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Item Code &amp; Construction</strong></td>
                <td>Verify yarn count, weave/knit construction, and composition (e.g., 100% Cotton, TC, CVC).</td>
            </tr>
            <tr>
                <td><strong>HS Code (Customs)</strong></td>
                <td>Ensure the 8-digit Harmonized Tariff Schedule matches your export declaration documentation.</td>
            </tr>
            <tr>
                <td><strong>Color / Batch Code</strong></td>
                <td>Verify lab dip code or color reference against your shade development library.</td>
            </tr>
            <tr>
                <td><strong>Cuttable Width &amp; GSM</strong></td>
                <td>Ensure finished width meets minimum usable requirement (e.g., 57/58") and density tolerances.</td>
            </tr>
            <tr>
                <td><strong>Volume &amp; Unit Price</strong></td>
                <td>Confirm booked yardage/meterage and contracted price per unit.</td>
            </tr>
            <tr>
                <td><strong>Tolerance Window</strong></td>
                <td>Note the permissible over/under delivery tolerance (standard: &plusmn;5.00%).</td>
            </tr>
        </tbody>
    </table>

    <h3 class="sub">4.3 Delivery Milestone Schedule</h3>
    <p>
        Inspect the phased delivery dates. Confirm that yarn spinning, greige weaving, and buffer lead-times align with the requested FOB/CIF readiness windows.
    </p>

    <div class="callout-warn">
        <strong>MANDATORY PRE-SHIPMENT QUALITY OBLIGATIONS:</strong><br/>
        All contracted fabrics are subject to standard compliance protocols prior to vessel dispatch:
        <ul>
            <li><strong>Mill Internal Test Report:</strong> Colorfastness (washing, rubbing, light), tensile/tear strength, shrinkage (&plusmn;3%).</li>
            <li><strong>Shadeband Submissions:</strong> Minimum 3 sets of physical swatches (A/B/C lots) submitted for shade approval.</li>
            <li><strong>100% 4-Point System Inspection:</strong> Internal inspection report showing defect score &le; 28 points per 100 sq yards.</li>
            <li><strong>Third-Party Pre-Shipment Inspection:</strong> SGS or nominated inspection agency sign-off prior to release.</li>
        </ul>
    </div>

    <div class="page-break"></div>

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="header-title">PT MEGA PERINTIS TBK</div>
                <div class="header-subtitle">Vendor Collaboration Portal &bull; Fabric Sourcing Division</div>
            </td>
            <td align="right">
                <div style="font-size: 11px; font-weight: bold; color: #0071e3;">SUPPLIER OPERATING GUIDE</div>
                <div style="font-size: 9px; color: #64748b;">Ref: SOP-PC-VND-2026</div>
            </td>
        </tr>
    </table>

    <h2 class="section">5. Action Protocols: Accept vs. Query</h2>
    <p>
        Suppliers must formally respond to newly issued contracts within <strong>three (3) business days</strong> of receipt.
    </p>

    <h3 class="sub"><span class="step-badge">OPTION A</span> Accept Contract (Capacity Confirmed)</h3>
    <ol>
        <li>Confirm all technical parameters, pricing, and scheduled dates are fully achievable.</li>
        <li>Click the green <strong>"Accept Contract"</strong> button at the bottom of the page.</li>
        <li>Review and confirm the acknowledgement modal dialog.</li>
        <li><strong>System Result:</strong>
            <ul>
                <li>The contract immediately transitions to <strong>"Confirmed"</strong>.</li>
                <li>A digital audit signature (User Name, Mill Name, Timestamp, IP Address) is permanently sealed.</li>
                <li>The official signed contract PDF is generated and made available for instant download.</li>
                <li>PT Mega Perintis merchandising team is alerted to proceed with PO allocation.</li>
            </ul>
        </li>
    </ol>

    <h3 class="sub"><span class="step-badge" style="background: #f59e0b;">OPTION B</span> Query Contract (Request Adjustment)</h3>
    <p>
        If your mill encounters technical constraints, delivery date conflicts, or pricing differences:
    </p>
    <ol>
        <li>Click the amber <strong>"Query Contract"</strong> button.</li>
        <li>In the query dialog, provide detailed, constructive remarks detailing:
            <ul>
                <li>Specific line item number or milestone date in question.</li>
                <li>Reason for constraint (e.g., yarn lead time, MOQ limitation, holiday shutdown).</li>
                <li>Counter-proposal with feasible target date or technical specification.</li>
            </ul>
        </li>
        <li>Click <strong>"Submit Query"</strong>.</li>
        <li><strong>System Result:</strong>
            <ul>
                <li>The contract transitions to <strong>"Vendor Queried"</strong>.</li>
                <li>The PT Mega Perintis buyer receives your detailed remarks.</li>
                <li>Once aligned, the buyer will issue a formal revised contract (Revision 1, 2, etc.) for final confirmation.</li>
            </ul>
        </li>
    </ol>

    <h2 class="section">6. Official Contract PDF Download</h2>
    <p>
        At any stage, you can click the <strong>"Official PDF"</strong> button on the top-right toolbar to download a publication-grade PDF contract. This document includes:
    </p>
    <ul>
        <li>PT Mega Perintis corporate letterhead, registration number, and tax identity.</li>
        <li>Complete matrix of technical fabric specifications, tolerances, and price schedules.</li>
        <li>Comprehensive pre-shipment quality and testing protocols.</li>
        <li>Bilateral digital signature blocks with verification stamps.</li>
    </ul>

    <h2 class="section">7. Transition to Purchase Orders (PO)</h2>
    <p>
        Upon garment style finalization, PT Mega Perintis will issue specific <strong>Purchase Orders (POs)</strong> linked directly to your Preliminary Contract. You will receive email notifications and will manage dyeing, finishing, and shipment lots against the PO.
    </p>

    <h2 class="section">8. Supplier Support &amp; Enquiries</h2>
    <p>
        For inquiries regarding specifications, delivery rescheduling, or portal support:
    </p>
    <ul>
        <li><strong>Fabric Procurement Division:</strong> <code>procurement@megaperintis.co.id</code></li>
        <li><strong>Direct Merchandiser Contact:</strong> Refer to the buyer listed on the contract header.</li>
        <li><strong>Technical Helpdesk:</strong> Contact your designated PT Mega Perintis sourcing representative.</li>
    </ul>
</body>
</html>
