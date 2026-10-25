<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Acota los documentos RAG al espacio (y por tanto a sus páginas).
 *
 * `page_id` ya existía, pero PR #10 permitía listar/ingerir documentos sin
 * página. Con `workspace_id` el filtro de Qdrant y la lista de MariaDB se
 * pueden limitar a los espacios donde el usuario es miembro.
 */
class AddWorkspaceScopeToDocuments extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE documents
             ADD COLUMN workspace_id INT UNSIGNED NULL AFTER page_id'
        );
        $this->db->query(
            'ALTER TABLE documents
             ADD INDEX documents_workspace_id_index (workspace_id)'
        );
        $this->db->query(
            'ALTER TABLE documents
             ADD CONSTRAINT documents_workspace_id_foreign
             FOREIGN KEY (workspace_id) REFERENCES workspaces(id)
             ON DELETE SET NULL ON UPDATE CASCADE'
        );

        // Backfill para documentos que ya tenían página.
        $this->db->query(
            'UPDATE documents d
             INNER JOIN pages p ON p.id = d.page_id
             SET d.workspace_id = p.workspace_id
             WHERE d.workspace_id IS NULL'
        );
    }

    public function down(): void
    {
        $this->db->query(
            'ALTER TABLE documents DROP FOREIGN KEY documents_workspace_id_foreign'
        );
        $this->db->query(
            'ALTER TABLE documents DROP INDEX documents_workspace_id_index'
        );
        $this->db->query('ALTER TABLE documents DROP COLUMN workspace_id');
    }
}
