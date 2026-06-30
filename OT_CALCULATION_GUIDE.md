# OT Calculation Guide

This document explains how overtime (OT) hours are calculated and distributed for both Non-Executive and Executive OT forms in the system.

---

## Non-Executive OT (OT 1 - OT 5)

Non-Executive staff use a detailed 5-tier OT breakdown based on Malaysian labor law requirements.

### OT 1: Normal Day OT
- **When:** Worked on a normal weekday (Monday-Friday, not a public holiday)
- **Calculation:** All actual hours worked after normal working hours
- **Formula:** `OT 1 = actual_total_hours`
- **Example:** If an employee works 2 hours overtime on a Tuesday, OT 1 = 2.00 hours

### OT 2: Rest Day OT (First 7.5 Hours)
- **When:** Worked on a rest day (Saturday or Sunday)
- **Calculation:** First 7.5 hours worked on the rest day
- **Formula:** `OT 2 = min(actual_total_hours, 7.5)`
- **Example:** If an employee works 5 hours on Saturday, OT 2 = 5.00 hours

### OT 3: Rest Day OT (Excess Hours)
- **When:** Worked on a rest day (Saturday or Sunday) for more than 7.5 hours
- **Calculation:** Hours worked beyond the first 7.5 hours
- **Formula:** `OT 3 = max(actual_total_hours - 7.5, 0)`
- **Example:** If an employee works 10 hours on Saturday, OT 2 = 7.50 hours, OT 3 = 2.50 hours

### OT 4: Public Holiday OT
- **When:** Worked on a public holiday (any day of the week)
- **Calculation:** Excess hours after the standard 7.5-hour normal work period
- **Formula:** `OT 4 = max(actual_total_hours - 7.5, 0)`
- **Example:** If an employee works 9 hours on a public holiday, OT 4 = 1.50 hours

### OT 5: Rest Day Count
- **When:** Any hours are worked on a rest day (Saturday or Sunday)
- **Calculation:** Always set to 1 if any OT hours exist on a rest day
- **Formula:** `OT 5 = 1` (if actual_total_hours > 0 on rest day)
- **Purpose:** Used for counting the number of rest days worked (for payroll calculation)

---

## Executive OT (3 Categories)

Executive staff use a simplified 3-category OT breakdown.

### Normal Day OT
- **When:** Worked on a normal weekday (Monday-Friday, not a public holiday)
- **Calculation:** All actual hours worked after normal working hours
- **Formula:** `Normal Day OT = actual_total_hours`
- **Example:** If an executive works 3 hours overtime on a Wednesday, Normal Day OT = 3.00 hours

### Rest Day OT
- **When:** Worked on a rest day (Saturday or Sunday)
- **Calculation:** All actual hours worked on the rest day
- **Formula:** `Rest Day OT = actual_total_hours`
- **Example:** If an executive works 6 hours on Sunday, Rest Day OT = 6.00 hours

### Public Holiday OT
- **When:** Worked on a public holiday (any day of the week)
- **Calculation:** All actual hours worked on the public holiday
- **Formula:** `Public Holiday OT = actual_total_hours`
- **Example:** If an executive works 8 hours on a public holiday, Public Holiday OT = 8.00 hours

---

## Summary Comparison

| Category | Non-Executive | Executive |
|----------|---------------|-----------|
| **Normal Day** | OT 1 (all hours) | Normal Day OT (all hours) |
| **Rest Day** | OT 2 (first 7.5h) + OT 3 (excess) + OT 5 (count) | Rest Day OT (all hours) |
| **Public Holiday** | OT 4 (excess after 7.5h) | Public Holiday OT (all hours) |

---

## Key Differences

1. **Non-Executive:** Detailed breakdown with 5 OT categories to comply with Malaysian labor law reporting requirements
2. **Executive:** Simplified 3-category view for easier readability and approval workflow
3. **Rest Day Calculation:** Non-Executive splits rest day hours into OT 2 (first 7.5h) and OT 3 (excess), while Executive combines all rest day hours into a single category
4. **Public Holiday Calculation:** Non-Executive only counts excess hours beyond 7.5h as OT 4, while Executive counts all hours worked on public holidays

---

## Implementation Notes

- The calculation is performed client-side in JavaScript (`calcOT()` function in `ot-forms/edit.blade.php`)
- Day type (normal/rest day/public holiday) is determined by:
  - **Rest Day:** Saturday (dayOfWeek = 6) or Sunday (dayOfWeek = 0)
  - **Public Holiday:** Manually marked via checkbox (`is_public_holiday` field)
- Actual hours are calculated from `actual_start_time` and `actual_end_time` inputs
- Meal break checkbox does not affect OT calculation in the current implementation
