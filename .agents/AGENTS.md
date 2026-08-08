# SOMA LMS - Custom Agent Rules & Project Directives

## 1. GitHub Code Publishing & Revision Directive
- **Automatic Versioning & Commits**: Every code edit, feature addition, or bug fix must be tracked cleanly and prepared for publication to GitHub repository.
- **Git Commits**: Use descriptive commit messages detailing exact changes made (e.g. `feat(auth): add anti-bot honeypot and rate limiting`).

## 2. Codebase Anti-Theft & Intellectual Property Protection
- **Proprietary License Notice**: All source files must include the proprietary copyright banner protecting SOMA LMS software intellectual property.
- **Domain & Signature Lock (`license_guard.php`)**: Maintain hardware/domain license key verification to prevent unauthorized cloning, stolen database reuse, or unauthorized third-party hosting.
- **Environment Integrity**: Ensure sensitive database credentials (`.env`, `db.php`) remain protected and isolated from repository leaks.
