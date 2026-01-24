# AI Proctor – AI-Powered Exam Monitoring for Moodle

Developed by Medwax Corporation Africa Ltd.  
Affiliated with Kabarak University, Kenya

Version 2.0.0 | Moodle 4.0+ | License: GPL-3.0

## Overview

AI Proctor is a professional exam monitoring solution for Moodle, utilizing advanced AI for real-time facial recognition and behavior analysis to support academic integrity.

## Features

- Real-time face detection and tracking
- Multiple person and device detection
- Tab switching and window focus monitoring
- Video and screenshot evidence capture
- Violation reporting and student review system
- Ban management for repeat offenders
- Teacher dashboard for evidence review
- Safe Exam Browser compatibility
- Configurable admin settings

## Requirements

- Moodle 4.0 or higher
- Modern web browser with webcam support
- JavaScript enabled

## Installation

1. Download the official plugin ZIP from the release page or Moodle plugins directory.
2. Extract and copy the `ai_proctor` folder to your Moodle `/blocks/` directory.
3. Complete installation via Site Administration > Notifications.

## Configuration

After installation, configure plugin settings in Site Administration → Plugins → Blocks → AI Proctor. Options include violation thresholds, evidence retention, warning grace period, upload cooldown, video size limits, detection sensitivity, auto-ban, and notifications.

## Usage

Add the AI Proctor block to a course and configure monitoring settings. Students will be prompted to enable their webcam during quizzes. Teachers can review violation reports and evidence in the block dashboard.

## Database Table

The plugin creates the `mdl_block_ai_proctor` table for evidence and violation tracking. Fields include student ID, course ID, violation type, evidence path, evidence type, duration, severity, status, timestamps, and teacher notes.

## Security and Privacy

- Evidence is stored securely in Moodle’s `$CFG->dataroot` (not web-accessible)
- Session validation, rate limiting, and input sanitization
- GDPR-compliant privacy provider

## Browser Compatibility

Supported browsers: Chrome 90+, Edge 90+, Firefox 88+, Safari 14+ (limited video support), Opera 76+

## Safe Exam Browser Support

Fully compatible with Safe Exam Browser, including progress indicators, error messages, and camera diagnostics.

## Troubleshooting

- Camera not starting: Check browser permissions and close other apps using the camera.
- Evidence not uploading: Check browser console, PHP error log, folder permissions, and database table.
- High false positives: Adjust detection sensitivity in plugin settings.

## License

GNU GPL v3.0 or later. See LICENSE.txt for details.

## Support

Company: Medwax Corporation Africa Ltd.
Email: medwaxcorpafrica@outlook.com
Phone: +254702960969
GitHub Issues: https://github.com/cosmogz/AI_PROCTOR/issues

## Copyright

© 2024-2026 Medwax Corporation Africa Ltd.  
Affiliated Institution: Kabarak University, Kenya

**Note:** Proprietary AI model and WASM files are distributed only via the official plugin ZIP and are not included in this public repository.

git push -u origin main
