💊 Pharmacy Management System

A complete pharmacy management system built with PHP, MySQL, JavaScript, and Bootstrap, enabling pharmacists to manage medicines, stock, sales, invoices, and admins to manage users, payments, and expenses.

📸 Project Pages Overview
🏠 Home Page

Main landing page showing the pharmacy system overview and navigation.
![Home Page](https://github.com/izhansajiddeveloper/pharmacy_website/blob/32ca76a14469cb5a4fc588fa91963e836a360f8e/home.png)


🔐 Login / Authentication
Secure login page for pharmacists and admin users.
![login page](https://github.com/izhansajiddeveloper/pharmacy_website/blob/0f8ee5a9334bbe3cbc07fc296f8b6341775678fc/login.png)


🧑‍⚕️ Pharmacist Dashboard

Dashboard for pharmacists to access sales, stock, and medicine management.

![pharamcist Dashboard](https://github.com/izhansajiddeveloper/pharmacy_website/blob/57b414c205b8e3c7b19a734799dbfa9ead533edc/pharma%20dashboard.png)
🔎 Search Medicine by Brand

Allows searching medicines based on brand name for quick access.


🔎 Search Medicine by Generic Name

Search medicines using generic names for accurate identification.


📦 Stock Management

View all stock batches, quantities, expiry dates, and locations.


🛠️ Add/Edit Stock

Page to add new stock or edit existing batches with pricing and supplier info.


🛒 Sales & Invoices

Record sales, generate invoices, and track batch-wise stock deduction.


🧑‍💼 Admin Dashboard

Admin panel to manage users, payments, expenses, and overall analytics.


💵 Payments & Expenses

Page to log payments, record expenses, and view financial reports.


🚀 Key Features
👤 Pharmacist

Add, edit, and delete medicines

Track stock batches with expiry dates

Search medicines by brand or generic name

Create sales and generate invoices

Automatic stock deduction per batch sold

🧑‍💼 Admin

Manage users (pharmacists and staff)

View and record payments & expenses

Dashboard with total sales, stock, and financial summaries

💡 System

Batch-wise stock tracking

Expiry alerts

Return and disposal management

Role-based access control

🛠️ Tech Stack
Layer	Technology
Backend	PHP (Procedural)
Frontend	HTML, CSS, JavaScript, Bootstrap
Database	MySQL
Server	Apache (XAMPP)
🗂️ Database Structure (Core Tables)

users — stores admin and pharmacist accounts

medicines — master table for all medicines

medicine_generics — generic medicine names

medicine_categories — medicine categories

medicine_types — medicine types

stock_batches — batch-wise stock management

suppliers — supplier information

sales — sale records

sale_items — individual items per sale

invoices — invoices for sales

invoice_items — items linked to invoices

payments — payments received or made

expenses — expense tracking

⚙️ Installation Guide
1️⃣ Clone the Repository
git clone https://github.com/izhansajiddeveloper/pharmacy_website.git

2️⃣ Setup Database

Create a new MySQL database (e.g., pharmacy_system)

Import database.sql file included in the repository

Update config/db.php with your database credentials

3️⃣ Configure XAMPP / Apache

Place project in htdocs folder

Start Apache and MySQL services

Access via http://localhost/pharmacy_website/

4️⃣ Login

Use default credentials (provided in README or setup script)

Admin can create pharmacists and manage system

📌 Notes

Expired stock will be highlighted in dashboards

Returns and disposals are tracked per batch

All sales and stock operations are transactional to prevent errors

Customize payment methods and discounts as neede
