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
- Server-side username length validation (3–30 characters)
- Server-side username character validation (letters, numbers, and underscores)
- Email validation
- Server-side password validation matching the visible strength requirements
- Confirm password validation
- Duplicate username detection
- Duplicate email detection
- Secure password hashing using `password_hash()`
- User records stored in MySQL
- Default user role set to `user`

### Registration Diagnostic — August 20, 2026

- **Issue found:** Registration initially reached the database connection step but could not authenticate to the local MySQL service.
- **Resolution:** The local database configuration was corrected and the connection was subsequently verified.
- **Security fix applied:** Registration now validates the same session-backed CSRF token mechanism used by the login flow. The token is submitted through a hidden form field and does not change the visible UI.
- **Files modified:** `register.php` and this `README.md` diagnostic record.
- **Testing result:** Database reachability, secure session initialization, CSRF rejection, server-side validation, duplicate-account checks, successful insertion, and bcrypt password hashing were verified.
- **Database safety:** No database credentials, users, tables, or schema were changed automatically.

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

### User Login and Logout

- Username or email login
- Password verification using `password_verify()`
- Session regeneration on successful login
- Session-based signed-in navigation state
- Logout route that clears the PHP session
- POST-only logout with a session-backed CSRF token kept out of URLs

### Login Query Fix — August 21, 2026

- Replaced the reused PDO login placeholder with separate username and email placeholders to prevent `SQLSTATE[HY093]: Invalid parameter number` when native prepared statements are used.
- Retained generic user-facing login errors while logging the underlying exception server-side.
- **Files modified:** `login.php` and this `README.md` change record.

### Authentication Hardening — August 21, 2026

- Added server-side enforcement of the registration password rules shown by the existing UI: at least 8 characters, including uppercase, lowercase, numeric, and special characters.
- Added server-side username validation requiring 3–30 letters, numbers, or underscores while retaining duplicate checks.
- Changed logout to accept only POST requests with a valid CSRF token; session data, the session cookie, and the server-side session are still cleared before redirecting to the home page.
- Removed both public database diagnostic scripts (`db_test.php` and `db-test.php`) and ignored both filename variants to prevent deployment of raw connection diagnostics or credentials.
- Confirmed the local MySQL connection. Google OAuth code is present and has been verified through its database and session paths.
- **Files modified:** `includes/auth.php`, `register.php`, `logout.php`, `index.php`, `css/style.css`, `.gitignore`, `README.md`; diagnostic files removed: `db_test.php`, `db-test.php`.

### Google OAuth Configuration Fix — August 21, 2026

- Confirmed the project-root `.env` contains non-empty Google OAuth client ID, client secret, and redirect URI values without exposing them.
- Identified that the web-server-served project copy did not have the same local environment configuration as the workspace.
- Synchronized the ignored local `.env` to the served project copy so its PHP runtime can detect the OAuth configuration.
- Verified the configured redirect URI is exactly `http://localhost/BrainOverflow/google-callback.php` and that the authorization request can be generated.
- Google OAuth configuration detection is complete, and the database now supports Google-only users with no local password.

### Google OAuth Database Compatibility — August 21, 2026

- Changed only the `users.password` column nullability from `NOT NULL` to `NULL`; its `VARCHAR(255)` type, `utf8mb4` character set, and `utf8mb4_general_ci` collation were preserved.
- Existing users and password hashes were left unchanged. Normal registration still requires a password and stores it with `password_hash()`.
- Google-created accounts continue to store `password = NULL`, retain the verified Google email, receive the default `user` role, and enter the existing authenticated session flow.
- Verified both normal-registration and Google-callback database paths inside a rolled-back transaction, leaving no test accounts behind.
- Applied SQL:

```sql
ALTER TABLE users
    MODIFY COLUMN password VARCHAR(255)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci
    NULL DEFAULT NULL;
```

### Google OAuth Token Diagnostics — August 21, 2026

- Confirmed the token exchange targets `https://oauth2.googleapis.com/token` with a form-encoded POST containing the authorization code, configured client ID and secret, exact redirect URI, and `authorization_code` grant type.
- Confirmed the configured client ID and secret are accepted by Google's token endpoint and the redirect URI exactly matches `http://localhost/BrainOverflow/google-callback.php`.
- Fixed OAuth HTTP handling so non-success status codes and Google's JSON `error`/`error_description` are retained as safe server-side diagnostics instead of being hidden behind a missing-access-token message.
- Authorization codes, access tokens, and the client secret are explicitly redacted from diagnostic messages; user-facing errors remain generic.
- Added 15-second timeouts to token and userinfo requests and retained all existing OAuth state validation.
- The historical token response body was not logged, so its exact upstream error cannot be recovered retroactively. A new interactive attempt will now write the safe Google error code and description to the PHP error log if the exchange still fails.
- **Files modified:** `includes/google_oauth.php`, `google-callback.php`, and this `README.md` record. The two PHP changes were also deployed to the web-server-served project copy.

### Google OAuth Client Secret Sync — August 21, 2026

- Resolved an `invalid_client` token exchange caused by the served project's `.env` containing an older client secret than the workspace `.env`.
- Confirmed the client ID and exact redirect URI already matched; synchronized the newer ignored workspace environment file to the served project copy without exposing any credential values.
- Safely verified that Google's token endpoint accepts the synchronized client credentials: a deliberately synthetic authorization code now produces the expected `invalid_grant` response instead of `invalid_client`.
- No OAuth PHP logic, UI, database schema, or tracked credential file was changed for this configuration fix.

### Local Database Configuration Sync — August 21, 2026

- Resolved the Google callback database connection error by synchronizing the served project's ignored database configuration with the working local configuration.
- The served copy omitted the configured non-default MySQL port, causing PDO to reach the wrong local database service.
- No database credentials were exposed or added to tracked files.
- Verified through the served code that PDO connects, the callback can query `users`, `password = NULL` remains supported, a Google-style user can be inserted and found, and an authenticated session is created.
- The integration test ran in a rolled-back transaction, leaving existing users and password hashes unchanged and no test account behind.
- No Google OAuth credentials, OAuth logic, UI, or database schema were changed.

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
└── index.php             # BrainOverflow home page
```

## Database

The application uses a MariaDB/MySQL database named `brainoverflow`. The current local connection has been verified successfully.

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
| Normal Login | Completed |
| Logout | Completed |
| Session-based Authentication | Completed |
| Blog CRUD | Pending |
| Google OAuth | Configured / Schema compatible |

## Future Improvements

- Complete a final interactive Google OAuth browser test with a real Google account
- Implement blog creation, viewing, editing, and deletion
- Add authorization for user-owned blog posts
- Create a single blog post view
- Build a blog editor interface
- Connect dynamic blog data from the database
- Prepare and deploy the application for online hosting

## Assignment

This project is being developed for the University of Moratuwa IN2120 Web Programming take-home assignment.
