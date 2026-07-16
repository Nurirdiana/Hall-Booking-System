# Simple Hall Booking System (PHP + MySQL + Bootstrap)

A simple CRUD web application for IMS566 project.

## Features
- Login / logout authentication
- Role-based user: ADMIN and CUSTOMER
- Hall CRUD
- Booking CRUD
- Booking receipt CRUD/view
- Search booking
- Export booking list to PDF using Dompdf
- Responsive Bootstrap UI

## Setup
1. Copy folder to `C:\xampp\htdocs\hall_booking_system_simple`
2. Create database in phpMyAdmin: `hall_booking_db`
3. Import `database/hall_booking_db.sql`
4. Install Dompdf:
   ```bash
   composer require dompdf/dompdf
   ```
5. Run:
   http://localhost/hallsystem

## Google Sign-In setup
1. Go to Google Cloud Console and create a new project.
2. Enable the Google OAuth API and create OAuth 2.0 Client Credentials.
3. Set the Authorized redirect URI exactly to:
   `http://localhost/hallsystem/google_callback.php`
4. Copy the Client ID and Client Secret into `config.php`.
   - `GOOGLE_CLIENT_ID` should not remain `YOUR_GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET` should not remain `YOUR_GOOGLE_CLIENT_SECRET`
5. If you see "invalid_client", the client values or redirect URI do not match the Google Cloud config.

## Default login
Admin:
- Email: admin@example.com
- Password: admin123

Customer:
- Email: customer@example.com
- Password: customer123

## Google login behavior
- Existing users can sign in with Google if their email matches a user record.
- New Google users are created as `CUSTOMER` by default.
