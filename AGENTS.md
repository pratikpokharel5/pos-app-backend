# Backend Agent Rules

## General

- Keep code simple and explicit.
- Do not over-engineer.
- Match the existing Laravel coding style and formatting.
- Prefer small, focused changes.
- Do not add abstractions unless they clearly reduce real duplication or complexity.
- Read existing code before changing it.
- Do not revert user changes unless explicitly asked.

## Laravel

- Follow Laravel conventions and best practices.
- Use Form Request classes for API validation.
- Keep controller actions clear and focused.
- Use services only when they simplify meaningful business logic.
- Keep business scoping by `business_id` consistent.
- Keep role and permission logic explicit and easy to understand.

## Database

- Avoid unnecessary migrations.
- Do not change existing data shape unless the feature requires it.
- Use existing columns and relationships when they already support the feature.
- Seeders should create useful test data without unnecessary complexity.

## API

- Keep API payloads simple and predictable.
- Validate backend expectations on the server even when the frontend validates too.
- Return resources consistently with existing API resources.
- Preserve existing behavior for deployed data unless a change is explicitly required.

## Tests

- Add or update tests when backend behavior changes.
- Keep tests focused on the behavior being changed.
- Run `php artisan test` after backend changes.
