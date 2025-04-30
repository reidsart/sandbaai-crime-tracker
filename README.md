# Sandbaai Crime Tracker

## Overview
The **Sandbaai Crime Tracker** WordPress plugin is designed to help residents of Sandbaai report crimes, view crime statistics, and manage security groups. The plugin offers a user-friendly interface optimized for mobile devices, robust data visualization, and seamless integration with third-party platforms like WhatsApp for notifications.

This plugin is ideal for community crime monitoring and management, providing tools for crime reporting, statistics tracking, and improved coordination between residents and security groups.

---

## Features

### **Phase 1: Initial Setup**
- **Custom Post Types**:
  - **Crime Reports**: For tracking reported crimes.
  - **Security Groups**: For managing local security groups.
- **Admin Menu**: User-friendly admin menus for managing crime reports, security groups, and plugin settings.
- **Database Schema**: Custom tables for storing structured data.

### **Phase 2: Feature Implementation**
- **Crime Reporting Form**:
  - Mobile-optimized, step-by-step form for reporting crimes.
  - Fields include title, location, date/time, description, category, and photo uploads.
  - Zone-based location selection with interactive maps.
- **Security Groups Management**:
  - Admin interface for adding and managing security groups.
  - Assign WordPress users to security groups.
- **Crime Statistics Dashboard**:
  - Filterable statistics with options for month, year, crime type, and response status.
  - Data visualizations including graphs, pie charts, and interactive maps.

### **Phase 3: Advanced Features**
- **Admin Backend Enhancements**:
  - Crime category management interface.
  - Approval workflow for resident-submitted reports.
  - Export crime reports to CSV.
- **Integration & Notifications**:
  - WhatsApp integration for automatic notifications.
  - Hooks for future patrol management integration.
  - API endpoints for external system integration.

---

## Installation

1. Download the plugin as a ZIP file or clone the repository.
2. Upload the plugin to your WordPress site's `wp-content/plugins` directory.
3. Activate the plugin through the **Plugins** menu in your WordPress admin dashboard.
4. Configure the plugin settings under the **Crime Tracker** menu.

---

## Usage

### **Crime Reporting**
- Add the shortcode `[sandcrime_reporting_form]` to any WordPress page or post to display the crime reporting form.
- Residents can submit reports with details such as location, category, description, and photos.

### **Crime Statistics**
- Navigate to **Crime Tracker > Crime Statistics** in the admin dashboard to view crime trends, category breakdowns, and interactive maps.

### **Security Groups**
- Manage security groups under **Crime Tracker > Security Groups**. Add new groups with details like title, logo, contact information, and description.

### **Crime Categories**
- Add or edit crime categories under **Crime Tracker > Crime Categories**.

### **Report Approvals**
- Approve or reject resident-submitted crime reports under **Crime Tracker > Approve Reports**.

---

## Integration

### WhatsApp Notifications
- Configure WhatsApp integration for automatic notifications about new crime reports. Update the `YOUR_PHONE_NUMBER` and `YOUR_API_KEY` placeholders in `includes/whatsapp-integration.php` with your details.

### API Endpoints
- The plugin includes REST API endpoints for integration with external systems. Contact the developer for more details.

---

## Development

### Directory Structure
```
sandbaai-crime-tracker/
├── sandbaai-crime-tracker.php       # Main plugin file
├── includes/
│   ├── security-groups.php          # Security group management
│   ├── crime-reporting-form.php     # Crime reporting form
│   ├── crime-statistics.php         # Crime statistics dashboard
│   ├── crime-category-management.php # Crime category management
│   ├── report-approval.php          # Report approval workflow
│   ├── whatsapp-integration.php     # WhatsApp notifications
├── assets/
│   ├── css/
│   │   ├── admin-style.css          # Admin-specific styles
│   │   ├── form-style.css           # Crime reporting form styles
│   ├── js/
│   │   ├── admin-scripts.js         # Admin-specific scripts
│   │   ├── form-scripts.js          # Crime reporting form scripts
├── README.md                        # Plugin documentation
```

---

## Support
If you encounter any issues or have feature requests, please create a GitHub issue in the repository or contact the developer.

---

## License
This plugin is licensed under the [MIT License](LICENSE).

---

## Credits
Developed by **reidsart2**, inspired by the need for community-driven crime management solutions.