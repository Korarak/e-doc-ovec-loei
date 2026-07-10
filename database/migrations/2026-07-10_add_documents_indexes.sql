-- Every document list query filters by inst_id (multi-tenant scoping),
-- but the documents table had no index on it. Run once on existing databases:
--   docker exec -i mariadb_edoc mysql -u esign -pesignpwd e-sign < database/migrations/2026-07-10_add_documents_indexes.sql

ALTER TABLE `documents`
  ADD INDEX `idx_documents_inst` (`inst_id`),
  ADD INDEX `idx_documents_uploader` (`doc_uploader`);
