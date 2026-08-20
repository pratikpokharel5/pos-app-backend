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

## Feature Structure

- For normal CRUD API features, follow the customer feature pattern:
  - `app/Http/Controllers/Api/{Feature}Controller.php`
  - `app/Http/Requests/Api/{Feature}Request.php`
  - `app/Http/Resources/{Feature}Resource.php`
  - `app/Models/{Feature}.php`
  - related migrations, factories, seeders, and tests when needed
- Keep feature behavior in the smallest appropriate layer.
- Use controllers for request flow, authorization checks, business scoping, model calls, and API responses.
- Use models for fillable fields, defaults, relationships, and simple query scopes.
- Use form requests for validation rules.
- Use resources for response shape.

## Controllers

- Keep API controllers thin and readable.
- Use `$request->validated()` for validated input.
- Always scope business-owned records by `business_id`.
- For route model binding, verify the model belongs to the current business before returning or updating it.
- Use `abort_unless($model->business_id === $this->businessId($request), 404)` for cross-business protection when following the customer pattern.
- Return resource classes for created, updated, and shown records.
- Return resource collections for paginated index responses.
- Use `response()->noContent()` for delete/archive actions that do not return data.

## Models

- Define `$fillable` for fields that can be mass assigned.
- Use `$attributes` for simple model defaults, such as default `status`.
- Put simple reusable query filters in local scopes, such as `scopeFilter`.
- Keep query scopes focused on filtering. Keep pagination, ordering, and business scoping in the controller unless there is a clear reason to move them.
- Define relationships with explicit return types, such as `BelongsTo` and `HasMany`.

## Requests

- Use one feature request class for create/update when the validation rules are the same.
- Required backend fields must match frontend required fields.
- Optional text fields should be `nullable`.
- Use `Rule::in(...)` for fixed status or enum-like values.
- Use `sometimes` for fields that are optional on update/create payloads but must be valid when present.

## Database

- Avoid unnecessary migrations.
- Do not change existing data shape unless the feature requires it.
- Use existing columns and relationships when they already support the feature.
- Seeders should create useful test data without unnecessary complexity.
- Add indexes for common business-scoped filters and searches.
- Prefer status changes for archive behavior when records should remain historically available.
- Final database migration shape must match API resources, form requests, and frontend types.

## API

- Keep API payloads simple and predictable.
- Validate backend expectations on the server even when the frontend validates too.
- Return resources consistently with existing API resources.
- Preserve existing behavior for deployed data unless a change is explicitly required.
- Keep API resources, form requests, database columns, and frontend API/form types consistent with each other.
- Keep route permissions explicit in `routes/api.php`.
- Allow staff routes and admin-only routes to be obvious from route definitions.
- If a resource can be archived but not hard-deleted, expose archive through the delete endpoint and update status internally.
- If related data can be created indirectly from another endpoint, enforce the same required fields there too.

## Tests

- Add or update tests when backend behavior changes.
- Keep tests focused on the behavior being changed.
- Run `php artisan test` after backend changes.
- Test important cross-feature rules, such as inactive customers not being usable in sales.
