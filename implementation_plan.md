---
# Implementation Plan

**Project**: ALATTEMPUR FORMGOOGLE  
**Last Updated**: 2026-08-26  
**Status**: In Progress

---

## Table of Contents

1. [Completed Items](#completed-items)
2. [Pending Items](#pending-items)
3. [Open Questions](#open-questions)
4. [Implementation Details](#implementation-details)
5. [Verification Plan](#verification-plan)

---

## Completed Items

✅ **Items Successfully Implemented**

| Item | Implementation | Notes |
|------|----------------|-------|
| Email Notification Format | `src/Mailer.php` | Includes Vendor, SO Date, Sales Code, Customer Data, Address, Package, Drive Link |
| 3-Line Address | `src/CbnDocumentTemplate.php` | `$alamat1`, `$alamat2`, `$alamat3` for three address lines |
| Tikor Field | `public/index.php` lines 443-451 | Coordinate field added to form |
| Phone Fields | `public/index.php` | `telp` (mobile) and `telp_rumah` (home) |
| Team Leader Support | Database & Forms | `tl_code` column added to sales table and edit/add forms |
| Team Leader Display | `public/admin/dashboard.php` | Team Leader column shown in admin table |

---

## Pending Items

❌ **Items Requiring Implementation**

| Priority | Item | File Locations | Status | Description |
|----------|------|----------------|--------|-------------|
| ~~P1~~ | ~~**Email Format: Team Leader & AE Name**~~ | ~~`src/Mailer.php`~~ | ✅ **DONE** | ~~Add Team Leader and AE Name to email~~ |
| ~~P1~~ | ~~**Email Format: Home ID & Tikor**~~ | ~~`src/Mailer.php`~~ | ✅ **DONE** | ~~Include Home ID and Tikor in email~~ |
| ~~P2~~ | ~~**TTD SPV Positioning**~~ | ~~`src/CbnDocumentTemplate.php`~~ | ✅ **DONE** | ~~Moved from left:81.5% to left:84.0%~~ |
| ~~P2~~ | ~~**PT SEP → PT TIN**~~ | ~~`src/Mailer.php`~~ | ✅ **DONE** | ~~Replaced PT SEP with PT TIN in email~~ |
| ~~P2~~ | ~~**Customer Email Toggle**~~ | ~~`src/SalesManager.php`, `dashboard.php`~~ | ✅ **DONE** | ~~Toggle already exists in dashboard~~ |
| ~~P3~~ | ~~**Customer Editable Home ID**~~ | ~~`public/index.php`~~ | ✅ **DONE** | ~~Already editable text input at line 361~~ |
| P3 | **Order Status Column** | New Admin Page | ⚠️ **TODO** | Add status column next to customer names (Pending/Done/Draft) |
| P4 | **Superadmin System** | `public/admin/index.php`, `public/admin/dashboard.php`, `src/SettingsManager.php`, `data/settings.json` | ⚠️ **TODO** | Create superadmin → TL → Sales hierarchy |

---

## Open Questions

---

### Question 1: Order Status Storage

> 🛑 **Critical Decision Required**

**R7 - Order Status Column**

Currently no local database storage exists for registered users (data saved via Google Spreadsheet). For the "order status" feature, need to decide:

- **Option A**: Store status in local JSON file?
- **Option B**: Query status from Google Sheets?

**Recommendation**: Option A for performance, sync to Google Sheets periodically.

---

### Question 2: Superadmin Data Storage

> 🛑 **Critical Design Decision**

**R8 - Superadmin System**

Current system has one admin account (settings.json). Need to create:

- Superadmin account (can create TL accounts)
- TL accounts (can manage created sales each)
- Each sales has its own TL

**Question**: Where should superadmin/TL data be stored?

- **Option A**: In single `settings.json` file
- **Option B**: New dedicated JSON file (`data/auth.json`)
- **Option C**: Database migration

**Recommendation**: Option B for better security and separation.

---

### Question 3: SPV Signature Position

> ⚠️ **Configuration Required**

**R3 - Signature Position**

Current position: `left:78.0%`

**Question**: What percentage should the signature move to? Visual confirmation needed.

---

## Implementation Details

---

### Task 1: Update Email Format (R1 & R2)

**Priority**: P1  
**Complexity**: Low  
**Estimated Time**: 30 minutes

**Affected Files**: `src/Mailer.php`

**Steps**:
1. Add Team Leader name to email template
2. Add AE Name (sales name) to email template
3. Add Home ID field to email template
4. Add Tikor field to email template
5. Enhance email subject for better relevance

**Implementation Code**:
```php
// Example structure
protected function buildEmailBody($data) {
    return [
        'vendor' => $data['vendor'],
        'so_date' => $data['so_date'],
        'sales_code' => $data['sales_code'],
        'sales_name' => $data['ae_name'],
        'team_leader' => $data['tl_name'],
        'home_id' => $data['home_id'],
        'tikor' => $data['tikor'],
        'customer_data' => $data['customer'],
        'address_lines' => $data['address'], // 3 lines
        'package' => $data['package'],
        'drive_link' => $data['drive_link']
    ];
}
```

---

### Task 2: Move SPV Signature (R3)

**Priority**: P2  
**Complexity**: Low  
**Estimated Time**: 15 minutes

**Affected Files**: `src/CbnDocumentTemplate.php`

**Steps**:
1. Locate signature image CSS
2. Change `left:78.0%` to `left:X%`
3. Adjust signature name position below image
4. Visual verification

**CSS Change**:
```css
/* Before */
.ttd-spv {
    position: absolute;
    left: 78.0%;
    top: [position];
}

/* After */
.ttd-spv {
    position: absolute;
    left: [new_percentage];
    top: [position];
}
```

---

### Task 3: Replace PT SEP with PT TIN (R4)

**Priority**: P2  
**Complexity**: Low  
**Estimated Time**: 10 minutes

**Affected Files**: 
- `src/CbnDocumentTemplate.php` (footer text)
- `data/settings.json` (display name)

**Steps**:
1. Search for "TIN006-SUHARTA" and "PT SEP" strings
2. Replace with PT TIN branding
3. Check all PDF displays for consistency

---

### Task 4: Customer Email Toggle (R5)

**Priority**: P2  
**Complexity**: Medium  
**Estimated Time**: 1 hour

**Affected Files**: 
- `src/SalesManager.php`
- `public/admin/dashboard.php`
- `src/AppsScriptService.php`

**Database Schema Change**:
```json
// Add to sales data structure
{
    "tl_code": "TL001",
    "email_customer_enabled": true  // ← Add this field
}
```

**Dashboard UI**:
```php
// Add toggle checkbox to sales table row
<input type="checkbox"
       name="email_toggle[<?php echo $sales['sales_code']; ?>]"
       <?php echo $sales['email_customer_enabled'] ? 'checked' : ''; ?>>
```

**Backend Processing**:
```php
// Send to Google Apps Script
$payload = [
    'data' => $formData,
    'send_customer_email' => $formData['email_customer_enabled']
];
```

---

### Task 5: Customer Editable Home ID (R6)

**Priority**: P3  
**Complexity**: Low  
**Estimated Time**: 20 minutes

**Affected Files**: 
- `public/index.php`
- `src/Validator.php`

**Form Change**:
```php
<!-- Before -->
<input type="hidden" name="home_id" value="PENDING">

<!-- After -->
<div class="form-group">
    <label for="home_id">Home ID</label>
    <input type="text"
           id="home_id"
           name="home_id"
           class="form-control"
           placeholder="Enter Home ID">
    <small class="text-muted">Visible to customer</small>
</div>
```

**Validation Update**:
```php
// Add to Validator.php
public function validateHomeId($value) {
    if (empty($value)) {
        return ['valid' => false, 'message' => 'Home ID is required'];
    }
    if (!preg_match('/^[A-Z0-9-]+$/i', $value)) {
        return ['valid' => false, 'message' => 'Invalid Home ID format'];
    }
    return ['valid' => true];
}
```

---

### Task 6: Order Status System (R7)

**Priority**: P3  
**Complexity**: High  
**Estimated Time**: 2-3 hours

**Affected Files**: 
- `public/admin/dashboard.php` (new page)
- `src/SalesManager.php` (status management)
- `data/orders.json` (new file for tracking)

**Steps**:
1. Create `data/orders.json` structure
2. Build status management UI in dashboard
3. Implement status transitions (Pending → In Progress → Done)
4. Add API endpoints for status updates

**Data Structure**:
```json
// data/orders.json
{
    "orders": [
        {
            "order_id": "ORD-001",
            "customer_name": "John Doe",
            "sales_code": "SALES001",
            "tl_code": "TL001",
            "status": "pending", // pending, in_progress, done
            "status_updated": "2026-08-25T10:30:00Z",
            "created_at": "2026-08-24T14:20:00Z"
        },
        // ... more orders
    ]
}
```

**Profile Column**:
```php
// Add status badge next to customer name
<td>
    <strong><?php echo $sales['customer_name']; ?></strong><br>
    <span class="badge badge-<?php echo $statusClass; ?>">
        <?php echo $statusText; ?>
    </span>
</td>
```

---

### Task 7: Superadmin System (R8)

**Priority**: P4  
**Complexity**: Very High  
**Estimated Time**: 1 day

**Affected Files**: 
- `public/admin/index.php` (login)
- `public/admin/dashboard.php` (management UI)
- `src/SettingsManager.php`
- `data/auth.json` (new file)
- `data/settings.json` (clean up)

**Architecture**:

```
Superadmin (create TLs)
    ↓
Team Leader (create sales for their team)
    ↓
Sales (create orders)
```

**Password Hashing**:
```php
// Use password_hash() for secure storage
$hashedPassword = password_hash($raw_password, PASSWORD_BCRYPT);
```

**Login Flow**:
```php
if ($isSuperadmin) {
    // Show superadmin dashboard - TL management only
    $action = 'manage_team_leaders';
} elseif ($isTL) {
    // Show TL dashboard - sales management only
    $action = 'manage_sales';
} else {
    // Regular admin or guests
    $action = 'view_orders';
}
```

**Hierarchy Navigation**:
```php
$currentUserRole = $_SESSION['role']; // superadmin | tl | admin

if ($currentUserRole === 'superadmin') {
    $view = 'tl_management.php';
} elseif ($currentUserRole === 'tl') {
    $view = 'sales_management.php';
} else {
    $view = 'orders_view.php';
}
```

---

## Verification Plan

### Pre-Implementation Checklist

- [ ] Backup existing `src/Mailer.php`
- [ ] Backup existing `src/CbnDocumentTemplate.php`
- [ ] Backup current `data/settings.json`
- [ ] Create documentation of URL structure for Apps Script

### Verification Steps

- [ ] **Email Send Test**
  - [ ] Verify all required fields appear in email
  - [ ] Check subject line clarity
  - [ ] Validate email delivery to correct address

- [ ] **PDF Generation Test**
  - [ ] Verify TTD SPV positioned correctly
  - [ ] Confirm "PT TIN" appears in all PDF outputs
  - [ ] Check 3-line address rendering
  - [ ] Validate all data fields correctly map to PDF

- [ ] **Dashboard Functionality**
  - [ ] Toggle switch works for each sales
  - [ ] Changes reflect immediately
  - [ ] No JavaScript errors in browser console

- [ ] **Form Validation**
  - [ ] Home ID field accepts valid inputs
  - [ ] Invalid inputs are rejected with clear messages
  - [ ] Hidden fields remain functional for system data

- [ ] **Superadmin System** (if implemented)
  - [ ] Superadmin can create TL accounts
  - [ ] TL accounts linked to specific team leader
  - [ ] Role-based access control working correctly
  - [ ] Password hashing prevents authentication bypass

### Post-Implementation Documentation

- [ ] Update API documentation for Google Apps Script
- [ ] Update admin user manual
- [ ] Create screenshot set for training materials
- [ ] Document new data structures in `data/schema.json`

---

## Risk Assessment

| Task | Risk Level | Mitigation Strategy | Responsible |
|------|------------|---------------------|-------------|
| Email Format Update | Low | Test thoroughly before production | Developer |
| SPV Signature Move | Medium | Keep backup of original CSS | Developer |
| PT SEP → PT TIN | Low | Search all occurrences carefully | Developer |
| Customer Email Toggle | Medium | Enable/disable tested for both states | Developer |
| Home ID Editable | Low | Backward compatibility check | Developer |
| Order Status System | High | Limited test users for beta | Team Lead |
| Superadmin System | Very High | Security audit required | Security Team |

---

## Summary Status Revisi dari revisi.md

### ✅ Completed (6/8 items)

| Item | Revisi | Status | Notes |
|------|--------|--------|-------|
| **R1** | Email: Team Leader & AE Name | ✅ DONE | Lines 112-113 di Mailer.php |
| **R2** | Email: Home ID & Tikor | ✅ DONE | Lines 140-141 di Mailer.php |
| **R3** | TTD SPV geser kanan | ✅ DONE | Changed left:81.5% → left:84.0% di CbnDocumentTemplate.php line 371 |
| **R4** | PT SEP → PT TIN | ✅ DONE | Updated email header & footer, plain text email di Mailer.php |
| **R5** | Toggle email customer per sales | ✅ DONE | Sudah ada di dashboard.php lines 1056-1067, field di SalesManager.php line 81 |
| **R6** | Input Home ID editable customer | ✅ DONE | Sudah editable text input di index.php line 361 |

### ⚠️ Pending (2/8 items - Complex Features)

| Item | Revisi | Priority | Status | Notes |
|------|--------|----------|--------|-------|
| **R7** | Kolom STATUS di samping nama pelanggan | MEDIUM | ⏳ TODO | Perlu sistem penyimpanan order & status management UI |
| **R8** | Sistem Superadmin hierarchy | HIGH | ⏳ TODO | Perlu redesign auth system: superadmin → TL → sales |

---

## Files Modified Summary

```
✏️  src/Mailer.php
    - Added Team Leader, AE Name, Home ID, Tikor to email
    - Changed PT. SINERGI EMAS PERDANA → PT. TIN

✏️  src/CbnDocumentTemplate.php
    - TTD SPV position: left:81.5% → left:84.0%

✏️  Other files (no changes needed for R1-R6)
    - src/SalesManager.php (already has email_customer_enabled field)
    - public/admin/dashboard.php (already has email toggle UI)
    - public/index.php (already has editable Home ID input)
    - src/Validator.php (Home ID already validated)
```

---

## Next Steps for Remaining Tasks

### R7: Order Status System

**Decision needed**: Where to store order status?
- Option A: `data/orders.json` (local file storage)
- Option B: Google Sheets (cloud storage, synced via Apps Script)
- **Recommendation**: Option A for faster performance

**Implementation outline**:
1. Create `data/orders.json` to store order data when form submitted
2. Add new admin dashboard page/tab for status management
3. Add status column in sales table (Pending → In Progress → Done)
4. Create API endpoint to update order status
5. Optional: Sync status back to Google Sheets

### R8: Superadmin System

**Architecture decision needed**:
```
Current: Admin (single account in settings.json)
         └─ Sales (many accounts)

Target:  Superadmin (manages system-wide)
         └─ Team Leader (manages sales team)
            └─ Sales (creates orders)
```

**Implementation outline**:
1. Create `data/auth.json` for multi-account management
2. Redesign login page with role selection
3. Update dashboard based on role (superadmin/TL/admin)
4. Add TL management interface (create, edit, delete TL accounts)
5. Add password hashing (use `password_hash()` & `password_verify()`)
6. Update sales creation logic (sales assigned to specific TL)

---

## Verification Completed

- [x] Email fields verified in Mailer.php
- [x] TTD SPV position updated
- [x] PT TIN replacement verified
- [x] Toggle switch working in dashboard
- [x] Home ID field editable in form

---

## Ready for Testing

All R1-R6 changes are ready for UAT. Please verify:

1. **Email notification** - Check if Team Leader, AE Name, Home ID, Tikor appear correctly
2. **PDF document** - Verify TTD SPV position moved right
3. **Dashboard toggle** - Test email on/off for each sales
4. **Form submission** - Confirm Home ID field accepts customer input
