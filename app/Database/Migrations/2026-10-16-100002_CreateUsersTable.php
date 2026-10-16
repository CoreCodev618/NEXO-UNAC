<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Usuarios institucionales. email UNIQUE validado contra
 * ALLOWED_EMAIL_DOMAINS (@unac.edu.pe) en capa Auth, no en BD.
 * role: alumno | docente | admin (docente validado por admin en V1).
 */
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'faculty_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['alumno', 'docente', 'admin'],
                'default'    => 'alumno',
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('faculty_id');
        $this->forge->addForeignKey('faculty_id', 'faculties', 'id', false, 'SET NULL');

        $this->forge->createTable('users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
