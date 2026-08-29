# SlideDown Component DOX (frontend/src/components/sharedComponents/SlideDown/)

## Purpose
An animation wrapper that provides a "slide-down" effect for expanding
collapsible content (e.g. the expandable filter list).

## Ownership
Web.Revizor.

## Local Contracts
- Default export (`import SlideDown from '.../SlideDown/SlideDown'`).
- **Interface** (`SlideDownProps`): `children: React.ReactNode`,
  `isOpen: boolean`.
- Measures the inner content with a `ResizeObserver` and animates an
  explicit pixel `height` (0 ↔ measured `scrollHeight`) together with
  opacity, via the Tailwind class `transition-[height,opacity]
  duration-300 ease-global`. When closed it also sets `overflow-hidden`.
- Falls back to a one-off height measurement when `window.ResizeObserver`
  is unavailable.

## Work Guidance
- Keep the height/opacity transition in sync; don't switch to
  `max-height` — the component intentionally uses the measured height so
  there is no easing lag on tall content.
- If reduced-motion support is needed, gate the transition classes on a
  `prefers-reduced-motion` check.

## Verification
- Verify smooth open/close with both short and tall content.
- Check the content is not clipped when fully expanded.
