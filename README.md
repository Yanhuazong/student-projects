# Student Projects

A full-stack student project showcase and management system built with React, PHP, and MySQL.

## Features

- Public homepage with top-rated projects by category for the current semester
- Semester switcher for browsing each semester
- Dedicated detail page for each project
- Admin users who manage all semesters, projects, and users
- Project managers who manage only their own project entries
- Regular users who sign in to vote on projects
- Rich text editor for project descriptions
- Admin-editable site logo and homepage heading text
- Admin-editable 4 voting categories (first category is primary / Best Overall)
- One signed-in user can cast one vote per category in the current semester

## Project Structure

```
student-projects/
├── api/
│   ├── public/
│   │   └── index.php
│   └── src/
│       ├── config/
│       ├── controllers/
│       ├── middleware/
│       └── utils/
├── docs/
├── sql/
├── src/
│   ├── components/
│   ├── contexts/
│   ├── pages/
│   └── utils/
├── index.html
├── package.json
└── vite.config.js
```

## Quick Start

### 1. Create the database

```sql
CREATE DATABASE student_projects CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema:

```bash
mysql -u root -p student_projects < sql/schema.sql
```

### 2. Configure the backend

Copy the example environment file:

```bash
copy api\.env.example api\.env
```

Set your database credentials and a strong `APP_SECRET` in `api/.env`.

### 3. Configure the frontend

Copy the example environment file:

```bash
copy .env.example .env
```

### 4. Install frontend dependencies

```bash
npm install
```

### 5. Run the PHP API

From the project root:

```bash
php -S localhost:8000 api/public/index.php
```

### 6. Run the React frontend

```bash
npm run dev
```

Open `http://localhost:3000`.

## Authentication Bootstrap

The first time the app starts, there will be no admin account. The login screen exposes a bootstrap form only while there are zero admins in the database. Use it once to create the initial admin.

## Voting Rules

- Voting is available only while viewing the current semester
- Users must sign in before voting
- There are always 4 categories per class
- The first category is the primary Best Overall section and returns one winner on Top-rated
- For the other categories, Top-rated shows:
	- up to 3 projects if total projects in semester is above 20
	- up to 2 projects if total projects in semester is 10 to 20
	- 1 project if total projects in semester is below 10
- Each user can vote for only one project per category (and can switch or remove that vote)

## Demo Seed Data

`sql/schema.sql` now includes optional dummy seed data (semesters, users, projects, and sample likes) so the homepage and semester views are populated immediately after import.

## Suggested Next Improvements

- Image upload support instead of URL-only images
- Audit log for admin changes
- Draft and scheduled publishing support
