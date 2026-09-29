import { index, integer, sqliteTable, text, uniqueIndex } from "drizzle-orm/sqlite-core";

export const admins = sqliteTable("admins", {
  id: text("id").primaryKey(),
  email: text("email").notNull(),
  userId: text("user_id"),
  role: text("role", { enum: ["owner", "manager"] }).notNull().default("manager"),
  createdAt: integer("created_at", { mode: "timestamp" }).notNull(),
}, (table) => [uniqueIndex("admins_email_unique").on(table.email), uniqueIndex("admins_user_id_unique").on(table.userId)]);

export const uploads = sqliteTable("uploads", {
  id: text("id").primaryKey(), label: text("label").notNull(), activityDate: text("activity_date"), sourceFilename: text("source_filename").notNull(), satellite: integer("satellite", { mode: "boolean" }).notNull().default(false), notes: text("notes"), createdAt: integer("created_at", { mode: "timestamp" }).notNull(), createdBy: text("created_by").notNull(),
}, (table) => [index("idx_uploads_activity_date").on(table.activityDate)]);

export const qsos = sqliteTable("qsos", {
  id: text("id").primaryKey(), uploadId: text("upload_id").notNull(), callsign: text("callsign").notNull(), qsoDate: text("qso_date"), qsoTime: text("qso_time"), band: text("band"), mode: text("mode"),
}, (table) => [index("idx_qsos_callsign").on(table.callsign), index("idx_qsos_upload").on(table.uploadId)]);

export const activations = sqliteTable("activations", {
  id: text("id").primaryKey(), uploadId: text("upload_id").notNull(), reference: text("reference").notNull(), name: text("name"),
}, (table) => [index("idx_activations_upload").on(table.uploadId)]);

export const endorsements = sqliteTable("endorsements", {
  id: text("id").primaryKey(), uploadId: text("upload_id").notNull(), code: text("code").notNull(), label: text("label").notNull(),
}, (table) => [index("idx_endorsements_upload").on(table.uploadId)]);
