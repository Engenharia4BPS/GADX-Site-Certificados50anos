CREATE TABLE `activations` (
	`id` text PRIMARY KEY NOT NULL,
	`upload_id` text NOT NULL,
	`reference` text NOT NULL,
	`name` text
);
--> statement-breakpoint
CREATE INDEX `idx_activations_upload` ON `activations` (`upload_id`);--> statement-breakpoint
CREATE TABLE `admins` (
	`id` text PRIMARY KEY NOT NULL,
	`email` text NOT NULL,
	`user_id` text,
	`role` text DEFAULT 'manager' NOT NULL,
	`created_at` integer NOT NULL
);
--> statement-breakpoint
CREATE UNIQUE INDEX `admins_email_unique` ON `admins` (`email`);--> statement-breakpoint
CREATE UNIQUE INDEX `admins_user_id_unique` ON `admins` (`user_id`);--> statement-breakpoint
CREATE TABLE `endorsements` (
	`id` text PRIMARY KEY NOT NULL,
	`upload_id` text NOT NULL,
	`code` text NOT NULL,
	`label` text NOT NULL
);
--> statement-breakpoint
CREATE INDEX `idx_endorsements_upload` ON `endorsements` (`upload_id`);--> statement-breakpoint
CREATE TABLE `qsos` (
	`id` text PRIMARY KEY NOT NULL,
	`upload_id` text NOT NULL,
	`callsign` text NOT NULL,
	`qso_date` text,
	`qso_time` text,
	`band` text,
	`mode` text
);
--> statement-breakpoint
CREATE INDEX `idx_qsos_callsign` ON `qsos` (`callsign`);--> statement-breakpoint
CREATE INDEX `idx_qsos_upload` ON `qsos` (`upload_id`);--> statement-breakpoint
CREATE TABLE `uploads` (
	`id` text PRIMARY KEY NOT NULL,
	`label` text NOT NULL,
	`activity_date` text,
	`source_filename` text NOT NULL,
	`satellite` integer DEFAULT false NOT NULL,
	`notes` text,
	`created_at` integer NOT NULL,
	`created_by` text NOT NULL
);
--> statement-breakpoint
CREATE INDEX `idx_uploads_activity_date` ON `uploads` (`activity_date`);
--> statement-breakpoint
PRAGMA optimize;
