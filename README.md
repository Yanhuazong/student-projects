# Student Projects

A full-stack student project showcase and management system built with React, PHP, and MySQL.

## Features

- Public homepage with top-rated projects across all semesters (up to 9)
- Semester switcher for browsing each semester
- Dedicated detail page for each project
- Admin users who manage all semesters, projects, and users
- Project managers who manage only their own project entries
- Rich text editor for project descriptions
- Admin-editable site logo and homepage heading text
- One-device up to 3 likes in the current semester, with toggle-to-unlike

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

## Like Rules

- Likes are available only while viewing the current semester
- Each device can like up to 3 projects in the current semester
- Clicking the heart again removes a like
- Likes are tracked with a locally stored device token and enforced in MySQL

## Demo Seed Data

`sql/schema.sql` now includes optional dummy seed data (semesters, users, projects, and sample likes) so the homepage and semester views are populated immediately after import.

## Suggested Next Improvements

- Image upload support instead of URL-only images
- Audit log for admin changes
- Draft and scheduled publishing support
