# BrainOverflow

BrainOverflow is a developer-focused blog application being built for the University of Moratuwa IN2120 Web Programming take-home assignment. The project currently includes a responsive home page with static sample blog content and a PHP database configuration for a MariaDB/MySQL database.

## Features

### Currently Implemented

- BrainOverflow home page
- Responsive dark navy developer-focused UI
- Navigation bar
- Hero section
- Featured blog posts section
- Latest blog posts section
- Footer
- Static sample blog content
- MariaDB/MySQL database named `brainoverflow`
- `users` table
- `blogpost` table
- PHP database connection configuration

### Planned

- User registration
- User login
- User logout
- Session-based authentication
- Create blog posts
- Read/view blog posts
- Update own blog posts
- Delete own blog posts
- Authorization so users can only modify their own blog posts
- Single blog view
- Blog editor
- Online hosting

## Technology Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MariaDB/MySQL
- **Local Development:** XAMPP or PHP development server

## Implemented Features

### User Registration

- User registration form
- Username validation
- Email validation
- Password validation
- Confirm password validation
- Duplicate username detection
- Duplicate email detection
- Secure password hashing using `password_hash()`
- User records stored in MySQL
- Default user role set to `user`

### Password Strength Checker

- Password strength indicator on the registration form
- Appears only when the user starts entering a password
- Hides again when the password field is empty
- Updates in real time as the user types
- Checks for:
  - Minimum 8 characters
  - Uppercase letter
  - Lowercase letter
  - Number
  - Special character
- Compact two-column requirement layout on desktop
- Responsive one-column layout on smaller screens

### Registration UI

- Dark navy developer-themed registration interface
- Two-column authentication card
- BrainOverflow branding
- Welcome panel
- Registration form panel
- Rounded input fields
- Responsive design for smaller screens
- Light-blue glowing border around the main registration card
- Clean flat Login and Register buttons
- Google sign-up button UI

### Interactive UI

- Mouse-following glow/comet-tail effect
- Normal browser cursor is preserved
- Touch/mobile devices avoid unnecessary cursor trail elements
- Subtle blue visual effects that match the BrainOverflow theme

### Database

- MySQL/MariaDB database support
- `brainoverflow` database
- `users` table for user accounts
- User account fields include:
  - `username`
  - `email`
  - `password`
  - `role`

No database passwords, API keys, OAuth client secrets, or other private credentials should be committed to the repository.

## Project Structure

```text
BrainOverflow/
├── config/
│   └── database.php      # PHP database connection configuration
├── css/
│   └── style.css         # Main stylesheet
├── includes/             # Shared PHP include files
├── js/
│   └── main.js           # Frontend JavaScript
├── db_test.php           # Database connection test file
└── index.php             # BrainOverflow home page
```

## Database

The application uses a MariaDB/MySQL database named `brainoverflow`.

Current database tables:

- `users` - stores user account information.
- `blogpost` - stores blog post information.

The intended relationship is that each blog post belongs to a user. This allows the application to support user-owned blog posts and future authorization rules where users can create, update, and delete only their own blogs.

No database passwords, API keys, secrets, or private credentials should be committed to this README or exposed publicly.

## Local Setup

1. Clone or download the project.
2. Place the project folder inside your XAMPP `htdocs` directory, or run it directly with PHP's development server.
3. Start Apache and MariaDB/MySQL using XAMPP.
4. Create a MariaDB/MySQL database named `brainoverflow`.
5. Ensure the required `users` and `blogpost` tables exist.
6. Update `config/database.php` locally with your own database username and password.

To run the project with PHP's built-in development server from the project root:

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000
```

If using XAMPP Apache, open the project through your local Apache URL, for example:

```text
http://localhost/BrainOverflow
```

## Development Status

BrainOverflow is currently under development.

| Feature | Status |
|---|---|
| User Registration | Completed |
| Password Strength Checker | Completed |
| Registration UI | Completed |
| Responsive Registration UI | Completed |
| Mouse Glow Effect | Completed |
| Normal Login | Pending |
| Logout | Pending |
| Blog CRUD | Pending |
| Google OAuth | Pending / Credentials required |

## Future Improvements

- Complete user authentication
- Add session handling
- Implement blog creation, viewing, editing, and deletion
- Add authorization for user-owned blog posts
- Create a single blog post view
- Build a blog editor interface
- Connect dynamic blog data from the database
- Prepare and deploy the application for online hosting

## Assignment

This project is being developed for the University of Moratuwa IN2120 Web Programming take-home assignment.
