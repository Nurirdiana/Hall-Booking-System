#  Hall Booking System (PHP + MySQL + Bootstrap)

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
1. Copy folder to `C:\xampp\htdocs\hallbookingsystem`
2. Create database in phpMyAdmin: `hall_booking_db`
3. Import `database/hall_booking_db.sql`
4. Install Dompdf:
   ```bash
   composer require dompdf/dompdf
   ```
5. Run:
   http://localhost/hallsystem


## Default login
Admin:
- Email: admin@example.com
- Password: admin123

Customer:
- Email: customer@example.com
- Password: customer123


