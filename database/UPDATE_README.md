# Database Update Instructions

## ⚠️ IMPORTANT: Read This First!

**You cannot run the live database update from your local XAMPP server!** The live credentials won't work locally.

## For LOCAL Testing (XAMPP):

1. **Test Locally First**:
   - Visit: `http://localhost/happyindia.org/database/update_local_db.php`
   - This will set up your local database for testing

2. **Test the Website**:
   - Make sure everything works locally
   - Admin login: `admin` / `password`

## For LIVE Deployment:

1. **Upload ALL Files** to your live server via FTP/cPanel

2. **Update Live Credentials** in `database/update_live_db.php`:
   ```php
   $host = 'your_actual_host'; // Usually 'localhost' or IP from hosting
   $user = 'your_db_username'; // From hosting control panel
   $pass = 'your_db_password'; // From hosting control panel
   $db = 'your_database_name'; // From hosting control panel
   $port = 3306; // Usually 3306
   ```

3. **Run Live Update**:
   - Visit: `https://yourdomain.com/database/update_live_db.php`
   - The script will create all tables and data

4. **Security - DELETE these files immediately after update**:
   - `database/update_live_db.php`
   - `database/update_local_db.php`
   - `database/test_connection.php`

## What Gets Created:

- ✅ **users** table - User accounts
- ✅ **payments** table - Payment records
- ✅ **referrals** table - Referral tracking
- ✅ **withdrawals** table - Withdrawal requests
- ✅ **admins** table - Admin accounts
- ✅ **upi_ids** table - UPI payment IDs
- ✅ Performance indexes

## Default Admin Access:

- **URL**: `https://yourdomain.com/admin/login.php`
- **Username**: `admin`
- **Password**: `password`

**🔴 CHANGE THE PASSWORD immediately after first login!**

## Troubleshooting:

### Connection Errors:
- **Local**: Check XAMPP is running, try different ports (3306/3311)
- **Live**: Contact hosting provider for correct credentials

### Permission Errors:
- Make sure the database user has CREATE/ALTER permissions

### File Not Found:
- Ensure all files are uploaded correctly
- Check file paths are correct

## Quick Commands (if you have SSH access):

```bash
# For local testing
mysql -u root -p happyindia_db < database/schema.sql

# For live (with correct credentials)
mysql -h your_host -u your_user -p your_db < database/schema.sql
```