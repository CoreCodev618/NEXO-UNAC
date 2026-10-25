<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nombre original del adjunto. El archivo en disco usa un nombre aleatorio,
 * pero la descarga debe conservar el nombre que subió el usuario.
 */
class AddOriginalNameToAttachments extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE attachments
             ADD COLUMN file_name VARCHAR(255) NULL AFTER file_path'
        );
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE attachments DROP COLUMN file_name');
    }
}
