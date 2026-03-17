# 🍗 PENONGS DAILY FOOD INVENTORY SYSTEM

A comprehensive web-based inventory management system for daily food inventory tracking with role-based access control, built with PHP and MySQL.

## 📋 Features

### 👤 User Roles

**Admin (System-Level)**
- ✅ Manage user accounts (Add, Edit, Deactivate managers)
- ✅ Manage branches
- ✅ Configure system settings
- ✅ View all reports (read-only)
- ✅ Activity logs and audit trail
- ✅ System backup and restore capabilities

**Manager (Operations-Level)**
- ✅ Input beginning inventory per item
- ✅ Add daily stock quantities
- ✅ Record daily sales
- ✅ Enter remarks and adjustments
- ✅ Generate daily inventory reports
- ✅ Edit inventory records (with logs)
- ✅ Print and export reports
- ✅ Monitor low-stock items
- ✅ Manage categories and items

### 📦 Inventory Management

- **Beginning Inventory**: Auto-filled from previous day's ending inventory
- **Daily Stock Addition**: System auto-computes total stock
- **Sales Recording**: Auto-deducts sales from stock
- **Ending Inventory**: Auto-computed (no manual entry)
- **Remarks**: Support for returns, spoilage, transfers, adjustments
- **Category-based Management**: Chicken, Beef, Pork, Seafoods, Others

### 📊 Reporting

- Daily inventory reports by branch and date
- Category-wise inventory viewing
- Export to CSV/Excel
- Print-friendly format
- Prepared by and Reviewed by signatures
- Activity tracking and audit logs

### 🎨 Design Features

- Modern responsive design (mobile-friendly)
- Color palette: White, Yellow (#F4D03F), Red (#E74C3C)
- Clean and intuitive interface
- Professional dashboard layouts
- Real-time calculations

## 🚀 Installation Instructions

### Prerequisites

- XAMPP (Apache + MySQL + PHP)
- Web browser (Chrome, Firefox, Edge, Safari)
- Text editor (optional, for customization)

### Step-by-Step Setup

1. **Install XAMPP**
   - Download XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org)
   - Install XAMPP to `C:\xampp`
   - Start Apache and MySQL from XAMPP Control Panel

2. **Extract Project Files**
   - The project files should already be in: `C:\xampp\htdocs\penongs_inventory`
   - If not, copy all files to this directory

3. **Create Database**
   - Open your web browser
   - Go to: `http://localhost/phpmyadmin`
   - Click "Import" tab
   - Click "Choose File" and select: `C:\xampp\htdocs\penongs_inventory\database.sql`
   - Click "Go" to import the database
   - This will create the database with sample data

4. **Access the System**
   - Open browser and go to: `http://localhost/penongs_inventory`
   - You will be redirected to the login page

## 🔑 Default Login Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `password`

### Creating Manager Accounts
1. Login as Admin
2. Go to "Manage Users"
3. Fill in the form to add a new manager
4. Assign branch and credentials

## 📁 File Structure

```
penongs_inventory/
├── config.php                 # Database configuration and helper functions
├── database.sql              # Database schema and initial data
├── login.php                 # Login page
├── admin_dashboard.php       # Admin dashboard
├── manager_dashboard.php     # Manager dashboard
├── users.php                 # User management (Admin)
├── branches.php              # Branch management (Admin)
├── categories.php            # Category management (Manager)
├── items.php                 # Item management (Manager)
├── daily_inventory.php       # Daily inventory entry (Manager)
├── reports.php               # Reports page (Manager)
├── reports_admin.php         # Reports view (Admin)
└── README.md                 # This file
```

## 🎯 Usage Guide

### For Administrators

1. **Dashboard**: View system statistics and recent activity
2. **Manage Users**: Add, edit, or deactivate managers
3. **Manage Branches**: Create and manage branch locations
4. **View Reports**: Access all branch reports (read-only)
5. **Activity Logs**: Track all system activities

### For Managers

1. **Dashboard**: View daily statistics and quick actions
2. **Daily Inventory**:
   - Select date
   - Enter beginning inventory (auto-filled from yesterday)
   - Add stock received
   - Record daily sales
   - Add remarks if needed
   - System calculates totals automatically
   - Save inventory

3. **Manage Categories**: Create food categories
4. **Manage Items**: Add items with categories and units
5. **Reports**:
   - Select date to view report
   - Print or export to Excel
   - View prepared/reviewed signatures

## 🔧 Configuration

### Database Settings
Edit `config.php` to change database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'penongs_inventory');
```

### Color Customization
The system uses CSS variables that can be easily customized in each PHP file's `<style>` section.

## 📱 Mobile Responsive

- All pages are fully responsive
- Optimized for tablets and smartphones
- Touch-friendly interface
- Collapsible sidebar navigation on mobile

## 🔐 Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- Role-based access control
- Session management
- Activity logging for audit trail

## 📊 Database Schema

### Main Tables
- `users` - Admin and manager accounts
- `branches` - Branch locations
- `categories` - Food categories
- `items` - Inventory items
- `daily_inventory` - Daily inventory records
- `activity_logs` - System activity tracking
- `system_settings` - System configuration

## 🆘 Troubleshooting

### Cannot access the website
- Ensure XAMPP Apache is running
- Check if you're accessing `http://localhost/penongs_inventory`
- Clear browser cache

### Database connection error
- Ensure XAMPP MySQL is running
- Verify database exists in phpMyAdmin
- Check config.php settings

### Login not working
- Ensure database was imported correctly
- Try default credentials: admin/password
- Check browser console for errors

### Pages not loading properly
- Clear browser cache
- Check Apache error logs in `C:\xampp\apache\logs\error.log`
- Ensure all PHP files are in correct directory

## 📞 Support

For issues or questions:
1. Check the error logs in XAMPP
2. Verify all files are present
3. Ensure database is properly imported
4. Check PHP version compatibility (PHP 7.4+ recommended)

## 🔄 Backup and Restore

### Backup
1. Go to phpMyAdmin
2. Select `penongs_inventory` database
3. Click "Export" tab
4. Click "Go" to download backup

### Restore
1. Go to phpMyAdmin
2. Select `penongs_inventory` database
3. Click "Import" tab
4. Choose your backup file
5. Click "Go"

## 📝 Notes

- Default timezone: Server timezone
- Date format: YYYY-MM-DD (MySQL standard)
- Decimal precision: 2 decimal places for quantities
- Activity logs are automatically created for all major actions
- Beginning inventory auto-fills from previous day's ending inventory
- Low stock threshold: 10 units (configurable in system settings)

## 🎨 Color Palette

- **Primary Yellow**: #F4D03F (Golden Yellow)
- **Secondary Yellow**: #F39C12 (Orange)
- **Primary Red**: #E74C3C (Red)
- **Secondary Red**: #C0392B (Dark Red)
- **White**: #FFFFFF
- **Background**: #F8F9FA (Light Gray)
- **Success Green**: #27AE60
- **Info Blue**: #3498DB

## ✨ System Highlights

- **Automatic Calculations**: Total stock and ending inventory computed automatically
- **Historical Data**: Yesterday's ending becomes today's beginning
- **Multi-Branch Support**: Manage multiple branch locations
- **Category Organization**: Items organized by food categories
- **Audit Trail**: Complete activity logging
- **Export Options**: CSV/Excel export for reports
- **Print Support**: Professional print layouts
- **Responsive Design**: Works on desktop, tablet, and mobile

## 🚀 Getting Started Quick Guide

1. Start XAMPP (Apache + MySQL)
2. Import database.sql via phpMyAdmin
3. Access http://localhost/penongs_inventory
4. Login with admin/password
5. Create manager accounts
6. Start managing inventory!

---

**© 2026 Penongs Inventory System. All rights reserved.**
