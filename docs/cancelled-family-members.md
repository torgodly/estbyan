# Cancelled family members (employees registered as family)

## The problem

Some employees were also added as a family member (spouse, father, mother, son, daughter)
on **another** employee's registration. So the same person was insured twice and, in many
cases, got two printed cards: their own employee card and a family-member card.

In the production database of 2026-10-28 there were **48** such records (matched by
national ID). 22 of them already had a printed family-member card.

New cases are blocked by `App\Rules\UniqueBeneficiaryNationalId`, which refuses an employee's
national ID as a family member. This document covers the records created before that rule.

## The decision

Do **not** delete these records. **Cancel** them instead.

- A cancelled family member has `beneficiaries.cancelled_at` set and
  `beneficiaries.cancellation_reason = employee_duplicate`
  (`App\Enums\BeneficiaryCancellationReason::EmployeeDuplicate`).
- The person keeps their **employee card**. That is the only card handed to them.
- Their family-member card (if printed) is **kept by the administration and never handed over**.

Why cancel instead of delete:

- Printed cards still exist physically. If the record were deleted, scanning the card would
  just say "card not found". With a cancelled record, the scan shows a clear warning.
- Nothing is lost. If a record was cancelled by mistake, set `cancelled_at` and
  `cancellation_reason` back to `NULL` to restore it.

## How it works in the code

| Where | Behaviour |
|---|---|
| `App\Models\Beneficiary` | Global scope `Beneficiary::ACTIVE_SCOPE` hides cancelled records from **every** normal query: registrations, family lists, delivery completeness, reports, exports, counts. |
| `Beneficiary::queryWithCancelled()` | Use this only when cancelled records must be included. |
| `Beneficiary::cancel($reason)` | Cancels a record. |
| `InsuranceCardScanService::find()` | Also finds cancelled cards and marks the hit as cancelled. |
| Deliver cards page (`DeliverInsuranceCards`) | Scanning a cancelled card shows a red persistent warning: *keep this card, do not hand it over*. The card is not added, and the family can be delivered without it. |
| Print scan page (`ScanInsuranceCards`) | Same warning. A cancelled card cannot be marked as printed. |
| `InsuranceCardNumberAssigner` | Cancelled card numbers stay reserved and are never given to someone else. |
| `beneficiaries:cleanup-duplicates` | Cancels employees-as-family (does **not** delete them). Other duplicates (same person under two families) are still deleted. |

## Rules for future changes

1. **Never hard-delete an employee-as-family record.** Cancel it.
2. If you write a query that must see cancelled cards (scanning, card numbers, data wipes),
   use `Beneficiary::queryWithCancelled()`. Everything else should keep the default scope.
3. Raw `DB::table('beneficiaries')` queries bypass the global scope. Add
   `whereNull('cancelled_at')` yourself if you write one.
4. If a new cancellation reason is needed, add a case to `BeneficiaryCancellationReason`
   with its label and scan warning.

## Running it on production

```bash
php artisan migrate
php artisan beneficiaries:cleanup-duplicates --dry-run --except=45594
php artisan beneficiaries:cleanup-duplicates --except=45594
```

`--except=45594` skips beneficiary #45594 (قاسم خالد بلقاسم بن طالب). Their national ID matches
employee 001654 (**محمد** خالد بلقاسم بن طالب), but the first name is different, so it may be a
wrong ID and not the same person. Check the real ID documents before cancelling it.

Also check by hand: beneficiary #11093 (أميرة طارق انبيه جاب الله) has the same name as
employee 001612 but a national ID that differs by one digit. It is not matched automatically.
