# WordPress Plugin Development Plan: Sandbaai Crime Reporting and Statistics Tracking

## Phase 1: Initial Plugin Setup

### 1. Basic Plugin Structure
- **Folder and Main File**: Create a plugin folder with a main PHP file containing proper WordPress plugin headers.
- **Activation/Deactivation Hooks**:
  - Activation: Setup custom database tables and default settings.
  - Deactivation: Clean up temporary data.
- **Custom Post Types (CPT)**:
  - `Crime Reports` CPT with fields: title, description, category, date/time, location, result status, related security groups, photo attachments.
  - `Security Groups` CPT with fields: title, logo, contact details, description.
- **Admin Menu**: Create a menu with submenus for managing crime reports, security groups, and plugin settings.

### 2. Database Schema
- **Crime Reports Table**:
  - Fields: Title, description, category, date/time, location (address/zone), result status, related security groups, photo attachments.
- **Security Groups Table**:
  - Fields: Title, logo, contact numbers, email, address, website, description.
- **Procedures**:
  - Design installation scripts to create and upgrade database tables.

### 3. Basic Admin Pages
- **Dashboard**: Overview of recent crime reports and statistics.
- **Crime Report Management**: List and manage reports with basic CRUD operations.
- **Security Group Management**: List and manage security groups with basic CRUD operations.
- **Settings Page**: Configuration options for the plugin.

---

## Phase 2: Feature Implementation

### 1. Security Groups Management
- **Admin Interface**: Manage security groups through an intuitive interface.
- **Group Creation Form**:
  - Fields: Title, logo upload, contact numbers, email, address, website, description.
- **User Assignment**: Link WordPress users to security groups.
- **Default Group**: Automatically assign "resident" group for new users.

### 2. Crime Reporting Form
- **Mobile-Optimized Form**: Progressive disclosure design for step-by-step input.
- **Form Sections**:
  - Location: Address entry or zone selection.
  - Title and Category: Crime title and category dropdown.
  - Date and Time: Auto-filled but editable.
  - Result and Security Groups: Dropdown for involved groups.
  - Description and Photos: Text description and photo uploads.
- **Zone Map Interface**: Interactive map with quadrants and subzones.
- **Validation**: Client-side and server-side validation for all inputs.
- **Submission Handling**: Save reports with a "Review" status for resident submissions.

### 3. Crime Statistics Display
- **Statistics Dashboard**:
  - Filters: Month, year, crime type, result, time range.
  - Data Views: Graph of crimes by day, pie chart of categories, interactive map of locations.
- **Crime List View**:
  - Color-coded list of crimes by category.

---

## Phase 3: Advanced Features

### 1. Admin Backend Enhancements
- **Crime Category Management**: Interface for adding/editing/removing crime categories.
- **Security Group Tools**: Advanced tools for group management.
- **Approval Workflow**: System for reviewing and approving resident-submitted reports.
- **Export to CSV**: Export reports with field selection.

### 2. Integration & Notifications
- **WhatsApp Integration**: Automatic notifications for specific crime activities.
- **Hooks for Patrol Management**: Prepare for future integration with patrol scheduling.
- **API Endpoints**: Expose REST API endpoints for external system integration.

---

## Deliverables
1. WordPress plugin folder with all necessary files and code.
2. Functional admin pages and user interfaces.
3. Fully operational database schema and API endpoints.
4. Mobile-optimized forms and interactive statistics dashboard.
5. Documentation for installation, usage, and future enhancements.