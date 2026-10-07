# MAMA Manage
### Smart Apartment Management System (SAMS)

MAMA Manage is a web-based **Smart Apartment Management System** developed to simplify and organize apartment management through a centralized database-driven platform.

The system allows apartment owners and tenants to manage and access information related to apartments, tenancies, billing, payments, complaints, maintenance, notices, and apartment requests.

---

## Overview

MAMA Manage is designed as a practical **DBMS Lab project** using PHP and MySQL. It demonstrates how relational database concepts can be applied to a real-world apartment management system.

The system provides two main user roles:

- **Owner** — manages apartments, tenants, tenancies, bills, payments, complaints, maintenance, notices, and apartment requests.
- **Tenant** — views apartment and tenancy information, requests available apartments, checks bills and payments, submits complaints, monitors maintenance, and manages move-out requests.

---

## Key Features

### Owner
- Apartment management
- Tenant and tenancy management
- Apartment request approval/rejection
- Monthly billing
- Payment management
- Overdue payment tracking
- Complaint management
- Maintenance and staff management
- Notice management
- Dashboard and reports
- Owner-specific data access

### Tenant
- Tenant dashboard
- Apartment and tenancy information
- Available apartment requests
- Apartment request history
- Bills and payment history
- Complaint submission and tracking
- Maintenance tracking
- Notice viewing
- Move-out request management

### Tenancy & Apartment Workflow

The system manages the complete apartment request and tenancy process:

```text
Available Apartment
        ↓
Tenant Request
        ↓
Owner Approval
        ↓
Tenancy Created
        ↓
Apartment Occupied
