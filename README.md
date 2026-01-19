# Event-Based Image Upload System

This project is a lightweight, secure, event-based image upload system written in PHP and designed to minimize webmaster liability while remaining easy for participants to use from mobile devices.

It allows an administrator to define short-lived upload “events” protected by a shareable code. Only users with a valid event code, during the event’s active time window, may upload images.

---

## Features

### Participant Features
- Upload images directly from a phone or computer
- Event access controlled by a short, human-readable code
- Uploads allowed **only during the event time window**
- No user accounts required

### Admin Features
- Secure admin login with hashed passwords
- Create upload events with start and end date/time
- Enforced maximum event duration (8 hours)
- Automatic generation of unique event codes
- Ability to disable events instantly (via database flag)

### Security & Liability Controls
- No anonymous uploads
- Time-restricted upload windows
- Server-side file validation (MIME type, size)
- All uploads tied to a specific event
- PHP execution disabled in uploads directory
- SQL injection protection via prepared statements
- Session-based authorization

---

## Directory Structure

project/
├── index.php
├── select-images.php
├── upload.php
├── uploads/
├── admin/
│ ├── login.php
│ ├── panel.php
│ └── logout.php
├── deploy.sh
└── README.md


