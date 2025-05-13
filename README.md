Ultimaker-openbaar
Ultimaker-openbaar is a custom WordPress plugin designed to streamline 3D file submission and printing for Ultimaker printers. This plugin provides features for managing printer configurations, submitting 3D files, and processing G-code for 3D prints.

Features
1. 3D File Submission
Allows users to upload 3D model files (e.g., STL, OBJ) and G-code files for printing.
Supports metadata collection, such as:
Submission title
Material type
Printer selection
Submission deadline
Provides a user-friendly submission form via a WordPress shortcode: [3d_file_submission].
2. Printer Management
Manage Ultimaker printers with:
IP address configuration
Printer API integration for job queueing and status retrieval
Displays printer status (e.g., idle, printing) and loaded materials.
3. Approval Workflow
Administrators can approve, reject, or flag submissions as failed.
Sends email notifications to users for status updates.
Tracks reasons for rejection or failure, which are shared with the submitter.
4. G-code Processing
Parses G-code files to extract:
Estimated print time
Material GUIDs and corresponding names
Print settings (e.g., nozzle size, layer height, infill percentage)
Previews G-code and 3D models directly in the WordPress admin interface using Three.js and Model Viewer.
5. Admin Customization
Custom columns for 3d_submission entries in the WordPress admin panel.
Sortable metadata, including submission status and deadlines.
Daily notifications for pending submissions.
6. Printer API Integration
Integrates with Ultimaker printer APIs for:
Submitting print jobs
Checking printer statuses
Retrieving material slot configurations
7. Material Guidelines
Includes detailed guidelines for using different filament materials (e.g., PLA, ABS, PETG), optimized for Ultimaker printers.
Installation
Clone or download this repository.
Upload the Ultimaker-openbaar plugin folder to your WordPress wp-content/plugins directory.
Activate the plugin through the WordPress Admin Dashboard.
Configure printer settings and setup necessary pages using the plugin's admin tools.
Usage
Adding the Submission Form
Use the [3d_file_submission] shortcode to embed the 3D file submission form on any WordPress page.

Managing Submissions
View and manage submissions from the WordPress admin dashboard under the 3D Submissions section.
Approve, reject, or mark submissions as failed, and notify users of the status.
Configuring Printers
Add printers using the Printers post type in the WordPress admin.
Specify printer IP addresses, API keys, and other settings.
Shortcodes
[3d_file_submission]: Renders the 3D file submission form.
[hotend_id_info]: Displays printer hotend information.
[current_material]: Lists materials currently loaded in the printers.
Requirements
WordPress 5.6 or higher
PHP 7.4 or higher
Ultimaker printers with API support
Contributing
Contributions are welcome! Please fork the repository, make your changes, and submit a pull request.

Authors
Kevin
Milos
Kasper
