# MAMA Manage
### Smart Apartment Management System (SAMS)

MAMA Manage is a web-based **Smart Apartment Management System** developed to simplify and organize the management of apartments, tenants, rent, utility bills, payments, complaints, maintenance, notices, and tenancy records.

The system is designed around a **relational database** and provides separate dashboards for apartment owners and tenants. It replaces manual record keeping with a centralized system where information can be stored, managed, searched, and monitored more efficiently.

---

## 📌 Project Overview

Managing apartments manually can become difficult when dealing with multiple tenants, monthly bills, payments, complaints, maintenance requests, and tenancy information.

MAMA Manage was developed to provide a centralized platform where apartment owners can manage their properties and tenants, while tenants can access their own apartment, billing, payment, complaint, maintenance, and notice information.

The project also demonstrates important **DBMS concepts**, including:

- Relational database design
- Primary and foreign keys
- Table relationships
- Database normalization
- SQL queries
- JOIN operations
- Aggregate functions
- CRUD operations
- Data filtering and reporting
- Role-based access

---

# ✨ Main Features

## 👤 Owner Dashboard

The owner dashboard provides an overview of the apartment management system.

Owners can monitor:

- Total apartments
- Occupied apartments
- Available apartments
- Apartments under maintenance
- Total tenants
- Monthly bills
- Payment information
- Overdue payments
- Complaints
- Maintenance activities
- Apartment requests
- Notices

The dashboard is scoped to the **currently logged-in owner**, so an owner cannot see another owner's apartment or tenant information.

---

## 🏢 Apartment Management

Owners can manage their apartments from a dedicated apartment management section.

Features include:

- Add apartments
- Edit apartment information
- View apartment details
- Monitor apartment status
- View monthly rent
- Track occupancy
- Manage apartment availability

Apartment statuses include:

- `Available`
- `Occupied`
- `Under Maintenance`

The system also prevents an occupied apartment from being incorrectly changed to an available apartment through normal apartment editing.

---

## 👥 Tenant Management

Owners can manage tenants and their tenancy information from one unified section.

The tenant management section includes:

- Tenant name
- Phone number
- Email
- Identification/reference information
- Assigned apartment
- Floor information
- Move-in date
- Move-out date
- Tenancy status

Tenant and tenancy information are connected so that the owner can easily understand which tenant is currently assigned to which apartment.

---

## 🏠 Apartment Request System

One of the major features of MAMA Manage is the apartment request workflow.

Tenants can view available apartments and send an apartment request directly from the system.

The workflow is:

```text
Tenant views available apartment
            ↓
Tenant sends request
            ↓
Owner receives request
            ↓
Owner approves or rejects
            ↓
If approved → Tenancy is created
            ↓
Apartment becomes Occupied
