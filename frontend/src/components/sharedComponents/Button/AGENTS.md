# Button Component DOX (frontend/src/components/sharedComponents/Button/)

## Purpose
A universal button component that supports various styles (variants) and
sizes, ensuring a consistent look for all interactive UI elements. Renders
a `<button>` (there is no anchor branch despite `href`/`target` being in
the props type).

## Ownership
Web.Revizor.

## Local Contracts
- Default export (`import Button from '.../Button/Button'`).
- **Interface** (`IButton`): `children` (falls back to `title`), `type`
  (`submit` | `reset` | `button`, default `button`), `variant`
  (`IButtonVariant` = `primary` | `secondary` | `clear` | `chat`, default
  `primary`), `size` (`IButtonSize` = `default` | `smaller` | `small` |
  `large` | `clear`, default `default`), `onClick`, `onMouseDown`,
  `className`, `id`, `disabled`, `arrow_icon` (appends the
  `common/send` `Icon`), `dataTooltip` (rendered as `data-tooltip`).
- Class composition is done with `clsx` (the only class helper installed —
  no `tailwind-merge`).

## Work Guidance
- Add new looks as a `variant` in the `IButtonVariant` union + a `clsx`
  branch, not via ad-hoc `className` at call sites.
- Keep it a native `<button>` for accessibility.

## Verification
- Verify correct rendering of every `variant` and `size`.
- Ensure `onClick` fires and `disabled` styling/behaviour is correct.
- Verify the `arrow_icon` icon renders (needs the SVG sprite present).
