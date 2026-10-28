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
| `beneficiaries:cleanup-duplicates` | Cancels employees-as-family (does **not** delete them) when the first name matches the employee. If the national ID matches but the first name does not, nothing changes and the record is flagged for manual review. Other duplicates (same person under two families) are still deleted, keeping the printed / approved copy. |

### How "same family member twice" is detected

1. Same national ID (or same passport + nationality).
2. Otherwise, same **full name + date of birth**. Spelling variants are ignored
   (أ/إ/آ → ا, ى → ي, ة → ه, diacritics, spaces). This catches a child added under both
   parents where one parent typed a wrong or fake ID (e.g. `120150000000`).

The copy that is kept is: printed card first, then approved registration, then the oldest record.

## Rules for future changes

1. **Never hard-delete an employee-as-family record.** Cancel it.
2. If you write a query that must see cancelled cards (scanning, card numbers, data wipes),
   use `Beneficiary::queryWithCancelled()`. Everything else should keep the default scope.
3. Raw `DB::table('beneficiaries')` queries bypass the global scope. Add
   `whereNull('cancelled_at')` yourself if you write one.
4. If a new cancellation reason is needed, add a case to `BeneficiaryCancellationReason`
   with its label and scan warning.

## Manual review (people the system is not sure about)

When the system *thinks* two records are the same person but cannot be sure, it never
changes them. It adds a **review flag** instead (`review_flags` table, `App\Models\ReviewFlag`).

- Page: **التسجيل الطبي → مراجعة يدوية** (`ReviewFlagResource`, HR and Smart Care only).
  The menu shows how many are still open.
- Each row shows the person, the reason, and the other record it may be confused with.
  "فتح الطلب" opens the registration. "تمت المراجعة" closes it with a note on what was found.
- **Do not print or deliver cards for a flagged person until the flag is closed.**
- Raising the same flag again only refreshes its note; a closed flag stays closed.

Reasons (`App\Enums\ReviewFlagReason`):

| Reason | Raised by | Meaning |
|---|---|---|
| `possible_duplicate_employee` | `review:flag-possible-duplicates` | Two employee records, national IDs one typo apart, same first two names. |
| `possible_employee_as_family` | `review:flag-possible-duplicates` | A family member with the same full name as an employee (different ID), or an ID one typo apart with the same first two names. |
| `possible_duplicate_family_member` | `review:flag-possible-duplicates` | Same first name, IDs one typo apart, under two different employees. |
| `employee_national_id_name_mismatch` | `beneficiaries:cleanup-duplicates` | Family member has an employee's exact national ID but a different first name. |

"One typo apart" = one digit different, or two neighbouring digits swapped.

## Accepted employees API

`GET /api/accepted-employees` (header `X-Api-Key: <ACCEPTED_REGISTRATIONS_API_KEY>`) returns approved
employees whose cards were delivered, with their family members, in the same format as the
estbyan-audit project. Differences in this project:

- Cancelled family members are never returned (global scope), and their photo URL returns 404.
  `beneficiaries_count` is the number of family members actually returned.
- `office` (employee's office) is included.
- `needs_review` is `true` on the employee or a family member who has an open review flag.
  The importer should not treat these people as confirmed until the flag is closed.

## Running it on production

```bash
php artisan migrate
php artisan beneficiaries:cleanup-duplicates --dry-run
php artisan beneficiaries:cleanup-duplicates
php artisan review:flag-possible-duplicates
```

Run the cleanup **before** the flagging, so automatic cases are not also flagged.

On the 2026-10-28 data:

- Cleanup cancels 45 employees-as-family and deletes 6 unprinted duplicates
  (4 الشبو sons with fake IDs, سميحة مجدي خليل, عبدالمالك عبدالوهاب سليمان).
- 3 are flagged instead of cancelled because the first name differs:
  #45594 قاسم/محمد خالد بن طالب, #48424 الامعة/اللامعة مختار, #61520 سييد/سيد محمد عابدين.
- `review:flag-possible-duplicates` flags 14 people, including 4 employees registered twice
  (حسام الدين الهليب, امحمد زكريا عبدالقادر, نجوى أبوعجيلة غيث, عبدالرازق عبدالفتاح عبدالرازق).
