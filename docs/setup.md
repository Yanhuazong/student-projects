# Setup Notes

## Default Roles

- `admin`: full access to semesters, users, and all projects
- `manager`: can manage only projects assigned to their user account

## Content Model

- A semester can contain multiple projects
- Each project has a summary for the homepage and a rich-text description for its detail page
- Every project is owned by one manager account

## Like Design

- Likes are available only in the current semester view
- Each device can like up to 3 projects per current semester
- Clicking the same heart again removes that like
- The frontend stores a random device token in local storage
- The backend enforces per-project uniqueness and semester like limits per device

## Recommended Local Workflow

1. Import `sql/schema.sql`
2. Configure `api/.env`
3. Start PHP with `php -S localhost:8000 api/public/index.php`
4. Start Vite with `npm run dev`
