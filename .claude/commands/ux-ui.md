Review the frontend code I specify (or recently changed files) for UX and UI quality, then fix the issues found.

## Stack context
- TailwindCSS 4 + Radix UI headless components + Lucide React icons + Framer Motion
- Three route groups: `(admin)/admin/`, `(auth)/`, `(customer)/`
- Thai-language UI — labels, messages, and status text are in Thai

## What to review and fix

**Visual consistency**
- Spacing, sizing, and color usage should follow the existing patterns in the file and its siblings
- Icon usage should be consistent (Lucide only; no mixing styles)
- Button variants, badge styles, and card layouts should match the design system already in use

**Interaction & feedback**
- Loading states: spinners or skeletons where data is async
- Empty states: meaningful messages when lists are empty, not just blank space
- Error states: visible, actionable error messages (not silent failures)
- Confirmation for destructive actions (delete, cancel order, adjust stock)

**Accessibility**
- Interactive elements must have accessible labels (`aria-label`, `aria-labelledby`, or visible text)
- Color contrast should not be the only indicator of state (add icons or text)
- Keyboard navigability for modals and dropdowns (Radix UI handles most of this — check it's wired up correctly)

**Responsiveness**
- Admin pages: tablet-friendly minimum (md: breakpoint)
- Customer pages: mobile-first (sm: breakpoint)
- POS page: designed for a fixed-screen kiosk, no scroll requirement

**Thai language**
- Use natural Thai phrasing — avoid literal machine-translation style
- Status labels should match the values in `lib/order-status.ts`
- Error/validation messages should be in Thai for customer-facing pages, English acceptable for admin-only pages

## What NOT to do
- Do not change business logic, API calls, or data flow
- Do not add new features beyond what the component already does
- Do not restructure component files or rename props
- Do not introduce new dependencies

After making changes, briefly state what you changed and why (one line per change).
