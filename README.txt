===========================================
  dormMNL - Setup Instructions
===========================================

REQUIREMENTS:
  - XAMPP / WAMP / Laragon (PHP 7.4+ and MySQL)
  - Any modern browser

SETUP STEPS:

1. COPY FILES
   Copy the entire "dormMNL" folder to:
   - XAMPP:   C:/xampp/htdocs/dormMNL/
   - WAMP:    C:/wamp/www/dormMNL/
   - Laragon: C:/laragon/www/dormMNL/

2. SETUP DATABASE
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "Import" tab
   - Choose "dormMNL.sql"
   - Click "Go"

3. CONFIGURE DATABASE (if needed)
   - Open: api/config.php
   - Change $user and $pass to match your MySQL login
   - Default is: user="root", pass=""

4. OPEN THE APP
   - Go to: http://localhost/dormMNL/
   - The app should load!

DEMO LOGIN ACCOUNTS:
  Admin:  admin@dormMNL.ph / password
  Renter: maria@example.com  / password
  Rentee: jose@example.com   / password

  !! IMPORTANT: Change the admin password after first login !!

FILE STRUCTURE:
  index.html         ← Main frontend app
  dormMNL.sql     ← Database tables + seed data
  api/
    config.php       ← DB connection + shared helpers
    login.php        ← User login
    register.php     ← New account registration
    forgot.php       ← Send password reset code
    verify_code.php  ← Verify reset code
    reset.php        ← Set new password
    dorms.php        ← List, add, delete, approve dorms
    bookings.php     ← Create and manage bookings
    reviews.php      ← Add and view reviews
    admin.php        ← Admin: users, stats, roles, ban
    logs.php         ← Activity logs view + clear

API ENDPOINTS SUMMARY:
  POST api/login.php
  POST api/register.php
  POST api/forgot.php
  POST api/verify_code.php
  POST api/reset.php
  GET  api/dorms.php?action=list
  POST api/dorms.php?action=add
  POST api/dorms.php?action=delete
  POST api/dorms.php?action=approve
  GET  api/bookings.php?action=list&user_id=X
  POST api/bookings.php?action=add
  POST api/bookings.php?action=update
  GET  api/reviews.php?action=list&dorm_id=X
  POST api/reviews.php?action=add
  GET  api/admin.php?action=users
  GET  api/admin.php?action=stats
  POST api/admin.php?action=change_role
  POST api/admin.php?action=toggle_ban
  POST api/admin.php?action=delete_user
  GET  api/admin.php?action=dorms
  GET  api/logs.php?action=list
  POST api/logs.php?action=clear
===========================================
