/**
* Module: @content-planner/once-per-realm
*/

// The header modules reach a page twice: through the import map (PageRenderer) and as inline
// <script src> tags from the middleware-rendered headers. The differing URLs make the browser
// evaluate them as two modules in the same window, which would bind every listener twice and,
// for example, open two record modals per click or toggle watching on and straight back off.
export default function oncePerRealm(name) {
  const initialized = window.contentPlannerInitializedModules ??= new Set()
  if (initialized.has(name)) {
    return false
  }
  initialized.add(name)
  return true
}
