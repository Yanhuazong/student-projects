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

## Upload Storage Configuration

The project upload endpoint now writes image files to an uploads directory (default: `student-projects/uploads`) and returns URLs under `/uploads/...`.

If you want the main app/API to stay on `va.tech.purdue.edu` but run only the upload endpoint on `web.ics.purdue.edu`, set the frontend upload API base separately:

- `VITE_UPLOAD_API_BASE`: optional API base used only for image upload requests

Example production split:

- `VITE_API_BASE=https://va.tech.purdue.edu/student-projects/api/public/index.php`
- `VITE_UPLOAD_API_BASE=https://web.ics.purdue.edu/~zong6/student-projects/api/public/index.php`

Optional environment variables in `api/.env.production`:

- `PROJECT_UPLOAD_DIR`: absolute filesystem path to the writable upload folder
- `PROJECT_UPLOAD_PUBLIC_PATH`: public URL path prefix used for returned image URLs (example: `/~zong6/student-projects/uploads`)
- `PROJECT_UPLOAD_PUBLIC_URL`: full absolute URL override for image URLs (example: `https://web.ics.purdue.edu/~zong6/student-projects/uploads`)

For the split-host setup above, the upload API running on `web.ics` must still have:

- the same `APP_SECRET` as the main API so it can verify login tokens
- database connectivity to the same app database so `require_auth()` can load the user and role
- CORS enabled for your `va.tech` frontend origin, or a permissive origin policy

## Password Reset Email Configuration

Set these environment variables in `api/.env.production` (or your server env):

- `APP_URL`: public base URL for your site, for example `https://your-domain.com`
- `RESET_PASSWORD_BASE_URL`: optional override for password reset links (defaults to `APP_URL`)
- `MAIL_ENABLED`: `1` to send reset emails, `0` to disable
- `MAIL_FROM`: sender email address, for example `no-reply@your-domain.com`
- `MAIL_FROM_NAME`: sender display name, for example `Student Projects`

Notes:

- Password reset currently uses PHP `mail()`, so your server must have outbound mail configured.
- In development (`APP_ENV=development`), the API also returns `reset_token` and `reset_link` for testing.
