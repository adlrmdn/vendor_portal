<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Fabric Vendor Portal — User Guide</title>
    <style>
        @page {
            margin: 40px 35px;
        }

        body {
            font-family: Arial, sans-serif;
            color: #2b2b2b;
            font-size: 13px;
            line-height: 1.5;
        }

        .cover {
            text-align: center;
            padding-top: 160px;
        }

        .cover h1 {
            font-size: 30px;
            margin-bottom: 6px;
            color: #1a1a1a;
        }

        .cover h2 {
            font-size: 15px;
            font-weight: normal;
            color: #666;
            margin-top: 0;
        }

        .cover .meta {
            margin-top: 40px;
            font-size: 12px;
            color: #888;
        }

        .page-break {
            page-break-before: always;
        }

        h1.section {
            font-size: 19px;
            color: #1a1a1a;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
            margin-top: 0;
            margin-bottom: 14px;
        }

        h2.sub {
            font-size: 15px;
            color: #222;
            margin-top: 22px;
            margin-bottom: 6px;
        }

        p {
            margin: 6px 0;
        }

        ol, ul {
            margin: 6px 0 10px 0;
            padding-left: 20px;
        }

        li {
            margin-bottom: 4px;
        }

        .note {
            background: #f4f6f8;
            border-left: 4px solid #6c8ebf;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 12px;
        }

        .warn {
            background: #fdf3ec;
            border-left: 4px solid #d98c3d;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 14px 0;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 5px 7px;
            text-align: left;
        }

        th {
            background: #efefef;
        }

        code {
            background: #f0f0f0;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 11.5px;
        }

        .toc ol {
            list-style: none;
            padding-left: 0;
        }

        .toc li {
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 11px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }

        .screenshot {
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin: 10px 0 6px 0;
        }

        .caption {
            font-size: 11px;
            color: #888;
            text-align: center;
            margin-bottom: 14px;
        }

        a {
            color: #1a56c4;
            text-decoration: none;
        }

        .links-table td:first-child {
            font-weight: bold;
            width: 32%;
        }

        .links-table td:last-child {
            word-break: break-all;
        }
    </style>
</head>

<body>

    <div class="cover">
        <h1>Fabric Vendor Portal</h1>
        <h2>User Guide for Vendors</h2>
        <div class="meta">
            Version 1.0 &middot; {{ now()->format('F Y') }}
        </div>
    </div>

    <div class="page-break"></div>

    <h1 class="section">Contents</h1>
    <div class="toc">
        <ol>
            <li>1. Getting Started &amp; Logging In</li>
            <li>2. Dashboard Overview</li>
            <li>3. Finding a Purchase Order</li>
            <li>4. Viewing a Purchase Order</li>
            <li>5. Recording Rolls Against an Item</li>
            <li>6. Marking an Item as Processed</li>
            <li>7. Delivery Tolerance, Partial Shipments &amp; Amendments</li>
            <li>8. Generating Packing Slips</li>
            <li>9. Getting Help</li>
        </ol>
    </div>

    <div class="page-break"></div>

    <h1 class="section">1. Getting Started &amp; Logging In</h1>
    <p>
        The Fabric Vendor Portal is a web application your company uses to report fabric roll
        details against purchase orders (POs) and to print packing slips for deliveries.
    </p>
    <ol>
        <li>Open the portal at <a href="https://vendor-portal.megaperintis.co.id">https://vendor-portal.megaperintis.co.id</a>.</li>
        <li>Sign in with the email address and password provided to you. If you have not
            received credentials, or have forgotten your password, contact your admin
            contact (see Section 9).</li>
        <li>After logging in you will land on your <strong>Dashboard</strong>.</li>
    </ol>
    <div class="note">
        Your account is linked to your company's vendor profile. You will only ever see purchase
        orders and items that belong to your own company.
    </div>

    <h2 class="sub">1.1 Quick Links</h2>
    <p>Once logged in, these pages are reachable directly:</p>
    <table class="links-table">
        <tr><td>Portal Home / Login</td><td><a href="https://vendor-portal.megaperintis.co.id/login">https://vendor-portal.megaperintis.co.id/login</a></td></tr>
        <tr><td>Dashboard</td><td><a href="https://vendor-portal.megaperintis.co.id/vendor/dashboard">https://vendor-portal.megaperintis.co.id/vendor/dashboard</a></td></tr>
        <tr><td>My Purchase Orders</td><td><a href="https://vendor-portal.megaperintis.co.id/vendor/purchase-orders">https://vendor-portal.megaperintis.co.id/vendor/purchase-orders</a></td></tr>
    </table>
    <div class="note">
        Direct links to a specific purchase order or item (e.g.
        <code>/vendor/purchase-order/{id}</code>) only work once you're logged in, and only for
        orders belonging to your own company.
    </div>

    @if(isset($imgLogin))
        <img src="{{ $imgLogin }}" class="screenshot">
        <div class="caption">The sign-in screen.</div>
    @endif

    <h1 class="section">2. Dashboard Overview</h1>
    <p>The dashboard gives you an at-a-glance summary of your work:</p>
    <ul>
        <li><strong>Active POs</strong> — purchase orders that are still pending or in progress.</li>
        <li><strong>Pending Items</strong> — individual order lines that have not had any rolls
            recorded yet.</li>
        <li><strong>Completed POs</strong> — purchase orders where every item has been fully
            processed.</li>
    </ul>
    <p>Below the summary cards, a <strong>Recent Orders</strong> table lists your most recently
        received purchase orders with quick links into each one.</p>

    @if(isset($imgDashboard))
        <img src="{{ $imgDashboard }}" class="screenshot">
        <div class="caption">The vendor dashboard, with summary cards and recent orders.</div>
    @endif

    <h1 class="section">3. Finding a Purchase Order</h1>
    <p>
        Go to <strong>Purchase Orders</strong> in the navigation menu to see the full list of
        orders assigned to your company. You can:
    </p>
    <ul>
        <li>Search by <strong>PO number</strong>, <strong>PLM number</strong>, the
            <strong>PC / reference</strong> code, or the <strong>style name</strong>. When
            searching by style name you can type several words in any order (for example
            <code>Eagle Blue Fall</code>) and the portal will find items whose style name
            contains all of them.</li>
        <li>Filter the list by <strong>status</strong> (pending, processing, completed,
            cancelled).</li>
        <li>Choose how many rows to display per page (10, 25 or 50).</li>
    </ul>

    @if(isset($imgPurchaseOrders))
        <img src="{{ $imgPurchaseOrders }}" class="screenshot">
        <div class="caption">The Purchase Orders list, with search and status filter.</div>
    @endif

    <h1 class="section">4. Viewing a Purchase Order</h1>
    <p>
        Opening a purchase order shows every item (order line) on that PO, along with its
        quantity, unit, status and any rolls already recorded against it. From here you can:
    </p>
    <ul>
        <li>Click into an item to record or edit its rolls (Section 5).</li>
        <li>Select completed items to generate a packing slip (Section 8).</li>
    </ul>

    @if(isset($imgPoView))
        <img src="{{ $imgPoView }}" class="screenshot">
        <div class="caption">A purchase order's detail page, showing each item's status and
            action buttons (Process Item / Edit Rolls / Done).</div>
    @endif

    <h1 class="section">5. Recording Rolls Against an Item</h1>
    <p>
        Click an item to open its processing page. Here you report the individual rolls / bales
        that make up the delivered quantity for that item.
    </p>

    <h2 class="sub">5.1 Units</h2>
    <p>Every item is ordered in one of three units, and that unit decides which field is
        required on each roll row:</p>
    <table>
        <tr><th>Order Unit</th><th>Required roll field</th></tr>
        <tr><td>YD (yards)</td><td>Length (yd)</td></tr>
        <tr><td>M (metres)</td><td>Length (m)</td></tr>
        <tr><td>KG (weight)</td><td>Weight (kg)</td></tr>
    </table>
    <div class="note">
        Yards and metres are interchangeable: if you only fill in one of the two length fields,
        the portal automatically calculates the other for you (1 yd = 0.9144 m). Weight (KG) is
        independent and only applies to weight-based items.
    </div>
    <p>Optional fields you may also fill in per roll: internal ID, your own vendor roll number,
        bale number, and color.</p>

    @if(isset($imgItemProcess))
        <img src="{{ $imgItemProcess }}" class="screenshot">
        <div class="caption">The item processing page: roll rows plus the Add Single Roll /
            Import (Excel/PDF) / Download Template / Clear Rolls / Save Rolls buttons, and Mark
            as Processed above.</div>
    @endif

    <h2 class="sub">5.2 Adding rolls manually</h2>
    <ol>
        <li>Click <strong>Add Roll</strong> to add a blank row, or add several at once by
            entering the number of rolls you need.</li>
        <li>Fill in the required quantity field (and any optional fields) for each roll.</li>
        <li>Click <strong>Save</strong>. The portal automatically numbers each roll as
            <code>{PO Number}-{Item Number}-{sequence}</code> (e.g.
            <code>PO12345-1-001</code>) and generates a QR code for it.</li>
    </ol>

    <h2 class="sub">5.3 Removing a roll</h2>
    <p>
        Click the red remove button next to a roll to mark it for deletion (it turns yellow to
        confirm the mark), then click <strong>Save</strong>. Deleted rolls are removed and the
        remaining rolls are automatically renumbered so the sequence stays continuous.
    </p>

    <h2 class="sub">5.4 Bulk upload from Excel or PDF</h2>
    <p>
        If you already track rolls in a spreadsheet, you don't need to type them in one by one.
        Click <strong>Import (Excel/PDF)</strong> and choose a file — the portal reads it and
        fills the roll table for you, ready to review before you save anything.
    </p>

    <h3 style="font-size:13.5px; margin:14px 0 4px 0;">Using the Excel/CSV template (recommended)</h3>
    <ol>
        <li>Click <strong>Download Template</strong>. The file it gives you is pre-built for
            this specific item's unit — the column set is slightly different depending on
            whether the item is ordered in YD/M/KG or in a count unit like PCS.</li>
        <li>Fill in one row per roll. The template's columns are:
            <table>
                <tr><th>Column</th><th>Required?</th><th>Notes</th></tr>
                <tr><td>Roll No.</td><td>Optional</td><td>Your own roll/reference number</td></tr>
                <tr><td>Bale No.</td><td>Optional</td><td>&nbsp;</td></tr>
                <tr><td>Lot ID</td><td>Optional</td><td>&nbsp;</td></tr>
                <tr><td>Color</td><td>Optional</td><td>&nbsp;</td></tr>
                <tr><td>Length (YD) / Length (M) / Weight (KG)<br>(or Qty for count units)</td><td>At least one</td><td>Fill whichever metric(s) you have — see Section 5.1</td></tr>
            </table>
        </li>
        <li>Save the file and upload it via <strong>Import (Excel/PDF)</strong>.</li>
    </ol>
    <div class="note">
        Columns are matched <strong>by header name, not position</strong> — the importer looks
        for keywords like "bale", "lot"/"batch", "colo(u)r", "yd"/"yard", "kg"/"weight",
        "m"/"meter"/"metre", "qty"/"quantity"/"length", and "roll" in each header. You can reorder
        columns or leave some out, as long as the wording is recognizable. Any row with none of
        the quantity columns filled in (for example a blank row, or a totals row) is skipped
        automatically.
    </div>

    <h3 style="font-size:13.5px; margin:14px 0 4px 0;">Uploading a PDF instead</h3>
    <p>
        If you only have a PDF packing/roll list (not the Excel template), the portal will still
        try to read it, line by line:
    </p>
    <ul>
        <li>It skips lines that look like totals or footers (containing words such as "total",
            "subtotal", "balance", "page", "amount", "signature").</li>
        <li>On each remaining line, it looks for a yard/metre pair — two decimal numbers whose
            ratio is close to 0.9144 — and reads those as Length (YD) and Length (M); a third
            decimal number on the same line is read as Weight (KG). If no such pair is found, the
            last decimal number on the line is taken as the roll's quantity instead.</li>
        <li>Alphanumeric codes on the line (containing digits, slashes or dashes) are picked up
            as the roll number and lot ID.</li>
    </ul>
    <div class="warn">
        PDF reading only works on a <strong>text-based</strong> PDF (one you could select/copy
        text from) — it cannot read a scanned image. It's also a best-effort heuristic, so for
        anything with an unusual layout, the Excel template is more reliable.
    </div>

    <p>
        Either way, nothing is saved automatically: after uploading, the detected rolls populate
        the on-screen table so you can check and correct them, and only clicking
        <strong>Save Rolls</strong> commits them to the item.
    </p>
    <p>Once you save at least one roll, the item's status automatically changes from
        <em>Pending</em> to <em>Processing</em>.</p>

    <div class="page-break"></div>

    <h1 class="section">6. Marking an Item as Processed</h1>
    <p>
        Once all rolls for an item are recorded and the total quantity matches what was shipped,
        click <strong>Mark as Processed</strong>. The portal checks that your total delivered
        quantity for the item falls within the allowed delivery tolerance before it lets the item
        move to <em>Completed</em>.
    </p>
    <div class="warn">
        If your total quantity is outside the allowed tolerance, the portal will block the
        action and explain that the quantity is outside the allowed delivery tolerance. See
        Section 7 for what to do next.
    </div>
    <p>
        If you need to correct something after marking an item processed, use
        <strong>Revert to Processing</strong> to reopen it for editing.
    </p>

    <h1 class="section">7. Delivery Tolerance, Partial Shipments &amp; Amendments</h1>
    <p>
        Each item has an allowed under-delivery and over-delivery percentage (shown on the item
        page). If your recorded quantity falls outside that window, you have two options:
    </p>

    <h2 class="sub">7.1 Request a Partial Shipment</h2>
    <p>
        Use this when you are shipping less than the full order quantity now and will ship the
        remainder later. Submit the quantity you are shipping and a reason. This creates an
        approval request that is emailed to your buying company's approver.
    </p>
    <p>
        Once approved, return to the item and click <strong>Process Partial Shipment</strong>.
        The portal will complete the item with the quantity you actually delivered and
        automatically create a new follow-up batch line for the remaining balance, so you can
        ship and report it later under the same PO.
    </p>

    <h2 class="sub">7.2 Request a Tolerance Amendment</h2>
    <p>
        Use this when you want the allowed under/over-delivery percentage itself increased for
        this item (for example, because of an agreed production variance). Submit the new
        percentages you are requesting and a reason. This is also routed to the approver by
        email for a decision.
    </p>
    <div class="note">
        Both request types are reviewed by an approver at the buying company. You'll be notified
        once a decision is made — there is nothing further to do while a request is pending.
    </div>

    <h1 class="section">8. Generating Packing Slips</h1>
    <p>
        Packing slips are generated per purchase order and cover the items you choose to ship.
    </p>
    <h2 class="sub">8.1 Standard packing slip</h2>
    <ol>
        <li>Open the purchase order and select the item(s) you want to include.</li>
        <li>Enter a delivery note reference.</li>
        <li>Optionally toggle <strong>Show Secondary Unit</strong> (on by default) and
            <strong>Reverse Units</strong> to control how quantities are displayed on the printed
            slip.</li>
        <li>Click <strong>Generate</strong>. The rolls in the selected items are marked as
            printed and a PDF packing slip opens, ready to print or save.</li>
    </ol>
    <h2 class="sub">8.2 Quick packing slip</h2>
    <p>
        If every completed item on the PO should go on one slip, use <strong>Quick Packing
        Slip</strong> instead — enter the delivery note and the portal automatically includes
        every completed item on that purchase order.
    </p>

    <h1 class="section">9. Getting Help</h1>
    <p>
        If you run into an error, can't find an order, or need your tolerance/access adjusted,
        contact your admin contact at the buying company. Include the PO number and item number
        (if applicable) so they can look into it quickly.
    </p>

    <div class="footer-note">
        This guide reflects the Fabric Vendor Portal as of {{ now()->format('F Y') }}. Screens
        and options may be adjusted over time.
    </div>

</body>

</html>
