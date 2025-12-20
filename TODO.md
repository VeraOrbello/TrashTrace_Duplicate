# TrashTrace Application Fixes TODO List

## Issues to Fix
1. **Schedule CRUD Access Issue**: Admin users cannot edit/delete/add schedules due to session/user_type checks
2. **Real-time Notifications**: Notifications are not updating in real-time for residents
3. **Application Workflow**: Ensure admin_pending appears in workers tab, driver_pending in drivers tab, and approval/rejection updates user_type correctly
4. **Reports Network Error**: Resident Reports / Feedback showing network errors

## Tasks
- [x] Verify current admin check in php/update_schedule.php
- [x] Add real-time polling to res_notif.php for notifications
- [x] Confirm application approval logic in barangay_applications.php updates user_type correctly
- [x] Debug and fix php/get_feedback.php query issues
- [ ] Test all fixes and ensure database consistency
