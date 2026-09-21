# Logic Integrity Audit — 2026-09

## Background: the bug pattern

While investigating a real financial-data-integrity incident, we found that
`QcReportPdfService::accessoryClaimRuling()` — a 3-tier business rule that
decides whether a "material retur shortfall" claim is actually charged to a
subcon vendor — had one authoritative implementation used to compute the
REAL charge (the debit note / `rpa_queues` "deduction" job, via
`RpaQueueService::deductionBreakdown()`), but the QC inspection report PDF
and the debit-note PDF template each had their OWN, separate, naive
computation of the same figure: a flat sum of every accessory's positive
`claim`, with no rule applied. The result: reports printed a nonzero "Total
Deductions" line for orders where the real, ruled charge was correctly
Rp 0 — no debit note was ever created, but the printed, signed,
already-distributed document said otherwise. Six live production orders
were found with this exact discrepancy across all 79 "completed"
`packaging_projects`.

The fix centralized the rule into one method (`accessoryClaimRuling()`),
called once inside `QcReportPdfService::context()`, with its result
(`accessoryClaimRuling.total`/`.lines`) embedded in `context()`'s return so
every consumer — the report body, the debit-note page, and
`RpaQueueService::deductionBreakdown()` — reads the same ruled figure
instead of re-deriving its own.

**The generalized root cause**: the same derived financial/business-logic
figure is computed independently in two or more places — typically a
display/report path and a charge/action path — with no single shared
source of truth, so a rule change (or the *existence* of a rule at all) in
one path silently doesn't propagate to the other(s).

This document is the result of auditing the rest of the codebase for the
same pattern, following the fix to `accessoryClaimRuling()`. Each finding
below was traced through the actual code paths (not just pattern-matched by
name), with a concrete scenario showing where the two computations would
disagree, and a severity rating (real money/approval impact vs. cosmetic).

---

## Finding 1 — HO/MD Production "Grand Total" showed the un-ruled accessory claim sum (CONFIRMED, FIXED)

**Locations:**
- `app/Http/Controllers/QcApprovalController.php`, `hoApprovalForm()` (accessory-claim snapshot block, ~line 536–565)
- `resources/views/subcon/partials/consumption-input.blade.php` (the "Accessory Retur Shortfall" / "Grand Total (Fabric + Accessory)" footer, ~line 401–430)

**What was duplicated:** the accessory retur-shortfall claim TOTAL. The real
charge path (`RpaQueueService::deductionBreakdown()` → `deductionTotal()`)
reads `QcReportPdfService::context()`'s `accessoryClaimRuling.total` — the
3-tier-ruled figure. `hoApprovalForm()` instead built its own
`$accessoryClaimTotal` by iterating `$ctx['accessoryBreakdown']` and summing
every row's raw `claim` directly:

```php
foreach ($ctx['accessoryBreakdown'] ?? [] as $a) {
    $accessoryClaimByLabel[$a['label']] = $a;
    $accessoryClaimTotal += (float) ($a['claim'] ?? 0);   // no ruling applied
}
```

This total is displayed to MD Production on the HO "Review & Approve" /
"Validate & Send" form as "Accessory Retur Shortfall (as of last save)" and
folded into "Grand Total (Fabric + Accessory)" — the number the approver
looks at right before signing.

**Concrete divergence scenario:** an order with cutting on target (Cut Qty
≥ 97% of Order Qty) and no fabric overconsumption (tier 3 of the ruling:
claim = 0), but with some accessories showing a raw positive `claim` from
untouched-Retur-Qty noise. The real charge (`deductionBreakdown()`) is
correctly Rp 0 for the accessory component, and no deduction job would ever
be queued for it — but the HO form's Grand Total showed a nonzero figure,
making MD Production believe (and potentially revise other figures against
the belief) that a shortfall charge existed when it didn't.

**Severity: real money / real approval.** This is the same class of bug as
the original incident, on the screen where MD Production actually reviews
and revises consumption figures before signing.

**Fix applied:** `hoApprovalForm()` now reads
`$ctx['accessoryClaimRuling']['total']` for `$accessoryClaimTotal`, exactly
mirroring `RpaQueueService::deductionBreakdown()` and the PDF report/
debit-note templates. `$accessoryClaimByLabel` (the per-row snapshot used
for the Expected Retur/Balance/Claim columns) is unchanged — only the total
now reads the ruled figure.

---

## Finding 2 — Subcon Admin order page had the identical un-ruled total (CONFIRMED, FIXED)

**Locations:**
- `app/Http/Controllers/SubconAdminController.php`, order-show method (accessory-claim snapshot block, ~line 1477–1508)
- Same `consumption-input.blade.php` footer as Finding 1, included twice from `resources/views/subcon/admin/orders/show.blade.php` (the cutting-review-gate approval form and the standalone "Save Reconciliation" form)

**What was duplicated:** identical pattern to Finding 1 — the controller's
own comment even says this is "the same snapshot
`QcApprovalController::hoApprovalForm()` shows", but it independently
re-implemented the same raw-sum bug rather than sharing the fixed logic:

```php
foreach ($ctx['accessoryBreakdown'] ?? [] as $a) {
    $accessoryClaimByLabel[$a['label']] = $a;
    $accessoryClaimTotal += (float) ($a['claim'] ?? 0);   // no ruling applied
}
```

**Concrete divergence scenario:** same as Finding 1, but surfaced on the
Subcon Admin's own order detail page — visible to admins well before (or
independent of) the QC-console HO stage, on both the cutting-approval
embedded form and the freestanding "Save Reconciliation" form.

**Severity: real money / real approval** (same reasoning as Finding 1 — this
page is a decision-support surface for the same underlying charge, shown to
subcon admins).

**Fix applied:** same fix as Finding 1 — read `$ctx['accessoryClaimRuling']['total']` instead of summing raw claims.

---

## Finding 3 — Director's final-authorization page omitted the accessory claim entirely (CONFIRMED, FIXED — highest severity)

**Location:** `resources/views/qc/director-approval-form.blade.php` (~line 17–19, and the "Deductions" table ~line 160–186)

**What was duplicated:** the project's total deduction amount — the same
figure `RpaQueueService::deductionTotal()` computes and the same figure the
Director's own approval **email/WhatsApp message**
(`QcApprovalController::sendDirectorApprovalRequest`, ~line 2050,
`$deductionTotal = app(RpaQueueService::class)->deductionTotal(...)`)
correctly displays. But the review page itself — where the Director
actually reads the numbers before clicking "Authorize" — computed its own
`$grandTotal` from scratch and left the accessory claim out completely:

```php
$grandTotal = $report
    ? ($ded['rejectProduksiPenalty'] + $ded['barangHilangPenalty']
        + $fabricDeductionLines->sum('deduction') + $manualLines->sum('amount'))
    : 0;
```

No accessory term at all — not even an un-ruled one. `$report` here is
`QcReportPdfService::context()`'s return value, which already carries
`accessoryClaimRuling.total`; the page simply never read it.

**Concrete divergence — confirmed live, not hypothetical:** verified
directly against a real production order
(`PRJ-MPG-PRG-2606-000013-1785424994874`, PO `MPG/PO/2606/00423`) where
`accessoryClaimRuling.total` = **Rp 108,466.64**. For this exact order:
- The Director's approval **email** (via `deductionTotal()`) would correctly show "Deduction: Rp 108,466.64" and mark it with the red badge.
- The Director's **review page** — the actual page they click through to and read before authorizing — showed a "Deductions" table with **no accessory line at all**, a `$grandTotal` short by the full Rp 108,466.64, and (if this were the ONLY deduction component) the page's own "No deductions for this inspection" message and the "Authorizing completes the project: **the Invoice** [and Deduction] **RPA jobs are queued**" copy would have read "the Invoice RPA jobs are queued" (omitting "and Deduction") even though `directorApprove()` was about to queue a nonzero deduction job the instant Authorize was clicked.

This is two views of the *same pending decision* disagreeing with each
other within the same approval flow, one step apart.

**Severity: real money / real approval — highest in this audit.** This is
the last human checkpoint before the Invoice + Deduction RPA jobs are
queued and the project is marked complete; an understated or missing total
here is the closest analogue to the original incident, but on the approval
screen itself rather than only the printed record.

**Fix applied:** added `$accessoryClaimLines`/`$accessoryClaimTotal` (read
from `$report['accessoryClaimRuling']`, same as the PDF report body) into
`$grandTotal`, and added a matching "Accessory Retur Shortfall — N accessory
item(s) returned short of Expected Retur" row to the Deductions table,
mirroring `inspection-report-body.blade.php`'s existing row for the same
figure.

---

## Areas checked and found consistent (no fix needed)

- **`SubconConsumptionService::persist()`** — the fabric deduction/
  overconsumption math is computed exactly once and persisted to
  `SubconFabricReconciliation`. Every display consumer (HO form, admin order
  page, Report Validation/Director list rows, PDF report, debit-note page,
  `SubconFabricLinePublisher` → QMS) reads `fabricLinesWithData()`, which
  reads that same persisted row. The only client-side recomputation is the
  live JS preview in `consumption-input.blade.php` (Cutt Plan/Actual
  Cons./Overconsumption/Deduction as the admin types) — verified the JS
  formula is byte-for-byte the same as `SubconConsumptionService::persist()`
  (`excess = act - cp * 1.03; ded = excess > 0 ? excess * total * price : 0`),
  and it is explicitly documented as a preview only — the server
  recomputes and overwrites on submit, so it cannot drift from what's
  actually charged.

- **`resources/views/subcon/admin/report-validations.blade.php` /
  `director-approvals.blade.php`** — both already read
  `RpaQueueService::deductionBreakdown()`'s single-sourced total/breakdown
  (`SubconAdminController::reportValidations()`/`directorApprovals()`,
  `$deduction = $rpaQueue->deductionBreakdown(...)`). No independent
  recomputation found here.

- **`RpaQueueService::deductionBreakdown()`/`deductionTotal()`** — searched
  for every other caller/display of "the deduction total" beyond the report
  body and debit-note page already fixed in the precedent incident. Found
  two more (Findings 1 and 2 above); no others.

- **`MaterialReturnService`** — the "is a Material Flow return check still
  pending" gate (`isReturnCheckPending()` → `realReturnCheckPending()`) is
  the single implementation used everywhere: `hoApprove()`/
  `hoSendApproval()`'s gate, `SubconAdminController`'s badge, and the
  batched list versions (`pendingStatusForOrders()`/
  `autoApprovedStatusForOrders()`) all delegate to the same three-condition
  check. No separate "is it actually resolved" computation exists anywhere
  else.

- **Fabric pricing (`SubconProductionService::resolveFabricPricing`)** —
  every consumer (HO form, admin order page, cutting-approval form,
  Director review, `SubconFabricLinePublisher`, `SubconCuttingPlanService`)
  goes through `fabricLinesWithData()`, which is the only caller of
  `resolveFabricPricing()`. The admin's saved value always wins over the
  resolved reference price, consistently. No divergent resolution path
  found.

- **QC report's other computed sections (yield matrix, Cut Qty %, reject
  penalties)** — `calculateDeductions()` (penalties) is called exactly once,
  from inside `context()`; `RpaQueueService` used to call it a second time
  independently but was already changed to read `context()`'s
  `deductions` key instead (see that method's own docblock). The yield-matrix
  `$totals` array is built once per `context()` call and is the same object
  `accessoryClaimRuling()` reads its Cut Qty %/Order Qty gate from — no
  separate re-derivation of "Cut Qty as % of Order Qty" exists elsewhere
  that could disagree with the ruling's own gate.

- **`context()`'s other fields read by multiple consumers** — checked
  `fabricLines` (deduction filtered/summed identically by report body,
  director form, and — post-fix — the HO/admin forms),
  `deductionLines`/manual deduction total (report body sums the
  `context()`-supplied collection; `RpaQueueService` runs its own
  `sum('amount')` query directly against `packaging_session_deduction_lines`
  scoped by the same `session_id` — a redundant query, not a redundant
  *rule*, since both simply sum every row for that session with no
  business logic applied; cannot diverge).

## Noted but not fixed — cosmetic, no financial/approval impact

- **`$cuttPlanTotal` (fabric-bottleneck Cut Plan)** — computed
  independently by the identical one-line formula
  (`$fabricLines->pluck('cutt_plan')->filter(...)->min()`) in
  `QcReportPdfService::context()`, `subcon/partials/production-detail.blade.php`,
  and `qc/director-approval-form.blade.php`. This is display-only (how many
  pieces the scarcest fabric could support) — it does not gate any charge,
  approval, or RPA payload. Because the underlying `cutt_plan` per fabric
  line is itself single-sourced (`SubconFabricReconciliation`, via
  `fabricLinesWithData()`), the three call sites cannot disagree with each
  other as long as they're handed the same `$fabricLines` array, which they
  are. No fix applied; flagged here only for completeness since the audit
  was asked to check this specific figure.

## Needs manual verification (not confirmed as an actual divergence)

None. Every area called out in the audit scope was traced to either a
confirmed divergence (Findings 1–3, fixed) or a confirmed single source of
truth (see "Areas checked and found consistent" above). No speculative
"this looks like it could maybe diverge" items were left unresolved.

---

## Recommendations

1. **Any figure that gates a real charge, RPA payload, stage transition, or
   approval decision must be computed in exactly one method.** That method's
   result should be embedded in whatever shared context object (here,
   `QcReportPdfService::context()`) already flows to every consumer, rather
   than each consumer re-deriving the figure from the same raw inputs.

2. **Every display consumer must read that method's output, never
   re-derive it — not even "the same formula, just typed again."** Findings
   1 and 2 in this audit were not a rule that changed out from under a
   forgotten copy; they were the SAME raw-sum mistake independently
   hand-written twice. Copy-pasting the read (`$ctx['accessoryClaimRuling']['total']`)
   is strictly safer than copy-pasting the computation, even when the
   computation looks trivial at the time it's written.

3. **When a comment says "same as X"**, treat that as a signal to actually
   share code with X, not just to informally match its behavior by hand.
   Both Finding 1 and Finding 2's controller code carried a comment
   explicitly saying "same snapshot as HO's form" / "same figures the
   report uses" — the intent to stay consistent was already documented, it
   just wasn't enforced by sharing the actual computation.

4. **A figure shown on two screens of the same approval flow (e.g. an
   email/notification and the page it links to) is a strong signal to
   check for this pattern.** Finding 3 was caught by tracing exactly this
   kind of pair — the notification already used the correct single source;
   the page it pointed to did not.

5. **When auditing for this pattern, don't stop at the first hit.** All
   three findings in this document are instances of the exact same
   underlying mistake (the accessory claim ruling, specifically), found by
   searching every remaining reader of `accessoryBreakdown`/
   `accessoryClaimRuling` after the first fix — not by searching for new,
   unrelated kinds of duplicated logic. A single fixed bug is a strong prior
   that its sibling call sites got the same fix applied inconsistently, or
   not at all.
