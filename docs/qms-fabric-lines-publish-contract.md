# QMS Fabric-Lines Publish Contract (Portal → QC Console)

**Status:** portal side implemented 2026-07-06. **Console side: action required** (see §4).

## 1. Why this changed
Previously the portal **pushed** fabric consumption into `packaging_project_fabric_lines`
only at HO-approval time, keyed by `project_id`. That required the QC packaging
**project to already exist**. But the cutting report (and its consumption figures)
is ready much earlier — often **before** the console has started its project.

Now the portal **publishes** the fabric lines as soon as they're ready and the
console **pulls** them by `production_group`, on its own schedule.

## 2. Where the data lands
Same table you already read: **`packaging_project_fabric_lines`** (QMS DB).
Portal migration `2026_07_06_000003` adds (all nullable, guarded):

| Column | Purpose |
|--------|---------|
| `production_group` (indexed) | **the pull key** — what you SELECT by |
| `overconsumption` | ratio, e.g. `0.0271` = 2.71% |
| `fabric_price` | IDR unit price used for the deduction |
| `deduction` | IDR amount charged (0 if within the 3% tolerance) |

and **relaxes `project_id` to `NULLABLE`** so a row can be *staged* before a project exists.

## 3. Row lifecycle
1. **Cutting approval** (portal) → upsert one row per fabric, keyed by
   `(production_group, label)`, with **`project_id = NULL`** (staged).
2. **HO approval** (portal) → re-publishes the same rows (figures may have been
   revised) and **sets `project_id`** to the HO session's project.
   `project_id` is only ever *set*, never nulled — a value you wrote survives a re-publish.

`created_by = 'web_portal'` on portal-inserted rows.

## 4. What the console must do
When you start a packaging project for a `production_group`:

```sql
-- read the staged lines
SELECT * FROM packaging_project_fabric_lines
WHERE production_group = :pg;

-- adopt them to your project (so your existing read-by-project_id PDF path works)
UPDATE packaging_project_fabric_lines
SET project_id = :project_id
WHERE production_group = :pg AND project_id IS NULL;
```

After adoption your existing `WHERE project_id = ?` read path is unchanged.

## 5. ⚠️ Deploy ordering
The portal **no longer inserts** fabric lines keyed only by `project_id` at HO time —
it publishes by `production_group` instead. **Deploy the console-side adoption
(§4) before/with the portal change**, or fabric lines won't be linked to projects
created in the gap.

## 6. Guarantees
- Publishing is **best-effort + fully guarded** on the portal side: a missing
  column/table or unreachable QMS is logged and skipped — it never blocks a
  cutting approval or HO sign-off.
- `retur_kain` (portal) maps to **`return_kain`** (QMS) — unchanged.
- Numeric NOT-NULL columns are always written (coalesced to 0), never NULL.
