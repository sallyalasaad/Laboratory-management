# Non-Functional Requirements (NFRs)

This document defines the key non-functional requirements of the Laboratory Management
system. They are derived from the actual implementation (Laravel backend, Python/FastAPI
forecasting service, MySQL database) and should guide design, testing, and acceptance.

## 1. Security

| ID | Requirement |
|----|-------------|
| NFR-SEC-1 | All user authentication must use token-based auth (Laravel Sanctum) for API requests and secure session cookies for web requests. |
| NFR-SEC-2 | Every state-changing web request must be protected by CSRF verification. |
| NFR-SEC-3 | Access to resources must be enforced via role/permission checks (spatie/laravel-permission); a user must only see and modify data their role permits. |
| NFR-SEC-4 | User verification must be protected by OTP verification before sensitive actions. |
| NFR-SEC-5 | Passwords must be stored using a strong one-way hash (bcrypt) and never stored in plain text. |
| NFR-SEC-6 | Secrets (DB credentials, app key, API keys) must be kept out of the repository and loaded only from environment configuration. |
| NFR-SEC-7 | User input must be validated and sanitized server-side to prevent injection, XSS, and malformed-data attacks. |

## 2. Performance

| ID | Requirement |
|----|-------------|
| NFR-PERF-1 | Demand forecasting requests must be served from pre-trained in-memory models without a retraining step at request time. |
| NFR-PERF-2 | Common read queries (listings, reports, dashboards) must return within acceptable time using eager loading and proper indexes. |
| NFR-PERF-3 | Production build must use optimized autoloading and compiled assets to minimize request latency. |
| NFR-PERF-4 | The forecasting API must validate inputs and fail fast (e.g., invalid date) rather than performing expensive work. |

## 3. Reliability & Data Integrity

| ID | Requirement |
|----|-------------|
| NFR-REL-1 | Inventory and batch operations (FIFO allocation, stock movements) must be atomic; partial allocations must not be persisted on failure. |
| NFR-REL-2 | Scheduled jobs must run automatically: expiry checks, task status updates, and cleanup of completed orders. |
| NFR-REL-3 | The database must be backed up on a scheduled basis (spatie/laravel-backup) and restorable on failure. |
| NFR-REL-4 | Critical state changes must be captured via observers/events to keep dependent records consistent. |
| NFR-REL-5 | The system must detect and report insufficient-quantity conditions without corrupting inventory data. |

## 4. Availability

| ID | Requirement |
|----|-------------|
| NFR-AVL-1 | The application must be deployable as a container (Docker) so environments are reproducible. |
| NFR-AVL-2 | Scheduled maintenance tasks (cleanup, expiry checks) must run without manual intervention. |
| NFR-AVL-3 | Core modules (production, inventory, sales) must remain functional during model-training failures; the forecast service degrades gracefully with an error response. |

## 5. Maintainability

| ID | Requirement |
|----|-------------|
| NFR-MNT-1 | Code must follow MVC structure with a clear separation between controllers, services, and data-access (DAO) layers. |
| NFR-MNT-2 | All schema changes must be versioned through migrations and rollback-safe. |
| NFR-MNT-3 | The project must be covered by automated tests (PHPUnit) and pass them before release. |
| NFR-MNT-4 | Code style must be enforced with Laravel Pint and documented conventions. |

## 6. Usability

| ID | Requirement |
|----|-------------|
| NFR-USE-1 | The system must provide a REST API (routes/api.php) so the client application can interact with all core features. |
| NFR-USE-2 | Users must receive notifications for relevant events (e.g., expiry warnings) in near real time. |
| NFR-USE-3 | The interface and content must support the primary language of users (Arabic) alongside the base UI. |

## 7. Portability & Compatibility

| ID | Requirement |
|----|-------------|
| NFR-PORT-1 | All environment-specific settings must be configurable via environment variables (`.env`). |
| NFR-PORT-2 | The system must run on the documented PHP/MySQL stack and the Python forecasting service as described in the deployment setup. |
