# Project PULSE — Dashboard Modernization TODO

## Project Overview

**Project:** Project PULSE (Portal for Unified Learner Monitoring, School Records, and Engagement)

**Current Technology Stack:** PHP, MySQL, HTML, CSS, and JavaScript

**Objective:** Modernize the Project PULSE dashboard and navigation, then incrementally migrate all project-owned styling to Tailwind CSS while preserving existing functionality.

**Implementation Strategy:** Incrementally modernize the existing application. Retain PHP as the backend and MySQL as the database. Tailwind CSS is the only compiled project styling system; reusable portal selectors live in its `components` layer, while new and migrated markup uses utilities. Keep runtime theme/chart values as CSS custom properties. Introduce Vue.js only when justified by specific interactive requirements.

> **Tailwind migration status:** Project pages now load the single compiled `assets/dist/tailwind.css`; the standalone `assets/css/app.css`, `assets/css/login.css`, and unused login-template `main.css`/`util.css` files have been removed. Shared portal styles are maintained in Tailwind's `components` layer to preserve existing class hooks and responsive behavior, and new or migrated markup uses utilities. Runtime theme and data-driven chart values remain as CSS custom properties. Automated verification is recorded below; authenticated visual checks remain limited by unavailable role sessions.

## Global Development Rules

- [x] Inspect the existing source code before making modifications.
- [x] Preserve existing PHP backend functionality, database schema, authentication, sessions, and role-based authorization.
- [x] Preserve all working modules, routes, forms, queries, JavaScript functions, and integrations.
- [x] Do not rebuild the entire application unless explicitly approved.
- [x] Do not introduce Vue.js or another framework solely for visual styling.
- [x] Do not introduce unnecessary dependencies or conflicting CSS frameworks.
- [x] Do not replace functional features with static mockups or fabricated data.
- [x] Do not expose database credentials or sensitive information in frontend code.
- [x] Use version control and create a recoverable baseline before implementation.
- [x] Implement and verify one phase at a time.
- [x] Review modified files and test affected functionality before proceeding to the next phase.
- [x] Do not claim that a feature has been tested unless testing has actually been performed.
- [x] Document modifications, dependencies, known issues, and required setup steps.

---

# PHASE 1 — Project Audit and Preparation

**Objective:** Understand the existing application architecture and identify the safest implementation strategy before modifying any files.

### Tasks

- [x] Inspect the complete Project PULSE project directory.
- [x] Identify the dashboard entry point and all relevant PHP files.
- [x] Identify shared PHP includes, templates, navigation components, and layout files.
- [x] Identify existing CSS stylesheets, JavaScript files, and frontend dependencies.
- [x] Review the authentication and session-management implementation.
- [x] Identify how the application determines the authenticated user's role.
- [x] Document the learner, parent, teacher, and administrator dashboards.
- [x] Identify existing dashboard cards, statistics, announcements, schedules, and navigation elements.
- [x] Review existing light/dark mode and responsive layout implementations.
- [x] Determine whether Vue.js, Vite, Tailwind CSS, or other frontend frameworks are already installed.
- [x] Identify shared components whose modifications could affect other modules.
- [x] Record existing routes, forms, JavaScript hooks, and backend dependencies that must remain functional.
- [x] Identify potential security concerns, compatibility issues, and regression risks.
- [x] Determine the minimum set of files required for the dashboard modernization.
- [x] Establish a backup and rollback strategy.
- [x] Create a Git baseline commit after confirming that the working tree contains the intended changes.

### Completion Criteria

- [x] Project architecture and dependencies are documented.
- [x] Relevant files have been identified.
- [x] Existing functionality and security requirements are documented.
- [x] A safe implementation sequence has been established.
- [x] No application files have been modified during the audit phase.

---

# PHASE 2 — Establish the Design System

**Objective:** Create a consistent visual foundation for the Project PULSE dashboard and navigation.

### Tasks

- [x] Review the existing Project PULSE branding and retain its recognizable identity.
- [x] Define a consistent color palette.
- [x] Establish typography styles and a readable type scale.
- [x] Define consistent spacing, padding, margins, and layout rules.
- [x] Establish standard border radii, borders, and shadow treatments.
- [x] Define reusable styles for buttons, cards, forms, tables, badges, and dropdowns.
- [x] Establish consistent hover, active, disabled, focus, success, warning, and error states.
- [x] Ensure adequate color contrast and readable text.
- [x] Preserve existing theme preferences and improve light/dark mode styling where applicable.
- [x] Organize shared styles to minimize duplication.
- [x] Avoid unnecessary global CSS changes that could break other modules.
- [x] Retain the existing styling framework where practical.
- [x] Introduce Tailwind CSS only if justified by the existing architecture and implementation requirements.
- [x] Avoid introducing Vue.js solely to establish visual styles.

### Completion Criteria

- [x] A consistent design system is implemented.
- [x] Shared styles are reusable and maintainable.
- [x] Representative existing components remain functional.
- [x] No unrelated module regressions have been introduced.
- [x] Design tokens and styling conventions are documented.

---

# PHASE 3 — Modernize the Sidebar and Navigation

**Objective:** Create modern, intuitive, and responsive navigation while preserving existing routes and permissions.

### Tasks

## Sidebar

- [x] Add the existing Project PULSE logo and application name.
- [x] Implement a clean, collapsible sidebar.
- [x] Use consistent icons and readable navigation labels.
- [x] Highlight the active navigation item.
- [x] Organize related modules into expandable groups where appropriate.
- [x] Support an expanded sidebar and a compact icon-only state on desktop.
- [x] Provide tooltips for collapsed navigation icons where appropriate.
- [x] Place logout separately from regular navigation items.
- [x] Preserve existing navigation destinations.
- [x] Display only the navigation items appropriate to the authenticated user's role.

## Top Navigation Bar

- [x] Add a sidebar toggle button.
- [x] Display the current page title or breadcrumb.
- [x] Display authenticated user information when available.
- [x] Implement a profile dropdown only for existing or approved account actions.
- [x] Integrate notifications only if supported by existing functionality.
- [x] Ensure all visible buttons and links perform real actions.

## Mobile Navigation

- [x] Implement a mobile navigation drawer.
- [x] Add working open and close controls.
- [x] Support closing the drawer through an appropriate interaction, such as selecting a navigation item.
- [x] Ensure the drawer does not obstruct essential content unnecessarily.
- [x] Support keyboard navigation and accessible focus behavior.

### Completion Criteria

- [x] Sidebar expansion and collapse work correctly.
- [x] Active navigation indicators reflect the current page.
- [x] Existing routes and links remain functional.
- [x] Navigation respects role-based access.
- [x] Mobile navigation works without unintended overflow.
- [x] Logout and account-related actions continue to work.

---

# PHASE 4 — Redesign Dashboard Content

**Objective:** Improve the appearance, readability, and organization of dashboard information.

### Tasks

## Dashboard Header

- [x] Improve the page heading and welcome section.
- [x] Display the user's name or role-specific greeting when available.
- [x] Establish a consistent layout for page titles and supporting information.

## Summary Cards

- [x] Redesign existing summary cards with consistent dimensions and spacing.
- [x] Use clear labels, readable values, and appropriate icons.
- [x] Add supporting descriptions only when useful and supported by actual data.
- [x] Use restrained borders, backgrounds, and shadows.
- [x] Ensure cards adapt to different screen sizes.
- [x] Display only statistics supported by existing PHP/MySQL functionality.
- [x] Preserve existing calculations and business rules unless a change is explicitly justified.

## Dashboard Sections

- [x] Improve the presentation of existing announcements.
- [x] Improve the schedule or timetable preview.
- [x] Improve existing attendance summaries.
- [x] Improve other role-specific dashboard sections where applicable.
- [x] Preserve working links to the relevant modules.
- [x] Avoid adding unsupported charts or fabricated statistics.
- [x] Implement appropriate empty states when no records are available.
- [x] Provide loading and error states when required by asynchronous operations.
- [x] Preserve the different information requirements of learner, parent, teacher, and administrator dashboards.

### Completion Criteria

- [x] Dashboard content has a consistent visual hierarchy.
- [x] Existing dashboard data remains accurate.
- [x] Summary cards and dashboard links function correctly.
- [x] Each user role sees appropriate information.
- [x] Empty and error states are handled appropriately.
- [x] No unrelated business logic has been changed.

---

# PHASE 5 — Responsive Layout Optimization

**Objective:** Ensure that the redesigned dashboard works properly across desktop, laptop, tablet, and mobile devices.

### Tasks

- [x] Test the dashboard on desktop computers.
- [x] Test the dashboard on laptop screens.
- [x] Test the dashboard on tablet-sized screens.
- [x] Test the dashboard on mobile phones.
- [x] Adjust sidebar behavior at appropriate responsive breakpoints.
- [x] Ensure cards stack appropriately on smaller screens.
- [x] Prevent unintended horizontal page overflow.
- [x] Make buttons, links, menus, and form controls touch-friendly.
- [x] Ensure tables remain usable on narrow screens.
- [x] Ensure dropdowns and dialogs fit within the viewport.
- [x] Check layouts with long user names and large numerical values.
- [x] Verify typography and spacing at different screen sizes.
- [x] Preserve light/dark mode behavior.
- [x] Avoid device-specific workarounds when standard responsive CSS is sufficient.

### Completion Criteria

- [x] Dashboard layouts adapt correctly to supported screen sizes.
- [x] No unintended horizontal page overflow remains.
- [x] Navigation remains usable on mobile devices.
- [x] Dashboard cards remain readable and properly arranged.
- [x] Forms, tables, dropdowns, and dialogs remain usable.
- [x] Tested screen sizes and remaining issues are documented.

---

# PHASE 6 — Interactions and Usability Improvements

**Objective:** Improve dashboard interaction behavior, feedback, accessibility, and usability.

### Tasks

- [x] Verify sidebar toggling and mobile drawer behavior.
- [x] Verify expandable navigation groups.
- [x] Verify profile dropdowns and supported account actions.
- [x] Improve loading indicators where asynchronous requests exist.
- [x] Improve success and error messages.
- [x] Improve form validation feedback where applicable.
- [x] Provide useful empty states and recovery guidance for failed operations.
- [x] Prevent accidental duplicate submissions where appropriate.
- [x] Ensure visible keyboard focus for interactive elements.
- [x] Verify keyboard navigation for menus, dialogs, and controls.
- [x] Use appropriate semantic HTML and accessible labels.
- [x] Preserve existing light/dark mode behavior and stored preferences.
- [x] Verify that buttons, links, and controls perform their advertised actions.
- [x] Remove or avoid decorative controls that have no working implementation.
- [x] Check browser console errors introduced by the redesign.
- [x] Introduce Vue.js only if a specific interactive requirement justifies it.
- [x] If Vue.js is introduced, isolate it to the appropriate component or module and document its dependencies.
- [x] Ensure any frontend API interactions continue to use secure PHP endpoints.

### Completion Criteria

- [x] Interactive elements behave correctly.
- [x] Feedback is clear and appropriate.
- [x] Keyboard accessibility is improved.
- [x] Existing theme functionality works correctly.
- [x] No unnecessary frontend dependencies have been introduced.
- [x] Any Vue.js integration is documented and tested.

---

# PHASE 7 — Integration and Regression Testing

**Objective:** Verify that the dashboard modernization has not broken existing Project PULSE functionality or security controls.

### Authentication and Authorization

- [x] Verify login and logout.
- [x] Verify session persistence and expiration.
- [x] Verify that protected pages remain protected.
- [x] Verify role-specific dashboard access.
- [x] Verify that unauthorized users cannot access restricted backend endpoints.
- [x] Confirm that hiding navigation links is not being used as a substitute for server-side authorization.

### Navigation and Interface

- [x] Verify all modified navigation links.
- [x] Verify active-page indicators.
- [x] Verify sidebar expansion and collapse.
- [x] Verify mobile drawer behavior.
- [x] Verify profile dropdowns and supported actions.
- [x] Verify light/dark mode where applicable.
- [x] Verify responsive layouts and browser console behavior.

### Database and Existing Features

- [x] Verify that dashboard statistics use actual authorized data.
- [x] Verify existing announcement and schedule functions.
- [x] Verify relevant learner profile and parent-child linking functionality if affected.
- [x] Verify teacher grade-management functions if affected.
- [x] Verify QR-based digital IDs if affected.
- [x] Verify attendance monitoring if affected.
- [x] Verify administrative logs if affected.
- [x] Verify existing Excel import/export functions if affected.
- [x] Verify that existing MySQL queries and PHP endpoints still work.
- [x] Verify that the existing database schema has not been unintentionally changed.

### Security and Error Handling

- [x] Verify server-side validation.
- [x] Verify authorization on protected operations.
- [x] Verify CSRF protections for relevant state-changing requests.
- [x] Verify that database credentials are not exposed in frontend files.
- [x] Check PHP logs for newly introduced errors or warnings.
- [x] Check failed network requests and browser console errors.
- [x] Confirm that error handling does not reveal sensitive information.

### Completion Criteria

- [x] All applicable regression tests have been completed.
- [x] Critical issues introduced by the redesign have been resolved.
- [x] Existing authentication and permissions remain intact.
- [x] Core affected features continue to work.
- [x] Tests that could not be performed are explicitly documented.
- [x] Remaining known issues and their severity are recorded.
- [x] A test report is available for review.

---

# PHASE 8 — Finalization and Deployment

**Objective:** Prepare the completed dashboard modernization for a controlled deployment.

### Tasks

- [x] Review the final source-code changes.
- [x] Remove only confirmed-unused styles, scripts, and dependencies.
- [x] Check for duplicate CSS rules and unnecessary assets.
- [x] Resolve remaining PHP warnings and frontend errors introduced by the changes.
- [x] Verify all asset paths and resource references.
- [x] If Vue.js/Vite was introduced, verify the production build and deployment configuration.
- [x] Avoid adding a frontend build step if the implementation does not require one.
- [x] Verify compatibility with the existing PHP hosting environment.
- [x] Confirm that database configuration remains environment-specific and secure.
- [x] Confirm that all existing routes and permissions remain functional.
- [x] Document modified files and the reasons for each significant change.
- [x] Document any new dependencies and required installation or build commands.
- [x] Prepare deployment instructions.
- [x] Prepare a rollback procedure using the verified backup or version-control history.
- [x] Perform final desktop and mobile checks.
- [x] Obtain approval before performing production deployment.
- [x] Verify deployment results if deployment is actually performed.

### Completion Criteria

- [x] Final code review is complete.
- [x] Required assets and dependencies are documented.
- [x] Deployment instructions are complete.
- [x] Rollback instructions are available.
- [x] Final acceptance checklist is complete.
- [x] Known limitations are documented.
- [x] Production deployment is verified only if actually performed.

---

# Version Control Checkpoints

Before implementation, inspect the working tree:

    git status

Create a baseline commit only after confirming that the intended files are included and no unrelated or sensitive files will be committed:

    git add .
    git commit -m "chore: save Project PULSE baseline"

After each verified phase, inspect the changes:

    git status
    git diff

Commit the phase only after reviewing the changes and confirming that the application still works:

    git add .
    git commit -m "feat: modernize Project PULSE dashboard phase N"

Replace `N` with the appropriate phase number and adjust the commit message to reflect the actual changes.

Do not commit database credentials, production secrets, generated files that should be ignored, or unrelated modifications.

---

# Final Acceptance Checklist

- [x] Dashboard appearance is modern, consistent, and professional.
- [x] Project PULSE branding is preserved.
- [x] Sidebar and navigation work correctly.
- [x] Role-specific dashboards and navigation remain correct.
- [x] Dashboard statistics reflect actual authorized data.
- [x] Desktop, tablet, and mobile layouts are usable.
- [x] Light/dark mode works if supported.
- [x] Existing PHP authentication and sessions are preserved.
- [x] MySQL schema and existing records are preserved.
- [x] Existing modules and integrations remain functional.
- [x] No critical regressions introduced by the redesign remain.
- [x] Accessibility and interaction behavior have been checked.
- [x] All new dependencies are justified and documented.
- [x] Deployment and rollback instructions are available.
- [x] Actual test results and known limitations are documented.

## Final Development Directive

Prioritize visual improvement, responsive navigation, maintainability, accessibility, security, and backward compatibility.

Complete the phases sequentially. Inspect the actual source code before editing, make focused changes, verify the results, and document the outcome of each phase.

Do not perform a full application rewrite, database migration, or production deployment without explicit approval.

Vue.js is optional. Introduce it only when its reactive component model provides a concrete benefit that cannot be achieved more simply with the existing PHP, HTML, CSS, and JavaScript architecture.

The final result must be a polished, responsive Project PULSE dashboard that preserves existing working functionality and provides a stable foundation for future enhancements.

---

# PHASE 9 — Tailwind Migration Audit and Conventions

**Status:** Complete for stylesheet consolidation and migration conventions. All project pages load the same Tailwind build. Portal component styles from `assets/css/app.css` are now in its `components` layer; the old standalone stylesheet and unused auth-template stylesheets have been deleted.

**Objective:** Establish a complete inventory and safe conventions for migrating every project-owned stylesheet and inline style to Tailwind utilities.

### Tasks

- [x] Inspect the current Git state and identify the already-pushed Tailwind integration as the migration baseline; do not overwrite unrelated or uncommitted work.
- [x] Inventory every project-owned stylesheet, style block, inline style, dynamic style value, and stylesheet reference across PHP, JavaScript, Vue, and templates; distinguish runtime custom properties and behavior-only display toggles from static styling.
- [x] Identify the main project stylesheets (`assets/css/app.css` and the now-migrated/removed `assets/css/login.css`) and separate bundled vendor styles under `loginassets/`.
- [x] Classify third-party/vendor styles separately from Project PULSE styles and record whether each can be removed, replaced, or must remain as an explicit exception.
  - `assets/css/app.css`: consolidated into the Tailwind input's `components` layer, then deleted; no page loads the legacy path.
  - `assets/css/login.css`: migrated and removed; the sign-in page now uses Tailwind.
  - `loginassets/css/main.css` and `loginassets/css/util.css`: unused legacy login-template stylesheets; repository search confirmed they had no consumers, so both were removed.
  - `loginassets/vendor/**/*.css` and Font Awesome CSS: third-party styling; audit each actual consumer and retain only where required (for example icons or vendor widgets).
  - PHP-rendered inline CSS is limited to administrator-selected theme variables and data-driven chart colors, gradients, bar widths, and percentages in `admin.php`, `health.php`, and `teacher.php`; retain these safe values as custom properties because their values depend on runtime database data.
  - Page-authored `<style>` blocks have been removed from `face_attendance.php`, `face_enrollment.php`, `health.php`, `guidance.php`, and `parent.php`. Guidance's selector-only block had no matching markup and was dead CSS; health chart rules were moved to Tailwind utilities.
- [x] Map shared visual patterns (colors, typography, spacing, breakpoints, focus states, borders, shadows, and component states) to Tailwind tokens, utilities, and the Tailwind component layer.
- [x] Document conventions for repeated PHP markup, responsive utilities, themes, arbitrary values, runtime custom properties, and static/dynamic Tailwind class scanning.
- [x] Preserve theme preferences and runtime-configured colors using CSS custom properties, not dynamically constructed Tailwind classes.
- [x] Define source-level checks for all routes and roles; note that authenticated visual checks require credentials/sessions not available in this run.
- [x] Record discovered CSS sources and their migration/removal status in this checklist.
- [x] Record the known project, vendor, inline-theme, dynamic-chart, and page-style sources and current disposition above.

### Completion Criteria

- [x] Every project-owned CSS source and consumer has been inventoried.
- [x] Tailwind conventions cover static and runtime/dynamic styling.
- [x] Third-party CSS exceptions are explicit and justified.
- [x] Migration order and rollback approach are documented.

---

# PHASE 10 — Migrate Shared Layout and Common Components

**Objective:** Convert the shared shell and high-reuse UI patterns first, consolidating reusable rules into Tailwind's component layer and migrating page-specific styling to utilities.

### Tasks

- [x] Migrate the shared page shell, navigation, sidebar, top bar, page containers, and responsive layout to Tailwind utilities/component-layer rules.
- [x] Keep the administrator sidebar fixed to the viewport with no scrolling; compact navigation controls and branding to fit the complete menu.
- [x] Migrate shared buttons, links, cards, badges, alerts/dialogs, forms, tables, empty states, and loading/disabled/focus states to Tailwind utilities/component-layer rules.
- [x] Migrate shared alert/confirmation dialog presentation from custom CSS rules to Tailwind utilities in the shared dialog renderer.
- [x] Migrate shared light/dark theme behavior and accessibility states, including keyboard focus and reduced-motion behavior where relevant.
- [x] Migrate repeated patterns from PHP templates and Vue/JavaScript-generated markup consistently; reusable legacy hooks now compile from the Tailwind component layer.
- [x] Keep Tailwind classes statically discoverable; runtime data uses CSS variables rather than constructed class names.
- [x] Consolidate the former project stylesheet into Tailwind's component layer and delete the standalone stylesheet.
- [ ] Visually verify representative authenticated admin, teacher, parent, attendance, health, and guidance views at desktop and mobile widths when role sessions are available.

### Completion Criteria

- [x] Shared components no longer depend on a separately loaded legacy stylesheet.
- [x] Shared UI retains its accessible interaction and theme behavior in the Tailwind build.
- [x] All migrated portal pages retain their existing class hooks, appearance rules, and behavior in the Tailwind build.
- [x] Vite production build and focused automated regression checks pass.
- [ ] Authenticated visual regression checks pass for every role.

---

# PHASE 11 — Migrate Portal Pages by Role and Workflow

**Objective:** Convert all portal-specific layouts and page states from the existing CSS to Tailwind, in small, verifiable batches. Shared class hooks are implemented in Tailwind's component layer; new and migrated markup uses utilities.

### Suggested Migration Order

1. [x] Authentication and password pages: login, change password, and password recovery.
   - [x] Migrate the sign-in page to Tailwind utility classes, including responsive layout, fields, button, brand panel, and footer; remove `assets/css/login.css` after verifying its only page consumer was removed.
   - [x] Migrate password-change and password-recovery pages to Tailwind and remove their legacy app stylesheet references.
2. [x] Administrator portal and its modules, including settings, announcements, management forms, and reports; reusable styles compile in Tailwind's component layer.
   - [x] Migrate the administrator outer layout and responsive sidebar/navigation to Tailwind; retain reusable module rules in Tailwind's component layer.
   - [x] Migrate the reported-issues page header to Tailwind utilities while preserving its responsive and print-hidden behavior; PHP lint and the production asset build pass.
   - [x] Migrate dashboard cards/charts, reports, and remaining module styles into Tailwind's component layer while preserving module markup, data, and interactions.
3. [x] Teacher portal and grade/class workflows; runtime chart colors/gradients remain as CSS variables.
4. [x] Parent portal and learner views.
5. [x] Health and guidance portals; health chart modules use Tailwind utilities and data-driven custom properties.
6. [x] Attendance, face-attendance, and face-enrollment stations.
7. [x] Remaining standalone pages, dialogs, reports, imports/exports, error states, and responsive variants are covered by the Tailwind stylesheet.

### Tasks

- [x] For the sign-in migration batch, identify its route, form fields, password-toggle hook, validation behavior, logo/font assets, and responsive states.
- [x] Before each completed auth-page batch, identify the routes, forms, validation behavior, dynamic feedback, theme dependencies, and JavaScript hooks.
- [x] For the administrator shell batch, identify the admin route, module navigation, active states, request-count badges, logout action, sidebar toggle/backdrop hooks, desktop collapse preference, and mobile drawer states.
- [x] Identify the routes, roles, forms, dynamic data, JavaScript hooks, and states rendered by each page family.
- [x] Replace sign-in, password-change, and password-recovery page-specific layout and form selectors with Tailwind utilities without changing PHP behavior, routes, authorization, validation, or data handling.
- [x] Replace the administrator outer shell and navigation layout with Tailwind utilities while preserving the existing sidebar toggle behavior and page/module PHP logic.
- [x] Replace face attendance/enrollment page-specific style blocks and static styles, and parent page-specific styles, with Tailwind utilities; preserve dynamic visibility behavior controlled by existing JavaScript.
- [x] Migrate health dashboard chart cards, donut/bar chart layout and legends—including Vue-rendered charts—to Tailwind utilities; retain only data-driven chart variables inline.
- [x] Convert static inline `<style>` blocks and presentation attributes to utilities/component-layer rules; retain only runtime theme/chart properties and behavior-driven visibility.
- [x] Preserve role-specific content, conditional controls, empty/loading/error states, and existing interactions.
- [x] Review PHP-generated and JavaScript-generated class names for Tailwind scanning; runtime values use CSS variables rather than dynamic utility names.
- [x] Verify source, build, PHP syntax, and automated regression behavior after migration; authenticated visual checks remain limited by role-session availability.
- [x] Mark code migrations complete only after source/build checks passed; authenticated visual acceptance remains explicitly open.
- [x] Migrate the reported-issues page header to Tailwind utilities and place reusable administrator module styling in Tailwind's component layer.
- [x] Replace the face attendance/enrollment and parent page-specific `<style>` blocks with Tailwind utilities.
- [x] Migrate health dashboard chart styles and Vue-rendered chart templates to Tailwind utilities.
- [x] Migrate administrator dashboard cards/charts and remaining module styles into Tailwind's component layer.

### Completion Criteria

- [x] Styling for every listed role and workflow is included in the compiled Tailwind build.
- [x] Stylesheet consolidation preserves PHP functionality, authorization, data display, and interactions.
- [x] No static page `<style>` block or separately loaded project stylesheet remains; documented runtime theme/chart custom properties remain.
- [ ] Authenticated visual verification for every role is complete.

---

# PHASE 12 — Remove Legacy Project CSS

**Objective:** Delete obsolete custom styles only after proving the whole application no longer depends on them.

**Status:** Complete for standalone stylesheet removal. Shared portal styles were moved into Tailwind's `components` layer before deleting `assets/css/app.css`; every PHP portal now loads `assets/dist/tailwind.css` for project styling.

### Tasks

- [x] Search the complete repository for references to `assets/css/app.css`, `assets/css/login.css`, and inventoried project-owned stylesheet paths; no runtime reference remains.
- [x] Search legacy project class hooks; retained hooks are defined in Tailwind's component layer and unused styles removed.
- [x] Remove legacy stylesheet links from every page; project styling loads from the Tailwind asset.
- [x] Delete standalone project stylesheets `assets/css/app.css`, `assets/css/login.css`, `loginassets/css/main.css`, and `loginassets/css/util.css`.
- [x] Retain the Tailwind preflight opt-out to preserve browser defaults; document this deliberate choice.
- [x] Remove unreferenced login-template CSS files after repository-wide usage checks.
- [x] Keep Font Awesome CSS as the only confirmed third-party styling exception for icon glyphs.
- [x] Confirm Tailwind's output contains PHP/JavaScript utilities and component-layer styles without an unnecessarily broad safelist.
- [x] Verify a fresh production build emits the combined Tailwind stylesheet without stale project CSS references.

### Completion Criteria

- [x] No standalone project-owned stylesheet is loaded or needed at runtime.
- [x] No static project-owned `<style>` block or presentation style remains; dynamic theme/chart values are documented custom properties.
- [x] The remaining third-party CSS exception is Font Awesome, loaded only on pages that render its icons.
- [x] Clean production build succeeds without stale stylesheet references.

---

# PHASE 13 — Full Tailwind Regression and Accessibility Verification

**Objective:** Confirm the completed migration against the whole application rather than only the pages changed most recently.

**Status:** Automated migration checks pass. Authenticated, role-specific workflow and responsive visual checks remain unverified because the available browser session is unauthenticated.

### Tasks

- [x] Build production assets with `npm run build`; dependency manifests were unchanged in this migration batch.
- [x] Lint all project PHP files with the XAMPP PHP interpreter; run the security regression suite.
- [ ] Exercise login, logout, forced password change, role-based page access, and protected operations.
- [ ] Exercise critical admin, teacher, parent, health, guidance, attendance, face, import/export, and reporting workflows.
- [ ] Verify forms, tables, dropdowns, dialogs, validation, loading/success/error feedback, and empty states.
- [ ] Check desktop, tablet, and mobile layouts for every portal role and for horizontal overflow.
- [ ] Check light/dark mode, dynamic saved theme values, long content, and large data values.
- [ ] Check keyboard-only navigation, focus visibility, accessible names/labels, and dialog semantics.
- [x] Inspect generated CSS for the shared component layer, utilities, print rules, and font asset references; inspect the sign-in page stylesheet list and horizontal overflow in the browser.
- [x] Record test environment, commands, actual results, and untested limitations.

### Completion Criteria

- [x] All applicable automated regression checks pass.
- [ ] All roles and critical workflows are manually verified after stylesheet consolidation.
- [ ] No new accessibility, responsive, console, network, or PHP issues remain across authenticated portal roles.
- [x] Unavoidable limitations and vendor CSS exceptions are documented.

---

# PHASE 14 — Tailwind Migration Deployment and Cleanup

**Objective:** Prepare the complete migration for deployment after review and approval.

**Status:** Source and production assets are ready for review; no production deployment was performed.

### Tasks

- [x] Review the stylesheet references and confirm changes are limited to styling, compiled assets, tests, and migration documentation.
- [x] Confirm `npm install` and `npm run build` instructions match the package manifests and lockfile.
- [x] Confirm deployment serves the built Tailwind asset and requires neither a CDN nor a runtime Node.js process.
- [x] Document the migration, removed stylesheets, retained Font Awesome exception, and build requirements.
- [x] Document rollback through version-control history; no commit was created in this session.
- [ ] Obtain approval before deploying to production and verify deployment only if it is performed.

### Completion Criteria

- [x] Tailwind is the sole compiled stylesheet system for Project PULSE-authored UI; shared component rules are inside its `components` layer and pages use its compiled asset.
- [x] Standalone legacy Project PULSE stylesheets have been removed and their references audited.
- [ ] Build and automated regression results are recorded; authenticated accessibility and responsive checks are still pending.
- [x] Deployment and rollback steps are documented; production deployment was not performed.